import { useCallback, useEffect, useMemo, useState } from 'react';
import { Calendar, dateFnsLocalizer } from 'react-big-calendar';
import { format, getDay, parse, startOfWeek as dateFnsStartOfWeek } from 'date-fns';
import { enZA } from 'date-fns/locale';
import { apiGet } from '../api/client.js';
import { useAppData } from '../context/AppDataContext.jsx';
import { ResourceStatsGrid } from './ResourceStatsGrid.jsx';

const localizer = dateFnsLocalizer({
  format,
  getDay,
  locales: { 'en-ZA': enZA },
  parse,
  startOfWeek: (date) => dateFnsStartOfWeek(date, { weekStartsOn: 1 }),
});

function asDate(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? new Date() : date;
}

async function loadAllBookings(params) {
  const first = await apiGet('/marketing/booking-appointments', { ...params, page: 1, per_page: 100 });
  if (first.meta.lastPage <= 1) return first.data;

  const remaining = await Promise.all(
    Array.from({ length: first.meta.lastPage - 1 }, (_, index) => (
      apiGet('/marketing/booking-appointments', { ...params, page: index + 2, per_page: 100 })
    )),
  );
  return [first, ...remaining].flatMap((page) => page.data);
}

export function BookingsPage() {
  const { options } = useAppData();
  const [query, setQuery] = useState('');
  const [clientId, setClientId] = useState('');
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [selected, setSelected] = useState(null);
  const params = useMemo(() => ({ q: query, client_id: clientId }), [clientId, query]);
  const shareClient = options.clients.find((client) => String(client.id) === String(clientId))
    || (options.clients.length === 1 ? options.clients[0] : null);
  const bookingUrl = shareClient?.slug ? `${window.location.origin}/book/${shareClient.slug}` : '';

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setRows(await loadAllBookings(params));
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
    title: `${row.name} - ${row.title}`,
    start: asDate(row.startsAt),
    end: asDate(row.endsAt || row.startsAt),
    resource: row,
  })), [rows]);

  async function copyBookingLink() {
    if (!bookingUrl) return;
    try {
      await navigator.clipboard.writeText(bookingUrl);
      setNotice(`Booking link copied for ${shareClient.name}.`);
      setError('');
    } catch {
      setError('The browser could not copy the link. Select the link and copy it manually.');
    }
  }

  async function shareBookingLink() {
    if (!bookingUrl) return;
    if (navigator.share) {
      try {
        await navigator.share({ title: `Book a meeting with ${shareClient.name}`, url: bookingUrl });
        return;
      } catch (shareError) {
        if (shareError.name === 'AbortError') return;
      }
    }
    await copyBookingLink();
  }

  return (
    <main className="app-shell bookings-page">
      <header className="app-header">
        <div>
          <p className="eyebrow">Marketing</p>
          <h1>Bookings</h1>
        </div>
      </header>

      <ResourceStatsGrid resourceId="booking-appointments" params={params} refreshKey={rows} />

      <section className="booking-share-bar">
        <div className="booking-share-copy">
          <span className="booking-share-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="18" cy="5" r="3" />
              <circle cx="6" cy="12" r="3" />
              <circle cx="18" cy="19" r="3" />
              <path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4" />
            </svg>
          </span>
          <div>
            <strong>Client booking link</strong>
            <span>Share this link so clients can choose an available time.</span>
          </div>
        </div>
        <select value={clientId} onChange={(event) => setClientId(event.target.value)} aria-label="Company for booking link">
          <option value="">{options.clients.length > 1 ? 'Choose company' : 'Company'}</option>
          {options.clients.map((client) => <option value={client.id} key={client.id}>{client.name}</option>)}
        </select>
        <input value={bookingUrl} readOnly placeholder="Choose a company to create its booking link" aria-label="Public booking link" />
        <button type="button" className="secondary-button" disabled={!bookingUrl} onClick={copyBookingLink}>Copy</button>
        <button type="button" className="primary-button" disabled={!bookingUrl} onClick={shareBookingLink}>Share</button>
        {bookingUrl && <a className="secondary-button booking-open-link" href={bookingUrl} target="_blank" rel="noreferrer">Open</a>}
      </section>

      <section className="toolbar command-toolbar calendar-command-toolbar booking-filter-toolbar">
        <label className="search-field command-search">
          <span className="sr-only">Search bookings</span>
          <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search attendee, email, phone, company, meeting, or status" />
        </label>
        <button type="button" className="secondary-button" onClick={() => setQuery('')}>Reset Search</button>
      </section>

      {error && <div className="alert">{error}</div>}
      {notice && <div className="notice-panel">{notice}</div>}

      <section className="calendar-workspace bookings-calendar-workspace" aria-busy={loading}>
        {loading && <div className="calendar-loading"><span className="button-spinner" /> Loading bookings...</div>}
        <Calendar
          culture="en-ZA"
          localizer={localizer}
          events={events}
          startAccessor="start"
          endAccessor="end"
          popup
          views={['month', 'week', 'day', 'agenda']}
          defaultView="month"
          onSelectEvent={(event) => setSelected(event.resource)}
          eventPropGetter={(event) => ({ className: `calendar-event status-${event.resource.status}` })}
          messages={{ showMore: (total) => `+${total} more bookings` }}
        />
      </section>

      {selected && (
        <section className="modal-backdrop">
          <div className="modal-panel booking-detail-modal">
            <div className="modal-head">
              <div>
                <p className="eyebrow">{selected.clientName}</p>
                <h2>{selected.title}</h2>
                <span>{format(asDate(selected.startsAt), 'EEEE, dd MMMM yyyy, HH:mm')}</span>
              </div>
              <button type="button" className="secondary-button" onClick={() => setSelected(null)}>Close</button>
            </div>
            <div className="detail-grid">
              <span>Attendee</span><strong>{selected.name}</strong>
              <span>Email</span><strong>{selected.email}</strong>
              <span>Phone</span><strong>{selected.phone || '-'}</strong>
              <span>Company</span><strong>{selected.company || '-'}</strong>
              <span>Status</span><strong>{selected.status}</strong>
              <span>Location</span><strong>{selected.location || '-'}</strong>
            </div>
          </div>
        </section>
      )}
    </main>
  );
}
