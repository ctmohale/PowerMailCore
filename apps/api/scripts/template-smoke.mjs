import { getDb } from '../src/database.js';
import {
  createClient,
  createEmailTemplate,
  deleteClient,
  deleteEmailTemplate,
  updateEmailTemplate,
} from '../src/repositories/adminWriteRepository.js';
import { showEmailTemplate } from '../src/repositories/appRepository.js';
import {
  dataWithHtmlBodySlots,
  renderTemplate,
  stripHtmlToText,
} from '../src/repositories/emailSendRepository.js';

const db = getDb();
const admin = { id: 0, role: 'admin' };
const suffix = Date.now();
let clientId;
let templateId;

try {
  clientId = createClient({
    name: `Template Smoke ${suffix}`,
    contact_email: `template-smoke-${suffix}@example.com`,
    is_active: true,
  });

  templateId = createEmailTemplate(admin, {
    client_id: clientId,
    key: `smoke-${suffix}`,
    name: 'Template Smoke Test',
    subject: 'Hello {{ name }} from {{ company }}',
    type: 'communication',
    body_html: '<h1>Hello {{ name }}</h1><main>{{ body }}</main>',
    body_text: 'Hello {{ name }}\n\n{{ body }}',
    is_active: true,
  });

  updateEmailTemplate(admin, templateId, {
    subject: 'Updated for {{ name }}',
    body_html: '<h1>Hello {{ name }}</h1><main>{{ body }}</main><footer>{{ company }}</footer>',
  });

  const template = showEmailTemplate(templateId, admin);
  const data = {
    name: '<Preview Recipient>',
    company: 'PowerMail Core',
    body: 'First line\nSecond line',
  };
  const subject = renderTemplate(template.subject, data);
  const html = renderTemplate(template.body_html, dataWithHtmlBodySlots(data), {
    escapeHtml: true,
    rawKeys: ['body', 'message', 'body_html', 'message_html', 'unsubscribe_url'],
  });
  const text = renderTemplate(template.body_text, data);

  if (
    template.name !== 'Template Smoke Test'
    || subject !== 'Updated for <Preview Recipient>'
    || !html.includes('&lt;Preview Recipient&gt;')
    || !html.includes('First line<br />Second line')
    || stripHtmlToText(html).includes('<h1>')
    || !text.includes('First line\nSecond line')
  ) {
    throw new Error(`Template smoke failed: ${JSON.stringify({ template, subject, html, text })}`);
  }

  deleteEmailTemplate(admin, templateId);
  templateId = undefined;

  console.log(JSON.stringify({ ok: true, subject, renderedHtml: html, renderedText: text }));
} finally {
  if (templateId) {
    db.prepare('DELETE FROM email_templates WHERE id = @templateId').run({ templateId });
  }

  if (clientId) {
    deleteClient(clientId);
  }
}
