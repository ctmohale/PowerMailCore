import { useEffect, useMemo, useState } from 'react';
import { apiGet, apiPost } from '../api/client.js';

function routeParts() {
  const [, , slug, confirmed, appointmentId] = window.location.pathname.split('/');

  return {
    slug,
    appointmentId: confirmed === 'confirmed' ? appointmentId : '',
  };
}

function formatSlot(value) {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('en-ZA', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value.replace(' ', 'T')));
}

export function PublicBookingPage() {
  const { slug, appointmentId } = useMemo(routeParts, []);
  const [page, setPage] = useState({ client: null, slots: [] });
  const [confirmation, setConfirmation] = useState(null);
  const [selectedSlot, setSelectedSlot] = useState(null);
  const [form, setForm] = useState({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    setError('');

    const request = appointmentId
      ? apiGet(`/public/book/${slug}/confirmed/${appointmentId}`)
      : apiGet(`/public/book/${slug}`);

    request
      .then((response) => {
        if (appointmentId) {
          setConfirmation(response);
        } else {
          setPage(response);
        }
      })
      .catch((requestError) => setError(requestError.message))
      .finally(() => setLoading(false));
  }, [appointmentId, slug]);

  function updateForm(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function submitBooking(event) {
    event.preventDefault();

    if (!selectedSlot) {
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiPost(`/public/book/${slug}`, {
        ...form,
        booking_availability_id: selectedSlot.id,
      });
      setConfirmation(response);
      window.history.replaceState(null, '', `/book/${slug}/confirmed/${response.appointment.id}`);
      setSelectedSlot(null);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return <main className="public-booking-shell"><section className="public-booking-card">Loading booking page...</section></main>;
  }

  if (error && !page.client && !confirmation) {
    return <main className="public-booking-shell"><section className="public-booking-card"><div className="alert">{error}</div></section></main>;
  }

  if (confirmation) {
    const { client, appointment } = confirmation;

    return (
      <main className="public-booking-shell">
        <section className="public-booking-card public-confirmation">
          <div className="confirmation-mark">OK</div>
          <p className="eyebrow">{client.name}</p>
          <h1>Meeting booked</h1>
          <p>
            Thanks, <strong>{appointment.name}</strong>. Your meeting is set for{' '}
            <strong>{formatSlot(appointment.slot.startsAt)}</strong>.
          </p>
          {appointment.slot.location && <p>{appointment.slot.location}</p>}
        </section>
      </main>
    );
  }

  return (
    <main className="public-booking-shell">
      <section className="public-booking-card">
        <header className="public-booking-head">
          <p className="eyebrow">{page.client?.name}</p>
          <h1>Book a meeting</h1>
        </header>

        {error && <div className="alert">{error}</div>}

        <div className="public-slot-grid">
          {page.slots.length === 0 ? (
            <div className="empty-state">No available meeting times are open right now.</div>
          ) : page.slots.map((slot) => (
            <button className="slot-button" type="button" key={slot.id} onClick={() => setSelectedSlot(slot)}>
              <strong>{formatSlot(slot.startsAt)}</strong>
              <span>{slot.title} · {slot.durationMinutes} min{slot.location ? ` · ${slot.location}` : ''}</span>
            </button>
          ))}
        </div>
      </section>

      {selectedSlot && (
        <section className="modal-backdrop">
          <form className="modal-panel" onSubmit={submitBooking}>
            <div className="modal-head">
              <div>
                <p className="eyebrow">{formatSlot(selectedSlot.startsAt)}</p>
                <h2>{selectedSlot.title}</h2>
              </div>
              <button type="button" className="secondary-button" onClick={() => setSelectedSlot(null)}>Close</button>
            </div>

            <div className="form-grid-stage">
              <label>
                <span>Name</span>
                <input value={form.name || ''} onChange={(event) => updateForm('name', event.target.value)} required />
              </label>
              <label>
                <span>Email</span>
                <input type="email" value={form.email || ''} onChange={(event) => updateForm('email', event.target.value)} required />
              </label>
              <label>
                <span>Phone</span>
                <input value={form.phone || ''} onChange={(event) => updateForm('phone', event.target.value)} />
              </label>
              <label>
                <span>Company</span>
                <input value={form.company || ''} onChange={(event) => updateForm('company', event.target.value)} />
              </label>
              <label className="form-span">
                <span>Notes</span>
                <textarea value={form.notes || ''} onChange={(event) => updateForm('notes', event.target.value)} />
              </label>
            </div>

            <div className="modal-actions">
              <button type="button" className="secondary-button" onClick={() => setSelectedSlot(null)}>Cancel</button>
              <button type="submit" className="primary-button" disabled={submitting}>
                {submitting ? 'Booking...' : 'Book Meeting'}
              </button>
            </div>
          </form>
        </section>
      )}
    </main>
  );
}
