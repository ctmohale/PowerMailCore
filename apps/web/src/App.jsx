import { useMemo, useState } from 'react';
import { ComposePage } from './components/ComposePage.jsx';
import { DashboardPage } from './components/DashboardPage.jsx';
import { EmailLogsPage } from './components/EmailLogsPage.jsx';
import { EmailTemplatesPage } from './components/EmailTemplatesPage.jsx';
import { LoginPage } from './components/LoginPage.jsx';
import { MarketingContactsPage } from './components/MarketingContactsPage.jsx';
import { PublicBookingPage } from './components/PublicBookingPage.jsx';
import { PublicUnsubscribePage } from './components/PublicUnsubscribePage.jsx';
import { ResourcePage } from './components/ResourcePage.jsx';
import { UnreadInboxButton } from './components/UnreadInboxButton.jsx';
import { CalendarSlotsPage } from './components/CalendarSlotsPage.jsx';
import { ProspectCallsPage } from './components/ProspectCallsPage.jsx';
import { BookingsPage } from './components/BookingsPage.jsx';
import { AppDataProvider } from './context/AppDataContext.jsx';
import { useAuth } from './context/AuthContext.jsx';
import { resourceGroups } from './config/resources.js';

const PERMISSIONS = {
  sendEmails: 'send_emails',
  viewInbox: 'view_inbox',
  viewLogs: 'view_logs',
  manageTemplates: 'manage_templates',
  manageAccounts: 'manage_accounts',
  manageMarketing: 'manage_marketing',
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

function Icon({ name }) {
  const paths = {
    dashboard: (
      <>
        <path d="M3 13h8V3H3v10Z" />
        <path d="M13 21h8V11h-8v10Z" />
        <path d="M13 3v6h8V3h-8Z" />
        <path d="M3 21h8v-6H3v6Z" />
      </>
    ),
    send: (
      <>
        <path d="m22 2-7 20-4-9-9-4Z" />
        <path d="M22 2 11 13" />
      </>
    ),
    inbox: (
      <>
        <path d="M22 12h-6l-2 3h-4l-2-3H2" />
        <path d="m5.45 5.11-3.43 6.86A2 2 0 0 0 2 13v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-.02-1.03l-3.43-6.86A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
      </>
    ),
    logs: (
      <>
        <path d="M9 5h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
        <path d="M11 3h6v4h-6z" />
        <path d="M11 12h6" />
        <path d="M11 16h4" />
        <path d="M3 9v8" />
      </>
    ),
    marketing: (
      <>
        <path d="M3 11h4l10-5v12L7 13H3z" />
        <path d="M7 13v5a2 2 0 0 0 2 2h1" />
        <path d="M21 9v6" />
      </>
    ),
    contacts: (
      <>
        <rect x="3" y="4" width="18" height="16" rx="2" />
        <circle cx="9" cy="10" r="2.5" />
        <path d="M5.5 17a3.5 3.5 0 0 1 7 0" />
        <path d="M15 9h3" />
        <path d="M15 13h3" />
      </>
    ),
    audiences: (
      <>
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
      </>
    ),
    campaigns: (
      <>
        <circle cx="12" cy="12" r="9" />
        <circle cx="12" cy="12" r="5" />
        <circle cx="12" cy="12" r="1" />
        <path d="m15 9 5-5" />
        <path d="M16 4h4v4" />
      </>
    ),
    leads: (
      <>
        <path d="M4 4h16l-6 7v5l-4 2v-7Z" />
        <path d="m17 17 1.3 1.3L21 15.6" />
      </>
    ),
    calls: (
      <>
        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.96.35 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.69A2 2 0 0 1 22 16.92Z" />
      </>
    ),
    calendar: (
      <>
        <rect x="3" y="4" width="18" height="17" rx="2" />
        <path d="M16 2v4" />
        <path d="M8 2v4" />
        <path d="M3 9h18" />
        <circle cx="12" cy="15" r="3" />
        <path d="M12 13.5V15l1 1" />
      </>
    ),
    bookings: (
      <>
        <rect x="3" y="4" width="18" height="17" rx="2" />
        <path d="M16 2v4" />
        <path d="M8 2v4" />
        <path d="M3 9h18" />
        <path d="m8 15 2 2 5-5" />
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
    users: (
      <>
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
        <path d="M18 8h4" />
        <path d="M20 6v4" />
      </>
    ),
    domains: (
      <>
        <circle cx="12" cy="12" r="10" />
        <path d="M2 12h20" />
        <path d="M12 2a15.3 15.3 0 0 1 0 20" />
        <path d="M12 2a15.3 15.3 0 0 0 0 20" />
      </>
    ),
    accounts: (
      <>
        <path d="M4 4h16v16H4z" />
        <path d="m22 6-10 7L2 6" />
      </>
    ),
    templates: (
      <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <path d="M14 2v6h6" />
        <path d="M16 13H8" />
        <path d="M16 17H8" />
        <path d="M10 9H8" />
      </>
    ),
    keys: (
      <>
        <circle cx="7.5" cy="15.5" r="5.5" />
        <path d="m21 2-9.6 9.6" />
        <path d="m15 2 6 6" />
        <path d="m18 5 3 3" />
      </>
    ),
    bell: (
      <>
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </>
    ),
    search: (
      <>
        <circle cx="11" cy="11" r="8" />
        <path d="m21 21-4.35-4.35" />
      </>
    ),
    chevron: <path d="m6 9 6 6 6-6" />,
    collapse: <path d="M15 18l-6-6 6-6" />,
    help: (
      <>
        <circle cx="12" cy="12" r="10" />
        <path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4" />
        <path d="M12 18h.01" />
      </>
    ),
    settings: (
      <>
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.37a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.08 14H3v-4h.08A1.7 1.7 0 0 0 4.63 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63h.01A1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15 4.63a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9v.01A1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z" />
      </>
    ),
    menu: (
      <>
        <path d="M4 6h16" />
        <path d="M7 12h13" />
        <path d="M10 18h10" />
      </>
    ),
  };

  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      {paths[name] || paths.dashboard}
    </svg>
  );
}

export default function App() {
  const [page, setPage] = useState({ type: 'dashboard' });
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => window.localStorage.getItem('powermail.sidebar.collapsed') === 'true');
  const [collapsedSections, setCollapsedSections] = useState(() => {
    try {
      return JSON.parse(window.localStorage.getItem('powermail.sidebar.sections.v2') || '{}') || {};
    } catch {
      return {};
    }
  });
  const { authenticated, loading, logout, user } = useAuth();
  const isPublicBooking = window.location.pathname.startsWith('/book/');
  const isPublicUnsubscribe = window.location.pathname.startsWith('/email-tracking/unsubscribe/');

  const isActive = (type, id) => page.type === type && (!id || page.id === id);
  const activeTitle = page.type === 'resource'
    ? resourceGroups
      .flatMap((group) => group.resources.map((resource) => ({ ...resource, groupLabel: group.label })))
      .find((resource) => resource.id === page.id)?.title || 'PowerMail'
    : {
      dashboard: 'Dashboard',
      contacts: 'Marketing Contacts',
      logs: 'Email Logs',
      compose: 'Send Email',
    }[page.type] || 'PowerMail';
  const initials = String(user?.name || user?.email || 'PM')
    .split(/\s|@/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || 'PM';
  const isAdmin = user?.role === 'admin';
  const navSections = useMemo(() => [
    {
      id: 'overview',
      label: 'Overview',
      items: [
        { label: 'Dashboard', icon: 'dashboard', page: { type: 'dashboard' }, active: isActive('dashboard') },
      ],
    },
    {
      id: 'mail',
      label: 'Mail',
      items: [
        hasAccess(user, PERMISSIONS.sendEmails) && { label: 'Send Email', icon: 'send', page: { type: 'compose' }, active: isActive('compose') },
        hasAccess(user, PERMISSIONS.viewInbox) && { label: 'Inbox', icon: 'inbox', page: { type: 'resource', groupId: 'messaging', id: 'inbox' }, active: page.type === 'resource' && page.id === 'inbox' },
        hasAccess(user, PERMISSIONS.viewLogs) && { label: 'Logs', icon: 'logs', page: { type: 'logs' }, active: isActive('logs') },
      ].filter(Boolean),
    },
    {
      id: 'marketing',
      label: 'Marketing',
      icon: 'marketing',
      items: hasAccess(user, PERMISSIONS.manageMarketing) ? [
        { label: 'Contacts', icon: 'contacts', page: { type: 'contacts' }, active: isActive('contacts') },
        { label: 'Audiences', icon: 'audiences', page: { type: 'resource', groupId: 'marketing', id: 'audiences' }, active: page.type === 'resource' && page.id === 'audiences' },
        { label: 'Campaigns', icon: 'campaigns', page: { type: 'resource', groupId: 'marketing', id: 'campaigns' }, active: page.type === 'resource' && page.id === 'campaigns' },
        { label: 'Lead Generation', icon: 'leads', page: { type: 'resource', groupId: 'marketing', id: 'lead-runs' }, active: page.type === 'resource' && page.id === 'lead-runs' },
        { label: 'Prospect Calls', icon: 'calls', page: { type: 'resource', groupId: 'marketing', id: 'prospect-calls' }, active: page.type === 'resource' && page.id === 'prospect-calls' },
        { label: 'Calendar Slots', icon: 'calendar', page: { type: 'resource', groupId: 'marketing', id: 'booking-slots' }, active: page.type === 'resource' && page.id === 'booking-slots' },
        { label: 'Bookings', icon: 'bookings', page: { type: 'resource', groupId: 'marketing', id: 'booking-appointments' }, active: page.type === 'resource' && page.id === 'booking-appointments' },
      ] : [],
    },
    {
      id: 'companies',
      label: 'Company',
      items: [
        isAdmin && { label: 'Clients', icon: 'clients', page: { type: 'resource', groupId: 'admin', id: 'clients' }, active: page.type === 'resource' && page.id === 'clients' },
        isAdmin && { label: 'Users', icon: 'users', page: { type: 'resource', groupId: 'admin', id: 'users' }, active: page.type === 'resource' && page.id === 'users' },
        isAdmin && { label: 'Domains', icon: 'domains', page: { type: 'resource', groupId: 'admin', id: 'domains' }, active: page.type === 'resource' && page.id === 'domains' },
        hasAccess(user, PERMISSIONS.manageAccounts) && { label: 'Email Accounts', icon: 'accounts', page: { type: 'resource', groupId: 'admin', id: 'accounts' }, active: page.type === 'resource' && page.id === 'accounts' },
        hasAccess(user, PERMISSIONS.manageTemplates) && { label: 'Email Templates', icon: 'templates', page: { type: 'resource', groupId: 'admin', id: 'templates' }, active: page.type === 'resource' && page.id === 'templates' },
      ].filter(Boolean),
    },
    {
      id: 'developer',
      label: 'Developer',
      items: isAdmin ? [
        { label: 'API Keys', icon: 'keys', page: { type: 'resource', groupId: 'admin', id: 'api-keys' }, active: page.type === 'resource' && page.id === 'api-keys' },
      ] : [],
    },
  ].filter((section) => section.items.length > 0), [isAdmin, page, user]);

  function toggleSidebar() {
    setSidebarCollapsed((current) => {
      window.localStorage.setItem('powermail.sidebar.collapsed', String(!current));
      return !current;
    });
  }

  function toggleSection(sectionId) {
    setCollapsedSections((current) => {
      const next = { ...current, [sectionId]: !current[sectionId] };
      window.localStorage.setItem('powermail.sidebar.sections.v2', JSON.stringify(next));
      return next;
    });
  }

  if (isPublicBooking) {
    return <PublicBookingPage />;
  }

  if (isPublicUnsubscribe) {
    return <PublicUnsubscribePage />;
  }

  if (loading) {
    return <main className="login-shell"><div className="login-panel">Loading session...</div></main>;
  }

  if (!authenticated) {
    return <LoginPage />;
  }

  return (
    <AppDataProvider>
      <div className={`react-app-shell ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
        <aside className="sidebar react-sidebar">
          <div className="sidebar-head">
            <button type="button" className="brand react-brand" onClick={() => setPage({ type: 'dashboard' })}>
              <span className="brand-mark">P</span>
              <span className="brand-text">
                PowerMail
                <small>Core</small>
              </span>
            </button>
            <button className="sidebar-toggle" type="button" onClick={toggleSidebar} aria-label="Collapse sidebar" aria-pressed={sidebarCollapsed}>
              <Icon name="collapse" />
            </button>
          </div>

          <div className="sidebar-scroll">
            {hasAccess(user, PERMISSIONS.sendEmails) && (
              <button className="sidebar-primary-action" type="button" onClick={() => setPage({ type: 'compose' })}>
                <Icon name="send" />
                <span>Send New Email</span>
              </button>
            )}
            <nav className="sidebar-nav" aria-label="Primary">
              {navSections.map((section) => {
                const collapsed = Boolean(collapsedSections[section.id]) && !section.items.some((item) => item.active);

                return (
                  <div className={`nav-section ${collapsed ? 'collapsed' : ''}`} key={section.id} data-nav-section={section.id}>
                    <button
                      className="nav-section-title"
                      type="button"
                      onClick={() => toggleSection(section.id)}
                      aria-expanded={!collapsed}
                      aria-controls={`nav-section-${section.id}`}
                    >
                      <span className="nav-section-label">
                        {section.icon && <span className="nav-section-icon"><Icon name={section.icon} /></span>}
                        <span>{section.label}</span>
                      </span>
                      <span className="nav-section-chevron"><Icon name="chevron" /></span>
                    </button>
                    <div className="nav-section-items" id={`nav-section-${section.id}`}>
                      {section.items.map((item) => (
                        <button
                          type="button"
                          className={item.active ? 'active' : ''}
                          key={item.label}
                          title={item.label}
                          aria-label={item.label}
                          onClick={() => setPage(item.page)}
                        >
                          <span className="nav-icon"><Icon name={item.icon} /></span>
                          <span>{item.label}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                );
              })}
            </nav>

            <div className="sidebar-footer">
              <strong>{user?.name || 'PowerMail User'}</strong>
              <span>{user?.email}</span>
              <span className="role-badge">{isAdmin ? 'Administrator' : 'Client user'}</span>
            </div>
          </div>
        </aside>

        <div className="app-main react-app-main">
          <header className="topbar react-topbar">
            <div className="topbar-title">
              <p className="eyebrow">PowerMail Core</p>
              <h1>{activeTitle}</h1>
            </div>
            <label className="search" aria-label="Search">
              <Icon name="search" />
              <input type="search" placeholder="Search PowerMail..." />
            </label>
            <div className="top-actions">
              {hasAccess(user, PERMISSIONS.viewInbox) ? (
                <UnreadInboxButton
                  icon={<Icon name="bell" />}
                  onOpen={() => setPage({ type: 'resource', groupId: 'messaging', id: 'inbox', inboxOpened: 'unopened', notificationKey: Date.now() })}
                />
              ) : (
                <button className="icon-button" type="button" aria-label="Notifications" disabled>
                  <Icon name="bell" />
                </button>
              )}
              {hasAccess(user, PERMISSIONS.sendEmails) && (
                <button className="icon-button message-action" type="button" aria-label="Messages" onClick={() => setPage({ type: 'compose' })}>
                  <Icon name="send" />
                </button>
              )}
              <button className="icon-button auxiliary-action" type="button" aria-label="Help" title="Help">
                <Icon name="help" />
              </button>
              {hasAccess(user, PERMISSIONS.manageAccounts) && (
                <button className="icon-button auxiliary-action" type="button" aria-label="Email account settings" title="Email account settings" onClick={() => setPage({ type: 'resource', groupId: 'admin', id: 'accounts' })}>
                  <Icon name="settings" />
                </button>
              )}
              <details className="profile-menu">
                <summary className="profile-trigger">
                  <span>{user?.name || user?.email}</span>
                  <span className="avatar">{initials}</span>
                </summary>
                <div className="profile-dropdown">
                  <strong>{user?.name || 'PowerMail User'}</strong>
                  <p>{user?.email}</p>
                  <button type="button" className="secondary-button" onClick={logout}>Log out</button>
                </div>
              </details>
              <button className="icon-button shell-menu" type="button" aria-label="Toggle navigation" onClick={toggleSidebar} aria-pressed={sidebarCollapsed}>
                <Icon name="menu" />
              </button>
            </div>
          </header>

          {page.type === 'dashboard' && <DashboardPage onNavigate={setPage} user={user} />}
          {page.type === 'contacts' && <MarketingContactsPage />}
          {page.type === 'logs' && <EmailLogsPage />}
          {page.type === 'compose' && <ComposePage />}
          {page.type === 'resource' && page.id === 'templates' && <EmailTemplatesPage />}
          {page.type === 'resource' && page.id === 'booking-slots' && <CalendarSlotsPage />}
          {page.type === 'resource' && page.id === 'prospect-calls' && <ProspectCallsPage />}
          {page.type === 'resource' && page.id === 'booking-appointments' && <BookingsPage />}
          {page.type === 'resource' && !['templates', 'booking-slots', 'prospect-calls', 'booking-appointments'].includes(page.id) && (
            <ResourcePage
              groupId={page.groupId}
              initialInboxOpened={page.inboxOpened}
              key={page.notificationKey || `${page.groupId}-${page.id}`}
              resourceId={page.id}
            />
          )}
        </div>
      </div>
    </AppDataProvider>
  );
}
