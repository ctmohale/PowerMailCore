import { useState } from 'react';
import { filesToAttachments } from '../utils/attachments.js';

function CampaignIcon({ name }) {
  const paths = {
    close: <><path d="m6 6 12 12" /><path d="M18 6 6 18" /></>,
    campaign: <><path d="M3 11v2" /><path d="M6 9v6" /><path d="m9 8 10-4v16L9 16Z" /><path d="m9 16 2 5H7l-1-6" /></>,
    paperclip: <path d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.6-9.6a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 1 1-2.8-2.8l8.9-8.9" />,
  };

  return <svg viewBox="0 0 24 24" aria-hidden="true">{paths[name]}</svg>;
}

export function CampaignDialog({ error, form, onClose, onSubmit, options, updateForm }) {
  const [localError, setLocalError] = useState('');
  const clientId = String(form.client_id || '');
  const senders = options.marketingSenders.filter((item) => !clientId || String(item.clientId) === clientId);
  const templates = options.marketingTemplates.filter((item) => !clientId || String(item.clientId) === clientId);
  const audiences = options.audiences.filter((item) => !clientId || String(item.clientId) === clientId);
  const selectedAudiences = Array.isArray(form.audience_ids) ? form.audience_ids.map(String) : [];

  function changeClient(value) {
    updateForm('client_id', value);
    updateForm('email_account_id', '');
    updateForm('email_template_id', '');
    updateForm('audience_ids', []);
  }

  function changeTemplate(value) {
    updateForm('email_template_id', value);
    const template = templates.find((item) => String(item.id) === String(value));
    if (template?.subject && !form.subject) updateForm('subject', template.subject);
  }

  function toggleAudience(id, checked) {
    const value = String(id);
    updateForm('audience_ids', checked
      ? [...new Set([...selectedAudiences, value])]
      : selectedAudiences.filter((item) => item !== value));
  }

  async function updateAttachments(fileList) {
    setLocalError('');
    try {
      updateForm('attachments', await filesToAttachments(fileList));
    } catch (requestError) {
      setLocalError(requestError.message);
    }
  }

  function handleSubmit(event) {
    setLocalError('');
    try {
      JSON.parse(form.template_data_json || '{}');
    } catch {
      event.preventDefault();
      setLocalError('Template data must be valid JSON.');
      return;
    }
    onSubmit(event);
  }

  return (
    <section className="modal-backdrop campaign-dialog-backdrop">
      <form className="modal-panel campaign-dialog" onSubmit={handleSubmit} role="dialog" aria-modal="true" aria-labelledby="new-campaign-title">
        <div className="modal-head">
          <div className="campaign-dialog-title">
            <span className="campaign-dialog-icon"><CampaignIcon name="campaign" /></span>
            <div>
              <h2 id="new-campaign-title">New Campaign</h2>
              <p>Choose recipients, compose the message, and save it as a draft.</p>
            </div>
          </div>
          <button className="campaign-close-button" type="button" onClick={onClose} aria-label="Close new campaign" title="Close">
            <CampaignIcon name="close" />
          </button>
        </div>

        <div className="campaign-dialog-body">
          {(localError || error) && <div className="campaign-dialog-error" role="alert">{localError || error}</div>}
          <div className="campaign-setup-grid">
            <label>
              <span>Client</span>
              <select value={form.client_id || ''} onChange={(event) => changeClient(event.target.value)} required autoFocus>
                <option value="">Choose client</option>
                {options.clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
              </select>
            </label>
            <label>
              <span>Sender</span>
              <select value={form.email_account_id || ''} onChange={(event) => updateForm('email_account_id', event.target.value)} required disabled={!clientId}>
                <option value="">Choose sender</option>
                {senders.map((sender) => <option key={sender.id} value={sender.id}>{[sender.fromName, sender.email].filter(Boolean).join(' - ')}</option>)}
              </select>
            </label>
            <label>
              <span>Marketing Template</span>
              <select value={form.email_template_id || ''} onChange={(event) => changeTemplate(event.target.value)} disabled={!clientId}>
                <option value="">No template</option>
                {templates.map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}
              </select>
            </label>
          </div>

          <div className="campaign-message-grid">
            <label>
              <span>Campaign Name</span>
              <input value={form.name || ''} onChange={(event) => updateForm('name', event.target.value)} placeholder="Internal campaign name" required />
            </label>
            <label>
              <span>Subject</span>
              <input value={form.subject || ''} onChange={(event) => updateForm('subject', event.target.value)} placeholder="Email subject" required />
            </label>
          </div>

          <fieldset className="campaign-audiences" disabled={!clientId}>
            <legend>
              <span>Audiences</span>
              <small>{selectedAudiences.length} selected</small>
            </legend>
            {!clientId && <p>Choose a client to see its audiences.</p>}
            {clientId && audiences.length === 0 && <p>No audiences are available for this client.</p>}
            {audiences.map((audience) => (
              <label key={audience.id}>
                <input type="checkbox" checked={selectedAudiences.includes(String(audience.id))} onChange={(event) => toggleAudience(audience.id, event.target.checked)} />
                <span>
                  <strong>{audience.name}</strong>
                  <small>{audience.contactCount || 0} contacts</small>
                </span>
              </label>
            ))}
          </fieldset>

          <label className="campaign-body-field">
            <span>Message Body</span>
            <textarea value={form.body || ''} onChange={(event) => updateForm('body', event.target.value)} placeholder="Write the campaign message, or choose a marketing template above." />
          </label>

          <div className="campaign-utility-grid">
            <label>
              <span>Recipient Tag</span>
              <input value={form.recipient_tag || ''} onChange={(event) => updateForm('recipient_tag', event.target.value)} placeholder="Optional contact tag" />
            </label>
            <div className="campaign-attachment-field">
              <span>Attachments</span>
              <label className="campaign-file-picker">
                <CampaignIcon name="paperclip" />
                <span>{form.attachments?.length ? `${form.attachments.length} file(s) selected` : 'Choose files'}</span>
                <input type="file" multiple onChange={(event) => updateAttachments(event.target.files)} />
              </label>
            </div>
          </div>

          {form.attachments?.length > 0 && (
            <div className="campaign-file-list">
              {form.attachments.map((attachment) => <span key={`${attachment.name}-${attachment.size}`}>{attachment.name}</span>)}
            </div>
          )}

          <details className="campaign-template-data">
            <summary>Template data <span>JSON variables</span></summary>
            <label>
              <span>Template Data JSON</span>
              <textarea value={form.template_data_json || ''} onChange={(event) => updateForm('template_data_json', event.target.value)} spellCheck="false" />
            </label>
          </details>
        </div>

        <div className="modal-actions">
          <span className="campaign-draft-note">Campaigns are saved as drafts before sending.</span>
          <button className="secondary-button" type="button" onClick={onClose}>Cancel</button>
          <button className="primary-button" type="submit">Create Campaign</button>
        </div>
      </form>
    </section>
  );
}
