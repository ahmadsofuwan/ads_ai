<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramBotService $telegramBotService): JsonResponse
    {
        try {
            $payload = $request->all();
            if (! empty($payload)) {
                $telegramBotService->handleWebhook($payload);
            }
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookController Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'service' => 'MetaAds AI Publisher Webhook',
        ]);
    }
}
