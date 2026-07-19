import { useState } from 'react';
import { apiGet } from '../api/client.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';

function formatDate(value) {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('en-ZA', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function StatusBadge({ status, opened }) {
  const label = opened ? 'Opened' : status;
  const tone = opened ? 'is-opened' : `is-${status || 'pending'}`;

  return <span className={`status-badge ${tone}`}>{label}</span>;
}

export function EmailLogsPage() {
  const {
    options,
    filters,
    logs,
    loading,
    error,
    updateFilter,
    resetFilters,
  } = useAppData();
  const [selectedLog, setSelectedLog] = useState(null);
  const [actionError, setActionError] = useState('');
  const pageError = actionError || error.logs || error.options;
  const pageLoading = loading.logs;

  async function viewLog(log) {
    setActionError('');

    try {
      const response = await apiGet(`/email-logs/${log.id}`);
      setSelectedLog(response.data);
    } catch (requestError) {
      setActionError(requestError.message);
    }
  }

  return (
    <main className="app-shell">
      <header className="app-header">
        <div>
          <p className="eyebrow">Mail</p>
          <h1>Email Logs</h1>
        </div>
      </header>

      <ResourceStatsGrid resourceId="logs" params={filters} refreshKey={logs} />

      <section className="toolbar command-toolbar">
        <label className="search-field command-search">
          <span className="sr-only">Search</span>
          <input
            value={filters.q}
            onChange={(event) => updateFilter('q', event.target.value)}
            placeholder="Email, subject, contact, company, cell, website, or message ID"
          />
        </label>

        <label className="command-filter">
          <span className="sr-only">Client</span>
          <select aria-label="Client" value={filters.client_id} onChange={(event) => updateFilter('client_id', event.target.value)}>
            <option value="">All clients</option>
            {options.clients.map((client) => (
              <option key={client.id} value={client.id}>{client.name}</option>
            ))}
          </select>
        </label>

        <label className="command-filter">
          <span className="sr-only">Status</span>
          <select aria-label="Status" value={filters.status} onChange={(event) => updateFilter('status', event.target.value)}>
            <option value="">All statuses</option>
            {options.emailLogStatuses.map((status) => (
              <option key={status} value={status}>{status}</option>
            ))}
          </select>
        </label>

        <label className="command-filter">
          <span className="sr-only">Opened</span>
          <select aria-label="Opened" value={filters.opened} onChange={(event) => updateFilter('opened', event.target.value)}>
            <option value="">All opens</option>
            <option value="opened">Opened</option>
            <option value="not_opened">Not opened</option>
          </select>
        </label>

        <button type="button" className="secondary-button" onClick={resetFilters}>Reset</button>
      </section>

      {pageError && <div className="alert">{pageError}</div>}

      {selectedLog && (
        <section className="modal-backdrop">
          <div className="modal-panel inbox-message-modal">
            <div className="modal-head">
              <div>
                <h2>Email Log #{selectedLog.id}</h2>
                <span>{selectedLog.subject || 'No subject'}</span>
              </div>
              <button type="button" className="secondary-button" onClick={() => setSelectedLog(null)}>Close</button>
            </div>
            <div className="detail-grid">
              <span>Status</span><strong>{selectedLog.opened ? 'opened' : selectedLog.status}</strong>
              <span>Client</span><strong>{selectedLog.client?.name || '-'}</strong>
              <span>Domain</span><strong>{selectedLog.domain || '-'}</strong>
              <span>Template</span><strong>{selectedLog.templateName || '-'}</strong>
              <span>API Key</span><strong>{selectedLog.apiKeyName || '-'}</strong>
              <span>Contact</span><strong>{selectedLog.contact?.company || selectedLog.contact?.name || selectedLog.contact?.email || '-'}</strong>
              <span>From</span><strong>{selectedLog.fromEmail || '-'}</strong>
              <span>To</span><strong>{selectedLog.toEmail || '-'}</strong>
              <span>Message ID</span><strong>{selectedLog.providerMessageId || '-'}</strong>
              <span>Sent</span><strong>{formatDate(selectedLog.sentAt)}</strong>
              <span>Opened</span><strong>{formatDate(selectedLog.openedAt)}</strong>
              <span>Clicked</span><strong>{formatDate(selectedLog.clickedAt)}</strong>
            </div>
            {selectedLog.errorMessage && <div className="alert">{selectedLog.errorMessage}</div>}
            <pre className="message-text">{JSON.stringify(selectedLog.payload || {}, null, 2)}</pre>
          </div>
        </section>
      )}

      <section className="table-panel" aria-busy={pageLoading}>
        {pageLoading && <div className="loading-bar">Refreshing logs...</div>}
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Status</th>
                <th>Client</th>
                <th>Contact</th>
                <th>Company</th>
                <th>Cell</th>
                <th>Website</th>
                <th>From</th>
                <th>To</th>
                <th>Subject</th>
                <th>Error</th>
                <th>Sent At</th>
                <th>Opened At</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {logs.data.length === 0 ? (
                <tr>
                  <td colSpan="13" className="empty-state">No sent email history yet.</td>
                </tr>
              ) : logs.data.map((log) => (
                <tr key={log.id}>
                  <td><StatusBadge status={log.status} opened={log.opened} /></td>
                  <td>{log.client?.name || '-'}</td>
                  <td>
                    <strong>{log.contact?.name || log.toEmail}</strong>
                    <span>{log.contact?.email || log.toEmail}</span>
                  </td>
                  <td>{log.contact?.company || '-'}</td>
                  <td>{log.contact?.cell || '-'}</td>
                  <td>
                    {log.contact?.website ? (
                      <a href={log.contact.website} target="_blank" rel="noreferrer">Open</a>
                    ) : '-'}
                  </td>
                  <td>{log.fromEmail}</td>
                  <td>{log.toEmail}</td>
                  <td className="wrap">{log.subject || '-'}</td>
                  <td className="wrap">{log.errorMessage || '-'}</td>
                  <td>{formatDate(log.sentAt)}</td>
                  <td>{formatDate(log.openedAt)}</td>
                  <td>
                    <button className="secondary-button compact" type="button" onClick={() => viewLog(log)}>View</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <nav className="pagination" aria-label="Email log pages">
        <button
          type="button"
          className="secondary-button"
          disabled={logs.meta.page <= 1}
          onClick={() => updateFilter('page', logs.meta.page - 1)}
        >
          Previous
        </button>
        <span>Page {logs.meta.page} of {logs.meta.lastPage}</span>
        <button
          type="button"
          className="secondary-button"
          disabled={logs.meta.page >= logs.meta.lastPage}
          onClick={() => updateFilter('page', logs.meta.page + 1)}
        >
          Next
        </button>
      </nav>
    </main>
  );
}
