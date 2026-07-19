import { useEffect, useState } from 'react';
import { apiPost } from '../api/client.js';
import { useUiFeedback } from '../context/UiFeedbackContext.jsx';

const SAMPLE_PLACEHOLDER = `Paste Google Maps or search results here. For example:

Lawtons Africa
4,3(37) · Law firm · Johannesburg · 011 286 6900
Website  Directions

Webber Wentzel
4,6(375) · Law firm · Sandton · 011 530 5000
Website  Directions`;

function previewDetails(lead) {
  const location = String(lead.address_or_location || '').trim();
  const phone = String(lead.phone || '').trim();
  return [location, phone].filter(Boolean).join(' | ') || 'No location or phone yet';
}

export function LeadGenerationDialog({ clients, form, onClose, onSubmit, updateForm }) {
  const { toast } = useUiFeedback();
  const [preview, setPreview] = useState({ status: 'idle', leads: [] });
  const [researching, setResearching] = useState(false);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    if (!researching) return undefined;
    setProgress(5);
    const startedAt = Date.now();
    const timer = window.setInterval(() => {
      const elapsed = Date.now() - startedAt;
      setProgress(Math.min(92, Math.round(5 + 87 * (1 - Math.exp(-elapsed / 70000)))));
    }, 800);
    return () => window.clearInterval(timer);
  }, [researching]);

  function updateSource(value) {
    updateForm('source_data', value);
    setPreview({ status: 'idle', leads: [] });
  }

  async function parsePastedData() {
    const sourceData = String(form.source_data || '').trim();
    if (!sourceData) {
      setPreview({ status: 'empty', leads: [] });
      toast('Paste business listing text first.', 'error');
      return;
    }

    setPreview({ status: 'parsing', leads: [] });

    try {
      const response = await apiPost('/marketing/lead-runs/preview', {
        client_id: form.client_id,
        source_data: sourceData,
        target_count: 200,
      });
      const leads = Array.isArray(response.companies) && response.companies.length
        ? response.companies
        : Array.isArray(response.leads) ? response.leads : [];

      if (!leads.length) {
        setPreview({ status: 'none', leads: [] });
        toast('No business records were found. Adjust the pasted text and try again.', 'error');
        return;
      }

      setPreview({ status: 'ready', leads });
      toast(`Parsed ${leads.length} business record${leads.length === 1 ? '' : 's'}.`, 'success');
    } catch (error) {
      setPreview({ status: 'failed', leads: [], message: error.message });
      toast(error.message || 'Failed to parse pasted text.', 'error');
    }
  }

  async function submit(event) {
    if (preview.status !== 'ready') {
      event.preventDefault();
      toast('Click Parse Pasted Data before finding websites and emails.', 'error');
      return;
    }

    setResearching(true);
    try {
      await onSubmit(event);
    } finally {
      setResearching(false);
    }
  }

  const researchSteps = [
    [5, 'Parsing pasted business entries'],
    [12, 'Searching for each official company website'],
    [28, 'Scoring results to identify official websites'],
    [44, 'Visiting websites and contact pages'],
    [60, 'Extracting emails, phone numbers, and logos'],
    [74, 'Structuring leads into import-ready records'],
    [88, 'Saving enriched lead records'],
  ];
  const activeStep = [...researchSteps].reverse().find(([at]) => progress >= at)?.[1] || 'Preparing research';

  const summary = {
    idle: ['Preview not generated yet.', 'Click Parse Pasted Data to extract company records before enrichment.'],
    empty: ['No text to parse.', 'Paste business listing text first.'],
    parsing: ['Parsing pasted businesses...', 'Please wait while company records are extracted.'],
    none: ['No business records found.', 'Adjust the pasted text and parse again.'],
    failed: ['Parse failed.', preview.message || 'Check the pasted data and try again.'],
    ready: [`Parsed ${preview.leads.length} business record${preview.leads.length === 1 ? '' : 's'}.`, 'Preview looks good. Click Find Websites & Emails to continue.'],
  }[preview.status];

  return (
    <section className="modal-backdrop lead-generation-backdrop">
      <form className="modal-panel lead-generation-dialog" onSubmit={submit}>
        <div className="modal-head lead-generation-head">
          <div>
            <p className="eyebrow">Lead Research</p>
            <h2>Generate leads from pasted data</h2>
            <p>Paste businesses from Google Maps, Google Search, or any directory. The system will visit each website, extract emails, phone numbers, and contact details, then build your lead list.</p>
          </div>
          <button type="button" className="secondary-button" onClick={onClose}>Close</button>
        </div>

        <div className="lead-generation-dialog-body">
          <label className="lead-client-field">
            <span>Client</span>
            <select value={form.client_id || ''} onChange={(event) => {
              updateForm('client_id', event.target.value);
              setPreview({ status: 'idle', leads: [] });
            }} required>
              <option value="">Select client</option>
              {clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
            </select>
          </label>

          <label className="lead-source-field">
            <span>Paste business list <small>copy and paste results from Google Maps, Google Search, or any business directory</small></span>
            <textarea
              value={form.source_data || ''}
              onChange={(event) => updateSource(event.target.value)}
              placeholder={SAMPLE_PLACEHOLDER}
              rows="12"
              required
              spellCheck="false"
            />
          </label>

          <section className={`lead-parse-preview is-${preview.status}`} aria-live="polite" aria-busy={preview.status === 'parsing'}>
            <header>
              {preview.status === 'parsing' && <span className="lead-parse-spinner" aria-hidden="true" />}
              <div>
                <strong>{summary[0]}</strong>
                <span>{summary[1]}</span>
              </div>
            </header>
            {preview.leads.length > 0 && (
              <div className="lead-parse-list">
                {preview.leads.slice(0, 50).map((lead, index) => (
                  <div className="lead-parse-row" key={`${lead.company_name || lead.company}-${index}`}>
                    <span>{index + 1}</span>
                    <div>
                      <strong>{lead.company_name || lead.company || 'Unnamed company'}</strong>
                      <small>{previewDetails(lead)}</small>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          {researching && (
            <section className="lead-research-progress" aria-live="polite" aria-busy="true">
              <header>
                <span className="lead-parse-spinner" aria-hidden="true" />
                <div><strong>{activeStep}</strong><span>Website research can take several minutes.</span></div>
                <strong>{progress}%</strong>
              </header>
              <div className="lead-research-track"><span style={{ width: `${progress}%` }} /></div>
              <ul>
                {researchSteps.map(([at, label]) => (
                  <li className={progress >= at + 10 ? 'complete' : progress >= at ? 'active' : ''} key={label}>{label}</li>
                ))}
              </ul>
            </section>
          )}
        </div>

        <div className="modal-actions lead-generation-actions">
          <button type="button" className="secondary-button" onClick={onClose} disabled={researching}>Cancel</button>
          <button type="button" className="secondary-button" onClick={parsePastedData} disabled={researching}>Parse Pasted Data</button>
          <button type="submit" className="primary-button" disabled={preview.status !== 'ready' || researching}>{researching ? 'Researching...' : 'Find Websites & Emails'}</button>
        </div>
      </form>
    </section>
  );
}
