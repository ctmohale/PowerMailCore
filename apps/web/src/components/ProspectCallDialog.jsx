import { useEffect, useRef, useState } from 'react';
import { apiGet } from '../api/client.js';

const statuses = [
  ['new', 'New'],
  ['called', 'Called'],
  ['follow_up', 'Follow up'],
  ['meeting_booked', 'Meeting booked'],
  ['not_interested', 'Not interested'],
  ['won', 'Won'],
  ['lost', 'Lost'],
];

function SearchIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-4-4" />
    </svg>
  );
}

function CloseIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="m6 6 12 12" />
      <path d="M18 6 6 18" />
    </svg>
  );
}

function contactDetails(contact) {
  return [...new Set([contact.decisionMaker, contact.email, contact.cell].filter(Boolean))].join(' | ');
}

export function ProspectCallDialog({ clients, error = '', form, onClose, onDelete, onSubmit, submitting = false, title = 'Record Call', updateForm }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(true);
  const [searchError, setSearchError] = useState('');
  const requestNumber = useRef(0);

  useEffect(() => {
    const currentRequest = ++requestNumber.current;
    const timer = window.setTimeout(() => {
      setSearching(true);
      setSearchError('');
      apiGet('/marketing/contacts', { q: query.trim(), per_page: 8, page: 1 })
        .then((response) => {
          if (currentRequest === requestNumber.current) setResults(response.data || []);
        })
        .catch((error) => {
          if (currentRequest === requestNumber.current) setSearchError(error.message);
        })
        .finally(() => {
          if (currentRequest === requestNumber.current) setSearching(false);
        });
    }, query ? 250 : 0);

    return () => window.clearTimeout(timer);
  }, [query]);

  function selectContact(contact) {
    updateForm('marketing_contact_id', contact.id);
    updateForm('client_id', contact.client?.id || '');
    updateForm('company_name', contact.company || '');
    updateForm('contact_name', contact.decisionMaker || '');
    updateForm('phone', contact.cell || '');
    updateForm('email', contact.email || '');
    setQuery('');
    setResults([]);
  }

  function clearContact() {
    updateForm('marketing_contact_id', '');
    updateForm('company_name', '');
    updateForm('contact_name', '');
    updateForm('phone', '');
    updateForm('email', '');
  }

  const selectedLabel = [form.contact_name, form.company_name].filter(Boolean).join(' at ');

  return (
    <section className="modal-backdrop prospect-call-backdrop">
      <form className="modal-panel prospect-call-dialog" onSubmit={onSubmit} role="dialog" aria-modal="true" aria-labelledby="record-call-title">
        <div className="modal-head">
          <div>
            <h2 id="record-call-title">{title}</h2>
            <p>Choose an existing lead to prefill their contact details.</p>
          </div>
          <button className="prospect-call-close" type="button" onClick={onClose} aria-label="Close record call" title="Close">
            <CloseIcon />
          </button>
        </div>

        <div className="prospect-call-body">
          {error && <div className="alert prospect-dialog-error">{error}</div>}
          <div className="prospect-contact-search">
            <label htmlFor="prospect-contact-query">Find a lead or contact</label>
            <div className="prospect-search-input">
              <SearchIcon />
              <input
                id="prospect-contact-query"
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search company, name, email, or phone"
                autoComplete="off"
                autoFocus
              />
              {searching && <span className="prospect-searching">Searching...</span>}
            </div>
            {searchError && <div className="prospect-search-error">{searchError}</div>}
            {!form.marketing_contact_id && !searchError && (
              <div className="prospect-contact-results" role="listbox" aria-label="Matching leads and contacts">
                {!searching && results.length === 0 && <p>No matching contacts. Enter the details below to record a new prospect.</p>}
                {results.map((contact) => (
                  <button type="button" key={contact.id} onClick={() => selectContact(contact)} role="option">
                    <span className="prospect-contact-avatar">{String(contact.company || contact.decisionMaker || contact.email || '?').charAt(0).toUpperCase()}</span>
                    <span className="prospect-contact-copy">
                      <strong>{contact.company || contact.decisionMaker || contact.email}</strong>
                      <small>{contactDetails(contact)}</small>
                    </span>
                    <span className="prospect-select-label">Select</span>
                  </button>
                ))}
              </div>
            )}
          </div>

          {form.marketing_contact_id && (
            <div className="prospect-selected-contact">
              <div>
                <span>Selected contact</span>
                <strong>{selectedLabel || form.email || form.phone}</strong>
              </div>
              <button type="button" onClick={clearContact}>Change</button>
            </div>
          )}

          <div className="prospect-call-fields">
            {clients.length > 1 && (
              <label>
                <span>Client</span>
                <select value={form.client_id || ''} onChange={(event) => updateForm('client_id', event.target.value)} required>
                  <option value="">Select client</option>
                  {clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
                </select>
              </label>
            )}
            <label>
              <span>Company</span>
              <input value={form.company_name || ''} onChange={(event) => updateForm('company_name', event.target.value)} required />
            </label>
            <label>
              <span>Contact</span>
              <input value={form.contact_name || ''} onChange={(event) => updateForm('contact_name', event.target.value)} />
            </label>
            <label>
              <span>Cell</span>
              <input type="tel" value={form.phone || ''} onChange={(event) => updateForm('phone', event.target.value)} />
            </label>
            <label>
              <span>Email</span>
              <input type="email" value={form.email || ''} onChange={(event) => updateForm('email', event.target.value)} />
            </label>
            <label>
              <span>Call Date</span>
              <input type="date" value={form.call_date || ''} onChange={(event) => updateForm('call_date', event.target.value)} />
            </label>
            <label>
              <span>Follow Up</span>
              <input type="datetime-local" value={form.follow_up_at || ''} onChange={(event) => updateForm('follow_up_at', event.target.value)} />
            </label>
            <label>
              <span>Status</span>
              <select value={form.status || 'new'} onChange={(event) => updateForm('status', event.target.value)} required>
                {statuses.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
            </label>
            <label className="prospect-outcome-field">
              <span>Outcome</span>
              <input value={form.outcome || ''} onChange={(event) => updateForm('outcome', event.target.value)} placeholder="Brief call outcome" />
            </label>
          </div>
        </div>

        <div className="modal-actions">
          {onDelete && <button className="danger-button" type="button" onClick={onDelete} disabled={submitting}>Delete</button>}
          <span className="modal-action-spacer" />
          <button className="secondary-button" type="button" onClick={onClose} disabled={submitting}>Cancel</button>
          <button className="primary-button" type="submit" disabled={submitting}>
            {submitting && <span className="button-spinner" />}
            Save Call
          </button>
        </div>
      </form>
    </section>
  );
}
