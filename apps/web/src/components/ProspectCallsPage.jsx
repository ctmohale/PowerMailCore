import { useCallback, useEffect, useMemo, useState } from 'react';
import { Calendar, dateFnsLocalizer } from 'react-big-calendar';
import { format, getDay, parse, startOfWeek as dateFnsStartOfWeek } from 'date-fns';
import { enZA } from 'date-fns/locale';
import { apiDelete, apiGet, apiPatch, apiPost } from '../api/client.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { useUiFeedback } from '../context/UiFeedbackContext.jsx';
import { ProspectCallDialog } from './ProspectCallDialog.jsx';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';

const localizer = dateFnsLocalizer({
  format,
  getDay,
  locales: { 'en-ZA': enZA },
  parse,
  startOfWeek: (date) => dateFnsStartOfWeek(date, { weekStartsOn: 1 }),
});

function asDate(value, allDay = false) {
  const source = String(value || '');
  const date = new Date(source.includes('T') || source.includes(' ') ? source.replace(' ', 'T') : `${source}T${allDay ? '00:00:00' : '09:00:00'}`);
  return Number.isNaN(date.getTime()) ? new Date() : date;
}

function dateInput(value) {
  return format(value instanceof Date ? value : asDate(value, true), 'yyyy-MM-dd');
}

function dateTimeInput(value) {
  if (!value) return '';
  return format(value instanceof Date ? value : asDate(value), "yyyy-MM-dd'T'HH:mm");
}

function blankCall(clientId = '', selectedDate = new Date(), includeTime = false) {
  return {
    client_id: clientId,
    marketing_contact_id: '',
    company_name: '',
    contact_name: '',
    phone: '',
    email: '',
    call_date: dateInput(selectedDate),
    follow_up_at: includeTime ? dateTimeInput(selectedDate) : '',
    status: 'new',
    outcome: '',
  };
}

async function loadAllCalls(params) {
  const first = await apiGet('/marketing/prospect-calls', { ...params, page: 1, per_page: 100 });
  if (first.meta.lastPage <= 1) return first.data;

  const remaining = await Promise.all(
    Array.from({ length: first.meta.lastPage - 1 }, (_, index) => (
      apiGet('/marketing/prospect-calls', { ...params, page: index + 2, per_page: 100 })
    )),
  );
  return [first, ...remaining].flatMap((page) => page.data);
}

export function ProspectCallsPage() {
  const { options } = useAppData();
  const { confirmAction } = useUiFeedback();
  const [query, setQuery] = useState('');
  const [clientId, setClientId] = useState('');
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editor, setEditor] = useState(null);
  const [form, setForm] = useState(() => blankCall());
  const [submitting, setSubmitting] = useState(false);
  const params = useMemo(() => ({ q: query, client_id: clientId }), [clientId, query]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setRows(await loadAllCalls(params));
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [params]);

  useEffect(() => {
    load();
  }, [load]);

  const events = useMemo(() => rows.flatMap((row) => {
    const title = row.companyName || row.contactName;
    const callDate = asDate(row.callDate, true);
    const callEvents = row.callDate ? [{
      id: `call-${row.id}`,
      title: `Call: ${title}`,
      start: callDate,
      end: new Date(callDate.getTime() + (30 * 60000)),
      allDay: true,
      resource: { row, type: 'call' },
    }] : [];
    const followUpEvents = row.followUpAt ? [{
      id: `follow-up-${row.id}`,
      title: `Follow-up: ${title}`,
      start: asDate(row.followUpAt),
      end: new Date(asDate(row.followUpAt).getTime() + (30 * 60000)),
      resource: { row, type: 'follow-up' },
    }] : [];
    return [...callEvents, ...followUpEvents];
  }), [rows]);

  function openCreate(selection) {
    const selectedDate = selection?.start || new Date();
    const includesTime = selection?.action === 'select' && (
      selectedDate.getHours() !== 0 || selectedDate.getMinutes() !== 0
    );
    setForm(blankCall(clientId || (options.clients.length === 1 ? options.clients[0].id : ''), selectedDate, includesTime));
    setEditor({ mode: 'create' });
    setError('');
  }

  function openEvent(event) {
    const row = event.resource.row;
    setForm({
      client_id: row.clientId || '',
      marketing_contact_id: row.marketing_contact_id || '',
      company_name: row.companyName || '',
      contact_name: row.contactName || '',
      phone: row.phone || '',
      email: row.email || '',
      call_date: row.callDate || '',
      follow_up_at: dateTimeInput(row.followUpAt),
      status: row.status || 'new',
      outcome: row.outcome || '',
    });
    setEditor({ mode: 'edit', row });
    setError('');
  }

  function updateForm(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function saveCall(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');
    try {
      if (editor.mode === 'edit') {
        await apiPatch(`/marketing/prospect-calls/${editor.row.id}`, form);
        setNotice('Prospect call updated.');
      } else {
        await apiPost('/marketing/prospect-calls', form);
        setNotice('Prospect call recorded.');
      }
      setEditor(null);
      await load();
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function deleteCall() {
    if (!editor?.row || !await confirmAction({
      title: 'Delete prospect call?',
      message: `${editor.row.companyName} will be removed from the calls calendar.`,
      confirmLabel: 'Delete Call',
      tone: 'danger',
    })) return;

    setSubmitting(true);
    setError('');
    try {
      await apiDelete(`/marketing/prospect-calls/${editor.row.id}`);
      setEditor(null);
      setNotice('Prospect call deleted.');
      await load();
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="app-shell prospect-calls-page">
      <header className="app-header">
        <div>
          <p className="eyebrow">Marketing</p>
          <h1>Prospect Calls</h1>
        </div>
      </header>

      <ResourceStatsGrid resourceId="prospect-calls" params={params} refreshKey={rows} />

      <section className="toolbar command-toolbar calendar-command-toolbar">
        <label className="search-field command-search">
          <span className="sr-only">Search calls</span>
          <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search company, contact, email, phone, status, or outcome" />
        </label>
        <label className="command-filter">
          <span className="sr-only">Company</span>
          <select value={clientId} onChange={(event) => setClientId(event.target.value)} aria-label="Company">
            <option value="">All companies</option>
            {options.clients.map((client) => <option value={client.id} key={client.id}>{client.name}</option>)}
          </select>
        </label>
        <button type="button" className="primary-button" onClick={() => openCreate()}>Record Call</button>
        <button type="button" className="secondary-button" onClick={() => { setQuery(''); setClientId(''); }}>Reset</button>
      </section>

      {error && !editor && <div className="alert">{error}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      <section className="calendar-workspace prospect-calendar-workspace" aria-busy={loading}>
        {loading && <div className="calendar-loading"><span className="button-spinner" /> Loading calls...</div>}
        <Calendar
          culture="en-ZA"
          localizer={localizer}
          events={events}
          startAccessor="start"
          endAccessor="end"
          allDayAccessor="allDay"
          selectable
          popup
          views={['month', 'week', 'day', 'agenda']}
          defaultView="month"
          onSelectSlot={openCreate}
          onSelectEvent={openEvent}
          eventPropGetter={(event) => ({ className: `calendar-event is-${event.resource.type} status-${event.resource.row.status}` })}
          messages={{ showMore: (total) => `+${total} more calls` }}
        />
      </section>

      {editor && (
        <ProspectCallDialog
          clients={options.clients}
          error={error}
          form={form}
          onClose={() => setEditor(null)}
          onDelete={editor.mode === 'edit' ? deleteCall : undefined}
          onSubmit={saveCall}
          submitting={submitting}
          title={editor.mode === 'edit' ? 'Edit Prospect Call' : 'Record Call'}
          updateForm={updateForm}
        />
      )}
    </main>
  );
}
