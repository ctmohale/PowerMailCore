import { useEffect, useMemo, useState } from 'react';
import { apiGet } from '../api/client.js';

function routeParts() {
  const [, , , contactId, token] = window.location.pathname.split('/');

  return { contactId, token };
}

export function PublicUnsubscribePage() {
  const { contactId, token } = useMemo(routeParts, []);
  const [contact, setContact] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    setError('');

    apiGet(`/public/email-tracking/unsubscribe/${contactId}/${token}`)
      .then((response) => setContact(response.contact))
      .catch((requestError) => setError(requestError.message))
      .finally(() => setLoading(false));
  }, [contactId, token]);

  if (loading) {
    return <main className="public-booking-shell"><section className="public-booking-card">Updating subscription...</section></main>;
  }

  if (error) {
    return <main className="public-booking-shell"><section className="public-booking-card"><div className="alert">{error}</div></section></main>;
  }

  return (
    <main className="public-booking-shell">
      <section className="public-booking-card public-confirmation">
        <div className="confirmation-mark">OK</div>
        <h1>You are unsubscribed</h1>
        <p><strong>{contact.email}</strong> will no longer receive marketing emails.</p>
      </section>
    </main>
  );
}
