<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\EmailAccountController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\MarketingController;
use App\Http\Controllers\Admin\SendEmailController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailTrackingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/email-tracking/open/{emailLog}', [EmailTrackingController::class, 'open'])
    ->middleware('signed:relative')
    ->name('email-tracking.open');

Route::get('/email-tracking/unsubscribe/{marketingContact}/{token}', [EmailTrackingController::class, 'unsubscribe'])
    ->name('email-tracking.unsubscribe');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active.user'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('admin')->group(function (): void {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::patch('/clients/{client}/suspend', [ClientController::class, 'suspend'])->name('clients.suspend');
        Route::patch('/clients/{client}/activate', [ClientController::class, 'activate'])->name('clients.activate');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::patch('/domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

        Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::patch('/api-keys/{apiKey}', [ApiKeyController::class, 'update'])->name('api-keys.update');
        Route::patch('/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->name('api-keys.regenerate');
        Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
    });

    Route::middleware('ability:manage_accounts')->group(function (): void {
        Route::get('/email-accounts', [EmailAccountController::class, 'index'])->name('email-accounts.index');
        Route::post('/email-accounts', [EmailAccountController::class, 'store'])->name('email-accounts.store');
        Route::patch('/email-accounts/{emailAccount}', [EmailAccountController::class, 'update'])->name('email-accounts.update');
        Route::patch('/email-accounts/{emailAccount}/inbox', [EmailAccountController::class, 'updateInbox'])->name('email-accounts.inbox.update');
        Route::post('/email-accounts/{emailAccount}/verify', [EmailAccountController::class, 'verify'])->name('email-accounts.verify');
        Route::delete('/email-accounts/{emailAccount}', [EmailAccountController::class, 'destroy'])->name('email-accounts.destroy');
    });

    Route::middleware('ability:manage_templates')->group(function (): void {
        Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::post('/email-templates', [EmailTemplateController::class, 'store'])->name('email-templates.store');
        Route::patch('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
    });

    Route::middleware('ability:send_emails')->group(function (): void {
        Route::get('/send-email', [SendEmailController::class, 'index'])->name('send-email.index');
        Route::post('/send-email', [SendEmailController::class, 'store'])->name('send-email.store');
    });

    Route::middleware('ability:manage_marketing')->group(function (): void {
        Route::get('/marketing', [MarketingController::class, 'index'])->name('marketing.index');
        Route::post('/marketing/contacts', [MarketingController::class, 'storeContact'])->name('marketing.contacts.store');
        Route::post('/marketing/contacts/import', [MarketingController::class, 'importContacts'])->name('marketing.contacts.import');
        Route::post('/marketing/contacts/{marketingContact}/send-email', [MarketingController::class, 'sendContactEmail'])->name('marketing.contacts.send-email');
        Route::patch('/marketing/contacts/{marketingContact}/subscribe', [MarketingController::class, 'subscribeContact'])->name('marketing.contacts.subscribe');
        Route::patch('/marketing/contacts/{marketingContact}/unsubscribe', [MarketingController::class, 'unsubscribeContact'])->name('marketing.contacts.unsubscribe');
        Route::delete('/marketing/contacts/{marketingContact}', [MarketingController::class, 'destroyContact'])->name('marketing.contacts.destroy');
        Route::post('/marketing/lead-generation/preview', [MarketingController::class, 'previewLeadGenerationParse'])->name('marketing.lead-generation.preview');
        Route::post('/marketing/lead-generation', [MarketingController::class, 'storeLeadGenerationRun'])->name('marketing.lead-generation.store');
        Route::post('/marketing/lead-generation/{marketingLeadGenerationRun}/import', [MarketingController::class, 'importLeadGenerationRun'])->name('marketing.lead-generation.import');
        Route::get('/marketing/lead-generation/{marketingLeadGenerationRun}/download', [MarketingController::class, 'downloadLeadGenerationRun'])->name('marketing.lead-generation.download');
        Route::post('/marketing/lead-generation/{marketingLeadGenerationRun}/leads/{leadIndex}/enrich', [MarketingController::class, 'enrichLead'])->name('marketing.lead-generation.leads.enrich');
        Route::delete('/marketing/lead-generation/{marketingLeadGenerationRun}/leads', [MarketingController::class, 'destroyLeadGenerationLead'])->name('marketing.lead-generation.leads.destroy');
        Route::delete('/marketing/lead-generation/{marketingLeadGenerationRun}/leads/bulk', [MarketingController::class, 'destroyLeadGenerationLeads'])->name('marketing.lead-generation.leads.mass-destroy');
        Route::delete('/marketing/lead-generation/{marketingLeadGenerationRun}', [MarketingController::class, 'destroyLeadGenerationRun'])->name('marketing.lead-generation.destroy');
        Route::post('/marketing/campaigns', [MarketingController::class, 'storeCampaign'])->name('marketing.campaigns.store');
        Route::get('/marketing/campaigns/{marketingCampaign}', [MarketingController::class, 'showCampaign'])->name('marketing.campaigns.show');
        Route::get('/marketing/campaigns/{marketingCampaign}/status', [MarketingController::class, 'campaignStatus'])->name('marketing.campaigns.status');
        Route::post('/marketing/campaigns/{marketingCampaign}/send', [MarketingController::class, 'sendCampaign'])->name('marketing.campaigns.send');
    });

    Route::middleware('ability:view_logs,send_emails')->group(function (): void {
        Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
        Route::get('/email-logs/{emailLog}', [EmailLogController::class, 'show'])->name('email-logs.show');
    });

    Route::middleware('ability:view_inbox')->group(function (): void {
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
        Route::post('/inbox/sync', [InboxController::class, 'sync'])->name('inbox.sync');
        Route::post('/inbox/sync-all', [InboxController::class, 'syncAll'])->name('inbox.sync-all');
        Route::post('/inbox/sync-older', [InboxController::class, 'syncOlder'])->name('inbox.sync-older');
        Route::post('/inbox/poll', [InboxController::class, 'poll'])->name('inbox.poll');
        Route::patch('/inbox/{receivedEmail}/opened', [InboxController::class, 'markOpened'])->name('inbox.mark-opened');
        Route::patch('/inbox/{receivedEmail}/unopened', [InboxController::class, 'markUnopened'])->name('inbox.mark-unopened');
        Route::delete('/inbox/bulk', [InboxController::class, 'destroyBulk'])->name('inbox.destroy-bulk');
        Route::delete('/inbox/{receivedEmail}', [InboxController::class, 'destroy'])->name('inbox.destroy');
        Route::get('/inbox/{receivedEmail}', [InboxController::class, 'show'])->name('inbox.show');
    });
});
