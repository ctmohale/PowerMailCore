import { useEffect, useMemo, useState } from 'react';
import { apiDelete, apiGet, apiPatch, apiPost } from '../api/client.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { useUiFeedback } from '../context/UiFeedbackContext.jsx';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';

const DEFAULT_HTML_BODY = `<!doctype html>
<html>
<body style="margin:0;background:#f6f7fb;font-family:Arial,sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:24px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="padding:28px 32px;border-bottom:1px solid #e5e7eb;">
              <h1 style="margin:0;font-size:22px;">Hello {{ name }}</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px;font-size:15px;line-height:1.65;">
              {{ body }}
            </td>
          </tr>
          <tr>
            <td style="padding:18px 32px;background:#f9fafb;color:#6b7280;font-size:12px;">
              Sent by PowerMail Core
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>`;

const DEFAULT_TEXT_BODY = `Hello {{ name }},

{{ body }}

Sent by PowerMail Core`;

const PREVIEW_VALUES = {
  name: 'Preview Recipient',
  first_name: 'Preview',
  last_name: 'Recipient',
  company: 'PowerMail Core',
  email: 'preview@example.com',
  body: '<p>This is where the compose message will appear.</p><p>Second paragraph preview.</p>',
  message: '<p>This is where the compose message will appear.</p><p>Second paragraph preview.</p>',
  unsubscribe_url: '#unsubscribe-preview',
};

const TEXT_PREVIEW_VALUES = {
  ...PREVIEW_VALUES,
  body: 'This is where the compose message will appear.\n\nSecond paragraph preview.',
  message: 'This is where the compose message will appear.\n\nSecond paragraph preview.',
};

const PREVIEW_FIT_STYLES = `<style>
html,body{max-width:100%!important;overflow-x:hidden!important}
body{margin-left:auto!important;margin-right:auto!important}
*,*::before,*::after{box-sizing:border-box!important}
table,tbody,thead,tfoot,tr,td,th{max-width:100%!important}
table[width]{width:100%!important}
img,video,canvas{max-width:100%!important;height:auto!important}
pre,code,p,span,a,div,h1,h2,h3{overflow-wrap:anywhere}
</style>`;

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function renderTemplate(value, data, html = false) {
  return String(value || '').replace(/{{\s*([A-Za-z0-9_.-]+)\s*}}/g, (_match, key) => {
    if (!Object.prototype.hasOwnProperty.call(data, key)) {
      return '';
    }

    const rendered = String(data[key] ?? '');
    return html && !['body', 'message', 'unsubscribe_url'].includes(key) ? escapeHtml(rendered) : rendered;
  });
}

function fitPreviewDocument(html) {
  if (/<\/head>/i.test(html)) {
    return html.replace(/<\/head>/i, `${PREVIEW_FIT_STYLES}</head>`);
  }

  if (/<body[^>]*>/i.test(html)) {
    return html.replace(/<body([^>]*)>/i, `<head>${PREVIEW_FIT_STYLES}</head><body$1>`);
  }

  return `<!doctype html><html><head>${PREVIEW_FIT_STYLES}</head><body>${html}</body></html>`;
}

function blankTemplate(clients) {
  return {
    client_id: clients.length === 1 ? clients[0].id : '',
    key: '',
    name: '',
    subject: 'Welcome to {{ company }}',
    type: 'communication',
    body_html: DEFAULT_HTML_BODY,
    body_text: DEFAULT_TEXT_BODY,
    is_active: true,
  };
}

function templateForm(row) {
  return {
    client_id: row.clientId || row.client_id || '',
    key: row.key || '',
    name: row.name || '',
    subject: row.subject || '',
    type: row.type || 'communication',
    body_html: row.body_html || DEFAULT_HTML_BODY,
    body_text: row.body_text || '',
    is_active: Boolean(row.isActive ?? row.is_active),
  };
}

function TemplatePreview({ form, mode, setMode }) {
  const subject = useMemo(() => renderTemplate(form.subject || 'Subject preview', PREVIEW_VALUES), [form.subject]);
  const html = useMemo(() => fitPreviewDocument(renderTemplate(form.body_html, PREVIEW_VALUES, true)), [form.body_html]);
  const text = useMemo(() => renderTemplate(form.body_text || '', TEXT_PREVIEW_VALUES), [form.body_text]);

  return (
    <aside className="react-template-preview">
      <div className="react-template-preview-head">
        <div>
          <span>Preview</span>
          <strong title={subject}>{subject || 'Subject preview'}</strong>
        </div>
        <div className="template-preview-tabs" role="tablist" aria-label="Template preview format">
          <button type="button" className={mode === 'html' ? 'active' : ''} onClick={() => setMode('html')}>HTML</button>
          <button type="button" className={mode === 'text' ? 'active' : ''} onClick={() => setMode('text')}>Text</button>
        </div>
      </div>
      {mode === 'html' ? (
        <iframe title="Template HTML preview" sandbox="" srcDoc={html} />
      ) : (
        <pre className="template-text-preview">{text || 'No text fallback supplied.'}</pre>
      )}
    </aside>
  );
}

function TemplateEditor({ form, setForm, clients, title, onClose, onSubmit, submitting }) {
  const [previewMode, setPreviewMode] = useState('html');
  const update = (name, value) => setForm((current) => ({ ...current, [name]: value }));

  return (
    <section className="modal-backdrop template-modal-backdrop">
      <form className="modal-panel react-template-modal" onSubmit={onSubmit}>
        <div className="modal-head">
          <div>
            <p className="eyebrow">Email Content</p>
            <h2>{title}</h2>
            <p className="template-modal-copy">Use {'{{ body }}'} where the compose message should appear.</p>
          </div>
          <button type="button" className="secondary-button" onClick={onClose}>Close</button>
        </div>

        <div className="react-template-builder">
          <div className="react-template-fields">
            <div className="template-meta-grid">
              <label>
                <span>Client</span>
                <select value={form.client_id} onChange={(event) => update('client_id', event.target.value)} required>
                  <option value="">Choose client</option>
                  {clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
                </select>
              </label>
              <label>
                <span>Template Key</span>
                <input value={form.key} onChange={(event) => update('key', event.target.value.toLowerCase())} placeholder="welcome" required />
              </label>
              <label>
                <span>Name</span>
                <input value={form.name} onChange={(event) => update('name', event.target.value)} placeholder="Welcome Email" required />
              </label>
              <label>
                <span>Type</span>
                <select value={form.type} onChange={(event) => update('type', event.target.value)} required>
                  <option value="communication">Communication</option>
                  <option value="marketing">Marketing</option>
                </select>
              </label>
              <label className="template-subject-field">
                <span>Subject</span>
                <input value={form.subject} onChange={(event) => update('subject', event.target.value)} placeholder="Welcome to {{ company }}" required />
              </label>
              <label className="check-inline template-active-field">
                <input type="checkbox" checked={form.is_active} onChange={(event) => update('is_active', event.target.checked)} />
                <span>Active template</span>
              </label>
            </div>

            <label className="template-editor-field">
              <span>HTML Body</span>
              <textarea className="react-template-html-editor" value={form.body_html} onChange={(event) => update('body_html', event.target.value)} spellCheck="false" required />
            </label>

            <label className="template-editor-field">
              <span>Text Body</span>
              <textarea className="react-template-text-editor" value={form.body_text} onChange={(event) => update('body_text', event.target.value)} />
            </label>
          </div>

          <TemplatePreview form={form} mode={previewMode} setMode={setPreviewMode} />
        </div>

        <div className="modal-actions">
          <button type="button" className="secondary-button" onClick={onClose}>Cancel</button>
          <button type="submit" className="primary-button" disabled={submitting}>{submitting ? 'Saving...' : 'Save Template'}</button>
        </div>
      </form>
    </section>
  );
}

export function EmailTemplatesPage() {
  const { options, refreshOptions } = useAppData();
  const { confirmAction } = useUiFeedback();
  const [query, setQuery] = useState('');
  const [clientId, setClientId] = useState('');
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState({ data: [], meta: { page: 1, total: 0, lastPage: 1 } });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editor, setEditor] = useState(null);
  const [form, setForm] = useState(blankTemplate(options.clients));
  const [previewRow, setPreviewRow] = useState(null);
  const [previewMode, setPreviewMode] = useState('html');
  const [submitting, setSubmitting] = useState(false);
  const statsParams = useMemo(() => ({ q: query, client_id: clientId }), [clientId, query]);

  const requestParams = useMemo(() => ({ q: query, client_id: clientId, page, per_page: 25 }), [clientId, page, query]);

  async function load(nextParams = requestParams) {
    setLoading(true);
    setError('');

    try {
      setRows(await apiGet('/admin/email-templates', nextParams));
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, [requestParams]);

  function openCreate() {
    setForm(blankTemplate(options.clients));
    setEditor({ mode: 'create' });
  }

  function openEdit(row) {
    setForm(templateForm(row));
    setEditor({ mode: 'edit', row });
  }

  async function submitTemplate(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      if (editor.mode === 'edit') {
        await apiPatch(`/admin/email-templates/${editor.row.id}`, form);
        setNotice('Template updated.');
      } else {
        await apiPost('/admin/email-templates', form);
        setNotice('Template created.');
      }

      setEditor(null);
      await Promise.all([load(), refreshOptions()]);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function deleteTemplate(row) {
    if (!await confirmAction({
      title: 'Delete email template?',
      message: `${row.name} will be permanently removed. Templates currently in use cannot be deleted.`,
      confirmLabel: 'Delete Template',
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      await apiDelete(`/admin/email-templates/${row.id}`);
      setNotice('Template deleted.');
      await Promise.all([load(), refreshOptions()]);
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function resetFilters() {
    setQuery('');
    setClientId('');
    setPage(1);
  }

  return (
    <main className="app-shell template-library-page">
      <header className="app-header">
        <div>
          <p className="eyebrow">Content</p>
          <h1>Email Templates</h1>
        </div>
      </header>

      <ResourceStatsGrid resourceId="templates" params={statsParams} refreshKey={rows} />

      <section className="toolbar command-toolbar template-command-toolbar">
        <label className="search-field command-search">
          <span className="sr-only">Search templates</span>
          <input value={query} onChange={(event) => { setQuery(event.target.value); setPage(1); }} placeholder="Search templates" />
        </label>
        <label className="command-filter">
          <span className="sr-only">Client</span>
          <select aria-label="Client" value={clientId} onChange={(event) => { setClientId(event.target.value); setPage(1); }}>
            <option value="">All companies</option>
            {options.clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
          </select>
        </label>
        <button type="button" className="primary-button" onClick={openCreate}>New Template</button>
        <button type="button" className="secondary-button" onClick={resetFilters}>Reset</button>
      </section>

      {error && <div className="alert">{error}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      {editor && (
        <TemplateEditor
          form={form}
          setForm={setForm}
          clients={options.clients}
          title={editor.mode === 'edit' ? `Edit ${editor.row.name}` : 'Create Template'}
          onClose={() => setEditor(null)}
          onSubmit={submitTemplate}
          submitting={submitting}
        />
      )}

      {previewRow && (
        <section className="modal-backdrop template-modal-backdrop">
          <div className="modal-panel template-preview-modal">
            <div className="modal-head">
              <div>
                <p className="eyebrow">Template Preview</p>
                <h2>{previewRow.name}</h2>
              </div>
              <button type="button" className="secondary-button" onClick={() => setPreviewRow(null)}>Close</button>
            </div>
            <TemplatePreview form={templateForm(previewRow)} mode={previewMode} setMode={setPreviewMode} />
          </div>
        </section>
      )}

      <section className="table-panel" aria-busy={loading}>
        {loading && <div className="loading-bar">Refreshing templates...</div>}
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Key</th>
                <th>Name</th>
                <th>Client</th>
                <th>Type</th>
                <th>Subject</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {rows.data.length ? rows.data.map((row) => (
                <tr key={row.id}>
                  <td><strong>{row.key}</strong></td>
                  <td>{row.name}</td>
                  <td>{row.clientName || '-'}</td>
                  <td><span className={`badge ${row.type === 'marketing' ? 'pending' : 'active'}`}>{row.type}</span></td>
                  <td className="wrap">{row.subject}</td>
                  <td><span className={`badge ${row.isActive ? 'active' : 'failed'}`}>{row.isActive ? 'Active' : 'Inactive'}</span></td>
                  <td>
                    <div className="row-actions">
                      <button type="button" className="secondary-button compact" onClick={() => { setPreviewMode('html'); setPreviewRow(row); }}>Preview</button>
                      <button type="button" className="secondary-button compact" onClick={() => openEdit(row)}>Edit</button>
                      <button type="button" className="danger-button" onClick={() => deleteTemplate(row)}>Delete</button>
                    </div>
                  </td>
                </tr>
              )) : (
                <tr><td colSpan="7" className="empty-state">No templates found.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <div className="pagination">
        <button type="button" className="secondary-button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Previous</button>
        <span>Page {rows.meta.page} of {rows.meta.lastPage}</span>
        <button type="button" className="secondary-button" disabled={page >= rows.meta.lastPage} onClick={() => setPage((current) => current + 1)}>Next</button>
      </div>
    </main>
  );
}
