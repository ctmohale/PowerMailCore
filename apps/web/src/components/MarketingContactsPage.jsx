import { useState } from 'react';
import { useAppData } from '../context/AppDataContext.jsx';
import { apiDelete, apiPatch, apiPost } from '../api/client.js';
import { filesToAttachments } from '../utils/attachments.js';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';
import { useUiFeedback } from '../context/UiFeedbackContext.jsx';

function formatDate(value) {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('en-ZA', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function StatusBadge({ status }) {
  return <span className={`status-badge is-${status || 'pending'}`}>{status || 'unknown'}</span>;
}

function CompactList({ items }) {
  if (!items || items.length === 0) {
    return <span className="muted-inline">-</span>;
  }

  return (
    <div className="pill-list">
      {items.slice(0, 3).map((item) => (
        <span key={item.id || item}>{item.name || item}</span>
      ))}
      {items.length > 3 && <span>+{items.length - 3}</span>}
    </div>
  );
}

export function MarketingContactsPage() {
  const { confirmAction } = useUiFeedback();
  const {
    options,
    contactFilters,
    contacts,
    loading,
    error,
    updateContactFilter,
    resetContactFilters,
    refreshContacts,
  } = useAppData();
  const [showCreate, setShowCreate] = useState(false);
  const [showImport, setShowImport] = useState(false);
  const [form, setForm] = useState({});
  const [importForm, setImportForm] = useState({ client_id: '', contacts_file: null, audience_ids: [], new_audience_name: '' });
  const [actionError, setActionError] = useState('');
  const [notice, setNotice] = useState('');
  const [selectedIds, setSelectedIds] = useState([]);
  const [bulkAudienceOpen, setBulkAudienceOpen] = useState(false);
  const [bulkForm, setBulkForm] = useState({ audience_ids: [], new_audience_name: '', audience_action: 'add' });
  const [sendContact, setSendContact] = useState(null);
  const [sendForm, setSendForm] = useState({ email_account_id: '', email_template_id: '', subject: '', message_body: '', attachments: [] });
  const visibleIds = contacts.data.map((contact) => contact.id);
  const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedIds.includes(id));
  const senderOptions = options.marketingSenders.length ? options.marketingSenders : options.emailAccounts;
  const templateOptions = options.marketingTemplates.length ? options.marketingTemplates : options.emailTemplates;

  function updateForm(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  function openImport() {
    setImportForm((current) => ({
      ...current,
      client_id: current.client_id || (options.clients.length === 1 ? options.clients[0].id : ''),
    }));
    setShowImport(true);
  }

  async function createContact(event) {
    event.preventDefault();
    setActionError('');
    setNotice('');

    try {
      await apiPost('/marketing/contacts', form);
      setNotice('Contact created.');
      setForm({});
      setShowCreate(false);
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  async function updateImportFile(fileList) {
    setActionError('');

    try {
      const [contactsFile] = await filesToAttachments(fileList);
      setImportForm((current) => ({ ...current, contacts_file: contactsFile || null }));
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  async function importContacts(event) {
    event.preventDefault();
    setActionError('');
    setNotice('');

    try {
      const response = await apiPost('/marketing/contacts/import', importForm);
      setNotice(`Import complete. ${response.created || 0} added, ${response.updated || 0} updated, ${response.skipped || 0} skipped.`);
      setImportForm({ client_id: '', contacts_file: null, audience_ids: [], new_audience_name: '' });
      setShowImport(false);
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  async function setStatus(contact, status) {
    const confirmed = await confirmAction({
      title: status === 'unsubscribed' ? 'Unsubscribe contact?' : 'Update contact status?',
      message: `${contact.email} will be marked as ${status}.`,
      confirmLabel: status === 'unsubscribed' ? 'Unsubscribe' : 'Update Status',
      tone: status === 'unsubscribed' ? 'danger' : 'info',
    });
    if (!confirmed) return;

    setActionError('');
    setNotice('');

    try {
      await apiPatch(`/marketing/contacts/${contact.id}/status`, { status });
      setNotice(`${contact.email} marked as ${status}.`);
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  async function deleteContact(contact) {
    if (!await confirmAction({
      title: 'Delete contact?',
      message: `${contact.email} will be permanently removed from contacts and audiences.`,
      confirmLabel: 'Delete Contact',
      tone: 'danger',
    })) {
      return;
    }

    setActionError('');
    setNotice('');

    try {
      await apiDelete(`/marketing/contacts/${contact.id}`);
      setNotice('Contact deleted.');
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  function toggleSelected(id, checked) {
    setSelectedIds((current) => (
      checked
        ? [...new Set([...current, id])]
        : current.filter((selectedId) => selectedId !== id)
    ));
  }

  function toggleVisible(checked) {
    setSelectedIds((current) => (
      checked
        ? [...new Set([...current, ...visibleIds])]
        : current.filter((id) => !visibleIds.includes(id))
    ));
  }

  async function applyBulkAudience(event) {
    event.preventDefault();
    setActionError('');
    setNotice('');

    try {
      const response = await apiPost('/marketing/contacts/audiences/bulk', {
        ...bulkForm,
        contact_ids: selectedIds,
      });
      setNotice(`${response.updated || 0} contacts updated.`);
      setBulkAudienceOpen(false);
      setBulkForm({ audience_ids: [], new_audience_name: '', audience_action: 'add' });
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  async function deleteSelected() {
    if (!selectedIds.length || !await confirmAction({
      title: 'Delete selected contacts?',
      message: `${selectedIds.length} selected contacts will be permanently removed.`,
      confirmLabel: `Delete ${selectedIds.length} Contacts`,
      tone: 'danger',
    })) {
      return;
    }

    setActionError('');
    setNotice('');

    try {
      const response = await apiDelete('/marketing/contacts/bulk', { contact_ids: selectedIds });
      setNotice(`${response.deleted || 0} contacts deleted.`);
      setSelectedIds([]);
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  function openSend(contact) {
    setSendContact(contact);
    setSendForm({
      email_account_id: senderOptions[0]?.id || '',
      email_template_id: '',
      subject: `Hello ${contact.decisionMaker || contact.company || ''}`.trim(),
      message_body: '',
      attachments: [],
    });
  }

  async function updateSendAttachments(fileList) {
    setActionError('');

    try {
      const attachments = await filesToAttachments(fileList);
      setSendForm((current) => ({ ...current, attachments }));
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  async function submitContactEmail(event) {
    event.preventDefault();
    setActionError('');
    setNotice('');

    try {
      const response = await apiPost(`/marketing/contacts/${sendContact.id}/send-email`, sendForm);
      setNotice(`Email sent. Log #${response.log_id}`);
      setSendContact(null);
      await refreshContacts();
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  return (
    <main className="app-shell">
      <header className="app-header">
        <div>
          <p className="eyebrow">Marketing</p>
          <h1>Marketing Contacts</h1>
        </div>
      </header>

      <ResourceStatsGrid resourceId="contacts" params={contactFilters} refreshKey={contacts} />

      <section className="toolbar contacts-toolbar-stage command-toolbar">
        <label className="search-field command-search">
          <span className="sr-only">Search</span>
          <input
            value={contactFilters.q}
            onChange={(event) => updateContactFilter('q', event.target.value)}
            placeholder="Email, decision maker, cell, company, sector, focus, tags, status"
          />
        </label>

        <label className="command-filter">
          <span className="sr-only">Client</span>
          <select aria-label="Client" value={contactFilters.client_id} onChange={(event) => updateContactFilter('client_id', event.target.value)}>
            <option value="">All clients</option>
            {options.clients.map((client) => (
              <option key={client.id} value={client.id}>{client.name}</option>
            ))}
          </select>
        </label>

        <label className="command-filter">
          <span className="sr-only">Status</span>
          <select aria-label="Status" value={contactFilters.status} onChange={(event) => updateContactFilter('status', event.target.value)}>
            <option value="">All statuses</option>
            {options.marketingContactStatuses.map((status) => (
              <option key={status} value={status}>{status}</option>
            ))}
          </select>
        </label>

        <label className="command-filter">
          <span className="sr-only">Audience</span>
          <select aria-label="Audience" value={contactFilters.audience_id} onChange={(event) => updateContactFilter('audience_id', event.target.value)}>
            <option value="">All audiences</option>
            {options.audiences.map((audience) => (
              <option key={audience.id} value={audience.id}>{audience.name}</option>
            ))}
          </select>
        </label>

        <button type="button" className="primary-button" onClick={() => setShowCreate(true)}>Add Contact</button>
        <button type="button" className="secondary-button" onClick={openImport}>Import</button>
        <button type="button" className="secondary-button" onClick={resetContactFilters}>Reset</button>
      </section>

      <section className="toolbar simple-toolbar command-toolbar bulk-command-toolbar">
        <span className="selection-count">{selectedIds.length} selected</span>
        <button type="button" className="secondary-button" disabled={!selectedIds.length} onClick={() => setBulkAudienceOpen(true)}>Audience</button>
        <button type="button" className="danger-button" disabled={!selectedIds.length} onClick={deleteSelected}>Delete Selected</button>
      </section>

      {(actionError || error.contacts || error.options) && <div className="alert">{actionError || error.contacts || error.options}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      {showCreate && (
        <section className="modal-backdrop">
          <form className="modal-panel" onSubmit={createContact}>
            <div className="modal-head">
              <h2>Add Contact</h2>
              <button type="button" className="secondary-button" onClick={() => setShowCreate(false)}>Close</button>
            </div>
            <div className="form-grid-stage">
              <label>
                <span>Email</span>
                <input type="email" value={form.email || ''} onChange={(event) => updateForm('email', event.target.value)} required />
              </label>
              <label>
                <span>Decision Maker</span>
                <input value={form.name || ''} onChange={(event) => updateForm('name', event.target.value)} />
              </label>
              <label>
                <span>Company</span>
                <input value={form.company || ''} onChange={(event) => updateForm('company', event.target.value)} />
              </label>
              <label>
                <span>Cell</span>
                <input value={form.phone || ''} onChange={(event) => updateForm('phone', event.target.value)} />
              </label>
              <label>
                <span>Tags</span>
                <input value={form.tags || ''} onChange={(event) => updateForm('tags', event.target.value)} placeholder="tag-one, tag-two" />
              </label>
              <label>
                <span>Audience</span>
                <select value={form.audience_ids?.[0] || ''} onChange={(event) => updateForm('audience_ids', event.target.value ? [event.target.value] : [])}>
                  <option value="">No audience</option>
                  {options.audiences.map((audience) => (
                    <option key={audience.id} value={audience.id}>{audience.name}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>New Audience</span>
                <input value={form.new_audience_name || ''} onChange={(event) => updateForm('new_audience_name', event.target.value)} />
              </label>
            </div>
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setShowCreate(false)}>Cancel</button>
              <button type="submit" className="primary-button">Save</button>
            </div>
          </form>
        </section>
      )}

      {showImport && (
        <section className="modal-backdrop">
          <form className="modal-panel compact-modal" onSubmit={importContacts}>
            <div className="modal-head">
              <h2>Import Contacts</h2>
              <button type="button" className="secondary-button" onClick={() => setShowImport(false)}>Close</button>
            </div>
            <div className="form-grid-stage">
              {options.clients.length > 1 && (
                <label>
                  <span>Client</span>
                  <select value={importForm.client_id} onChange={(event) => setImportForm((current) => ({ ...current, client_id: event.target.value }))} required>
                    <option value="">Choose client</option>
                    {options.clients.map((client) => (
                      <option key={client.id} value={client.id}>{client.name}</option>
                    ))}
                  </select>
                </label>
              )}
              <label>
                <span>Audience</span>
                <select value={importForm.audience_ids[0] || ''} onChange={(event) => setImportForm((current) => ({ ...current, audience_ids: event.target.value ? [event.target.value] : [] }))}>
                  <option value="">Auto audience</option>
                  {options.audiences.map((audience) => (
                    <option key={audience.id} value={audience.id}>{audience.name}</option>
                  ))}
                </select>
              </label>
              <label className="form-span">
                <span>New Audience</span>
                <input value={importForm.new_audience_name} onChange={(event) => setImportForm((current) => ({ ...current, new_audience_name: event.target.value }))} />
              </label>
              <label className="form-span">
                <span>Contacts File</span>
                <input type="file" accept=".csv,.txt,.tsv,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" onChange={(event) => updateImportFile(event.target.files)} required />
              </label>
            </div>
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setShowImport(false)}>Cancel</button>
              <button type="submit" className="primary-button" disabled={!importForm.contacts_file}>Import</button>
            </div>
          </form>
        </section>
      )}

      {bulkAudienceOpen && (
        <section className="modal-backdrop">
          <form className="modal-panel compact-modal" onSubmit={applyBulkAudience}>
            <div className="modal-head">
              <h2>Bulk Audience</h2>
              <button type="button" className="secondary-button" onClick={() => setBulkAudienceOpen(false)}>Close</button>
            </div>
            <div className="form-grid-stage">
              <label>
                <span>Action</span>
                <select value={bulkForm.audience_action} onChange={(event) => setBulkForm((current) => ({ ...current, audience_action: event.target.value }))}>
                  <option value="add">Add to audience</option>
                  <option value="replace">Replace audiences</option>
                </select>
              </label>
              <label>
                <span>Audience</span>
                <select value={bulkForm.audience_ids[0] || ''} onChange={(event) => setBulkForm((current) => ({ ...current, audience_ids: event.target.value ? [event.target.value] : [] }))}>
                  <option value="">Choose audience</option>
                  {options.audiences.map((audience) => (
                    <option key={audience.id} value={audience.id}>{audience.name}</option>
                  ))}
                </select>
              </label>
              <label className="form-span">
                <span>New Audience</span>
                <input value={bulkForm.new_audience_name} onChange={(event) => setBulkForm((current) => ({ ...current, new_audience_name: event.target.value }))} />
              </label>
            </div>
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setBulkAudienceOpen(false)}>Cancel</button>
              <button type="submit" className="primary-button">Apply</button>
            </div>
          </form>
        </section>
      )}

      {sendContact && (
        <section className="modal-backdrop">
          <form className="modal-panel compact-modal" onSubmit={submitContactEmail}>
            <div className="modal-head">
              <h2>Send Contact Email</h2>
              <button type="button" className="secondary-button" onClick={() => setSendContact(null)}>Close</button>
            </div>
            <div className="form-grid-stage">
              <label>
                <span>From</span>
                <select value={sendForm.email_account_id} onChange={(event) => setSendForm((current) => ({ ...current, email_account_id: event.target.value }))} required>
                  <option value="">Choose sender</option>
                  {senderOptions.map((account) => (
                    <option key={account.id} value={account.id}>{[account.email, account.clientName].filter(Boolean).join(' - ')}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Template</span>
                <select value={sendForm.email_template_id} onChange={(event) => setSendForm((current) => ({ ...current, email_template_id: event.target.value }))}>
                  <option value="">No template</option>
                  {templateOptions.map((template) => (
                    <option key={template.id} value={template.id}>{[template.name, template.clientName, template.type].filter(Boolean).join(' - ')}</option>
                  ))}
                </select>
              </label>
              <label className="form-span">
                <span>To</span>
                <input value={sendContact.email} readOnly />
              </label>
              <label className="form-span">
                <span>Subject</span>
                <input value={sendForm.subject} onChange={(event) => setSendForm((current) => ({ ...current, subject: event.target.value }))} />
              </label>
              <label className="form-span">
                <span>Message</span>
                <textarea value={sendForm.message_body} onChange={(event) => setSendForm((current) => ({ ...current, message_body: event.target.value }))} />
              </label>
              <label className="form-span">
                <span>Attachments</span>
                <input type="file" multiple onChange={(event) => updateSendAttachments(event.target.files)} />
              </label>
            </div>
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setSendContact(null)}>Cancel</button>
              <button type="submit" className="primary-button">Send</button>
            </div>
          </form>
        </section>
      )}

      <section className="table-panel" aria-busy={loading.contacts}>
        {loading.contacts && <div className="loading-bar">Refreshing contacts...</div>}
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Email</th>
                <th>
                  <input type="checkbox" checked={allVisibleSelected} onChange={(event) => toggleVisible(event.target.checked)} aria-label="Select visible contacts" />
                </th>
                <th>Decision Maker</th>
                <th>Cell</th>
                <th>Company</th>
                <th>Website</th>
                <th>Sector</th>
                <th>Focus</th>
                <th>Audience</th>
                <th>Tags</th>
                <th>Status</th>
                <th>Logs</th>
                <th>Last Email</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {contacts.data.length === 0 ? (
                <tr>
                  <td colSpan="14" className="empty-state">No marketing contacts found.</td>
                </tr>
              ) : contacts.data.map((contact) => (
                <tr key={contact.id}>
                  <td>
                    <strong>{contact.email}</strong>
                    <span>{contact.client?.name || '-'}</span>
                  </td>
                  <td>
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(contact.id)}
                      onChange={(event) => toggleSelected(contact.id, event.target.checked)}
                      aria-label={`Select ${contact.email}`}
                    />
                  </td>
                  <td>{contact.decisionMaker || '-'}</td>
                  <td>{contact.cell || '-'}</td>
                  <td className="wrap">{contact.company || '-'}</td>
                  <td>
                    {contact.website ? (
                      <a href={contact.website} target="_blank" rel="noreferrer">Open</a>
                    ) : '-'}
                  </td>
                  <td className="wrap">{contact.sector || '-'}</td>
                  <td className="wrap">{contact.focus || '-'}</td>
                  <td><CompactList items={contact.audiences} /></td>
                  <td><CompactList items={contact.tags} /></td>
                  <td><StatusBadge status={contact.status} /></td>
                  <td>{contact.emailLogCount}</td>
                  <td>{formatDate(contact.lastEmailAt)}</td>
                  <td>
                    <div className="row-actions">
                      {contact.status === 'subscribed' ? (
                        <button className="secondary-button compact" type="button" onClick={() => setStatus(contact, 'unsubscribed')}>Unsub</button>
                      ) : (
                        <button className="secondary-button compact" type="button" onClick={() => setStatus(contact, 'subscribed')}>Sub</button>
                      )}
                      <button className="secondary-button compact" type="button" onClick={() => openSend(contact)}>Send</button>
                      <button className="danger-button" type="button" onClick={() => deleteContact(contact)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <nav className="pagination" aria-label="Marketing contact pages">
        <button
          type="button"
          className="secondary-button"
          disabled={contacts.meta.page <= 1}
          onClick={() => updateContactFilter('page', contacts.meta.page - 1)}
        >
          Previous
        </button>
        <span>Page {contacts.meta.page} of {contacts.meta.lastPage}</span>
        <button
          type="button"
          className="secondary-button"
          disabled={contacts.meta.page >= contacts.meta.lastPage}
          onClick={() => updateContactFilter('page', contacts.meta.page + 1)}
        >
          Next
        </button>
      </nav>
    </main>
  );
}
