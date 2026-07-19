import crypto from 'node:crypto';
import { getDb } from '../database.js';
import { nowSql } from './shared.js';

export const trackingPixel = Buffer.from('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==', 'base64');

function notFound() {
  const error = new Error('Not found');
  error.status = 404;
  return error;
}

function tokensMatch(expected, actual) {
  const left = Buffer.from(String(expected || ''));
  const right = Buffer.from(String(actual || ''));

  return left.length > 0 && left.length === right.length && crypto.timingSafeEqual(left, right);
}

export function recordEmailOpen(emailLogId) {
  const log = getDb().prepare('SELECT id, opened_at FROM email_logs WHERE id = @id LIMIT 1')
    .get({ id: Number.parseInt(String(emailLogId), 10) });

  if (!log) {
    throw notFound();
  }

  if (!log.opened_at) {
    const now = nowSql();

    getDb().prepare(`
      UPDATE email_logs
      SET status = 'opened', opened_at = @now, updated_at = @now
      WHERE id = @id
    `).run({ id: log.id, now });
  }
}

export function unsubscribeContact(contactId, token) {
  const contact = getDb().prepare(`
    SELECT id, email, unsubscribe_token, status
    FROM marketing_contacts
    WHERE id = @id
    LIMIT 1
  `).get({ id: Number.parseInt(String(contactId), 10) });

  if (!contact || !tokensMatch(contact.unsubscribe_token, token)) {
    throw notFound();
  }

  if (contact.status !== 'unsubscribed') {
    const now = nowSql();

    getDb().prepare(`
      UPDATE marketing_contacts
      SET status = 'unsubscribed', unsubscribed_at = @now, updated_at = @now
      WHERE id = @id
    `).run({ id: contact.id, now });
  }

  return {
    contact: {
      id: contact.id,
      email: contact.email,
      status: 'unsubscribed',
    },
  };
}
