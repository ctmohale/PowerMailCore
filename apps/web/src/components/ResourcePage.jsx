import { useEffect, useMemo, useState } from 'react';
import { apiDelete, apiGet, apiPatch, apiPost } from '../api/client.js';
import { findResource } from '../config/resources.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { filesToAttachments } from '../utils/attachments.js';
import { ApiIntegrationGuide } from './ApiIntegrationGuide.jsx';
import { CampaignDialog } from './CampaignDialog.jsx';
import { LeadGenerationDialog } from './LeadGenerationDialog.jsx';
import { ProspectCallDialog } from './ProspectCallDialog.jsx';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';
import { useUiFeedback } from '../context/UiFeedbackContext.jsx';

function displayValue(value) {
  if (Array.isArray(value)) {
    return value.length ? value.join(', ') : '-';
  }

  if (value === true || value === 1) {
    return 'Yes';
  }

  if (value === false || value === 0) {
    return 'No';
  }

  if (value === null || value === undefined || value === '') {
    return '-';
  }

  return String(value);
}

const mailboxOptions = [
  ['all', 'All folders'],
  ['inbox', 'Inbox'],
  ['spam', 'Spam'],
  ['sent', 'Sent'],
  ['drafts', 'Drafts'],
  ['trash', 'Trash'],
  ['archive', 'Archive'],
];

function messagePreviewDocument(value, mode) {
  const html = String(value || '');
  const styles = mode === 'fit'
    ? '<style data-powermail-reader>html,body{max-width:100%!important;overflow-x:hidden!important}*,*::before,*::after{box-sizing:border-box!important}table{max-width:100%!important}img,video,canvas{max-width:100%!important;height:auto!important}</style>'
    : '<style data-powermail-reader>html,body{overflow:auto!important}body{width:max-content!important;min-width:100%!important}img,video,canvas{max-width:none!important}</style>';

  if (/<\/head>/i.test(html)) {
    return html.replace(/<\/head>/i, `${styles}</head>`);
  }

  if (/<body[^>]*>/i.test(html)) {
    return html.replace(/<body([^>]*)>/i, `<head>${styles}</head><body$1>`);
  }

  return `<!doctype html><html><head>${styles}</head><body>${html}</body></html>`;
}

function notifyInboxChanged() {
  window.dispatchEvent(new Event('powermail:inbox-changed'));
}

function todayInputValue() {
  const today = new Date();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${today.getFullYear()}-${month}-${day}`;
}

function mailHostError(value, label) {
  const host = String(value || '').trim();

  if (!host) {
    return `${label} is required.`;
  }

  if (/^\d+$/.test(host) || /^(?:https?|ssl|tls):\/\//i.test(host) || /\s/.test(host)) {
    return `${label} must be a server hostname, for example mail.example.com.`;
  }

  return '';
}

function emailAccountFormError(form, creating) {
  const smtpHostError = mailHostError(form.smtp_host, 'SMTP host');
  if (smtpHostError) return smtpHostError;

  if (creating && !String(form.smtp_password || '').trim()) {
    return 'SMTP password is required.';
  }

  if (!form.inbox_enabled) return '';

  const imapHostError = mailHostError(form.imap_host, 'IMAP host');
  if (imapHostError) return imapHostError;

  if (!String(form.imap_username || '').trim()) {
    return 'IMAP username is required when inbox access is enabled.';
  }

  if (creating && !String(form.imap_password || '').trim()) {
    return 'IMAP password is required when inbox access is enabled.';
  }

  return '';
}

function paginationItems(currentPage, lastPage) {
  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => index + 1);
  }

  const pages = new Set([1, lastPage, currentPage - 1, currentPage, currentPage + 1]);
  const visible = [...pages].filter((item) => item >= 1 && item <= lastPage).sort((a, b) => a - b);

  return visible.flatMap((item, index) => {
    if (index === 0 || item === visible[index - 1] + 1) return [item];
    return [`ellipsis-${item}`, item];
  });
}

export function ResourcePage({ groupId, resourceId, initialInboxOpened = 'all' }) {
  const { confirmAction } = useUiFeedback();
  const { group, resource } = useMemo(() => findResource(groupId, resourceId), [groupId, resourceId]);
  const { options } = useAppData();
  const [search, setSearch] = useState('');
  const [inboxFilters, setInboxFilters] = useState({ client_id: '', email_account_id: '', mailbox: 'all', opened: initialInboxOpened || 'all' });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(resource.id === 'inbox' ? 10 : 25);
  const [rows, setRows] = useState({ data: [], meta: { page: 1, perPage: 25, total: 0, lastPage: 1 } });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [editingRow, setEditingRow] = useState(null);
  const [sendRow, setSendRow] = useState(null);
  const [viewRow, setViewRow] = useState(null);
  const [messageViewMode, setMessageViewMode] = useState('actual');
  const [leadEdit, setLeadEdit] = useState(null);
  const [form, setForm] = useState({});
  const [sendForm, setSendForm] = useState({ to: '', subject: '', message: '' });
  const hasRowActions = Boolean(resource.write || resource.inboxActions || resource.detail);
  const [selectedIds, setSelectedIds] = useState([]);
  const visibleIds = rows.data.map((row) => row.id);
  const supportsSelection = resource.inboxActions || resource.bulkDelete;
  const allVisibleSelected = supportsSelection && visibleIds.length > 0 && visibleIds.every((id) => selectedIds.includes(id));
  const requestParams = useMemo(() => ({
    q: search,
    page,
    per_page: perPage,
    ...(resource.id === 'inbox' ? inboxFilters : {}),
  }), [inboxFilters, page, perPage, resource.id, search]);

  useEffect(() => {
    setPage(1);
    setPerPage(resource.id === 'inbox' ? 10 : 25);
    setSelectedIds([]);
    setSearch('');
    setInboxFilters({ client_id: '', email_account_id: '', mailbox: 'all', opened: initialInboxOpened || 'all' });
  }, [initialInboxOpened, resource.id]);

  useEffect(() => {
    setLoading(true);
    setError('');

    apiGet(resource.endpoint, requestParams)
      .then(setRows)
      .catch((requestError) => setError(requestError.message))
      .finally(() => setLoading(false));
  }, [requestParams, resource.endpoint]);


  function updateForm(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  function updateSearch(value) {
    setSearch(value);
    setPage(1);
    setSelectedIds([]);
  }

  function updatePerPage(value) {
    setPerPage(Number(value));
    setPage(1);
    setSelectedIds([]);
  }

  function formWithDefaults(source = {}) {
    if (!resource.write) {
      return source;
    }

    return resource.write.fields.reduce((current, [name, , type, , fieldOptions]) => {
      if (current[name] !== undefined) {
        return current;
      }

      if (type === 'select' && fieldOptions?.length) {
        return { ...current, [name]: fieldOptions[0] };
      }

      if (type === 'client-select' && options.clients.length === 1) {
        return { ...current, [name]: options.clients[0].id };
      }

      if (type === 'checkbox') {
        return { ...current, [name]: typeof fieldOptions === 'boolean' ? fieldOptions : true };
      }

      if (type === 'checkbox-group' && fieldOptions?.length) {
        return { ...current, [name]: [fieldOptions[0]] };
      }

      if (type === 'option-multi') {
        return { ...current, [name]: [] };
      }

      return current;
    }, { ...source });
  }

  function openCreate() {
    setError('');
    if (resource.id === 'lead-runs') {
      setForm({
        ...formWithDefaults(),
        prompt: 'Generate leads from pasted business list. Extract contact details for each company.',
        source_data: '',
        target_count: 200,
      });
    } else if (resource.id === 'campaigns') {
      setForm({
        ...formWithDefaults(),
        audience_ids: [],
        attachments: [],
        template_data_json: '{\n}',
      });
    } else if (resource.id === 'prospect-calls') {
      setForm({
        client_id: options.clients.length === 1 ? options.clients[0].id : '',
        marketing_contact_id: '',
        company_name: '',
        contact_name: '',
        phone: '',
        email: '',
        call_date: todayInputValue(),
        follow_up_at: '',
        status: 'new',
        outcome: '',
      });
    } else {
      setForm(formWithDefaults());
    }
    setShowCreate(true);
  }

  function openEdit(row) {
    setForm(formWithDefaults(row));
    setEditingRow(row);
  }

  async function refreshRows(nextPage = page) {
    const refreshed = await apiGet(resource.endpoint, { ...requestParams, page: nextPage });
    setRows(refreshed);
  }

  async function handleCreate(event) {
    event.preventDefault();
    setError('');
    setNotice('');

    if (resource.id === 'accounts') {
      const validationMessage = emailAccountFormError(formWithDefaults(form), true);
      if (validationMessage) {
        setError(validationMessage);
        return;
      }
    }

    try {
      const response = await apiPost(resource.endpoint, formWithDefaults(form));
      if (response?.plainTextKey) {
        setNotice(`New API key: ${response.plainTextKey}`);
      } else {
        setNotice(`${resource.title} record created.`);
      }
      setForm({});
      setShowCreate(false);
      setPage(1);
      await refreshRows(1);
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleUpdate(event) {
    event.preventDefault();
    setError('');
    setNotice('');

    if (resource.id === 'accounts') {
      const validationMessage = emailAccountFormError(formWithDefaults(form), false);
      if (validationMessage) {
        setError(validationMessage);
        return;
      }
    }

    try {
      await apiPatch(`${resource.endpoint}/${editingRow.id}`, formWithDefaults(form));
      setNotice(`${resource.title} record updated.`);
      setEditingRow(null);
      setForm({});
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleDelete(row) {
    if (!await confirmAction({
      title: `Delete ${resource.title.toLowerCase()} record?`,
      message: `${row.name || row.email || row.subject || `Record #${row.id}`} will be permanently removed.`,
      confirmLabel: 'Delete Record',
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      await apiDelete(`${resource.endpoint}/${row.id}`);
      setNotice(`${resource.title} record deleted.`);
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleRegenerate(row) {
    if (!await confirmAction({
      title: 'Regenerate API key?',
      message: `${row.name} will stop working immediately until connected applications use the new key.`,
      confirmLabel: 'Regenerate Key',
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      const response = await apiPatch(`${resource.endpoint}/${row.id}/regenerate`, {});
      if (response?.plainTextKey) {
        setNotice(`New API key: ${response.plainTextKey}`);
      }
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleStatusAction(row, action) {
    if (!await confirmAction({
      title: `${action === 'activate' ? 'Activate' : 'Suspend'} ${resource.title.toLowerCase()}?`,
      message: `${row.name || row.email || `Record #${row.id}`} will be ${action === 'activate' ? 'activated' : 'suspended'}.`,
      confirmLabel: action === 'activate' ? 'Activate' : 'Suspend',
      tone: action === 'activate' ? 'info' : 'danger',
    })) return;

    setError('');
    setNotice('');

    try {
      await apiPatch(`${resource.endpoint}/${row.id}/${action}`, {});
      setNotice(`${resource.title} ${action === 'activate' ? 'activated' : 'suspended'}.`);
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleCampaignSend(row) {
    if (!await confirmAction({
      title: 'Send campaign?',
      message: `${row.name} will be sent to its selected audiences.`,
      confirmLabel: 'Send Campaign',
      tone: 'info',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      const response = await apiPost(`${resource.endpoint}/${row.id}/send`, {});
      setNotice(`Campaign ${response.status_label || response.status}. ${response.sent || 0} sent, ${response.failed || 0} failed.`);
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function summarizeInboxSync(response, label = 'Inbox synced') {
    const sync = response.sync || response;
    const errorText = sync.errors?.length ? ` ${sync.errors.join(' ')}` : '';

    return `${label}. Imported ${sync.imported || 0}, skipped ${sync.skipped || 0}.${errorText}`;
  }

  async function handleInboxSync(endpoint = '/inbox/sync', label = 'Inbox synced') {
    setError('');
    setNotice('');

    try {
      const response = await apiPost(endpoint, {
        client_id: inboxFilters.client_id,
        email_account_id: inboxFilters.email_account_id,
        mailbox: inboxFilters.mailbox,
        limit: 10,
      });
      setNotice(summarizeInboxSync(response, label));
      await refreshRows(1);
      notifyInboxChanged();
      setPage(1);
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function toggleInboxSelected(id, checked) {
    setSelectedIds((current) => (
      checked
        ? [...new Set([...current, id])]
        : current.filter((selectedId) => selectedId !== id)
    ));
  }

  function toggleVisibleInbox(checked) {
    setSelectedIds((current) => (
      checked
        ? [...new Set([...current, ...visibleIds])]
        : current.filter((id) => !visibleIds.includes(id))
    ));
  }

  function updateInboxFilter(name, value) {
    setInboxFilters((current) => ({
      ...current,
      [name]: value,
      ...(name === 'client_id' ? { email_account_id: '' } : {}),
    }));
    setPage(1);
    setSelectedIds([]);
  }

  function resetResourceFilters() {
    setSearch('');
    setPage(1);
    setSelectedIds([]);
    if (resource.id === 'inbox') {
      setInboxFilters({ client_id: '', email_account_id: '', mailbox: 'all', opened: 'all' });
    }
  }

  async function handleInboxOpened(row, opened) {
    setError('');
    setNotice('');

    try {
      await apiPatch(`${resource.endpoint}/${row.id}/${opened ? 'opened' : 'unopened'}`, {});
      setNotice(opened ? 'Message marked opened.' : 'Message marked unread.');
      await refreshRows();
      notifyInboxChanged();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleInboxView(row) {
    setError('');
    setNotice('');

    try {
      const response = await apiGet(`${resource.endpoint}/${row.id}`);
      setMessageViewMode('actual');
      setViewRow({ __type: 'inbox', ...response.data });
      await refreshRows();
      notifyInboxChanged();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleResourceView(row) {
    setError('');
    setNotice('');

    try {
      const response = await apiGet(`${resource.endpoint}/${row.id}`);
      setViewRow({ __type: resource.id, ...response.data });
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function downloadTextFile(filename, content, mime = 'text/plain') {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  async function handleLeadDownload(row) {
    setError('');
    setNotice('');

    try {
      const response = await apiGet(`${resource.endpoint}/${row.id}/download`);
      downloadTextFile(response.filename || `lead-run-${row.id}.csv`, response.content || '', response.mime || 'text/csv');
      setNotice('Lead CSV ready.');
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleLeadDelete(index) {
    if (!viewRow || !await confirmAction({
      title: 'Delete lead?',
      message: 'This lead will be permanently removed from the generation run.',
      confirmLabel: 'Delete Lead',
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      await apiDelete(`${resource.endpoint}/${viewRow.id}/leads/${index}`);
      const response = await apiGet(`${resource.endpoint}/${viewRow.id}`);
      setViewRow({ __type: resource.id, ...response.data });
      setNotice('Lead removed.');
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function openLeadEdit(index, lead) {
    setLeadEdit({
      runId: viewRow.id,
      index,
      form: {
        email: lead.email || '',
        name: lead.name || lead.decision_maker || '',
        company: lead.company || '',
        phone: lead.phone || lead.phone_number || '',
        website: lead.source_url || lead.website || '',
        notes: lead.notes || '',
      },
    });
  }

  async function handleLeadEdit(event) {
    event.preventDefault();
    setError('');
    setNotice('');

    try {
      await apiPatch(`${resource.endpoint}/${leadEdit.runId}/leads/${leadEdit.index}/enrich`, leadEdit.form);
      const response = await apiGet(`${resource.endpoint}/${leadEdit.runId}`);
      setViewRow({ __type: resource.id, ...response.data });
      setLeadEdit(null);
      setNotice('Lead updated.');
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleResourceBulkDelete() {
    if (!selectedIds.length || !await confirmAction({
      title: `Delete selected ${resource.title.toLowerCase()}?`,
      message: `${selectedIds.length} selected records will be permanently removed.`,
      confirmLabel: `Delete ${selectedIds.length} Records`,
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      const response = await apiDelete(`${resource.endpoint}/bulk`, { ids: selectedIds });
      setNotice(`${response.deleted || 0} records deleted.`);
      setSelectedIds([]);
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleInboxDelete(row) {
    if (!await confirmAction({
      title: 'Delete inbox message?',
      message: `"${row.subject || row.fromEmail || row.id}" will be removed from this inbox.`,
      confirmLabel: 'Delete Message',
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      await apiDelete(`${resource.endpoint}/${row.id}`);
      setNotice('Message deleted.');
      await refreshRows();
      notifyInboxChanged();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleInboxBulkDelete() {
    if (!selectedIds.length || !await confirmAction({
      title: 'Delete selected messages?',
      message: `${selectedIds.length} selected inbox messages will be removed.`,
      confirmLabel: `Delete ${selectedIds.length} Messages`,
      tone: 'danger',
    })) {
      return;
    }

    setError('');
    setNotice('');

    try {
      const response = await apiDelete(`${resource.endpoint}/bulk`, { ids: selectedIds });
      setNotice(`${response.deleted || 0} messages deleted.`);
      setSelectedIds([]);
      await refreshRows();
      notifyInboxChanged();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleLeadImport(row) {
    setError('');
    setNotice('');

    try {
      const response = await apiPost(`${resource.endpoint}/${row.id}/import`, {});
      setNotice(`Import complete. ${response.created || 0} added, ${response.updated || 0} updated, ${response.skipped || 0} skipped.`);
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleVerify(row) {
    setError('');
    setNotice('');

    try {
      await apiPost(`${resource.endpoint}/${row.id}/verify`, {});
      setNotice(`${row.email || row.name} verified successfully.`);
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function openSend(row) {
    setSendRow(row);
    setSendForm({
      to: '',
      subject: 'Test email',
      message: `Hi,\n\nThis is a test email from ${row.email || resource.title}.\n\nThanks`,
    });
  }

  async function handleSend(event) {
    event.preventDefault();
    setError('');
    setNotice('');

    try {
      const response = await apiPost(`${resource.endpoint}/${sendRow.id}/send-test`, sendForm);
      setNotice(`Email sent. Log #${response.log_id}`);
      setSendRow(null);
      setSendForm({ to: '', subject: '', message: '' });
      await refreshRows();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function renderField([name, label, type, required, fieldOptions]) {
    const optionRows = Array.isArray(fieldOptions) ? fieldOptions : options[fieldOptions?.source] || [];
    const optionLabel = (item) => {
      if (typeof item !== 'object') {
        return item;
      }

      return [item.name || item.email, item.clientName, item.type].filter(Boolean).join(' - ');
    };

    if (type === 'checkbox-group') {
      const selected = Array.isArray(form[name]) ? form[name] : [];

      return (
        <label key={name} className="form-span">
          <span>{label}</span>
          <div className="check-grid">
            {fieldOptions?.map((option) => (
              <label key={option}>
                <input
                  type="checkbox"
                  checked={selected.includes(option)}
                  onChange={(event) => {
                    const next = event.target.checked
                      ? [...selected, option]
                      : selected.filter((value) => value !== option);
                    updateForm(name, next);
                  }}
                />
                <span>{option}</span>
              </label>
            ))}
          </div>
        </label>
      );
    }

    if (type === 'option-multi') {
      const selected = Array.isArray(form[name]) ? form[name].map(String) : [];

      return (
        <label key={name} className="form-span">
          <span>{label}</span>
          <select multiple value={selected} onChange={(event) => updateForm(name, Array.from(event.target.selectedOptions).map((option) => option.value))} required={required}>
            {optionRows.map((option) => (
              <option key={option.id ?? option} value={option.id ?? option}>{optionLabel(option)}</option>
            ))}
          </select>
        </label>
      );
    }

    if (type === 'file-multi') {
      return (
        <label key={name} className="form-span">
          <span>{label}</span>
          <input
            type="file"
            multiple
            onChange={async (event) => {
              try {
                updateForm(name, await filesToAttachments(event.target.files));
              } catch (requestError) {
                setError(requestError.message);
              }
            }}
          />
        </label>
      );
    }

    return (
      <label key={name} className={type === 'textarea' ? 'form-span' : ''}>
        <span>{label}</span>
        {type === 'textarea' ? (
          <textarea value={form[name] || ''} onChange={(event) => updateForm(name, event.target.value)} required={required} />
        ) : type === 'select' ? (
          <select value={form[name] || fieldOptions?.[0] || ''} onChange={(event) => updateForm(name, event.target.value)} required={required}>
            {fieldOptions?.map((option) => <option key={option} value={option}>{option}</option>)}
          </select>
        ) : type === 'client-select' ? (
          <select value={form[name] || ''} onChange={(event) => updateForm(name, event.target.value)} required={required}>
            <option value="">Choose client</option>
            {options.clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
          </select>
        ) : type === 'option-select' ? (
          <select value={form[name] || ''} onChange={(event) => updateForm(name, event.target.value)} required={required}>
            <option value="">None</option>
            {optionRows.map((option) => (
              <option key={option.id ?? option} value={option.id ?? option}>{optionLabel(option)}</option>
            ))}
          </select>
        ) : type === 'checkbox' ? (
          <input type="checkbox" checked={Boolean(form[name])} onChange={(event) => updateForm(name, event.target.checked)} />
        ) : (
          <input
            type={type}
            value={form[name] || ''}
            onChange={(event) => updateForm(name, event.target.value)}
            required={required}
            placeholder={name.endsWith('_host') ? 'mail.example.com' : undefined}
            min={name.endsWith('_port') ? 1 : undefined}
            max={name.endsWith('_port') ? 65535 : undefined}
          />
        )}
      </label>
    );
  }

  return (
    <main className="app-shell">
      <header className="app-header">
        <div>
          <p className="eyebrow">{group.label}</p>
          <h1>{resource.title}</h1>
        </div>
      </header>

      {resource.id === 'api-keys' && <ApiIntegrationGuide />}

      <ResourceStatsGrid resourceId={resource.id} params={requestParams} refreshKey={rows} />

      <section className={`toolbar simple-toolbar command-toolbar ${resource.id === 'inbox' ? 'inbox-toolbar' : ''}`}>
        <label className={`search-field ${resource.id === 'inbox' ? 'inbox-search' : 'command-search'}`}>
          <span className="sr-only">Search</span>
          <input value={search} onChange={(event) => updateSearch(event.target.value)} placeholder={`Search ${resource.title.toLowerCase()}`} />
        </label>
        {resource.id === 'inbox' && (
          <>
            <label className="inbox-filter inbox-company-filter">
              <span className="sr-only">Company</span>
              <select aria-label="Company" value={inboxFilters.client_id} onChange={(event) => updateInboxFilter('client_id', event.target.value)}>
                <option value="">All companies</option>
                {options.clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
              </select>
            </label>
            <label className="inbox-filter inbox-account-filter">
              <span className="sr-only">Email Account</span>
              <select aria-label="Email Account" value={inboxFilters.email_account_id} onChange={(event) => updateInboxFilter('email_account_id', event.target.value)}>
                <option value="">All accounts</option>
                {options.emailAccounts
                  .filter((account) => !inboxFilters.client_id || String(account.clientId) === String(inboxFilters.client_id))
                  .map((account) => (
                    <option key={account.id} value={account.id}>
                      {[account.email, account.clientName].filter(Boolean).join(' - ')}
                    </option>
                  ))}
              </select>
            </label>
            <label className="inbox-filter inbox-folder-filter">
              <span className="sr-only">Folder</span>
              <select aria-label="Folder" value={inboxFilters.mailbox} onChange={(event) => updateInboxFilter('mailbox', event.target.value)}>
                {mailboxOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
            </label>
            <label className="inbox-filter inbox-status-filter">
              <span className="sr-only">Status</span>
              <select aria-label="Status" value={inboxFilters.opened} onChange={(event) => updateInboxFilter('opened', event.target.value)}>
                <option value="all">All statuses</option>
                <option value="unopened">Unopened</option>
                <option value="opened">Opened</option>
              </select>
            </label>
          </>
        )}
        {resource.write && (
          <button className="primary-button" type="button" onClick={openCreate}>
            {resource.write.createLabel}
          </button>
        )}
        {resource.id === 'inbox' && (
          <div className="inbox-actions" role="group" aria-label="Inbox actions">
            <button
              className="primary-button inbox-sync-button"
              type="button"
              onClick={() => handleInboxSync(
                inboxFilters.email_account_id ? '/inbox/sync' : '/inbox/sync-all',
                inboxFilters.email_account_id ? 'Inbox synced' : 'All inboxes synced',
              )}
            >
              Sync
            </button>
            <button className="secondary-button" type="button" onClick={() => handleInboxSync('/inbox/sync-older', 'Older inbox mail fetched')}>
              Older
            </button>
            <button className="secondary-button" type="button" onClick={() => handleInboxSync('/inbox/poll', 'Inbox checked')}>
              Poll
            </button>
            <button className="danger-button" type="button" disabled={!selectedIds.length} onClick={handleInboxBulkDelete}>
              Delete Selected
            </button>
            <span className="selection-count" aria-live="polite">{selectedIds.length} selected</span>
            <button className="secondary-button inbox-reset-button" type="button" onClick={resetResourceFilters}>Reset</button>
          </div>
        )}
        {resource.bulkDelete && (
          <>
            <button className="danger-button" type="button" disabled={!selectedIds.length} onClick={handleResourceBulkDelete}>
              Delete Selected
            </button>
            <span className="selection-count">{selectedIds.length} selected</span>
          </>
        )}
        {resource.id !== 'inbox' && <button className="secondary-button" type="button" onClick={resetResourceFilters}>Reset</button>}
      </section>

      {error && <div className="alert">{error}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      {showCreate && resource.write && resource.id === 'lead-runs' && (
        <LeadGenerationDialog
          clients={options.clients}
          form={form}
          onClose={() => setShowCreate(false)}
          onSubmit={handleCreate}
          updateForm={updateForm}
        />
      )}

      {showCreate && resource.write && resource.id === 'prospect-calls' && (
        <ProspectCallDialog
          clients={options.clients}
          form={form}
          onClose={() => setShowCreate(false)}
          onSubmit={handleCreate}
          updateForm={updateForm}
        />
      )}

      {showCreate && resource.write && resource.id === 'campaigns' && (
        <CampaignDialog
          error={error}
          form={form}
          onClose={() => setShowCreate(false)}
          onSubmit={handleCreate}
          options={options}
          updateForm={updateForm}
        />
      )}

      {showCreate && resource.write && !['campaigns', 'lead-runs', 'prospect-calls'].includes(resource.id) && (
        <section className="modal-backdrop">
          <form className="modal-panel" onSubmit={handleCreate}>
            <div className="modal-head">
              <h2>{resource.write.createLabel}</h2>
              <button type="button" className="secondary-button" onClick={() => setShowCreate(false)}>Close</button>
            </div>
            <div className="form-grid-stage">
              {resource.write.fields.map(renderField)}
            </div>
            {error && <div className="alert">{error}</div>}
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setShowCreate(false)}>Cancel</button>
              <button type="submit" className="primary-button">Save</button>
            </div>
          </form>
        </section>
      )}

      {editingRow && resource.write?.canEdit && (
        <section className="modal-backdrop">
          <form className="modal-panel" onSubmit={handleUpdate}>
            <div className="modal-head">
              <h2>Edit {resource.title}</h2>
              <button type="button" className="secondary-button" onClick={() => setEditingRow(null)}>Close</button>
            </div>
            <div className="form-grid-stage">
              {resource.write.fields.map(renderField)}
            </div>
            {error && <div className="alert">{error}</div>}
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setEditingRow(null)}>Cancel</button>
              <button type="submit" className="primary-button">Save</button>
            </div>
          </form>
        </section>
      )}

      {sendRow && (
        <section className="modal-backdrop">
          <form className="modal-panel compact-modal" onSubmit={handleSend}>
            <div className="modal-head">
              <h2>Send Email</h2>
              <button type="button" className="secondary-button" onClick={() => setSendRow(null)}>Close</button>
            </div>
            <div className="form-grid-stage">
              <label className="form-span">
                <span>From</span>
                <input value={sendRow.email || ''} readOnly />
              </label>
              <label className="form-span">
                <span>To</span>
                <input type="email" value={sendForm.to} onChange={(event) => setSendForm((current) => ({ ...current, to: event.target.value }))} required />
              </label>
              <label className="form-span">
                <span>Subject</span>
                <input value={sendForm.subject} onChange={(event) => setSendForm((current) => ({ ...current, subject: event.target.value }))} required />
              </label>
              <label className="form-span">
                <span>Message</span>
                <textarea value={sendForm.message} onChange={(event) => setSendForm((current) => ({ ...current, message: event.target.value }))} required />
              </label>
            </div>
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setSendRow(null)}>Cancel</button>
              <button type="submit" className="primary-button">Send</button>
            </div>
          </form>
        </section>
      )}

      {viewRow?.__type === 'inbox' && (
        <section className="modal-backdrop">
          <div className="modal-panel inbox-message-modal">
            <div className="modal-head">
              <div>
                <h2>{viewRow.subject || '(No subject)'}</h2>
                <span>{viewRow.fromName ? `${viewRow.fromName} <${viewRow.fromEmail}>` : viewRow.fromEmail}</span>
              </div>
              <div className="message-modal-actions">
                {viewRow.bodyHtml && (
                  <div className="message-view-toggle" role="group" aria-label="Email preview size">
                    <button type="button" className={messageViewMode === 'actual' ? 'active' : ''} onClick={() => setMessageViewMode('actual')}>Actual size</button>
                    <button type="button" className={messageViewMode === 'fit' ? 'active' : ''} onClick={() => setMessageViewMode('fit')}>Fit width</button>
                  </div>
                )}
                <button type="button" className="secondary-button" onClick={() => setViewRow(null)}>Close</button>
              </div>
            </div>
            <div className="message-meta">
              <span>To: {viewRow.toEmail || '-'}</span>
              <span>Account: {viewRow.accountEmail || '-'}</span>
              <span>Received: {displayValue(viewRow.receivedAt)}</span>
            </div>
            {viewRow.bodyHtml ? (
              <iframe
                className="message-frame"
                title="Email body"
                sandbox="allow-popups allow-popups-to-escape-sandbox"
                srcDoc={messagePreviewDocument(viewRow.bodyHtml, messageViewMode)}
              />
            ) : (
              <pre className="message-text">{viewRow.bodyText || 'No message body.'}</pre>
            )}
          </div>
        </section>
      )}

      {viewRow?.__type === 'campaigns' && (
        <section className="modal-backdrop">
          <div className="modal-panel detail-modal">
            <div className="modal-head">
              <div>
                <h2>{viewRow.name}</h2>
                <span>{viewRow.subject}</span>
              </div>
              <button type="button" className="secondary-button" onClick={() => setViewRow(null)}>Close</button>
            </div>
            <div className="detail-grid">
              <span>Status</span><strong>{displayValue(viewRow.status?.status_label)} · {viewRow.status?.sent || 0} sent · {viewRow.status?.failed || 0} failed</strong>
              <span>Client</span><strong>{displayValue(viewRow.client?.name)}</strong>
              <span>Sender</span><strong>{displayValue(viewRow.account?.email)}</strong>
              <span>Template</span><strong>{displayValue(viewRow.template?.name || viewRow.template?.key)}</strong>
              <span>Audiences</span><strong>{displayValue((viewRow.audiences || []).map((audience) => audience.name))}</strong>
              <span>Attachments</span><strong>{displayValue((viewRow.attachments || []).map((attachment) => attachment.name))}</strong>
            </div>
            <div className="table-scroll compact-scroll">
              <table className="detail-table">
                <thead>
                  <tr>
                    <th>Recipient</th>
                    <th>Company</th>
                    <th>Cell</th>
                    <th>Status</th>
                    <th>Opened</th>
                    <th>Error</th>
                  </tr>
                </thead>
                <tbody>
                  {(viewRow.recipients || []).length === 0 ? (
                    <tr><td className="empty-state" colSpan="6">No recipients yet.</td></tr>
                  ) : viewRow.recipients.map((recipient) => (
                    <tr key={recipient.id}>
                      <td>{displayValue(recipient.contactName || recipient.email)}</td>
                      <td>{displayValue(recipient.company)}</td>
                      <td>{displayValue(recipient.phone)}</td>
                      <td>{displayValue(recipient.status)}</td>
                      <td>{displayValue(recipient.openedAt)}</td>
                      <td>{displayValue(recipient.error)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      )}

      {viewRow?.__type === 'lead-runs' && (
        <section className="modal-backdrop">
          <div className="modal-panel detail-modal">
            <div className="modal-head">
              <div>
                <h2>Lead Run #{viewRow.id}</h2>
                <span>{viewRow.prompt || 'Lead generation'}</span>
              </div>
              <button type="button" className="secondary-button" onClick={() => setViewRow(null)}>Close</button>
            </div>
            <div className="detail-grid">
              <span>Status</span><strong>{displayValue(viewRow.status)}</strong>
              <span>Client</span><strong>{displayValue(viewRow.client?.name)}</strong>
              <span>Found</span><strong>{displayValue(viewRow.discoveredCount)}</strong>
              <span>Imported</span><strong>{displayValue(viewRow.importedCount)}</strong>
              <span>Tags</span><strong>{displayValue(viewRow.keywords)}</strong>
              <span>Error</span><strong>{displayValue(viewRow.errorMessage)}</strong>
            </div>
            <div className="table-scroll compact-scroll">
              <table className="detail-table">
                <thead>
                  <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Cell</th>
                    <th>Website</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {(viewRow.leads || []).length === 0 ? (
                    <tr><td className="empty-state" colSpan="6">No leads in this run.</td></tr>
                  ) : viewRow.leads.map((lead, index) => (
                    <tr key={`${lead.email || lead.company}-${index}`}>
                      <td>{displayValue(lead.email)}</td>
                      <td>{displayValue(lead.name || lead.decision_maker)}</td>
                      <td>{displayValue(lead.company)}</td>
                      <td>{displayValue(lead.phone || lead.phone_number)}</td>
                      <td>{lead.source_url || lead.website ? <a href={lead.source_url || lead.website} target="_blank" rel="noreferrer">Open</a> : '-'}</td>
                      <td>
                        <div className="row-actions">
                          <button className="secondary-button compact" type="button" onClick={() => openLeadEdit(index, lead)}>Edit</button>
                          <button className="danger-button" type="button" onClick={() => handleLeadDelete(index)}>Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      )}

      {leadEdit && (
        <section className="modal-backdrop">
          <form className="modal-panel compact-modal" onSubmit={handleLeadEdit}>
            <div className="modal-head">
              <h2>Edit Lead</h2>
              <button type="button" className="secondary-button" onClick={() => setLeadEdit(null)}>Close</button>
            </div>
            <div className="form-grid-stage">
              {[
                ['email', 'Email', 'email'],
                ['name', 'Name', 'text'],
                ['company', 'Company', 'text'],
                ['phone', 'Cell', 'text'],
                ['website', 'Website', 'text'],
                ['notes', 'Notes', 'textarea'],
              ].map(([name, label, type]) => (
                <label key={name} className={type === 'textarea' ? 'form-span' : ''}>
                  <span>{label}</span>
                  {type === 'textarea' ? (
                    <textarea value={leadEdit.form[name] || ''} onChange={(event) => setLeadEdit((current) => ({ ...current, form: { ...current.form, [name]: event.target.value } }))} />
                  ) : (
                    <input type={type} value={leadEdit.form[name] || ''} onChange={(event) => setLeadEdit((current) => ({ ...current, form: { ...current.form, [name]: event.target.value } }))} />
                  )}
                </label>
              ))}
            </div>
            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setLeadEdit(null)}>Cancel</button>
              <button type="submit" className="primary-button">Save</button>
            </div>
          </form>
        </section>
      )}

      <section className="table-panel" aria-busy={loading}>
        {loading && <div className="loading-bar">Refreshing {resource.title.toLowerCase()}...</div>}
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                {supportsSelection && (
                  <th>
                    <input type="checkbox" checked={allVisibleSelected} onChange={(event) => toggleVisibleInbox(event.target.checked)} aria-label={`Select visible ${resource.title.toLowerCase()} records`} />
                  </th>
                )}
                {resource.columns.map(([, label]) => <th key={label}>{label}</th>)}
                {hasRowActions && <th></th>}
              </tr>
            </thead>
            <tbody>
              {rows.data.length === 0 ? (
                <tr>
                  <td className="empty-state" colSpan={resource.columns.length + (hasRowActions ? 1 : 0) + (supportsSelection ? 1 : 0)}>No records found.</td>
                </tr>
              ) : rows.data.map((row) => (
                <tr
                  key={row.id}
                  className={resource.id === 'inbox' && !row.openedAt ? 'inbox-unread-row' : undefined}
                >
                  {supportsSelection && (
                    <td>
                      <input
                        type="checkbox"
                        checked={selectedIds.includes(row.id)}
                        onChange={(event) => toggleInboxSelected(row.id, event.target.checked)}
                        aria-label={`Select ${resource.title.toLowerCase()} record ${row.name || row.subject || row.id}`}
                      />
                    </td>
                  )}
                  {resource.columns.map(([key]) => (
                    <td className="wrap" key={key}>{displayValue(row[key])}</td>
                  ))}
                  {hasRowActions && (
                    <td>
                      <div className="row-actions">
                        {resource.write?.canEdit && (
                          <button className="secondary-button compact" type="button" onClick={() => openEdit(row)}>Edit</button>
                        )}
                        {resource.write?.canRegenerate && (
                          <button className="secondary-button compact" type="button" onClick={() => handleRegenerate(row)}>Regenerate</button>
                        )}
                        {resource.write?.statusActions && (
                          <button
                            className="secondary-button compact"
                            type="button"
                            onClick={() => handleStatusAction(
                              row,
                              row[resource.write.statusActions.field] === resource.write.statusActions.activeValue ? 'suspend' : 'activate',
                            )}
                          >
                            {row[resource.write.statusActions.field] === resource.write.statusActions.activeValue
                              ? resource.write.statusActions.suspendLabel
                              : resource.write.statusActions.activateLabel}
                          </button>
                        )}
                        {resource.id === 'accounts' && (
                          <>
                            <button className="secondary-button compact" type="button" onClick={() => handleVerify(row)}>Verify</button>
                            <button className="secondary-button compact" type="button" onClick={() => openSend(row)}>Send</button>
                          </>
                        )}
                        {resource.id === 'campaigns' && (
                          <>
                            <button className="secondary-button compact" type="button" onClick={() => handleResourceView(row)}>View</button>
                            {row.status !== 'sending' && (
                              <button className="secondary-button compact" type="button" onClick={() => handleCampaignSend(row)}>Send</button>
                            )}
                          </>
                        )}
                        {resource.id === 'lead-runs' && (
                          <>
                            <button className="secondary-button compact" type="button" onClick={() => handleResourceView(row)}>View</button>
                            <button className="secondary-button compact" type="button" onClick={() => handleLeadDownload(row)}>Download</button>
                            <button className="secondary-button compact" type="button" onClick={() => handleLeadImport(row)}>Import</button>
                          </>
                        )}
                        {resource.inboxActions && (
                          <>
                            <button className="secondary-button compact" type="button" onClick={() => handleInboxView(row)}>View</button>
                            <button className="secondary-button compact" type="button" onClick={() => handleInboxOpened(row, !row.openedAt)}>
                              {row.openedAt ? 'Unread' : 'Open'}
                            </button>
                            <button className="danger-button" type="button" onClick={() => handleInboxDelete(row)}>Delete</button>
                          </>
                        )}
                        {resource.write && (
                          <button className="danger-button" type="button" onClick={() => handleDelete(row)}>Delete</button>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <nav className="pagination" aria-label={`${resource.title} pages`}>
        <div className="pagination-summary">
          <strong>
            {rows.meta.total > 0 ? ((rows.meta.page - 1) * rows.meta.perPage) + 1 : 0}
            {'-'}
            {Math.min(rows.meta.page * rows.meta.perPage, rows.meta.total)}
          </strong>
          <span>of {rows.meta.total} records</span>
        </div>
        <div className="pagination-controls">
          {resource.id === 'inbox' && (
            <label className="pagination-size">
              <span>Rows</span>
              <select value={perPage} onChange={(event) => updatePerPage(event.target.value)} aria-label="Rows per page">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </label>
          )}
          <div className="pagination-pages">
            <button className="pagination-arrow" type="button" aria-label="Previous page" title="Previous page" disabled={rows.meta.page <= 1} onClick={() => setPage(rows.meta.page - 1)}>&lsaquo;</button>
            {paginationItems(rows.meta.page, rows.meta.lastPage).map((item) => (
              typeof item === 'string' ? (
                <span className="pagination-ellipsis" key={item} aria-hidden="true">&hellip;</span>
              ) : (
                <button
                  className={`pagination-page ${item === rows.meta.page ? 'active' : ''}`}
                  type="button"
                  key={item}
                  aria-label={`Page ${item}`}
                  aria-current={item === rows.meta.page ? 'page' : undefined}
                  onClick={() => setPage(item)}
                >
                  {item}
                </button>
              )
            ))}
            <button className="pagination-arrow" type="button" aria-label="Next page" title="Next page" disabled={rows.meta.page >= rows.meta.lastPage} onClick={() => setPage(rows.meta.page + 1)}>&rsaquo;</button>
          </div>
        </div>
      </nav>
    </main>
  );
}
