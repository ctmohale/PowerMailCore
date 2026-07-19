import { getDb } from '../src/database.js';
import {
  createLeadRun,
  deleteLead,
  deleteLeadRun,
  deleteLeadRunsBulk,
  downloadLeadRun,
  enrichLead,
  importLeadRun,
  previewLeadRun,
  showLeadRun,
} from '../src/repositories/leadGenerationRepository.js';
import { nowSql } from '../src/repositories/shared.js';

const db = getDb();
const suffix = Date.now();
const now = nowSql();
let clientId;
let runId;
let bulkRunId;

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage18 Client ${suffix}`,
    slug: `stage18-client-${suffix}`,
    email: `stage18-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  const sourceData = [
    `Alpha Studio ${suffix}`,
    `hello-alpha-${suffix}@example.com`,
    '+27 82 111 2222',
    `https://alpha-${suffix}.example.com`,
    '',
    `Beta Works ${suffix}`,
    `sales-beta-${suffix}@example.com`,
    '+27 83 333 4444',
    `www.beta-${suffix}.example.com`,
  ].join('\n');

  const preview = previewLeadRun({ role: 'admin' }, {
    client_id: clientId,
    target_count: 10,
    keywords: 'lead-generation,web-design',
    source_data: sourceData,
  });

  runId = await createLeadRun({ role: 'admin' }, {
    client_id: clientId,
    prompt: 'Find local web design leads',
    target_count: 10,
    keywords: 'lead-generation,web-design',
    source_data: sourceData,
    enrich: false,
  });

  const run = db.prepare('SELECT status, discovered_count FROM marketing_lead_generation_runs WHERE id = @runId')
    .get({ runId });
  const shownRun = showLeadRun({ role: 'admin' }, runId);
  const enriched = enrichLead({ role: 'admin' }, runId, 0, {
    name: 'Alpha Decision Maker',
    website: `alpha-${suffix}.example.com`,
  });
  const download = downloadLeadRun({ role: 'admin' }, runId);
  const deleteResult = deleteLead({ role: 'admin' }, runId, 1);
  const result = importLeadRun({ role: 'admin' }, runId);
  const contacts = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM marketing_contacts
    WHERE client_id = @clientId AND source = 'lead_generation'
  `).get({ clientId }).aggregate;
  const audienceContacts = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM marketing_audience_contact
    INNER JOIN marketing_audiences ON marketing_audiences.id = marketing_audience_contact.marketing_audience_id
    WHERE marketing_audiences.client_id = @clientId
      AND marketing_audiences.source = 'lead_generation'
  `).get({ clientId }).aggregate;

  bulkRunId = await createLeadRun({ role: 'admin' }, {
    client_id: clientId,
    source_data: `Gamma ${suffix}\ngamma-${suffix}@example.com`,
    enrich: false,
  });
  const bulkDelete = deleteLeadRunsBulk({ role: 'admin' }, [bulkRunId]);
  bulkRunId = null;

  if (
    preview.discovered_count !== 2
    || run.status !== 'completed'
    || run.discovered_count !== 2
    || shownRun.leads.length !== 2
    || enriched.lead.name !== 'Alpha Decision Maker'
    || !download.content.includes(`hello-alpha-${suffix}@example.com`)
    || deleteResult.discovered_count !== 1
    || result.created !== 1
    || contacts !== 1
    || audienceContacts !== 1
    || bulkDelete.deleted !== 1
  ) {
    throw new Error(`Lead smoke failed preview=${JSON.stringify(preview)} run=${JSON.stringify(run)} shown=${shownRun.leads.length} enriched=${JSON.stringify(enriched)} delete=${JSON.stringify(deleteResult)} result=${JSON.stringify(result)} contacts=${contacts} audienceContacts=${audienceContacts} bulk=${JSON.stringify(bulkDelete)}`);
  }

  console.log(JSON.stringify({
    ok: true,
    runId,
    discovered: run.discovered_count,
    created: result.created,
    audienceContacts,
  }));
} finally {
  if (bulkRunId) {
    deleteLeadRun({ role: 'admin' }, bulkRunId);
  }
  if (clientId) {
    const audienceIds = db.prepare('SELECT id FROM marketing_audiences WHERE client_id = @clientId').all({ clientId }).map((row) => row.id);
    if (audienceIds.length) {
      const placeholders = audienceIds.map(() => '?').join(',');
      db.prepare(`DELETE FROM marketing_audience_contact WHERE marketing_audience_id IN (${placeholders})`).run(...audienceIds);
    }
    db.prepare('DELETE FROM marketing_contacts WHERE client_id = @clientId').run({ clientId });
    db.prepare('DELETE FROM marketing_audiences WHERE client_id = @clientId').run({ clientId });
    db.prepare('DELETE FROM marketing_lead_generation_runs WHERE client_id = @clientId').run({ clientId });
    db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
  }
}
