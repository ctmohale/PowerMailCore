<?php

use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\SendEmailController;
use App\Http\Controllers\Api\SendingAccountController;
use App\Http\Controllers\Api\TemplateController;
use Illuminate\Support\Facades\Route;

Route::post('/send', SendEmailController::class)->name('api.send');
Route::get('/sending-accounts', [SendingAccountController::class, 'index'])->name('api.sending-accounts.index');
Route::get('/templates', [TemplateController::class, 'index'])->name('api.templates.index');
Route::get('/templates/{key}', [TemplateController::class, 'show'])->name('api.templates.show');
Route::get('/inbox', [InboxController::class, 'index'])->name('api.inbox.index');
Route::get('/inbox/{receivedEmail}', [InboxController::class, 'show'])->name('api.inbox.show');
Route::patch('/inbox/{receivedEmail}/opened', [InboxController::class, 'markOpened'])->name('api.inbox.opened');
