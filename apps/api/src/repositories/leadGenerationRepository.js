import { getDb } from '../database.js';
import { assertTenant, cleanString, nowSql, resolveClientId, tagsFromString } from './shared.js';
import { enrichLeadRecords } from './leadEnrichmentService.js';

function httpError(message, status = 422) {
  const error = new Error(message);
  error.status = status;
  return error;
}

function ensureClientExists(clientId) {
  const exists = getDb().prepare('SELECT id FROM clients WHERE id = @clientId').get({ clientId });
  if (!exists) throw httpError('Client not found.');
}

function parseKeywords(value) {
  return tagsFromString(value);
}

function normalizeWebsite(value) {
  const text = cleanString(value, 500);
  if (!text) return null;
  return text.startsWith('http://') || text.startsWith('https://') ? text : `https://${text}`;
}

function parseLeadRecords(sourceData, fallbackTags = []) {
  const sourceText = String(sourceData || '')
    .replace(/\u00a0|\u202f|\u2009|\u200b/g, ' ')
    .replace(/\r\n?|\r/g, '\n');
  let sourceBlocks;

  if (/\bdirections\b/i.test(sourceText)) {
    const blocks = [];
    let current = [];

    for (const rawLine of sourceText.split('\n')) {
      const line = rawLine.trim();
      if (!line) continue;
      const boundary = /\bdirections\b/i.test(line);
      const content = line.replace(/\bwebsite\b/gi, '').replace(/\bdirections\b/gi, '').trim();
      if (content) current.push(content);

      if (boundary && current.length) {
        blocks.push(current.join('\n'));
        current = [];
      }
    }

    if (current.length) blocks.push(current.join('\n'));
    sourceBlocks = blocks;
  } else {
    sourceBlocks = sourceText.split(/\n\s*\n/g).map((block) => block.trim()).filter(Boolean);
    if (!sourceBlocks.length && sourceText.trim()) sourceBlocks = [sourceText.trim()];
  }
  const leads = [];

  for (const block of sourceBlocks) {
    const lines = block.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
    const text = lines.join(' ');
    const email = text.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i)?.[0]?.toLowerCase() || '';
    const phone = text.match(/(?:\+?\d[\d\s().-]{7,}\d)/)?.[0]?.trim() || '';
    const website = text.match(/https?:\/\/[^\s,]+|(?:www\.)?[a-z0-9.-]+\.[a-z]{2,}(?:\/[^\s,]*)?/i)?.[0] || '';
    const firstUseful = lines.find((line) => (
      !line.includes('@')
      && !/https?:\/\/|www\.|^(?:website|directions|sponsored)$/i.test(line)
      && !/^\d+[,.]\d+\s*\(\d+\)/.test(line)
      && !/^\d+\+?\s+years?\s+in\s+business/i.test(line)
      && !/^(?:closed|open\s+24|opens?\b|closes?\b)/i.test(line)
      && !/^["“”]/.test(line)
      && !/^(?:\+27|0)\d[\d\s().-]+$/.test(line)
    )) || '';
    const company = cleanString(firstUseful.replace(/\s+-\s+.*$/, ''), 255) || (email ? email.split('@')[1] : '');
    const locationFromYears = lines.find((line) => /years?\s+in\s+business\s*·/i.test(line))
      ?.split('·').at(-1)?.trim() || '';
    const standaloneLocation = lines.slice(1).find((line) => (
      /^[A-Z][A-Za-z\s-]{2,40}$/.test(line)
      && !/website|directions/i.test(line)
    )) || '';
    const addressOrLocation = locationFromYears || standaloneLocation;
    const category = lines.find((line) => /^\d+[,.]\d+\s*\(\d+\)\s*·/i.test(line))
      ?.split('·')[1]?.trim() || '';

    if (!email && !company) {
      continue;
    }

    leads.push({
      email,
      name: '',
      company,
      phone,
      tags: fallbackTags,
      source_url: normalizeWebsite(website),
      address_or_location: addressOrLocation,
      category,
      notes: text.slice(0, 1000),
    });
  }

  return leads.filter((lead, index, rows) => {
    const key = lead.email || `${lead.company}:${lead.phone}`;
    return key && rows.findIndex((row) => (row.email || `${row.company}:${row.phone}`) === key) === index;
  });
}

function scopedRun(id, user) {
  const run = getDb().prepare('SELECT * FROM marketing_lead_generation_runs WHERE id = @id')
    .get({ id: Number.parseInt(String(id), 10) });

  if (!run) throw httpError('Not found', 404);
  assertTenant(user, run.client_id);
  return run;
}

function parseLeadArray(value) {
  if (!value) return [];

  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return Array.isArray(parsed) ? parsed.filter((lead) => lead && typeof lead === 'object' && !Array.isArray(lead)) : [];
  } catch {
    return [];
  }
}

function serializeRun(run) {
  const leads = parseLeadArray(run.leads);
  const keywords = parseKeywordsFromJson(run.keywords);
  const sourceUrls = parseKeywordsFromJson(run.source_urls);
  const client = getDb().prepare('SELECT id, name, slug FROM clients WHERE id = @id').get({ id: run.client_id });

  return {
    id: run.id,
    clientId: run.client_id,
    client,
    userId: run.user_id,
    prompt: run.prompt,
    industry: run.industry,
    location: run.location,
    province: run.province,
    targetCount: run.target_count,
    keywords,
    sourceUrls,
    sourceData: run.source_data,
    status: run.status,
    discoveredCount: run.discovered_count,
    importedCount: run.imported_count,
    errorMessage: run.error_message,
    leads,
    startedAt: run.started_at,
    finishedAt: run.finished_at,
    createdAt: run.created_at,
    updatedAt: run.updated_at,
  };
}

function parseKeywordsFromJson(value) {
  if (!value) return [];

  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return Array.isArray(parsed) ? parsed.filter(Boolean).map(String) : [];
  } catch {
    return [];
  }
}

function updateRunLeads(run, leads) {
  const now = nowSql();

  getDb().prepare(`
    UPDATE marketing_lead_generation_runs
    SET leads = @leads, raw_results = @leads, discovered_count = @count, updated_at = @now
    WHERE id = @id
  `).run({
    id: run.id,
    leads: JSON.stringify(leads),
    count: leads.length,
    now,
  });
}

function csvEscape(value) {
  const text = String(value ?? '');

  if (/[",\r\n]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }

  return text;
}

function leadsToCsv(leads) {
  const headers = ['email', 'name', 'company', 'phone', 'website', 'tags', 'notes'];
  const rows = leads.map((lead) => [
    lead.email,
    lead.name || lead.decision_maker,
    lead.company,
    lead.phone || lead.phone_number,
    lead.source_url || lead.website,
    Array.isArray(lead.tags) ? lead.tags.join('; ') : lead.tags,
    lead.notes,
  ]);

  return [headers, ...rows].map((row) => row.map(csvEscape).join(',')).join('\n');
}

function leadIndex(value) {
  const index = Number.parseInt(String(value), 10);

  if (!Number.isFinite(index) || index < 0) {
    throw httpError('Lead index is invalid.');
  }

  return index;
}

export function previewLeadRun(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const keywords = parseKeywords(payload.keywords);
  const targetCount = Math.max(1, Math.min(Number.parseInt(String(payload.target_count || 25), 10) || 25, 200));
  const leads = parseLeadRecords(payload.source_data, ['lead-generation', ...keywords]).slice(0, targetCount);

  return {
    client_id: clientId,
    discovered_count: leads.length,
    leads,
    companies: leads.map((lead) => ({
      company_name: lead.company || '',
      address_or_location: lead.address_or_location || '',
      category: lead.category || '',
      phone: lead.phone || '',
      website: lead.source_url || '',
      email: lead.email || '',
      raw_block: lead.notes || '',
    })),
  };
}

export function showLeadRun(user, id) {
  return serializeRun(scopedRun(id, user));
}

export function downloadLeadRun(user, id) {
  const run = scopedRun(id, user);
  const leads = parseLeadArray(run.leads);

  return {
    filename: `lead-run-${run.id}.csv`,
    mime: 'text/csv',
    content: leadsToCsv(leads),
  };
}

export function enrichLead(user, id, indexValue, payload = {}) {
  const run = scopedRun(id, user);
  const leads = parseLeadArray(run.leads);
  const index = leadIndex(indexValue);

  if (!leads[index]) {
    throw httpError('Lead not found.', 404);
  }

  const lead = {
    ...leads[index],
    email: cleanString(payload.email, 320)?.toLowerCase() || leads[index].email || '',
    name: cleanString(payload.name || payload.decision_maker) || leads[index].name || leads[index].decision_maker || '',
    company: cleanString(payload.company) || leads[index].company || '',
    phone: cleanString(payload.phone || payload.phone_number) || leads[index].phone || leads[index].phone_number || '',
    source_url: normalizeWebsite(payload.source_url || payload.website) || leads[index].source_url || leads[index].website || null,
    notes: cleanString(payload.notes, 1000) || leads[index].notes || '',
  };

  leads[index] = lead;
  updateRunLeads(run, leads);

  return {
    index,
    lead,
    discovered_count: leads.length,
  };
}

export function deleteLead(user, id, indexValue) {
  const run = scopedRun(id, user);
  const leads = parseLeadArray(run.leads);
  const index = leadIndex(indexValue);

  if (!leads[index]) {
    throw httpError('Lead not found.', 404);
  }

  leads.splice(index, 1);
  updateRunLeads(run, leads);

  return {
    deleted: 1,
    discovered_count: leads.length,
  };
}

export function deleteLeadsBulk(user, id, indexes = []) {
  const run = scopedRun(id, user);
  const leads = parseLeadArray(run.leads);
  const selected = new Set((Array.isArray(indexes) ? indexes : [])
    .map((value) => Number.parseInt(String(value), 10))
    .filter((index) => Number.isFinite(index) && index >= 0));

  if (!selected.size) {
    throw httpError('Choose at least one lead to delete.');
  }

  const nextLeads = leads.filter((_lead, index) => !selected.has(index));
  updateRunLeads(run, nextLeads);

  return {
    deleted: leads.length - nextLeads.length,
    discovered_count: nextLeads.length,
  };
}

export function deleteLeadRunsBulk(user, ids = []) {
  const selected = [...new Set((Array.isArray(ids) ? ids : [])
    .map((value) => Number.parseInt(String(value), 10))
    .filter((id) => Number.isFinite(id) && id > 0))];

  if (!selected.length) {
    throw httpError('Choose at least one lead run to delete.');
  }

  let deleted = 0;

  getDb().transaction(() => {
    for (const id of selected) {
      const run = scopedRun(id, user);
      deleted += getDb().prepare('DELETE FROM marketing_lead_generation_runs WHERE id = @id').run({ id: run.id }).changes;
    }
  })();

  return { deleted };
}

export async function createLeadRun(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const sourceData = cleanString(payload.source_data, 100000);
  const prompt = cleanString(payload.prompt, 2000) || 'Generate leads from pasted business list. Extract contact details for each company.';

  if (!sourceData && !prompt) {
    throw httpError('Paste business/source text or enter a research brief.');
  }

  const keywords = parseKeywords(payload.keywords);
  const now = nowSql();
  const targetCount = Math.max(1, Math.min(Number.parseInt(String(payload.target_count || 25), 10) || 25, 200));
  let leads = sourceData ? parseLeadRecords(sourceData, ['lead-generation', ...keywords]) : [];
  leads = leads.slice(0, targetCount);
  const shouldEnrich = sourceData && payload.enrich !== false;
  const status = sourceData ? (shouldEnrich ? 'running' : 'completed') : 'failed';
  const errorMessage = sourceData ? null : 'Live AI/web lead discovery is not available in the local Node runtime. Paste source data to generate leads.';

  const result = getDb().prepare(`
    INSERT INTO marketing_lead_generation_runs (
      client_id, user_id, prompt, industry, location, province, target_count, keywords,
      source_urls, source_data, use_openai, status, discovered_count, imported_count,
      error_message, raw_results, leads, started_at, finished_at, created_at, updated_at
    ) VALUES (
      @clientId, @userId, @prompt, @industry, @location, @province, @targetCount, @keywords,
      @sourceUrls, @sourceData, @useOpenAi, @status, @discoveredCount, 0,
      @errorMessage, @rawResults, @leads, @now, @finishedAt, @now, @now
    )
  `).run({
    clientId,
    userId: user.id || null,
    prompt,
    industry: cleanString(payload.industry, 120),
    location: cleanString(payload.location, 120),
    province: cleanString(payload.province, 120),
    targetCount,
    keywords: JSON.stringify(keywords),
    sourceUrls: JSON.stringify(leads.map((lead) => lead.source_url).filter(Boolean)),
    sourceData,
    useOpenAi: shouldEnrich ? 1 : 0,
    status,
    discoveredCount: leads.length,
    errorMessage,
    rawResults: JSON.stringify(leads),
    leads: JSON.stringify(leads),
    finishedAt: shouldEnrich ? null : now,
    now,
  });

  const runId = Number(result.lastInsertRowid);

  if (shouldEnrich && leads.length) {
    try {
      leads = await enrichLeadRecords(leads);
      const finished = nowSql();
      getDb().prepare(`
        UPDATE marketing_lead_generation_runs
        SET status = 'completed', discovered_count = @count, source_urls = @sourceUrls,
            raw_results = @leads, leads = @leads, error_message = NULL,
            finished_at = @finished, updated_at = @finished
        WHERE id = @runId
      `).run({
        runId,
        count: leads.length,
        sourceUrls: JSON.stringify(leads.map((lead) => lead.source_url).filter(Boolean)),
        leads: JSON.stringify(leads),
        finished,
      });
    } catch (error) {
      const finished = nowSql();
      getDb().prepare(`
        UPDATE marketing_lead_generation_runs
        SET status = 'failed', error_message = @message, finished_at = @finished, updated_at = @finished
        WHERE id = @runId
      `).run({ runId, message: cleanString(error.message, 2000) || 'Lead enrichment failed.', finished });
      throw error;
    }
  }

  return runId;
}

function normalizeCompany(company) {
  return String(company || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function companyExists(clientId, company) {
  const normalized = normalizeCompany(company);
  if (!normalized) return false;

  return getDb().prepare('SELECT company FROM marketing_contacts WHERE client_id = @clientId AND company IS NOT NULL')
    .all({ clientId })
    .some((row) => normalizeCompany(row.company) === normalized);
}

function audienceName(run) {
  const client = getDb().prepare('SELECT name FROM clients WHERE id = @clientId').get({ clientId: run.client_id });
  return `Lead Run #${run.id} | ${client?.name || 'Client'}`.slice(0, 255);
}

function audienceFromName(clientId, name, runId) {
  const existing = getDb().prepare('SELECT id FROM marketing_audiences WHERE client_id = @clientId AND name = @name')
    .get({ clientId, name });
  if (existing) return existing.id;

  const now = nowSql();
  return Number(getDb().prepare(`
    INSERT INTO marketing_audiences (client_id, name, description, source, created_at, updated_at)
    VALUES (@clientId, @name, @description, 'lead_generation', @now, @now)
  `).run({ clientId, name, description: `Imported from lead generation run #${runId}.`, now }).lastInsertRowid);
}

export function importLeadRun(user, id) {
  const run = scopedRun(id, user);
  const leads = parseLeadArray(run.leads);
  const now = nowSql();
  const stats = { rows: leads.length, created: 0, updated: 0, skipped: 0, errors: [], contact_ids: [] };

  getDb().transaction(() => {
    for (const lead of leads) {
      const email = cleanString(lead.email, 320)?.toLowerCase();
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        stats.skipped += 1;
        continue;
      }

      const tags = Array.isArray(lead.tags) ? lead.tags.map(String).filter(Boolean) : tagsFromString(lead.tags);
      const metadata = {
        source_url: lead.source_url || lead.website || null,
        notes: lead.notes || null,
      };
      const existing = getDb().prepare('SELECT id FROM marketing_contacts WHERE client_id = @clientId AND lower(email) = lower(@email)')
        .get({ clientId: run.client_id, email });

      if (existing) {
        getDb().prepare(`
          UPDATE marketing_contacts
          SET name = COALESCE(@name, name), company = COALESCE(@company, company),
              phone = COALESCE(@phone, phone), tags = @tags, metadata = @metadata,
              last_imported_at = @now, updated_at = @now
          WHERE id = @id
        `).run({
          id: existing.id,
          name: cleanString(lead.name || lead.decision_maker),
          company: cleanString(lead.company),
          phone: cleanString(lead.phone || lead.phone_number),
          tags: JSON.stringify(tags),
          metadata: JSON.stringify(metadata),
          now,
        });
        stats.updated += 1;
        stats.contact_ids.push(existing.id);
        continue;
      }

      if (lead.company && companyExists(run.client_id, lead.company)) {
        stats.skipped += 1;
        continue;
      }

      const contactId = Number(getDb().prepare(`
        INSERT INTO marketing_contacts (
          client_id, email, name, company, phone, tags, metadata, status, source,
          subscribed_at, last_imported_at, created_at, updated_at
        ) VALUES (
          @clientId, @email, @name, @company, @phone, @tags, @metadata, 'subscribed', 'lead_generation',
          @now, @now, @now, @now
        )
      `).run({
        clientId: run.client_id,
        email,
        name: cleanString(lead.name || lead.decision_maker),
        company: cleanString(lead.company),
        phone: cleanString(lead.phone || lead.phone_number),
        tags: JSON.stringify(tags),
        metadata: JSON.stringify(metadata),
        now,
      }).lastInsertRowid);
      stats.created += 1;
      stats.contact_ids.push(contactId);
    }

    const audienceId = audienceFromName(run.client_id, audienceName(run), run.id);
    const attach = getDb().prepare(`
      INSERT OR IGNORE INTO marketing_audience_contact (marketing_audience_id, marketing_contact_id, created_at, updated_at)
      VALUES (@audienceId, @contactId, @now, @now)
    `);
    for (const contactId of [...new Set(stats.contact_ids)]) {
      attach.run({ audienceId, contactId, now });
    }

    getDb().prepare(`
      UPDATE marketing_lead_generation_runs
      SET imported_count = imported_count + @imported, updated_at = @now
      WHERE id = @id
    `).run({ id: run.id, imported: stats.created + stats.updated, now });
  })();

  return stats;
}

export function deleteLeadRun(user, id) {
  const run = scopedRun(id, user);
  getDb().prepare('DELETE FROM marketing_lead_generation_runs WHERE id = @id').run({ id: run.id });
}
