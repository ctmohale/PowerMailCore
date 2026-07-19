import { useEffect, useState } from 'react';
import { apiPost } from '../api/client.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { filesToAttachments } from '../utils/attachments.js';

const emptyForm = {
  email_account_id: '',
  email_template_id: '',
  to: '',
  subject: '',
  message_body: '',
  data_json: '{\n}',
  save_template_default: false,
  attachments: [],
};

function ComposeIcon({ name }) {
  const paths = {
    close: <><path d="m6 6 12 12" /><path d="M18 6 6 18" /></>,
    mail: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></>,
    paperclip: <path d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.6-9.6a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 1 1-2.8-2.8l8.9-8.9" />,
    send: <><path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" /></>,
  };

  return <svg viewBox="0 0 24 24" aria-hidden="true">{paths[name]}</svg>;
}

export function ComposePage() {
  const { options } = useAppData();
  const [showCompose, setShowCompose] = useState(true);
  const [form, setForm] = useState(emptyForm);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const senders = options.marketingSenders.length ? options.marketingSenders : options.emailAccounts;
  const templates = options.emailTemplates;

  useEffect(() => {
    if (!showCompose) return undefined;

    function closeOnEscape(event) {
      if (event.key === 'Escape') setShowCompose(false);
    }

    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [showCompose]);

  function update(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setNotice('');
    setError('');

    try {
      JSON.parse(form.data_json || '{}');
    } catch {
      setError('Template data must be valid JSON.');
      return;
    }

    try {
      const response = await apiPost('/send-email', form);
      setNotice(`${response.message} Log #${response.log_id}`);
      setForm(emptyForm);
      setShowCompose(false);
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function updateAttachments(fileList) {
    setError('');

    try {
      update('attachments', await filesToAttachments(fileList));
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  return (
    <main className="app-shell">
      <header className="app-header">
        <div>
          <p className="eyebrow">Messaging</p>
          <h1>Send Email</h1>
        </div>
        <button className="primary-button" type="button" onClick={() => setShowCompose(true)}>Compose</button>
      </header>

      {!showCompose && error && <div className="alert">{error}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      <section className="table-panel compose-empty">
        <strong>Compose mail from your connected sending accounts.</strong>
        <span>Sent messages are written to Logs and the PowerMail Sent mailbox.</span>
      </section>

      {showCompose && (
        <section className="modal-backdrop compose-modal-backdrop">
          <form className="modal-panel compose-modal" onSubmit={handleSubmit} role="dialog" aria-modal="true" aria-labelledby="compose-title">
            <div className="modal-head">
              <div className="compose-modal-title">
                <span className="compose-modal-icon"><ComposeIcon name="mail" /></span>
                <div>
                  <h2 id="compose-title">Compose Email</h2>
                  <p>Send from a connected PowerMail account.</p>
                </div>
              </div>
              <button className="compose-close-button" type="button" aria-label="Close compose email" title="Close" onClick={() => setShowCompose(false)}>
                <ComposeIcon name="close" />
              </button>
            </div>
            <div className="compose-modal-body">
              {error && <div className="compose-error" role="alert">{error}</div>}
              <div className="form-grid-stage compose-form-grid">
                <label className="compose-field">
                  <span>From</span>
                  <select value={form.email_account_id} onChange={(event) => update('email_account_id', event.target.value)} required autoFocus>
                    <option value="">Choose sender</option>
                    {senders.map((account) => (
                      <option key={account.id} value={account.id}>
                        {[account.email, account.clientName].filter(Boolean).join(' - ')}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="compose-field">
                  <span>Template</span>
                  <select value={form.email_template_id} onChange={(event) => update('email_template_id', event.target.value)}>
                    <option value="">No template</option>
                    {templates.map((template) => (
                      <option key={template.id} value={template.id}>
                        {[template.name, template.clientName, template.type].filter(Boolean).join(' - ')}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="form-span compose-field">
                  <span>To</span>
                  <input type="email" value={form.to} onChange={(event) => update('to', event.target.value)} placeholder="recipient@company.com" required />
                </label>
                <label className="form-span compose-field">
                  <span>Subject</span>
                  <input value={form.subject} onChange={(event) => update('subject', event.target.value)} placeholder="Email subject" />
                </label>
                <label className="form-span compose-field compose-message-field">
                  <span>Message</span>
                  <textarea value={form.message_body} onChange={(event) => update('message_body', event.target.value)} placeholder="Write your message..." />
                </label>
                <details className="compose-template-data form-span">
                  <summary>Template data <span>JSON variables</span></summary>
                  <label className="compose-field">
                    <span>Template Data JSON</span>
                    <textarea value={form.data_json} onChange={(event) => update('data_json', event.target.value)} spellCheck="false" />
                  </label>
                </details>
                <div className="form-span compose-attachment-field">
                  <span className="compose-attachment-label">Attachments</span>
                  <label className="compose-file-picker">
                    <ComposeIcon name="paperclip" />
                    <span>{form.attachments.length ? 'Add more files' : 'Choose files'}</span>
                    <small>Up to 5 files</small>
                    <input type="file" multiple onChange={(event) => updateAttachments(event.target.files)} />
                  </label>
                  {form.attachments.length > 0 && (
                    <div className="compose-file-list">
                      {form.attachments.map((attachment) => (
                        <span key={`${attachment.name}-${attachment.size}`}>{attachment.name}</span>
                      ))}
                    </div>
                  )}
                </div>
                <label className="check-inline form-span compose-default-check">
                  <input
                    type="checkbox"
                    checked={form.save_template_default}
                    onChange={(event) => update('save_template_default', event.target.checked)}
                  />
                  <span>Save selected template as default</span>
                </label>
              </div>
            </div>
            <div className="modal-actions">
              <button className="secondary-button" type="button" onClick={() => setShowCompose(false)}>Cancel</button>
              <button className="primary-button compose-send-button" type="submit">
                <ComposeIcon name="send" />
                <span>Send Email</span>
              </button>
            </div>
          </form>
        </section>
      )}
    </main>
  );
}
