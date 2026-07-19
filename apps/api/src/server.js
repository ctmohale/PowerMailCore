import cors from 'cors';
import express from 'express';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  createApiKey,
  createClient,
  createDomain,
  createEmailAccount,
  createEmailTemplate,
  createUser,
  deleteApiKey,
  deleteClient,
  deleteDomain,
  deleteEmailAccount,
  deleteEmailTemplate,
  deleteUser,
  regenerateApiKey,
  setClientActive,
  setUserStatus,
  updateApiKey,
  updateClient,
  updateDomain,
  updateEmailAccount,
  updateEmailTemplate,
  updateUser,
} from './repositories/adminWriteRepository.js';
import { findUserByEmail, passwordMatches, publicUser } from './auth/authRepository.js';
import { requireAdmin, requireAuth, requirePermission } from './auth/middleware.js';
import { createToken } from './auth/tokens.js';
import { config } from './config.js';
import {
  dashboardSummary,
  inboxUnreadCount,
  listApiKeys,
  listEmailAccountOptions,
  listBookingAppointments,
  listBookingSlots,
  listClients as listClientRows,
  listDomains,
  listDomainOptions,
  listEmailAccounts,
  listEmailTemplateOptions,
  listEmailTemplates,
  listInboxMessages,
  listLeadRuns,
  listMarketingSenderOptions,
  listMarketingTemplateOptions,
  listMarketingAudiences,
  listMarketingCampaigns,
  listProspectCalls,
  listUsers,
  showEmailTemplate,
} from './repositories/appRepository.js';
import { emailLogStatuses, listClients, listEmailLogs, showEmailLog } from './repositories/emailLogsRepository.js';
import {
  authorizeApiKey,
  dataWithHtmlBodySlots,
  renderTemplate,
  sendComposedEmailForUser,
  sendPlainEmailForAccount,
  sendTemplateEmailForApiKey,
  stripHtmlToText,
  verifyAccountForUser,
} from './repositories/emailSendRepository.js';
import { syncInbox } from './repositories/inboxSyncRepository.js';
import { deleteInboxMessage, deleteInboxMessagesBulk, markInboxMessageOpened, showInboxMessage } from './repositories/inboxActionRepository.js';
import { listAudiences, listMarketingContacts, marketingContactStatuses } from './repositories/marketingContactsRepository.js';
import {
  createAudience,
  createBookingSlot,
  createCampaign,
  createContact,
  createProspectCall,
  campaignStatus,
  deleteAudience,
  deleteBookingSlot,
  deleteCampaign,
  deleteContact,
  deleteContactsBulk,
  deleteProspectCall,
  importContacts,
  sendCampaign,
  sendContactEmail,
  setContactStatus,
  showCampaign,
  updateContactAudiencesBulk,
  updateBookingSlot,
  updateProspectCall,
} from './repositories/marketingWriteRepository.js';
import {
  createLeadRun,
  deleteLead,
  deleteLeadRun,
  deleteLeadRunsBulk,
  deleteLeadsBulk,
  downloadLeadRun,
  enrichLead,
  importLeadRun,
  previewLeadRun,
  showLeadRun,
} from './repositories/leadGenerationRepository.js';
import {
  bookPublicSlot,
  publicBookingConfirmation,
  publicBookingPage,
} from './repositories/publicBookingRepository.js';
import {
  recordEmailOpen,
  trackingPixel,
  unsubscribeContact,
} from './repositories/publicEmailTrackingRepository.js';
import {
  listPublicInbox,
  listPublicSendingAccounts,
  listPublicTemplates,
  markPublicInboxMessageOpened,
  showPublicInboxMessage,
  showPublicTemplate,
} from './repositories/publicApiRepository.js';

const app = express();
const apiDir = path.dirname(fileURLToPath(import.meta.url));
const webDistPath = path.resolve(apiDir, '..', '..', 'web', 'dist');

app.use(cors({ origin: config.webOrigin }));
app.use(express.json());

app.get('/api/health', (_request, response) => {
  response.json({
    ok: true,
    app: config.appName,
    runtime: 'node',
  });
});

app.get('/api/public/book/:slug', (request, response) => {
  response.json(publicBookingPage(request.params.slug));
});

app.post('/api/public/book/:slug', (request, response) => {
  response.status(201).json(bookPublicSlot(request.params.slug, request.body || {}));
});

app.get('/api/public/book/:slug/confirmed/:appointmentId', (request, response) => {
  response.json(publicBookingConfirmation(request.params.slug, request.params.appointmentId));
});

app.get('/email-tracking/open/:emailLogId', (request, response) => {
  recordEmailOpen(request.params.emailLogId);
  response
    .set({
      'Content-Type': 'image/gif',
      'Content-Length': String(trackingPixel.length),
      'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
      Pragma: 'no-cache',
    })
    .send(trackingPixel);
});

app.get('/api/public/email-tracking/unsubscribe/:contactId/:token', (request, response) => {
  response.json(unsubscribeContact(request.params.contactId, request.params.token));
});

app.post('/api/send', async (request, response, next) => {
  try {
    const apiKey = authorizeApiKey(request, 'send');
    const result = await sendTemplateEmailForApiKey(apiKey, request.body || {});

    response.json({
      message: 'Email sent.',
      log_id: result.logId,
      status: result.status,
    });
  } catch (error) {
    if (error.deliveryStatus) {
      response.status(error.status || 422).json({
        message: error.message,
        log_id: error.logId,
        status: error.deliveryStatus,
      });
      return;
    }

    next(error);
  }
});

app.get('/api/sending-accounts', (request, response, next) => {
  try {
    const apiKey = authorizeApiKey(request, 'send');
    response.json(listPublicSendingAccounts(apiKey));
  } catch (error) {
    next(error);
  }
});

app.get('/api/templates', (request, response, next) => {
  try {
    const apiKey = authorizeApiKey(request, 'templates');
    response.json(listPublicTemplates(apiKey));
  } catch (error) {
    next(error);
  }
});

app.get('/api/templates/:key', (request, response, next) => {
  try {
    const apiKey = authorizeApiKey(request, 'templates');
    response.json(showPublicTemplate(apiKey, request.params.key));
  } catch (error) {
    next(error);
  }
});

app.get('/api/inbox', (request, response, next) => {
  try {
    const apiKey = authorizeApiKey(request, 'inbox');
    response.json(listPublicInbox(apiKey, request.query || {}));
  } catch (error) {
    next(error);
  }
});

app.get('/api/inbox/:id', (request, response, next) => {
  if (['messages', 'unread-count'].includes(request.params.id)) {
    next('route');
    return;
  }

  try {
    const apiKey = authorizeApiKey(request, 'inbox');
    response.json(showPublicInboxMessage(apiKey, request.params.id));
  } catch (error) {
    next(error);
  }
});

app.patch('/api/inbox/:id/opened', (request, response, next) => {
  try {
    const apiKey = authorizeApiKey(request, 'inbox');
    response.json(markPublicInboxMessageOpened(apiKey, request.params.id));
  } catch (error) {
    next(error);
  }
});

app.post('/api/auth/login', async (request, response) => {
  const email = String(request.body?.email || '').trim();
  const password = String(request.body?.password || '');

  if (!email || !password) {
    response.status(422).json({ error: 'Email and password are required.' });
    return;
  }

  const user = findUserByEmail(email);

  if (!user || user.status !== 'active' || !(await passwordMatches(password, user.password))) {
    response.status(401).json({ error: 'These credentials do not match our records.' });
    return;
  }

  response.json({
    token: createToken(user),
    user: publicUser(user),
  });
});

app.get('/api/auth/me', requireAuth, (request, response) => {
  response.json({ user: request.user });
});

app.use('/api', requireAuth);

const statsListResolvers = {
  logs: listEmailLogs,
  contacts: listMarketingContacts,
  clients: listClientRows,
  domains: listDomains,
  accounts: listEmailAccounts,
  templates: listEmailTemplates,
  'api-keys': listApiKeys,
  users: listUsers,
  inbox: listInboxMessages,
  audiences: listMarketingAudiences,
  campaigns: listMarketingCampaigns,
  'lead-runs': listLeadRuns,
  'prospect-calls': listProspectCalls,
  'booking-slots': listBookingSlots,
  'booking-appointments': listBookingAppointments,
};

function allFilteredRows(resolver, query, user) {
  const firstPage = resolver({ ...query, page: 1, per_page: 100 }, user);
  const rows = [...firstPage.data];

  for (let page = 2; page <= firstPage.meta.lastPage; page += 1) {
    rows.push(...resolver({ ...query, page, per_page: 100 }, user).data);
  }

  return { rows, total: firstPage.meta.total };
}

function filteredResourceStats(resourceId, query, user) {
  const resolver = statsListResolvers[resourceId];

  if (!resolver) {
    const error = new Error('Statistics are not available for this page.');
    error.status = 404;
    throw error;
  }

  const { rows, total } = allFilteredRows(resolver, query, user);
  const count = (predicate) => rows.filter(predicate).length;
  const sum = (field) => rows.reduce((value, row) => value + Number(row[field] || 0), 0);
  const statusIs = (...statuses) => (row) => statuses.includes(String(row.status || '').toLowerCase());

  const calculators = {
    logs: () => ({ total, sent: count(statusIs('sent')), pending: count(statusIs('pending')), failed: count(statusIs('failed')) }),
    contacts: () => ({ total, subscribed: count(statusIs('subscribed')), unsubscribed: count(statusIs('unsubscribed')), bounced: count(statusIs('bounced')) }),
    clients: () => ({ total, active: count((row) => Boolean(row.isActive)), withContact: count((row) => Boolean(row.contactEmail)), inactive: count((row) => !row.isActive) }),
    domains: () => ({ total, active: count(statusIs('active')), pending: count(statusIs('pending')), companies: new Set(rows.map((row) => row.clientId).filter(Boolean)).size }),
    accounts: () => ({ total, active: count((row) => Boolean(row.isActive)), inbox: count((row) => Boolean(row.inboxEnabled)), inactive: count((row) => !row.isActive) }),
    templates: () => ({ total, active: count((row) => Boolean(row.isActive)), marketing: count((row) => row.type === 'marketing'), communication: count((row) => row.type === 'communication') }),
    'api-keys': () => ({ total, active: count((row) => Boolean(row.isActive)), used: count((row) => Boolean(row.lastUsedAt)), inactive: count((row) => !row.isActive) }),
    users: () => ({ total, active: count(statusIs('active')), admins: count((row) => row.role === 'admin'), suspended: count(statusIs('suspended')) }),
    inbox: () => ({ total, unread: count((row) => !row.openedAt), opened: count((row) => Boolean(row.openedAt)), accounts: new Set(rows.map((row) => row.accountEmail).filter(Boolean)).size }),
    audiences: () => ({ total, contacts: sum('contactCount'), populated: count((row) => Number(row.contactCount || 0) > 0), campaigns: sum('campaignCount') }),
    campaigns: () => ({ total, recipients: sum('totalRecipients'), sent: sum('sentCount'), failed: sum('failedCount') }),
    'lead-runs': () => ({ total, discovered: sum('discoveredCount'), imported: sum('importedCount'), completed: count(statusIs('completed')) }),
    'prospect-calls': () => ({ total, followUp: count(statusIs('follow_up')), meetings: count(statusIs('meeting_booked')), won: count(statusIs('won')) }),
    'booking-slots': () => ({ total, available: count(statusIs('available')), booked: count((row) => Boolean(row.bookedBy) || row.status === 'booked'), blocked: count(statusIs('blocked')) }),
    'booking-appointments': () => ({ total, booked: count(statusIs('booked')), completed: count(statusIs('completed')), cancelled: count(statusIs('cancelled')) }),
  };

  return calculators[resourceId]();
}

const canManageMarketing = requirePermission('manage_marketing');
const canManageTemplates = requirePermission('manage_templates');
const canManageAccounts = requirePermission('manage_accounts');
const canViewInbox = requirePermission('view_inbox');
const canSendEmails = requirePermission('send_emails');

app.get('/api/options', (request, response) => {
  response.json({
    clients: listClients(request.user),
    audiences: listAudiences(request.user),
    domains: listDomainOptions(request.user),
    emailAccounts: listEmailAccountOptions(request.user),
    emailTemplates: listEmailTemplateOptions(request.user),
    marketingSenders: listMarketingSenderOptions(request.user),
    marketingTemplates: listMarketingTemplateOptions(request.user),
    emailLogStatuses: emailLogStatuses(),
    marketingContactStatuses: marketingContactStatuses(),
  });
});

app.get('/api/email-logs', (request, response) => {
  response.json(listEmailLogs(request.query, request.user));
});

app.get('/api/email-logs/:id', (request, response) => {
  response.json(showEmailLog(request.params.id, request.user));
});

app.get('/api/marketing/contacts', (request, response) => {
  response.json(listMarketingContacts(request.query, request.user));
});

app.get('/api/dashboard', (request, response) => response.json(dashboardSummary(request.user)));
app.get('/api/resource-stats/:id', (request, response) => {
  response.json(filteredResourceStats(request.params.id, request.query, request.user));
});
app.get('/api/admin/clients', (request, response) => response.json(listClientRows(request.query, request.user)));
app.get('/api/admin/domains', (request, response) => response.json(listDomains(request.query, request.user)));
app.get('/api/admin/email-accounts', (request, response) => response.json(listEmailAccounts(request.query, request.user)));
app.get('/api/admin/email-templates', (request, response) => response.json(listEmailTemplates(request.query, request.user)));
app.get('/api/admin/email-templates/:id', canManageTemplates, (request, response) => {
  response.json({ data: showEmailTemplate(request.params.id, request.user) });
});
app.get('/api/admin/api-keys', (request, response) => response.json(listApiKeys(request.query, request.user)));
app.get('/api/admin/users', (request, response) => response.json(listUsers(request.query, request.user)));
app.get('/api/inbox/messages', canViewInbox, (request, response) => response.json(listInboxMessages(request.query, request.user)));
app.get('/api/inbox/unread-count', canViewInbox, (request, response) => response.json(inboxUnreadCount(request.user)));
app.get('/api/marketing/audiences', (request, response) => response.json(listMarketingAudiences(request.query, request.user)));
app.get('/api/marketing/campaigns', (request, response) => response.json(listMarketingCampaigns(request.query, request.user)));
app.get('/api/marketing/lead-runs', (request, response) => response.json(listLeadRuns(request.query, request.user)));
app.get('/api/marketing/prospect-calls', (request, response) => response.json(listProspectCalls(request.query, request.user)));
app.get('/api/marketing/booking-slots', (request, response) => response.json(listBookingSlots(request.query, request.user)));
app.get('/api/marketing/booking-appointments', (request, response) => response.json(listBookingAppointments(request.query, request.user)));

app.post('/api/admin/clients', requireAdmin, (request, response) => {
  const id = createClient(request.body || {});
  response.status(201).json({ id });
});

app.patch('/api/admin/clients/:id', requireAdmin, (request, response) => {
  updateClient(request.params.id, request.body || {});
  response.json({ ok: true });
});

app.patch('/api/admin/clients/:id/suspend', requireAdmin, (request, response) => {
  setClientActive(request.params.id, false);
  response.json({ ok: true });
});

app.patch('/api/admin/clients/:id/activate', requireAdmin, (request, response) => {
  setClientActive(request.params.id, true);
  response.json({ ok: true });
});

app.delete('/api/admin/clients/:id', requireAdmin, (request, response) => {
  deleteClient(request.params.id);
  response.status(204).send();
});

app.post('/api/admin/domains', requireAdmin, (request, response) => {
  const id = createDomain(request.body || {});
  response.status(201).json({ id });
});

app.patch('/api/admin/domains/:id', requireAdmin, (request, response) => {
  updateDomain(request.params.id, request.body || {});
  response.json({ ok: true });
});

app.delete('/api/admin/domains/:id', requireAdmin, (request, response) => {
  deleteDomain(request.params.id);
  response.status(204).send();
});

app.post('/api/admin/email-accounts', canManageAccounts, (request, response) => {
  const id = createEmailAccount(request.user, request.body || {});
  response.status(201).json({ id });
});

app.patch('/api/admin/email-accounts/:id', canManageAccounts, (request, response) => {
  updateEmailAccount(request.user, request.params.id, request.body || {});
  response.json({ ok: true });
});

app.patch('/api/admin/email-accounts/:id/inbox', canManageAccounts, (request, response) => {
  updateEmailAccount(request.user, request.params.id, request.body || {});
  response.json({ ok: true });
});

app.delete('/api/admin/email-accounts/:id', canManageAccounts, (request, response) => {
  deleteEmailAccount(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/admin/email-accounts/:id/verify', canManageAccounts, async (request, response, next) => {
  try {
    response.json(await verifyAccountForUser(request.user, request.params.id));
  } catch (error) {
    next(error);
  }
});

app.post('/api/admin/email-accounts/:id/send-test', canManageAccounts, async (request, response, next) => {
  try {
    const result = await sendPlainEmailForAccount(request.user, request.params.id, request.body || {});
    response.json({
      message: 'Email sent.',
      log_id: result.logId,
      status: result.status,
    });
  } catch (error) {
    if (error.deliveryStatus) {
      response.status(error.status || 422).json({
        message: error.message,
        log_id: error.logId,
        status: error.deliveryStatus,
      });
      return;
    }

    next(error);
  }
});

app.post('/api/admin/email-templates', canManageTemplates, (request, response) => {
  const id = createEmailTemplate(request.user, request.body || {});
  response.status(201).json({ id });
});

app.post('/api/admin/email-templates/preview', canManageTemplates, (request, response) => {
  const payload = request.body || {};
  const customData = payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data) ? payload.data : {};
  const data = {
    name: 'Preview Recipient',
    first_name: 'Preview',
    last_name: 'Recipient',
    company: 'PowerMail Core',
    email: 'preview@example.com',
    body: 'This is where the compose message will appear.\n\nSecond paragraph preview.',
    message: 'This is where the compose message will appear.\n\nSecond paragraph preview.',
    unsubscribe_url: '#unsubscribe-preview',
    ...customData,
  };
  const subject = renderTemplate(payload.subject || 'Subject preview', data);
  const html = renderTemplate(payload.body_html || '', dataWithHtmlBodySlots(data), {
    escapeHtml: true,
    rawKeys: ['body', 'message', 'body_html', 'message_html', 'unsubscribe_url'],
  });
  const text = payload.body_text ? renderTemplate(payload.body_text, data) : stripHtmlToText(html);

  response.json({ subject, html, text });
});

app.patch('/api/admin/email-templates/:id', canManageTemplates, (request, response) => {
  updateEmailTemplate(request.user, request.params.id, request.body || {});
  response.json({ ok: true });
});

app.delete('/api/admin/email-templates/:id', canManageTemplates, (request, response) => {
  deleteEmailTemplate(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/admin/api-keys', requireAdmin, (request, response) => {
  response.status(201).json(createApiKey(request.body || {}));
});

app.patch('/api/admin/api-keys/:id', requireAdmin, (request, response) => {
  updateApiKey(request.params.id, request.body || {});
  response.json({ ok: true });
});

app.patch('/api/admin/api-keys/:id/regenerate', requireAdmin, (request, response) => {
  response.json(regenerateApiKey(request.params.id));
});

app.delete('/api/admin/api-keys/:id', requireAdmin, (request, response) => {
  deleteApiKey(request.params.id);
  response.status(204).send();
});

app.post('/api/admin/users', requireAdmin, (request, response) => {
  const id = createUser(request.body || {});
  response.status(201).json({ id });
});

app.patch('/api/admin/users/:id', requireAdmin, (request, response) => {
  updateUser(request.user, request.params.id, request.body || {});
  response.json({ ok: true });
});

app.patch('/api/admin/users/:id/suspend', requireAdmin, (request, response) => {
  setUserStatus(request.user, request.params.id, 'suspended');
  response.json({ ok: true });
});

app.patch('/api/admin/users/:id/activate', requireAdmin, (request, response) => {
  setUserStatus(request.user, request.params.id, 'active');
  response.json({ ok: true });
});

app.delete('/api/admin/users/:id', requireAdmin, (request, response) => {
  deleteUser(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/inbox/sync', canViewInbox, async (request, response, next) => {
  try {
    response.json(await syncInbox(request.user, request.body || {}));
  } catch (error) {
    next(error);
  }
});

app.post('/api/inbox/sync-all', canViewInbox, async (request, response, next) => {
  try {
    const { email_account_id, ...body } = request.body || {};
    response.json(await syncInbox(request.user, body));
  } catch (error) {
    next(error);
  }
});

app.post('/api/inbox/sync-older', canViewInbox, async (request, response, next) => {
  try {
    response.json(await syncInbox(request.user, { ...(request.body || {}), older: true }));
  } catch (error) {
    next(error);
  }
});

app.post('/api/inbox/poll', canViewInbox, async (request, response, next) => {
  try {
    const body = request.body || {};
    const sync = body.sync === false ? { imported: 0, skipped: 0, total: 0, errors: [] } : await syncInbox(request.user, body);
    response.json({
      sync,
      messages: listInboxMessages({ ...request.query, ...body }, request.user),
      synced_at: new Date().toISOString(),
    });
  } catch (error) {
    next(error);
  }
});

app.get('/api/inbox/messages/:id', canViewInbox, (request, response) => {
  response.json(showInboxMessage(request.user, request.params.id));
});

app.patch('/api/inbox/messages/:id/opened', canViewInbox, (request, response) => {
  response.json(markInboxMessageOpened(request.user, request.params.id, true));
});

app.patch('/api/inbox/messages/:id/unopened', canViewInbox, (request, response) => {
  response.json(markInboxMessageOpened(request.user, request.params.id, false));
});

app.delete('/api/inbox/messages/bulk', canViewInbox, (request, response) => {
  response.json(deleteInboxMessagesBulk(request.user, request.body?.ids || request.body?.message_ids || []));
});

app.delete('/api/inbox/messages/:id', canViewInbox, (request, response) => {
  deleteInboxMessage(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/send-email', canSendEmails, async (request, response, next) => {
  try {
    const result = await sendComposedEmailForUser(request.user, request.body || {});
    response.json({
      message: result.status === 'sent' ? 'Your email has been sent.' : `Email processed with status: ${result.status}.`,
      log_id: result.logId,
      status: result.status,
    });
  } catch (error) {
    if (error.deliveryStatus) {
      response.status(error.status || 422).json({
        message: error.message,
        log_id: error.logId,
        status: error.deliveryStatus,
      });
      return;
    }

    next(error);
  }
});

app.post('/api/marketing/audiences', canManageMarketing, (request, response) => {
  const id = createAudience(request.user, request.body || {});
  response.status(201).json({ id });
});

app.delete('/api/marketing/audiences/:id', canManageMarketing, (request, response) => {
  deleteAudience(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/marketing/campaigns', canManageMarketing, (request, response) => {
  const id = createCampaign(request.user, request.body || {});
  response.status(201).json({ id });
});

app.get('/api/marketing/campaigns/:id/status', canManageMarketing, (request, response) => {
  response.json(campaignStatus(request.user, request.params.id));
});

app.get('/api/marketing/campaigns/:id', canManageMarketing, (request, response) => {
  response.json({ data: showCampaign(request.user, request.params.id) });
});

app.post('/api/marketing/campaigns/:id/send', canManageMarketing, async (request, response, next) => {
  try {
    response.json(await sendCampaign(request.user, request.params.id));
  } catch (error) {
    next(error);
  }
});

app.delete('/api/marketing/campaigns/:id', canManageMarketing, (request, response) => {
  deleteCampaign(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/marketing/contacts', canManageMarketing, (request, response) => {
  const id = createContact(request.user, request.body || {});
  response.status(201).json({ id });
});

app.post('/api/marketing/contacts/import', canManageMarketing, async (request, response, next) => {
  try {
    response.json(await importContacts(request.user, request.body || {}));
  } catch (error) {
    next(error);
  }
});

app.patch('/api/marketing/contacts/:id/status', canManageMarketing, (request, response) => {
  setContactStatus(request.user, request.params.id, request.body?.status);
  response.json({ ok: true });
});

app.patch('/api/marketing/contacts/:id/subscribe', canManageMarketing, (request, response) => {
  setContactStatus(request.user, request.params.id, 'subscribed');
  response.json({ ok: true });
});

app.patch('/api/marketing/contacts/:id/unsubscribe', canManageMarketing, (request, response) => {
  setContactStatus(request.user, request.params.id, 'unsubscribed');
  response.json({ ok: true });
});

app.post('/api/marketing/contacts/audiences/bulk', canManageMarketing, (request, response) => {
  response.json(updateContactAudiencesBulk(request.user, request.body || {}));
});

app.post('/api/marketing/contacts/:id/audiences', canManageMarketing, (request, response) => {
  response.json(updateContactAudiencesBulk(request.user, {
    ...(request.body || {}),
    contact_ids: [request.params.id],
  }));
});

app.post('/api/marketing/contacts/:id/send-email', canManageMarketing, async (request, response, next) => {
  try {
    const result = await sendContactEmail(request.user, request.params.id, request.body || {});
    response.json({
      message: 'Email sent.',
      log_id: result.logId,
      status: result.status,
    });
  } catch (error) {
    if (error.deliveryStatus) {
      response.status(error.status || 422).json({
        message: error.message,
        log_id: error.logId,
        status: error.deliveryStatus,
      });
      return;
    }

    next(error);
  }
});

app.delete('/api/marketing/contacts/bulk', canManageMarketing, (request, response) => {
  response.json(deleteContactsBulk(request.user, request.body || {}));
});

app.delete('/api/marketing/contacts/:id', canManageMarketing, (request, response) => {
  deleteContact(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/marketing/lead-runs', canManageMarketing, async (request, response, next) => {
  try {
    const id = await createLeadRun(request.user, request.body || {});
    response.status(201).json({ id });
  } catch (error) {
    next(error);
  }
});

app.post('/api/marketing/lead-runs/preview', canManageMarketing, (request, response) => {
  response.json(previewLeadRun(request.user, request.body || {}));
});

app.get('/api/marketing/lead-runs/:id', canManageMarketing, (request, response) => {
  response.json({ data: showLeadRun(request.user, request.params.id) });
});

app.get('/api/marketing/lead-runs/:id/download', canManageMarketing, (request, response) => {
  response.json(downloadLeadRun(request.user, request.params.id));
});

app.post('/api/marketing/lead-runs/:id/import', canManageMarketing, (request, response) => {
  response.json(importLeadRun(request.user, request.params.id));
});

app.patch('/api/marketing/lead-runs/:id/leads/:leadIndex/enrich', canManageMarketing, (request, response) => {
  response.json(enrichLead(request.user, request.params.id, request.params.leadIndex, request.body || {}));
});

app.post('/api/marketing/lead-runs/:id/leads/:leadIndex/enrich', canManageMarketing, (request, response) => {
  response.json(enrichLead(request.user, request.params.id, request.params.leadIndex, request.body || {}));
});

app.delete('/api/marketing/lead-runs/:id/leads/bulk', canManageMarketing, (request, response) => {
  response.json(deleteLeadsBulk(request.user, request.params.id, request.body?.indexes || request.body?.lead_indexes || []));
});

app.delete('/api/marketing/lead-runs/:id/leads', canManageMarketing, (request, response) => {
  response.json(deleteLead(
    request.user,
    request.params.id,
    request.body?.lead_index ?? request.body?.index,
  ));
});

app.delete('/api/marketing/lead-runs/:id/leads/:leadIndex', canManageMarketing, (request, response) => {
  response.json(deleteLead(request.user, request.params.id, request.params.leadIndex));
});

app.delete('/api/marketing/lead-runs/bulk', canManageMarketing, (request, response) => {
  response.json(deleteLeadRunsBulk(request.user, request.body?.ids || request.body?.run_ids || []));
});

app.delete('/api/marketing/lead-runs/:id', canManageMarketing, (request, response) => {
  deleteLeadRun(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/marketing/prospect-calls', canManageMarketing, (request, response) => {
  const id = createProspectCall(request.user, request.body || {});
  response.status(201).json({ id });
});

app.patch('/api/marketing/prospect-calls/:id', canManageMarketing, (request, response) => {
  updateProspectCall(request.user, request.params.id, request.body || {});
  response.json({ ok: true });
});

app.delete('/api/marketing/prospect-calls/:id', canManageMarketing, (request, response) => {
  deleteProspectCall(request.user, request.params.id);
  response.status(204).send();
});

app.post('/api/marketing/booking-slots', canManageMarketing, (request, response) => {
  const id = createBookingSlot(request.user, request.body || {});
  response.status(201).json({ id });
});

app.patch('/api/marketing/booking-slots/:id', canManageMarketing, (request, response) => {
  updateBookingSlot(request.user, request.params.id, request.body || {});
  response.json({ ok: true });
});

app.delete('/api/marketing/booking-slots/:id', canManageMarketing, (request, response) => {
  deleteBookingSlot(request.user, request.params.id);
  response.status(204).send();
});

app.use('/api', (_request, response) => {
  response.status(404).json({ error: 'API route not found.' });
});

if (fs.existsSync(path.join(webDistPath, 'index.html'))) {
  app.use(express.static(webDistPath));
  app.get('*', (_request, response) => response.sendFile(path.join(webDistPath, 'index.html')));
}

app.use((error, _request, response, _next) => {
  console.error(error);
  const status = error.status || 500;

  response.status(status).json({
    error: status >= 500 ? 'Internal Server Error' : error.message,
    message: error.message,
  });
});

app.listen(config.apiPort, () => {
  console.log(`PowerMail Node API running on http://127.0.0.1:${config.apiPort}`);
});
