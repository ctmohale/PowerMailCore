import { getDb } from '../src/database.js';
import { importContacts } from '../src/repositories/marketingWriteRepository.js';
import { nowSql } from '../src/repositories/shared.js';

const db = getDb();
const suffix = Date.now();
const now = nowSql();
let clientId;
let audienceId;
const contactIds = [];

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage28 Client ${suffix}`,
    slug: `stage28-client-${suffix}`,
    email: `stage28-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  audienceId = Number(db.prepare(`
    INSERT INTO marketing_audiences (client_id, name, source, created_at, updated_at)
    VALUES (@clientId, 'Stage Import Audience', 'manual', @now, @now)
  `).run({ clientId, now }).lastInsertRowid);

  const existingId = Number(db.prepare(`
    INSERT INTO marketing_contacts (
      client_id, email, name, company, phone, tags, metadata, status, source, subscribed_at, created_at, updated_at
    ) VALUES (
      @clientId, @email, 'Old Name', 'Old Co', NULL, '[]', '{}', 'subscribed', 'manual', @now, @now, @now
    )
  `).run({ clientId, email: `existing-${suffix}@example.com`, now }).lastInsertRowid);
  contactIds.push(existingId);

  const csv = [
    'Email,Name,Company,Phone,Tags,Website',
    `new-one-${suffix}@example.com,New One,New Co,+27110000001,"lead, import",https://new.example.com`,
    `existing-${suffix}@example.com,Updated Name,Updated Co,+27110000002,updated,https://updated.example.com`,
    `skip-company-${suffix}@example.com,Skip Company,New Co,+27110000003,skip,https://skip.example.com`,
    'missing-email,No Email,Missing Co,+27110000004,skip,',
  ].join('\n');

  const result = await importContacts({ id: 1, role: 'admin' }, {
    client_id: clientId,
    contacts_file: {
      name: 'stage-import.csv',
      mime: 'text/csv',
      content_base64: Buffer.from(csv).toString('base64'),
    },
    audience_ids: [audienceId],
  });

  const importedRows = db.prepare(`
    SELECT id, email, name, company, phone, tags, metadata, last_imported_at
    FROM marketing_contacts
    WHERE client_id = @clientId
    ORDER BY id ASC
  `).all({ clientId });
  for (const row of importedRows) {
    contactIds.push(row.id);
  }

  const audienceContacts = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM marketing_audience_contact
    WHERE marketing_audience_id = @audienceId
  `).get({ audienceId }).aggregate;
  const newContact = importedRows.find((row) => row.email === `new-one-${suffix}@example.com`);
  const updatedContact = importedRows.find((row) => row.email === `existing-${suffix}@example.com`);
  const skippedCompany = importedRows.find((row) => row.email === `skip-company-${suffix}@example.com`);

  if (
    result.rows !== 4
    || result.created !== 1
    || result.updated !== 1
    || result.skipped !== 2
    || audienceContacts !== 2
    || !newContact
    || !JSON.parse(newContact.tags).includes('lead')
    || JSON.parse(newContact.metadata).website !== 'https://new.example.com'
    || updatedContact.name !== 'Updated Name'
    || updatedContact.company !== 'Updated Co'
    || !updatedContact.last_imported_at
    || skippedCompany
  ) {
    throw new Error(`Import smoke failed result=${JSON.stringify(result)} rows=${JSON.stringify(importedRows)} audienceContacts=${audienceContacts}`);
  }

  console.log(JSON.stringify({
    ok: true,
    rows: result.rows,
    created: result.created,
    updated: result.updated,
    skipped: result.skipped,
    audienceContacts,
  }));
} finally {
  if (contactIds.length) {
    const uniqueIds = [...new Set(contactIds)];
    const placeholders = uniqueIds.map(() => '?').join(',');
    db.prepare(`DELETE FROM marketing_audience_contact WHERE marketing_contact_id IN (${placeholders})`).run(...uniqueIds);
    db.prepare(`DELETE FROM marketing_contacts WHERE id IN (${placeholders})`).run(...uniqueIds);
  }
  if (audienceId) {
    db.prepare('DELETE FROM marketing_audiences WHERE id = @audienceId').run({ audienceId });
  }
  if (clientId) {
    db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
  }
}
