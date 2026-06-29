<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\EmailAccountController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');

    Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
    Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');

    Route::get('/email-accounts', [EmailAccountController::class, 'index'])->name('email-accounts.index');
    Route::post('/email-accounts', [EmailAccountController::class, 'store'])->name('email-accounts.store');
    Route::patch('/email-accounts/{emailAccount}/inbox', [EmailAccountController::class, 'updateInbox'])->name('email-accounts.inbox.update');

    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::post('/email-templates', [EmailTemplateController::class, 'store'])->name('email-templates.store');

    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');

    Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
    Route::get('/email-logs/{emailLog}', [EmailLogController::class, 'show'])->name('email-logs.show');

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::post('/inbox/sync', [InboxController::class, 'sync'])->name('inbox.sync');
    Route::post('/inbox/sync-all', [InboxController::class, 'syncAll'])->name('inbox.sync-all');
    Route::get('/inbox/{receivedEmail}', [InboxController::class, 'show'])->name('inbox.show');
});
