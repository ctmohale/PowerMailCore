import fs from 'node:fs';
import net from 'node:net';
import tls from 'node:tls';

function smtpError(message, response = null) {
  const error = new Error(response ? `${message}: ${response.text}` : message);
  error.smtpResponse = response;
  return error;
}

function encodeHeader(value) {
  const text = String(value ?? '');

  return /^[\x00-\x7F]*$/.test(text)
    ? text.replace(/\r|\n/g, ' ')
    : `=?UTF-8?B?${Buffer.from(text).toString('base64')}?=`;
}

function addressHeader(email, name = '') {
  const cleanEmail = String(email || '').replace(/[<>\r\n]/g, '');
  const cleanName = String(name || '').replace(/["\r\n]/g, '').trim();

  return cleanName ? `"${encodeHeader(cleanName)}" <${cleanEmail}>` : cleanEmail;
}

function normalizeLineEndings(value) {
  return String(value ?? '').replace(/\r?\n/g, '\r\n');
}

function dotStuff(value) {
  return normalizeLineEndings(value).replace(/^\./gm, '..');
}

function quotedPrintableHtml(value) {
  return normalizeLineEndings(value)
    .replace(/=/g, '=3D')
    .replace(/[^\S\r\n]+$/gm, (match) => match.replace(/ /g, '=20').replace(/\t/g, '=09'));
}

function headerParam(value) {
  return String(value || 'attachment')
    .replace(/["\r\n]/g, '')
    .slice(0, 180);
}

function wrapBase64(value) {
  return String(value || '').replace(/.{1,76}/g, '$&\r\n').trimEnd();
}

function attachmentContentBase64(attachment) {
  if (attachment.content_base64 || attachment.data_base64 || attachment.base64) {
    return String(attachment.content_base64 || attachment.data_base64 || attachment.base64).replace(/^data:[^;]+;base64,/, '');
  }

  if (attachment.content || attachment.data) {
    return Buffer.from(String(attachment.content || attachment.data), 'utf8').toString('base64');
  }

  if (attachment.path) {
    return fs.readFileSync(String(attachment.path)).toString('base64');
  }

  throw new Error(`Attachment ${attachment.name || 'file'} is missing content.`);
}

function attachmentPart(attachment, boundary) {
  const name = headerParam(attachment.name || (attachment.path ? String(attachment.path).split(/[\\/]/).pop() : 'attachment'));
  const mime = headerParam(attachment.mime || 'application/octet-stream');

  return [
    `--${boundary}`,
    `Content-Type: ${mime}; name="${name}"`,
    'Content-Transfer-Encoding: base64',
    `Content-Disposition: attachment; filename="${name}"`,
    '',
    wrapBase64(attachmentContentBase64(attachment)),
  ].join('\r\n');
}

function readResponse(socket, bufferState) {
  return new Promise((resolve, reject) => {
    let timer;

    function cleanup() {
      clearTimeout(timer);
      socket.off('data', onData);
      socket.off('error', onError);
      socket.off('end', onEnd);
    }

    function tryParse() {
      const lines = bufferState.text.split(/\r?\n/);
      const lastPartial = lines.pop() ?? '';
      const completeLines = lines.filter(Boolean);

      if (!completeLines.length) {
        bufferState.text = lastPartial;
        return false;
      }

      const lastLine = completeLines[completeLines.length - 1];

      if (!/^\d{3} /.test(lastLine)) {
        bufferState.text = `${completeLines.join('\r\n')}\r\n${lastPartial}`;
        return false;
      }

      bufferState.text = lastPartial;
      cleanup();
      resolve({
        code: Number.parseInt(lastLine.slice(0, 3), 10),
        text: completeLines.join('\n'),
      });
      return true;
    }

    function onData(chunk) {
      bufferState.text += chunk.toString('utf8');
      tryParse();
    }

    function onError(error) {
      cleanup();
      reject(error);
    }

    function onEnd() {
      cleanup();
      reject(smtpError('SMTP connection closed unexpectedly'));
    }

    timer = setTimeout(() => {
      cleanup();
      reject(smtpError('SMTP response timed out'));
    }, 30000);

    socket.on('data', onData);
    socket.on('error', onError);
    socket.on('end', onEnd);

    tryParse();
  });
}

function writeLine(socket, line) {
  socket.write(`${line}\r\n`);
}

async function command(socket, bufferState, line, expectedCodes, commandName = null) {
  writeLine(socket, line);
  const response = await readResponse(socket, bufferState);
  const expected = Array.isArray(expectedCodes) ? expectedCodes : [expectedCodes];

  if (!expected.includes(response.code)) {
    throw smtpError(`SMTP command failed (${commandName || line.split(' ')[0]})`, response);
  }

  return response;
}

function connectSocket(account) {
  const port = Number(account.smtp_port || 587);
  const host = account.smtp_host;
  const timeout = 30000;

  return new Promise((resolve, reject) => {
    const socket = account.smtp_encryption === 'ssl'
      ? tls.connect({ host, port, servername: host })
      : net.connect({ host, port });

    const timer = setTimeout(() => {
      socket.destroy();
      reject(smtpError('SMTP connection timed out'));
    }, timeout);

    socket.once(account.smtp_encryption === 'ssl' ? 'secureConnect' : 'connect', () => {
      clearTimeout(timer);
      socket.setTimeout(timeout);
      resolve(socket);
    });

    socket.once('error', (error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

async function establishSession(account) {
  let socket = await connectSocket(account);
  const bufferState = { text: '' };
  const greeting = await readResponse(socket, bufferState);

  if (greeting.code !== 220) {
    throw smtpError('SMTP server rejected connection', greeting);
  }

  await command(socket, bufferState, 'EHLO powermail.local', [250]);

  if (account.smtp_encryption === 'starttls') {
    await command(socket, bufferState, 'STARTTLS', [220]);
    socket = tls.connect({ socket, servername: account.smtp_host });
    bufferState.text = '';
    await new Promise((resolve, reject) => {
      socket.once('secureConnect', resolve);
      socket.once('error', reject);
    });
    await command(socket, bufferState, 'EHLO powermail.local', [250]);
  }

  if (account.smtp_username || account.smtp_password) {
    await command(socket, bufferState, 'AUTH LOGIN', [334]);
    await command(
      socket,
      bufferState,
      Buffer.from(String(account.smtp_username || '')).toString('base64'),
      [334],
      'AUTH username',
    );
    await command(
      socket,
      bufferState,
      Buffer.from(String(account.smtp_password || '')).toString('base64'),
      [235],
      'AUTH credentials',
    );
  }

  return { socket, bufferState };
}

async function closeSession(socket, bufferState) {
  try {
    await command(socket, bufferState, 'QUIT', [221]);
  } catch {
    socket.end();
  }
}

export async function verifySmtpAccount(account) {
  const { socket, bufferState } = await establishSession(account);
  await closeSession(socket, bufferState);
}

export function buildEmailMessage({ account, to, subject, html, text = '', messageId, listUnsubscribeUrl = null, attachments = [] }) {
  const alternativeBoundary = `powermail_alt_${Date.now()}_${Math.random().toString(16).slice(2)}`;
  const mixedBoundary = `powermail_mixed_${Date.now()}_${Math.random().toString(16).slice(2)}`;
  const safeAttachments = Array.isArray(attachments) ? attachments.filter(Boolean) : [];
  const headers = [
    `From: ${addressHeader(account.email, account.from_name || account.email)}`,
    `To: ${addressHeader(to)}`,
    `Subject: ${encodeHeader(subject)}`,
    `Message-ID: <${messageId}>`,
    `Date: ${new Date().toUTCString()}`,
    'MIME-Version: 1.0',
  ];

  if (listUnsubscribeUrl) {
    headers.push(`List-Unsubscribe: <${listUnsubscribeUrl}>`);
    headers.push('List-Unsubscribe-Post: List-Unsubscribe=One-Click');
  }

  if (safeAttachments.length) {
    headers.push(`Content-Type: multipart/mixed; boundary="${mixedBoundary}"`);
  } else {
    headers.push(`Content-Type: multipart/alternative; boundary="${alternativeBoundary}"`);
  }

  const alternativePart = [
    safeAttachments.length ? `--${mixedBoundary}` : null,
    safeAttachments.length ? `Content-Type: multipart/alternative; boundary="${alternativeBoundary}"` : null,
    safeAttachments.length ? '' : null,
    `--${alternativeBoundary}`,
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    '',
    normalizeLineEndings(text || ''),
    `--${alternativeBoundary}`,
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: quoted-printable',
    '',
    quotedPrintableHtml(html || ''),
    `--${alternativeBoundary}--`,
  ].filter((line) => line !== null).join('\r\n');

  if (!safeAttachments.length) {
    return [
      headers.join('\r\n'),
      '',
      alternativePart,
      '',
    ].join('\r\n');
  }

  return [
    headers.join('\r\n'),
    '',
    alternativePart,
    ...safeAttachments.map((attachment) => attachmentPart(attachment, mixedBoundary)),
    `--${mixedBoundary}--`,
    '',
  ].join('\r\n');
}

export async function sendSmtpMessage({ account, to, rawMessage }) {
  const { socket, bufferState } = await establishSession(account);

  try {
    await command(socket, bufferState, `MAIL FROM:<${account.email}>`, [250]);
    await command(socket, bufferState, `RCPT TO:<${to}>`, [250, 251]);
    await command(socket, bufferState, 'DATA', [354]);
    socket.write(`${dotStuff(rawMessage)}\r\n.\r\n`);
    const dataResponse = await readResponse(socket, bufferState);

    if (dataResponse.code !== 250) {
      throw smtpError('SMTP DATA command failed', dataResponse);
    }

    await closeSession(socket, bufferState);
  } catch (error) {
    socket.destroy();
    throw error;
  }
}
