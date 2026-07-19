import { useCallback, useEffect, useState } from 'react';
import { apiGet } from '../api/client.js';

export function UnreadInboxButton({ icon, onOpen }) {
  const [count, setCount] = useState(0);

  const refresh = useCallback(() => {
    apiGet('/inbox/unread-count')
      .then((response) => setCount(Math.max(0, Number(response.count || 0))))
      .catch(() => {});
  }, []);

  useEffect(() => {
    refresh();
    const timer = window.setInterval(refresh, 30000);
    window.addEventListener('powermail:inbox-changed', refresh);

    return () => {
      window.clearInterval(timer);
      window.removeEventListener('powermail:inbox-changed', refresh);
    };
  }, [refresh]);

  const label = count === 1 ? '1 unread email' : `${count} unread emails`;

  return (
    <button className="icon-button notification-bell" type="button" aria-label={label} title={label} onClick={onOpen}>
      {icon}
      {count > 0 && <span className="notification-count" aria-hidden="true">{count > 99 ? '99+' : count}</span>}
    </button>
  );
}
