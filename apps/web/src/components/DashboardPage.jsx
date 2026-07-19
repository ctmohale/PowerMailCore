import { useEffect, useMemo, useState } from 'react';
import { apiGet } from '../api/client.js';

const PERMISSIONS = {
  sendEmails: 'send_emails',
  viewInbox: 'view_inbox',
  viewLogs: 'view_logs',
  manageTemplates: 'manage_templates',
  manageAccounts: 'manage_accounts',
};

function hasAccess(user, permission) {
  if (user?.role === 'admin') {
    return true;
  }

  if (Array.isArray(user?.permissions)) {
    return user.permissions.includes(permission);
  }

  return Boolean(user?.permissions?.[permission]);
}

function numberFormat(value) {
  return new Intl.NumberFormat().format(Number(value || 0));
}

function dateFormat(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date).replace(',', '');
}

function clipPercent(value) {
  return Math.max(0, Math.min(100, Number(value || 0)));
}

function chartGeometry(rows) {
  const plotLeft = 50;
  const plotRight = 720;
  const plotTop = 28;
  const plotBottom = 196;
  const chartWidth = plotRight - plotLeft;
  const plotHeight = plotBottom - plotTop;
  const maxTrend = Math.max(1, ...rows.flatMap((row) => [row.sent, row.received].map(Number)));
  const step = chartWidth / Math.max(1, rows.length - 1);
  const makePoints = (key) => rows.map((row, index) => ({
    x: Number((plotLeft + (index * step)).toFixed(2)),
    y: Number((plotBottom - ((Number(row[key] || 0) / maxTrend) * plotHeight)).toFixed(2)),
    value: Number(row[key] || 0),
    label: row.label,
  }));
  const makePath = (points) => points.map((point, index) => {
    if (index === 0) {
      return `M ${point.x} ${point.y}`;
    }

    const previous = points[index - 1];
    const controlOffset = step / 2;

    return `C ${(previous.x + controlOffset).toFixed(2)} ${previous.y} ${(point.x - controlOffset).toFixed(2)} ${point.y} ${point.x} ${point.y}`;
  }).join(' ');
  const sentPoints = makePoints('sent');
  const receivedPoints = makePoints('received');
  const sentPath = makePath(sentPoints);
  const lastPoint = sentPoints[sentPoints.length - 1] || { x: 0 };
  const firstPoint = sentPoints[0] || { x: 0 };
  const yTicks = Array.from({ length: 5 }, (_, index) => {
    const value = Math.round((maxTrend / 4) * (4 - index));
    const y = Number((plotTop + ((plotHeight / 4) * index)).toFixed(2));

    return { value, y };
  });

  return {
    plotLeft,
    plotRight,
    plotBottom,
    sentPoints,
    receivedPoints,
    sentPath,
    receivedPath: makePath(receivedPoints),
    sentArea: `${sentPath} L ${lastPoint.x} ${plotBottom} L ${firstPoint.x} ${plotBottom} Z`,
    xLabels: rows.map((row, index) => ({
      label: row.label,
      x: Number((plotLeft + (index * step)).toFixed(2)),
    })),
    yTicks,
  };
}

function MetricIcon({ type }) {
  const paths = {
    sent: (
      <>
        <path d="m22 2-7 20-4-9-9-4Z" />
        <path d="M22 2 11 13" />
      </>
    ),
    received: (
      <>
        <path d="M22 12h-6l-2 3h-4l-2-3H2" />
        <path d="m5.45 5.11-3.43 6.86A2 2 0 0 0 2 13v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-.02-1.03l-3.43-6.86A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
      </>
    ),
    clients: (
      <>
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
      </>
    ),
    issues: (
      <>
        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
        <path d="M12 9v4" />
        <path d="M12 17h.01" />
      </>
    ),
  };

  return (
    <span className="metric-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
        {paths[type]}
      </svg>
    </span>
  );
}

function StatusBadge({ status }) {
  return <span className={`badge ${status || 'draft'}`}>{status || '-'}</span>;
}

export function DashboardPage({ onNavigate, user }) {
  const [summary, setSummary] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    apiGet('/dashboard')
      .then((data) => {
        setSummary(data);
        setError('');
      })
      .catch((requestError) => setError(requestError.message));
  }, []);

  const rows = summary?.deliveryTrend || [];
  const geometry = useMemo(() => chartGeometry(rows.length ? rows : Array.from({ length: 7 }, (_, index) => ({
    label: `Day ${index + 1}`,
    sent: 0,
    failed: 0,
    received: 0,
  }))), [rows]);
  const counts = summary?.counts || {};
  const processed = Number(counts.sent || 0) + Number(counts.failed || 0);
  const canViewLogs = hasAccess(user, PERMISSIONS.viewLogs);
  const canSendEmails = hasAccess(user, PERMISSIONS.sendEmails);
  const canManageAccounts = hasAccess(user, PERMISSIONS.manageAccounts);
  const canManageTemplates = hasAccess(user, PERMISSIONS.manageTemplates);
  const canViewInbox = hasAccess(user, PERMISSIONS.viewInbox);
  const isAdmin = summary?.isAdmin ?? user?.role === 'admin';
  const quickActions = [
    canSendEmails && ['Send email', { type: 'compose' }],
    isAdmin && ['Add client', { type: 'resource', groupId: 'admin', id: 'clients' }],
    isAdmin && ['Add domain', { type: 'resource', groupId: 'admin', id: 'domains' }],
    isAdmin && ['Create API key', { type: 'resource', groupId: 'admin', id: 'api-keys' }],
    canManageTemplates && ['Create template', { type: 'resource', groupId: 'admin', id: 'templates' }],
    canManageAccounts && ['Add account', { type: 'resource', groupId: 'admin', id: 'accounts' }],
    canViewInbox && ['Open inbox', { type: 'resource', groupId: 'messaging', id: 'inbox' }],
  ].filter(Boolean);

  return (
    <main className="app-shell dashboard-command">
      <div className="page-header">
        <div className="page-title">
          <p className="eyebrow">PowerMail Workspace</p>
          <h1>My PowerMail</h1>
          <p className="lede">Your sending, inbox, delivery, and company activity in one place.</p>
        </div>
        <div className="actions">
          {canViewLogs && (
            <button className="button secondary" type="button" onClick={() => onNavigate({ type: 'logs' })}>
              View Logs
            </button>
          )}
          {canSendEmails ? (
            <button className="button" type="button" onClick={() => onNavigate({ type: 'compose' })}>
              Send Email
            </button>
          ) : canManageAccounts ? (
            <button className="button" type="button" onClick={() => onNavigate({ type: 'resource', groupId: 'admin', id: 'accounts' })}>
              Add Account
            </button>
          ) : null}
        </div>
      </div>

      {error && <div className="alert">{error}</div>}

      <section className="kpi-grid" aria-label="Metrics">
        <div className="metric" data-tone="blue">
          <div className="metric-top">
            <span className="metric-label">Sent</span>
            <MetricIcon type="sent" />
          </div>
          <strong className="metric-value">{summary ? numberFormat(counts.sent) : '-'}</strong>
          <div className="metric-footer">
            <span className="trend up">+{summary?.deliveryRate || 0}%</span>
            <span className="metric-hint">delivery rate</span>
          </div>
        </div>

        <div className="metric" data-tone="purple">
          <div className="metric-top">
            <span className="metric-label">Received</span>
            <MetricIcon type="received" />
          </div>
          <strong className="metric-value">{summary ? numberFormat(counts.received) : '-'}</strong>
          <div className="metric-footer">
            <span className="trend up">+{numberFormat(counts.inboxAccounts)}</span>
            <span className="metric-hint">inbox accounts</span>
          </div>
        </div>

        <div className="metric" data-tone="green">
          <div className="metric-top">
            <span className="metric-label">{isAdmin ? 'Clients' : 'Company'}</span>
            <MetricIcon type="clients" />
          </div>
          <strong className="metric-value">{summary ? numberFormat(counts.clients) : '-'}</strong>
          <div className="metric-footer">
            <span className="trend up">+{numberFormat(counts.domains)}</span>
            <span className="metric-hint">{isAdmin ? 'domains' : 'workspace'}</span>
          </div>
        </div>

        <div className="metric" data-tone={Number(counts.failed || 0) > 0 ? 'red' : 'amber'}>
          <div className="metric-top">
            <span className="metric-label">Issues</span>
            <MetricIcon type="issues" />
          </div>
          <strong className="metric-value">{summary ? numberFormat(Number(counts.failed || 0) + Number(counts.pending || 0)) : '-'}</strong>
          <div className="metric-footer">
            <span className={`trend ${Number(counts.failed || 0) > 0 ? 'down' : 'up'}`}>{summary?.failureRate || 0}%</span>
            <span className="metric-hint">failure rate</span>
          </div>
        </div>
      </section>

      <section className="split-grid">
        <div className="panel chart-panel analytics-card">
          <div className="panel-header analytics-card-header">
            <div>
              <h2>Delivery Analytics</h2>
              <p>Seven-day sent and received message trend.</p>
            </div>
            <button className="chart-filter-pill" type="button" aria-label="Chart period filter">
              All time
              <span aria-hidden="true">v</span>
            </button>
          </div>

          <div className="chart-wrap">
            <svg className="chart-svg analytics-chart-svg" viewBox="0 0 760 260" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Seven day delivery chart">
              <defs>
                <linearGradient id="sentLineReact" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stopColor="#8A8DFB" />
                  <stop offset="100%" stopColor="#5EA5FF" />
                </linearGradient>
                <linearGradient id="sentAreaReact" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#DDE8FF" stopOpacity="0.62" />
                  <stop offset="100%" stopColor="#F7FAFF" stopOpacity="0" />
                </linearGradient>
              </defs>
              {geometry.yTicks.map((tick) => (
                <g key={`tick-${tick.y}`}>
                  <line className="chart-grid" x1={geometry.plotLeft} y1={tick.y} x2={geometry.plotRight} y2={tick.y} />
                  <text className="chart-axis-label" x="20" y={tick.y + 4}>{tick.value}</text>
                </g>
              ))}
              <path className="chart-area" d={geometry.sentArea} fill="url(#sentAreaReact)" />
              <path className="chart-line chart-line-blue" d={geometry.sentPath} stroke="url(#sentLineReact)" />
              <path className="chart-line chart-line-orange" d={geometry.receivedPath} stroke="#F2A15F" />
              {geometry.sentPoints.map((point) => (
                <circle className="chart-dot chart-dot-blue" cx={point.x} cy={point.y} r="5.5" fill="#fff" stroke="#7B8EFA" strokeWidth="3" key={`sent-${point.label}`} />
              ))}
              {geometry.receivedPoints.map((point) => (
                <circle className="chart-dot chart-dot-orange" cx={point.x} cy={point.y} r="5.5" fill="#fff" stroke="#F2A15F" strokeWidth="3" key={`received-${point.label}`} />
              ))}
              {geometry.xLabels.map((tick) => (
                <text className="chart-axis-label chart-axis-label-x" x={tick.x} y="236" textAnchor="middle" key={`label-${tick.label}`}>
                  {tick.label}
                </text>
              ))}
            </svg>
          </div>

          <div className="chart-legend">
            <span className="legend-item"><span className="legend-sample legend-sample-blue" />Sent email</span>
            <span className="legend-item"><span className="legend-sample legend-sample-orange" />Received email</span>
          </div>
        </div>

        <aside className="panel">
          <div className="panel-header">
            <div>
              <h2>Delivery Health</h2>
              <p>Sending state.</p>
            </div>
            <span className={`badge ${Number(counts.failed || 0) > 0 ? 'pending' : 'active'}`}>
              {Number(counts.failed || 0) > 0 ? 'Review' : 'Healthy'}
            </span>
          </div>

          <div className="summary-list">
            <div className="summary-item">
              <div>
                <strong>Delivered mail</strong>
                <div className="muted">{numberFormat(counts.sent)} sent from {numberFormat(processed)} processed</div>
              </div>
              <strong>{summary?.deliveryRate || 0}%</strong>
            </div>
            <div className="bar" aria-hidden="true"><span style={{ width: `${clipPercent(summary?.deliveryRate)}%` }} /></div>

            <div className="summary-item">
              <div>
                <strong>Active SMTP accounts</strong>
                <div className="muted">{numberFormat(counts.activeAccounts)} active from {numberFormat(counts.accounts)} accounts</div>
              </div>
              <strong>{summary?.accountCoverage || 0}%</strong>
            </div>
            <div className="bar" aria-hidden="true"><span style={{ width: `${clipPercent(summary?.accountCoverage)}%` }} /></div>

            <div className="summary-item">
              <div>
                <strong>Active templates</strong>
                <div className="muted">{numberFormat(counts.activeTemplates)} active from {numberFormat(counts.templates)} templates</div>
              </div>
              <strong>{summary?.templateCoverage || 0}%</strong>
            </div>
            <div className="bar" aria-hidden="true"><span style={{ width: `${clipPercent(summary?.templateCoverage)}%` }} /></div>
          </div>
        </aside>
      </section>

      <section className="split-grid">
        {canViewLogs && (
          <div className="panel">
            <div className="panel-header">
              <div>
                <h2>Recent Logs</h2>
                <p>Latest send attempts.</p>
              </div>
              <button className="button secondary" type="button" onClick={() => onNavigate({ type: 'logs' })}>All Logs</button>
            </div>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Status</th>
                    <th>Client</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Subject</th>
                    <th>Time</th>
                  </tr>
                </thead>
                <tbody>
                  {(summary?.recentLogs || []).length ? summary.recentLogs.map((log) => (
                    <tr key={log.id}>
                      <td><StatusBadge status={log.status} /></td>
                      <td>{log.clientName || '-'}</td>
                      <td>{log.fromEmail || '-'}</td>
                      <td>{log.toEmail || '-'}</td>
                      <td className="wrap">{log.subject || '-'}</td>
                      <td>{dateFormat(log.createdAt)}</td>
                    </tr>
                  )) : (
                    <tr>
                      <td colSpan="6" className="muted">No email logs yet.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        <aside className="panel">
          <div className="panel-header">
            <div>
              <h2>Quick Actions</h2>
              <p>Shortcuts.</p>
            </div>
          </div>
          <div className="quick-actions">
            {quickActions.map(([label, target]) => (
              <button className="quick-link" type="button" onClick={() => onNavigate(target)} key={label}>
                <span>{label}</span>
                <span>Open</span>
              </button>
            ))}
          </div>
        </aside>
      </section>

      {canViewInbox && (
        <section className="panel">
          <div className="panel-header">
            <div>
              <h2>Recent Inbox</h2>
              <p>Newest synced messages.</p>
            </div>
            <button className="button secondary" type="button" onClick={() => onNavigate({ type: 'resource', groupId: 'messaging', id: 'inbox' })}>Open Inbox</button>
          </div>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Client</th>
                  <th>Inbox</th>
                  <th>From</th>
                  <th>Subject</th>
                </tr>
              </thead>
              <tbody>
                {(summary?.recentReceived || []).length ? summary.recentReceived.map((message) => (
                  <tr key={message.id}>
                    <td>{dateFormat(message.receivedAt)}</td>
                    <td>{message.clientName || '-'}</td>
                    <td>{message.accountEmail || '-'}</td>
                    <td className="wrap">{message.fromEmail || '-'}</td>
                    <td className="wrap">{message.subject || '(no subject)'}</td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan="5" className="muted">No received emails yet.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </main>
  );
}
