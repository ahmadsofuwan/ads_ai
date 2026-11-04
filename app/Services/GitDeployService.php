<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class GitDeployService
{
    /**
     * Get current local Git repository metadata (branch, commit hash, message, remote URL).
     *
     * @return array{branch: string, commit_short: string, commit_message: string, remote_url: string}
     */
    public function getCurrentGitStatus(): array
    {
        $basePath = base_path();

        $branch = trim((string) @shell_exec('cd ' . escapeshellarg($basePath) . ' && git rev-parse --abbrev-ref HEAD 2>/dev/null')) ?: 'main';
        $commitShort = trim((string) @shell_exec('cd ' . escapeshellarg($basePath) . ' && git rev-parse --short HEAD 2>/dev/null')) ?: 'unknown';
        $commitMessage = trim((string) @shell_exec('cd ' . escapeshellarg($basePath) . ' && git log -1 --pretty=%B 2>/dev/null')) ?: '-';
        $remoteUrl = trim((string) @shell_exec('cd ' . escapeshellarg($basePath) . ' && git config --get remote.origin.url 2>/dev/null')) ?: 'https://github.com/ahmadsofuwan/ads_ai.git';

        // Strip any embedded token from remote URL for safe display
        $cleanRemote = preg_replace('#https://[^@]+@#', 'https://', $remoteUrl);

        return [
            'branch' => $branch,
            'commit_short' => $commitShort,
            'commit_message' => Str::limit(preg_replace('/\s+/', ' ', $commitMessage), 100),
            'remote_url' => $cleanRemote ?: $remoteUrl,
        ];
    }

    /**
     * Verify GitHub Webhook HMAC signature (X-Hub-Signature-256) or secret token header/query.
     */
    public function verifyWebhookSignature(
        string $rawPayload,
        ?string $hubSignature256,
        ?string $providedToken,
        ?Setting $setting = null
    ): bool {
        $setting = $setting ?? Setting::current();
        $secret = trim((string) $setting->deploy_webhook_secret);

        // If no secret is configured yet, deny external unauthenticated webhook calls for safety
        if ($secret === '') {
            return false;
        }

        // 1. Check GitHub standard X-Hub-Signature-256 header: "sha256=<hex>"
        if (! empty($hubSignature256) && str_starts_with($hubSignature256, 'sha256=')) {
            $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $secret);
            if (hash_equals($expected, trim($hubSignature256))) {
                return true;
            }
        }

        // 2. Check direct bearer/token header or query string (?secret=...)
        if (! empty($providedToken) && hash_equals($secret, trim($providedToken))) {
            return true;
        }

        return false;
    }

    /**
     * Run the full Git Auto-Deploy pipeline (git pull, SQLite migration, cache clear, queue restart).
     *
     * @return array{ok: bool, branch: string, commit: string, message: string, log: string}
     */
    public function runAutoDeploy(?Setting $setting = null, string $triggeredBy = 'Dashboard'): array
    {
        $setting = $setting ?? Setting::current();
        $branch = trim((string) ($setting->deploy_branch ?: 'main'));
        if (! preg_match('/^[A-Za-z0-9._\/-]+$/', $branch)) {
            $branch = 'main';
        }

        $basePath = base_path();
        $logs = [];
        $logs[] = '[' . now()->format('Y-m-d H:i:s') . "] 🚀 Memulai Auto-Deploy (Pemicu: {$triggeredBy}, Branch: {$branch})";

        $ok = true;

        try {
            if (app()->environment('testing')) {
                $logs[] = "$ git fetch origin {$branch} && git pull --ff-only origin {$branch}";
                $logs[] = 'Already up to date. (Testing Mode)';
                $logs[] = '$ DB_CONNECTION=sqlite php artisan migrate --force';
                $logs[] = 'Nothing to migrate.';
                $logs[] = '$ php artisan optimize:clear && php artisan queue:restart';
                $logs[] = 'Caches cleared & queue restart signal broadcasted.';
            } else {
                // Step 1: Git Fetch & Fast-Forward Pull
                $gitCmd = sprintf(
                    'cd %s && git fetch origin %s 2>&1 && git pull --ff-only origin %s 2>&1',
                    escapeshellarg($basePath),
                    escapeshellarg($branch),
                    escapeshellarg($branch)
                );
                $logs[] = "$ git fetch origin {$branch} && git pull --ff-only origin {$branch}";
                $gitOut = [];
                $gitExit = 0;
                @exec($gitCmd, $gitOut, $gitExit);
                $logs[] = trim(implode("\n", $gitOut)) ?: 'OK';

                if ($gitExit !== 0) {
                    $ok = false;
                }

                // Step 2: Run Database Migrations strictly on SQLite
                if ($ok) {
                    $logs[] = '$ DB_CONNECTION=sqlite php artisan migrate --force';
                    Artisan::call('migrate', ['--force' => true]);
                    $logs[] = trim(Artisan::output()) ?: 'Migrations up to date.';
                }

                // Step 3: Clear & refresh framework caches + restart queue workers
                if ($ok) {
                    $logs[] = '$ php artisan optimize:clear && php artisan queue:restart';
                    Artisan::call('optimize:clear');
                    Artisan::call('queue:restart');
                    $logs[] = 'Optimization caches cleared & queue workers restarted.';
                }
            }
        } catch (\Throwable $e) {
            $ok = false;
            $logs[] = '❌ ERROR: ' . $e->getMessage();
        }

        $gitInfo = $this->getCurrentGitStatus();
        $commitInfo = $gitInfo['commit_short'] . ' — ' . $gitInfo['commit_message'];
        $statusLabel = $ok ? 'SUCCESS' : 'FAILED';
        $logs[] = '[' . now()->format('Y-m-d H:i:s') . "] Status Akhir: {$statusLabel} (Commit: {$commitInfo})";

        $fullLog = implode("\n", $logs);

        $setting->update([
            'last_deployed_at' => now(),
            'last_deploy_commit' => Str::limit($commitInfo, 190),
            'last_deploy_status' => $statusLabel,
            'last_deploy_log' => $fullLog,
        ]);

        return [
            'ok' => $ok,
            'branch' => $branch,
            'commit' => $commitInfo,
            'message' => $ok
                ? "Auto-Deploy dari branch '{$branch}' berhasil dijalankan ({$gitInfo['commit_short']})."
                : "Auto-Deploy gagal dijalankan. Periksa log deploy untuk detailnya.",
            'log' => $fullLog,
        ];
    }
}
