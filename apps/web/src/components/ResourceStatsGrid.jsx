import { useEffect, useState } from 'react';
import { apiGet } from '../api/client.js';

const emptyParams = {};

const metricSets = {
  logs: [['total', 'Total Emails'], ['sent', 'Sent'], ['pending', 'Pending'], ['failed', 'Failed']],
  contacts: [['total', 'Total Contacts'], ['subscribed', 'Subscribed'], ['unsubscribed', 'Unsubscribed'], ['bounced', 'Bounced']],
  clients: [['total', 'Total Clients'], ['active', 'Active'], ['withContact', 'With Contact'], ['inactive', 'Inactive']],
  domains: [['total', 'Total Domains'], ['active', 'Active'], ['pending', 'Pending'], ['companies', 'Companies']],
  accounts: [['total', 'Total Accounts'], ['active', 'Active'], ['inbox', 'Inbox Enabled'], ['inactive', 'Inactive']],
  templates: [['total', 'Total Templates'], ['active', 'Active'], ['marketing', 'Marketing'], ['communication', 'Communication']],
  'api-keys': [['total', 'Total API Keys'], ['active', 'Active'], ['used', 'Used'], ['inactive', 'Inactive']],
  users: [['total', 'Total Users'], ['active', 'Active'], ['admins', 'Administrators'], ['suspended', 'Suspended']],
  inbox: [['total', 'Total Messages'], ['unread', 'Unread'], ['opened', 'Opened'], ['accounts', 'Inbox Accounts']],
  audiences: [['total', 'Audiences'], ['contacts', 'Contacts'], ['populated', 'With Contacts'], ['campaigns', 'Campaigns']],
  campaigns: [['total', 'Campaigns'], ['recipients', 'Recipients'], ['sent', 'Emails Sent'], ['failed', 'Failed']],
  'lead-runs': [['total', 'Lead Runs'], ['discovered', 'Leads Found'], ['imported', 'Imported'], ['completed', 'Completed']],
  'prospect-calls': [['total', 'Total Calls'], ['followUp', 'Follow Ups'], ['meetings', 'Meetings'], ['won', 'Won']],
  'booking-slots': [['total', 'Calendar Slots'], ['available', 'Available'], ['booked', 'Booked'], ['blocked', 'Blocked']],
  'booking-appointments': [['total', 'Bookings'], ['booked', 'Confirmed'], ['completed', 'Completed'], ['cancelled', 'Cancelled']],
};

const hints = {
  total: 'all records',
  active: 'currently enabled',
  sent: 'successfully delivered',
  pending: 'awaiting action',
  failed: 'needs attention',
  subscribed: 'ready to contact',
  unsubscribed: 'opted out',
  bounced: 'delivery blocked',
  inactive: 'currently disabled',
  inbox: 'receiving mail',
  used: 'used at least once',
  admins: 'admin access',
  contacts: 'marketing contacts',
  campaigns: 'marketing campaigns',
  marketing: 'campaign templates',
  communication: 'direct email templates',
  draft: 'being prepared',
  completed: 'successfully finished',
  running: 'currently processing',
  followUp: 'follow-up required',
  meetings: 'meeting booked',
  won: 'converted prospects',
  available: 'open for booking',
  booked: 'reserved slots',
  blocked: 'not available',
  cancelled: 'cancelled bookings',
  domains: 'configured domains',
  accounts: 'linked senders',
  withContact: 'contact email added',
  companies: 'companies represented',
  unread: 'needs attention',
  opened: 'viewed messages',
  populated: 'audiences with contacts',
  recipients: 'campaign recipients',
  discovered: 'discovered leads',
  imported: 'contacts imported',
};

function MetricIcon({ index }) {
  const paths = [
    <><rect x="4" y="4" width="16" height="16" rx="3" /><path d="M8 9h8M8 13h8M8 17h5" /></>,
    <><path d="m5 12 4 4L19 6" /><circle cx="12" cy="12" r="9" /></>,
    <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    <><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></>,
  ];

  return (
    <span className="metric-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {paths[index]}
      </svg>
    </span>
  );
}

export function ResourceStatsGrid({ resourceId, params = emptyParams, refreshKey }) {
  const [stats, setStats] = useState(null);
  const metrics = metricSets[resourceId];

  useEffect(() => {
    if (!metrics) return;

    apiGet(`/resource-stats/${resourceId}`, params)
      .then(setStats)
      .catch(() => setStats({}));
  }, [metrics, params, refreshKey, resourceId]);

  if (!metrics) return null;

  return (
    <section className="kpi-grid resource-kpi-grid" aria-label={`${resourceId} metrics`}>
      {metrics.map(([key, label], index) => (
        <div className="metric" data-tone={['blue', 'green', 'purple', 'amber'][index]} key={key}>
          <div className="metric-top">
            <span className="metric-label">{label}</span>
            <MetricIcon index={index} />
          </div>
          <strong className="metric-value">{stats ? Number(stats[key] || 0).toLocaleString() : '-'}</strong>
          <div className="metric-footer">
            <span className={`trend ${index === 3 ? 'down' : 'up'}`}>{index === 0 ? 'Total' : label}</span>
            <span className="metric-hint">{hints[key] || 'current total'}</span>
          </div>
        </div>
      ))}
    </section>
  );
}
