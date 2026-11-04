<?php

use App\Http\Controllers\GitDeployWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('api.telegram.webhook');

Route::post('/deploy/webhook', [GitDeployWebhookController::class, 'handle'])
    ->name('api.deploy.webhook');
