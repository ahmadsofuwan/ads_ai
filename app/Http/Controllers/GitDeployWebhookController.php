<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GitDeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitDeployWebhookController extends Controller
{
    public function handle(Request $request, GitDeployService $deployService): JsonResponse
    {
        $setting = Setting::current();
        $rawPayload = (string) $request->getContent();
        $hubSignature = $request->header('X-Hub-Signature-256');
        $tokenParam = $request->header('X-Deploy-Token') ?? $request->query('secret');

        if (! $deployService->verifyWebhookSignature($rawPayload, $hubSignature, $tokenParam, $setting)) {
            return response()->json([
                'ok' => false,
                'message' => 'Signature atau Deploy Secret Token tidak valid.',
            ], 403);
        }

        $githubEvent = strtolower((string) $request->header('X-GitHub-Event', 'push'));
        if ($githubEvent === 'ping') {
            return response()->json([
                'ok' => true,
                'message' => 'Pong! GitHub Auto-Deploy Webhook terhubung dengan benar.',
            ]);
        }

        $targetBranch = trim((string) ($setting->deploy_branch ?: 'main'));
        $payloadRef = (string) $request->input('ref', '');

        // If GitHub sends a ref (e.g. "refs/heads/main"), ignore pushes to other branches
        if ($payloadRef !== '' && $payloadRef !== "refs/heads/{$targetBranch}") {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'message' => "Push pada {$payloadRef} dilewati (target branch aktif: {$targetBranch}).",
            ]);
        }

        $pusher = (string) ($request->input('pusher.name') ?? 'GitHub Webhook');
        $result = $deployService->runAutoDeploy($setting, "GitHub Webhook ({$pusher})");

        return response()->json($result, $result['ok'] ? 200 : 500);
    }
}
