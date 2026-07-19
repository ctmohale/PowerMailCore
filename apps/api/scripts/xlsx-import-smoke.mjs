import { strToU8, zipSync } from 'fflate';
import { getDb } from '../src/database.js';
import { importContacts } from '../src/repositories/marketingWriteRepository.js';
import { nowSql } from '../src/repositories/shared.js';

const xml = (value) => strToU8(value);
const workbook = Buffer.from(zipSync({
  '[Content_Types].xml': xml(`<?xml version="1.0" encoding="UTF-8"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
      <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
      <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
      <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    </Types>`),
  '_rels/.rels': xml(`<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    </Relationships>`),
  'xl/workbook.xml': xml(`<?xml version="1.0" encoding="UTF-8"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <sheets><sheet name="Contacts" sheetId="1" r:id="rId1"/></sheets>
    </workbook>`),
  'xl/_rels/workbook.xml.rels': xml(`<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
      <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    </Relationships>`),
  'xl/styles.xml': xml(`<?xml version="1.0" encoding="UTF-8"?>
    <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
      <numFmts count="0"/><fonts count="1"><font/></fonts><fills count="1"><fill/></fills>
      <borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>
      <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
    </styleSheet>`),
  'xl/worksheets/sheet1.xml': xml(`<?xml version="1.0" encoding="UTF-8"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
      <row r="1"><c r="A1" t="inlineStr"><is><t>Email</t></is></c><c r="B1" t="inlineStr"><is><t>Name</t></is></c><c r="C1" t="inlineStr"><is><t>Company</t></is></c><c r="D1" t="inlineStr"><is><t>Phone</t></is></c></row>
      <row r="2"><c r="A2" t="inlineStr"><is><t>xlsx.one@example.com</t></is></c><c r="B2" t="inlineStr"><is><t>XLSX One</t></is></c><c r="C2" t="inlineStr"><is><t>XLSX One Ltd</t></is></c><c r="D2" t="inlineStr"><is><t>011 100 0001</t></is></c></row>
      <row r="3"><c r="A3" t="inlineStr"><is><t>xlsx.two@example.com</t></is></c><c r="B3" t="inlineStr"><is><t>XLSX Two</t></is></c><c r="C3" t="inlineStr"><is><t>XLSX Two Ltd</t></is></c><c r="D3" t="inlineStr"><is><t>011 100 0002</t></is></c></row>
    </sheetData></worksheet>`),
}));

const db = getDb();
const suffix = Date.now();
const now = nowSql();
let clientId;
let audienceId;

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({ name: `XLSX Smoke ${suffix}`, slug: `xlsx-smoke-${suffix}`, email: `xlsx-${suffix}@example.com`, now }).lastInsertRowid);
  audienceId = Number(db.prepare(`
    INSERT INTO marketing_audiences (client_id, name, source, created_at, updated_at)
    VALUES (@clientId, 'XLSX Audience', 'import', @now, @now)
  `).run({ clientId, now }).lastInsertRowid);

  const result = await importContacts({ id: 1, role: 'admin' }, {
    client_id: clientId,
    audience_ids: [audienceId],
    contacts_file: {
      name: 'contacts.xlsx',
      mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      content_base64: workbook.toString('base64'),
    },
  });
  const contacts = db.prepare('SELECT email, name, company, phone FROM marketing_contacts WHERE client_id = @clientId ORDER BY email').all({ clientId });
  const audienceContacts = db.prepare('SELECT COUNT(*) AS count FROM marketing_audience_contact WHERE marketing_audience_id = @audienceId').get({ audienceId }).count;

  if (result.created !== 2 || result.rows !== 2 || contacts.length !== 2 || audienceContacts !== 2) {
    throw new Error(`XLSX import smoke failed: ${JSON.stringify({ result, contacts, audienceContacts })}`);
  }

  console.log(JSON.stringify({ ok: true, created: result.created, rows: result.rows, audienceContacts }));
} finally {
  if (clientId) db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
}
