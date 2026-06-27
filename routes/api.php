<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);
