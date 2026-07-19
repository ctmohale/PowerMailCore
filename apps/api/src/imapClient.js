import net from 'node:net';
import tls from 'node:tls';

const MAILBOX_CANDIDATES = {
  inbox: ['INBOX'],
  spam: ['INBOX.spam', 'INBOX.Spam', 'Spam', 'spam', 'Junk', 'INBOX.Junk', 'Junk E-mail', '[Gmail]/Spam'],
  sent: ['INBOX.Sent', 'Sent', 'sent', 'Sent Items', 'INBOX.Sent Items', '[Gmail]/Sent Mail'],
  drafts: ['INBOX.Drafts', 'Drafts', 'drafts', '[Gmail]/Drafts'],
  trash: ['INBOX.Trash', 'Trash', 'trash', 'Deleted Items', 'INBOX.Deleted Items', '[Gmail]/Trash'],
  archive: ['INBOX.Archive', 'Archive', 'archive', '[Gmail]/All Mail'],
};

export const MAILBOX_TYPES = Object.keys(MAILBOX_CANDIDATES);

function imapError(message, response = '') {
  return new Error(response ? `${message}: ${response}` : message);
}

function normalizeMailboxType(mailboxType = 'inbox') {
  const value = String(mailboxType || 'inbox').trim().toLowerCase();
  return MAILBOX_TYPES.includes(value) ? value : 'inbox';
}

function connectSocket(account) {
  const host = account.imap_host;
  const port = Number(account.imap_port || 993);

  return new Promise((resolve, reject) => {
    const socket = account.imap_encryption === 'ssl'
      ? tls.connect({ host, port, servername: host })
      : net.connect({ host, port });
    const timer = setTimeout(() => {
      socket.destroy();
      reject(imapError('IMAP connection timed out'));
    }, 30000);

    socket.once(account.imap_encryption === 'ssl' ? 'secureConnect' : 'connect', () => {
      clearTimeout(timer);
      socket.setTimeout(30000);
      resolve(socket);
    });
    socket.once('error', (error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

function quote(value) {
  return `"${String(value ?? '').replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
}

function readUntilTagged(socket, tag, state) {
  return new Promise((resolve, reject) => {
    let timer;

    function cleanup() {
      clearTimeout(timer);
      socket.off('data', onData);
      socket.off('error', onError);
      socket.off('end', onEnd);
    }

    function tryResolve() {
      const marker = new RegExp(`(?:^|\\r?\\n)${tag} (OK|NO|BAD) ([^\\r\\n]*)`, 'i');
      const match = state.text.match(marker);

      if (!match) {
        return;
      }

      const endIndex = match.index + match[0].length;
      const response = state.text.slice(0, endIndex);
      state.text = state.text.slice(endIndex).replace(/^\r?\n/, '');
      cleanup();

      if (match[1].toUpperCase() !== 'OK') {
        reject(imapError('IMAP command failed', response));
      } else {
        resolve(response);
      }
    }

    function onData(chunk) {
      state.text += chunk.toString('binary');
      tryResolve();
    }

    function onError(error) {
      cleanup();
      reject(error);
    }

    function onEnd() {
      cleanup();
      reject(imapError('IMAP connection closed unexpectedly'));
    }

    timer = setTimeout(() => {
      cleanup();
      reject(imapError('IMAP response timed out'));
    }, 30000);

    socket.on('data', onData);
    socket.on('error', onError);
    socket.on('end', onEnd);
    tryResolve();
  });
}

async function command(session, text) {
  session.count += 1;
  const tag = `A${String(session.count).padStart(4, '0')}`;
  session.socket.write(`${tag} ${text}\r\n`);
  return readUntilTagged(session.socket, tag, session.state);
}

async function openSession(account) {
  let socket = await connectSocket(account);
  const state = { text: '' };

  await new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(imapError('IMAP greeting timed out')), 30000);
    socket.once('data', (chunk) => {
      clearTimeout(timer);
      state.text += chunk.toString('binary');
      resolve();
    });
    socket.once('error', reject);
  });

  const session = { socket, state, count: 0 };

  if (account.imap_encryption === 'starttls') {
    await command(session, 'STARTTLS');
    socket = tls.connect({ socket, servername: account.imap_host });
    session.socket = socket;
    session.state.text = '';
    await new Promise((resolve, reject) => {
      socket.once('secureConnect', resolve);
      socket.once('error', reject);
    });
  }

  await command(session, `LOGIN ${quote(account.imap_username)} ${quote(account.imap_password)}`);
  return session;
}

async function closeSession(session) {
  try {
    await command(session, 'LOGOUT');
  } catch {
    session.socket.end();
  }
}

function decodeMimeWords(value) {
  return String(value || '').replace(/=\?([^?]+)\?([BQ])\?([^?]+)\?=/gi, (_match, charset, encoding, text) => {
    const buffer = encoding.toUpperCase() === 'B'
      ? Buffer.from(text, 'base64')
      : Buffer.from(text.replace(/_/g, ' ').replace(/=([0-9A-F]{2})/gi, (_m, hex) => String.fromCharCode(Number.parseInt(hex, 16))), 'binary');

    try {
      return buffer.toString(String(charset).toLowerCase() === 'utf-8' ? 'utf8' : 'latin1');
    } catch {
      return buffer.toString('utf8');
    }
  }).trim();
}

function parseHeaders(rawMessage) {
  const [rawHeaders = '', ...bodyParts] = String(rawMessage || '').split(/\r?\n\r?\n/);
  const headers = {};
  let current = '';

  for (const line of rawHeaders.split(/\r?\n/)) {
    if (/^\s/.test(line) && current) {
      headers[current] += ` ${line.trim()}`;
      continue;
    }

    const index = line.indexOf(':');
    if (index > 0) {
      current = line.slice(0, index).toLowerCase();
      headers[current] = line.slice(index + 1).trim();
    }
  }

  return { headers, body: bodyParts.join('\n\n') };
}

function parseAddress(value) {
  const text = decodeMimeWords(value);
  const match = text.match(/(?:"?([^"<]*)"?\s*)?<?([^<>\s]+@[^<>\s]+)>?/);

  return {
    name: match?.[1]?.trim() || null,
    email: match?.[2]?.trim().toLowerCase() || null,
  };
}

function quotedPrintableDecode(value) {
  return String(value || '')
    .replace(/=\r?\n/g, '')
    .replace(/=([0-9A-F]{2})/gi, (_match, hex) => String.fromCharCode(Number.parseInt(hex, 16)));
}

function decodeBody(value, encoding = '') {
  const lower = String(encoding || '').toLowerCase();

  if (lower.includes('base64')) {
    return Buffer.from(String(value).replace(/\s/g, ''), 'base64').toString('utf8');
  }

  if (lower.includes('quoted-printable')) {
    return quotedPrintableDecode(value);
  }

  return String(value || '').trim();
}

function parseBody(rawMessage, headers) {
  const contentType = headers['content-type'] || '';
  const encoding = headers['content-transfer-encoding'] || '';
  const { body } = parseHeaders(rawMessage);
  const boundary = contentType.match(/boundary="?([^";]+)"?/i)?.[1];

  if (!boundary) {
    const decoded = decodeBody(body, encoding);
    return contentType.toLowerCase().includes('html')
      ? { body_text: null, body_html: decoded }
      : { body_text: decoded, body_html: null };
  }

  const parts = body.split(`--${boundary}`);
  const result = { body_text: null, body_html: null };

  for (const part of parts) {
    if (!part.trim() || part.trim() === '--') {
      continue;
    }

    const parsed = parseHeaders(part.replace(/^\r?\n/, ''));
    const type = parsed.headers['content-type'] || '';
    const decoded = decodeBody(parsed.body, parsed.headers['content-transfer-encoding'] || '');

    if (!result.body_text && /text\/plain/i.test(type)) {
      result.body_text = decoded;
    }

    if (!result.body_html && /text\/html/i.test(type)) {
      result.body_html = decoded;
    }
  }

  return result;
}

function messageFromRaw({ uid, mailbox, mailboxType, rawMessage, flags, size, internalDate, account }) {
  const { headers } = parseHeaders(rawMessage);
  const from = parseAddress(headers.from || '');
  const to = parseAddress(headers.to || account.email);
  const bodies = parseBody(rawMessage, headers);

  return {
    uid,
    mailbox,
    mailbox_type: mailboxType,
    message_id: String(headers['message-id'] || '').replace(/[<>]/g, '') || null,
    from_name: from.name,
    from_email: from.email,
    to_email: to.email || account.email,
    subject: decodeMimeWords(headers.subject || '(no subject)') || '(no subject)',
    ...bodies,
    raw_headers: String(rawMessage).split(/\r?\n\r?\n/)[0] || null,
    size,
    seen: /\\Seen/i.test(flags || ''),
    received_at: internalDate ? new Date(internalDate).toISOString().slice(0, 19).replace('T', ' ') : null,
  };
}

function parseSearchResponse(response) {
  const match = response.match(/\* SEARCH ([^\r\n]*)/i);
  return match ? match[1].trim().split(/\s+/).filter(Boolean).map(Number) : [];
}

function parseFetchResponse(response, uid, mailbox, mailboxType, account) {
  const literalMatch = response.match(/\{(\d+)\}\r?\n([\s\S]*)\r?\n\)/);
  const rawMessage = literalMatch ? literalMatch[2].slice(0, Number(literalMatch[1])) : '';
  const flags = response.match(/FLAGS \(([^)]*)\)/i)?.[1] || '';
  const size = Number(response.match(/RFC822\.SIZE (\d+)/i)?.[1] || rawMessage.length);
  const internalDate = response.match(/INTERNALDATE "([^"]+)"/i)?.[1] || null;

  return messageFromRaw({ uid, mailbox, mailboxType, rawMessage, flags, size, internalDate, account });
}

export async function fetchMailboxMessages(account, mailboxType = 'inbox', limit = 25, beforeUid = null) {
  const normalizedType = normalizeMailboxType(mailboxType);
  const session = await openSession(account);

  try {
    let lastError;

    for (const mailbox of MAILBOX_CANDIDATES[normalizedType]) {
      try {
        await command(session, `SELECT ${quote(mailbox)}`);
        const searchResponse = await command(session, 'UID SEARCH ALL');
        let uids = parseSearchResponse(searchResponse).sort((a, b) => b - a);

        if (beforeUid) {
          uids = uids.filter((uid) => uid < beforeUid);
        }

        const messages = [];
        for (const uid of uids.slice(0, Math.max(1, Math.min(Number(limit || 25), 50)))) {
          const response = await command(session, `UID FETCH ${uid} (UID FLAGS RFC822.SIZE INTERNALDATE BODY.PEEK[])`);
          messages.push(parseFetchResponse(response, uid, mailbox, normalizedType, account));
        }

        return messages;
      } catch (error) {
        lastError = error;
      }
    }

    throw lastError || imapError('Could not connect to mailbox.');
  } finally {
    await closeSession(session);
  }
}
