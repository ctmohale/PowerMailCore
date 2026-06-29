<?php

use App\Http\Controllers\Api\SendEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/send', SendEmailController::class)->name('api.send');
