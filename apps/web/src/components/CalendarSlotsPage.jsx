import { useCallback, useEffect, useMemo, useState } from 'react';
import { Calendar, dateFnsLocalizer } from 'react-big-calendar';
import { format, getDay, parse, startOfWeek as dateFnsStartOfWeek } from 'date-fns';
import { enZA } from 'date-fns/locale';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { apiDelete, apiGet, apiPatch, apiPost } from '../api/client.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { useUiFeedback } from '../context/UiFeedbackContext.jsx';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';

const localizer = dateFnsLocalizer({
  format,
  getDay,
  locales: { 'en-ZA': enZA },
  parse,
  startOfWeek: (date) => dateFnsStartOfWeek(date, { weekStartsOn: 1 }),
});

function asDate(value) {
  const date = value instanceof Date ? value : new Date(String(value || '').replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? new Date() : date;
}

function inputDateTime(value) {
  const date = asDate(value);
  const local = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
  return local.toISOString().slice(0, 16);
}

function defaultSlot() {
  const start = new Date();
  start.setMinutes(0, 0, 0);
  start.setHours(start.getHours() + 1);
  const end = new Date(start.getTime() + (30 * 60000));
  return { client_id: '', title: 'Discovery Call', starts_at: inputDateTime(start), ends_at: inputDateTime(end), status: 'available', location: '' };
}

async function loadAllSlots(params) {
  const first = await apiGet('/marketing/booking-slots', { ...params, page: 1, per_page: 100 });

  if (first.meta.lastPage <= 1) return first.data;

  const remaining = await Promise.all(
    Array.from({ length: first.meta.lastPage - 1 }, (_, index) => (
      apiGet('/marketing/booking-slots', { ...params, page: index + 2, per_page: 100 })
    )),
  );

  return [first, ...remaining].flatMap((page) => page.data);
}

export function CalendarSlotsPage() {
  const { options } = useAppData();
  const { confirmAction } = useUiFeedback();
  const [query, setQuery] = useState('');
  const [clientId, setClientId] = useState('');
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editor, setEditor] = useState(null);
  const [form, setForm] = useState(defaultSlot);
  const [submitting, setSubmitting] = useState(false);
  const params = useMemo(() => ({ q: query, client_id: clientId }), [clientId, query]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setRows(await loadAllSlots(params));
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [params]);

  useEffect(() => {
    load();
  }, [load]);

  const events = useMemo(() => rows.map((row) => ({
    id: row.id,
    title: row.bookedBy ? `${row.title} - ${row.bookedBy}` : row.title,
    start: asDate(row.startsAt),
    end: asDate(row.endsAt),
    resource: row,
  })), [rows]);

  function openCreate(selection) {
    const next = defaultSlot();
    const start = selection?.start || next.starts_at;
    const end = selection?.end || new Date(asDate(start).getTime() + (30 * 60000));
    setForm({
      ...next,
      client_id: clientId || (options.clients.length === 1 ? options.clients[0].id : ''),
      starts_at: inputDateTime(start),
      ends_at: inputDateTime(end),
    });
    setEditor({ mode: 'create' });
    setError('');
  }

  function openEvent(event) {
    const row = event.resource;
    setForm({
      client_id: row.clientId || '',
      title: row.title || 'Discovery Call',
      starts_at: inputDateTime(row.startsAt),
      ends_at: inputDateTime(row.endsAt),
      status: row.status || 'available',
      location: row.location || '',
    });
    setEditor({ mode: 'edit', row });
    setError('');
  }

  function updateForm(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function saveSlot(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      if (asDate(form.ends_at) <= asDate(form.starts_at)) {
        throw new Error('End time must be after the start time.');
      }

      if (editor.mode === 'edit') {
        await apiPatch(`/marketing/booking-slots/${editor.row.id}`, form);
        setNotice('Calendar slot updated.');
      } else {
        await apiPost('/marketing/booking-slots', form);
        setNotice('Calendar slot added.');
      }

      setEditor(null);
      await load();
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function deleteSlot() {
    const row = editor?.row;
    if (!row || row.bookedBy || !await confirmAction({
      title: 'Delete calendar slot?',
      message: `${row.title} on ${format(asDate(row.startsAt), 'dd MMM yyyy, HH:mm')} will be removed.`,
      confirmLabel: 'Delete Slot',
      tone: 'danger',
    })) return;

    setSubmitting(true);
    setError('');
    try {
      await apiDelete(`/marketing/booking-slots/${row.id}`);
      setEditor(null);
      setNotice('Calendar slot deleted.');
      await load();
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="app-shell calendar-slots-page">
      <header className="app-header">
        <div>
          <p className="eyebrow">Marketing</p>
          <h1>Calendar Slots</h1>
        </div>
      </header>

      <ResourceStatsGrid resourceId="booking-slots" params={params} refreshKey={rows} />

      <section className="toolbar command-toolbar calendar-command-toolbar">
        <label className="search-field command-search">
          <span className="sr-only">Search calendar</span>
          <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search meeting, attendee, company, or location" />
        </label>
        <label className="command-filter">
          <span className="sr-only">Company</span>
          <select value={clientId} onChange={(event) => setClientId(event.target.value)} aria-label="Company">
            <option value="">All companies</option>
            {options.clients.map((client) => <option value={client.id} key={client.id}>{client.name}</option>)}
          </select>
        </label>
        <button type="button" className="primary-button" onClick={() => openCreate()}>Add Slot</button>
        <button type="button" className="secondary-button" onClick={() => { setQuery(''); setClientId(''); }}>Reset</button>
      </section>

      {error && !editor && <div className="alert">{error}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      <section className="calendar-workspace" aria-busy={loading}>
        {loading && <div className="calendar-loading"><span className="button-spinner" /> Loading calendar...</div>}
        <Calendar
          culture="en-ZA"
          localizer={localizer}
          events={events}
          startAccessor="start"
          endAccessor="end"
          selectable
          popup
          views={['month', 'week', 'day', 'agenda']}
          defaultView="month"
          onSelectSlot={openCreate}
          onSelectEvent={openEvent}
          eventPropGetter={(event) => ({ className: `calendar-event is-${event.resource.bookedBy ? 'booked' : event.resource.status}` })}
          messages={{ showMore: (total) => `+${total} more appointments` }}
        />
      </section>

      {editor && (
        <section className="modal-backdrop">
          <form className="modal-panel calendar-slot-modal" onSubmit={saveSlot}>
            <div className="modal-head">
              <div>
                <p className="eyebrow">Calendar</p>
                <h2>{editor.mode === 'edit' ? 'Appointment Details' : 'Add Slot'}</h2>
                {editor.row?.bookedBy && <span>Booked by {editor.row.bookedBy}</span>}
              </div>
              <button type="button" className="secondary-button" onClick={() => setEditor(null)}>Close</button>
            </div>

            {error && <div className="alert">{error}</div>}

            <div className="calendar-slot-form">
              {options.clients.length > 1 && (
                <label className="form-span">
                  <span>Company</span>
                  <select value={form.client_id} onChange={(event) => updateForm('client_id', event.target.value)} required>
                    <option value="">Choose company</option>
                    {options.clients.map((client) => <option value={client.id} key={client.id}>{client.name}</option>)}
                  </select>
                </label>
              )}
              <label className="form-span">
                <span>Meeting</span>
                <input value={form.title} onChange={(event) => updateForm('title', event.target.value)} required />
              </label>
              <label>
                <span>Starts</span>
                <input type="datetime-local" value={form.starts_at} onChange={(event) => updateForm('starts_at', event.target.value)} required />
              </label>
              <label>
                <span>Ends</span>
                <input type="datetime-local" value={form.ends_at} onChange={(event) => updateForm('ends_at', event.target.value)} required />
              </label>
              <label>
                <span>Status</span>
                <select value={form.status} onChange={(event) => updateForm('status', event.target.value)} disabled={Boolean(editor.row?.bookedBy)}>
                  {editor.row?.bookedBy && <option value="booked">Booked</option>}
                  <option value="available">Available</option>
                  <option value="blocked">Blocked</option>
                </select>
              </label>
              <label>
                <span>Location</span>
                <input value={form.location} onChange={(event) => updateForm('location', event.target.value)} placeholder="Online or physical location" />
              </label>
            </div>

            <div className="modal-actions calendar-modal-actions">
              {editor.mode === 'edit' && !editor.row.bookedBy && <button type="button" className="danger-button" onClick={deleteSlot} disabled={submitting}>Delete</button>}
              <span className="modal-action-spacer" />
              <button type="button" className="secondary-button" onClick={() => setEditor(null)} disabled={submitting}>Cancel</button>
              <button type="submit" className="primary-button" disabled={submitting}>
                {submitting && <span className="button-spinner" />}
                {editor.mode === 'edit' ? 'Save Changes' : 'Add Slot'}
              </button>
            </div>
          </form>
        </section>
      )}
    </main>
  );
}
