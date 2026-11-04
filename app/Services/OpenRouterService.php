<?php

namespace App\Services;

use App\Models\AdDraft;
use App\Models\AiProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OpenRouterService
{
    protected const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Test connectivity and validity of a specific AiProvider record.
     */
    public function testProviderConnection(AiProvider $provider): array
    {
        $apiKey = trim((string) $provider->api_key);
        $model = $provider->resolveModelName();
        $endpoint = $provider->resolveEndpointUrl();

        if ($apiKey === '') {
            return [
                'ok' => false,
                'message' => "API Key untuk provider AI \"{$provider->name}\" belum diisi.",
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => config('app.url', 'http://localhost:8000'),
                'X-Title' => config('app.name', 'MetaAds AI Publisher'),
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Balas hanya dengan kata: OK'],
                ],
                'max_tokens' => 15,
            ]);

            if (! $response->successful()) {
                $err = $response->json('error.message') ?? $response->body();

                return [
                    'ok' => false,
                    'message' => "Gagal terhubung ke {$provider->provider} ({$provider->name}): {$err}",
                ];
            }

            $resolvedModel = $response->json('model') ?? $model;

            return [
                'ok' => true,
                'model' => $resolvedModel,
                'message' => "Terhubung ke {$provider->provider} - {$provider->name} ({$resolvedModel})",
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => "Error koneksi {$provider->provider} ({$provider->name}): " . $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve ordered list of active AI Provider configurations (supports Multi-Provider & Fallback).
     *
     * @return array<int, array{name: string, provider: string, endpoint: string, api_key: string, model: string}>
     */
    public function resolveActiveAiConfigs(?Setting $setting = null, ?int $preferredProviderId = null): array
    {
        $setting = $setting ?? Setting::current();
        $configs = [];
        $seenKeys = [];

        if ($preferredProviderId !== null && $preferredProviderId > 0) {
            $preferred = AiProvider::find($preferredProviderId);
            if ($preferred && $preferred->hasValidCredentials()) {
                $ep = $preferred->resolveEndpointUrl();
                $key = trim((string) $preferred->api_key);
                $mod = $preferred->resolveModelName();
                $sig = $ep . '|' . $key . '|' . $mod;
                $seenKeys[$sig] = true;
                $configs[] = [
                    'name' => $preferred->name,
                    'provider' => $preferred->provider,
                    'endpoint' => $ep,
                    'api_key' => $key,
                    'model' => $mod,
                ];
            }
        }

        $settingKey = trim((string) $setting->openrouter_api_key);
        if ($settingKey !== '') {
            $provName = strtoupper(trim((string) ($setting->ai_provider ?: 'OPENROUTER')));
            $ep = AiProvider::defaultEndpointForProvider($provName, $setting->ai_base_url);
            $mod = trim((string) ($setting->openrouter_model ?: AiProvider::DEFAULT_MODELS[$provName] ?? 'openai/gpt-4o-mini'));
            $sig = $ep . '|' . $settingKey . '|' . $mod;
            if (! isset($seenKeys[$sig])) {
                $seenKeys[$sig] = true;
                $configs[] = [
                    'name' => "Setting Utama ({$provName})",
                    'provider' => $provName,
                    'endpoint' => $ep,
                    'api_key' => $settingKey,
                    'model' => $mod,
                ];
            }
        }

        $dbProviders = AiProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($dbProviders as $dbProv) {
            if (! $dbProv->hasValidCredentials()) {
                continue;
            }
            $ep = $dbProv->resolveEndpointUrl();
            $key = trim((string) $dbProv->api_key);
            $mod = $dbProv->resolveModelName();
            $sig = $ep . '|' . $key . '|' . $mod;
            if (! isset($seenKeys[$sig])) {
                $seenKeys[$sig] = true;
                $configs[] = [
                    'name' => $dbProv->name,
                    'provider' => $dbProv->provider,
                    'endpoint' => $ep,
                    'api_key' => $key,
                    'model' => $mod,
                ];
            }
        }

        return $configs;
    }

    /**
     * Test connectivity and validity of OpenRouter / Active AI Provider API Key.
     */
    public function testConnection(?Setting $setting = null): array
    {
        $configs = $this->resolveActiveAiConfigs($setting);

        if (empty($configs)) {
            return [
                'ok' => false,
                'message' => 'API Key AI Provider (OpenRouter / OpenAI / Gemini / Groq / DeepSeek) belum diisi pada Pengaturan Sistem.',
            ];
        }

        $primary = $configs[0];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $primary['api_key'],
                'HTTP-Referer' => config('app.url', 'http://localhost:8000'),
                'X-Title' => config('app.name', 'MetaAds AI Publisher'),
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($primary['endpoint'], [
                'model' => $primary['model'],
                'messages' => [
                    ['role' => 'user', 'content' => 'Balas hanya dengan kata: OK'],
                ],
                'max_tokens' => 15,
            ]);

            if (! $response->successful()) {
                $err = $response->json('error.message') ?? $response->body();

                return [
                    'ok' => false,
                    'message' => "Gagal terhubung ke {$primary['provider']}: {$err}",
                ];
            }

            $resolvedModel = $response->json('model') ?? $primary['model'];

            return [
                'ok' => true,
                'model' => $resolvedModel,
                'message' => "Terhubung ke {$primary['provider']} ({$resolvedModel})",
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => "Error koneksi {$primary['provider']}: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse user natural language prompt and coordinates to generate structured ad config & copywriting
     * for Meta Ads, TikTok Ads, and Google/YouTube Ads.
     *
     * @param  string  $userPrompt
     * @param  array<int, array<string, mixed>>|null  $coordinates
     * @param  Setting|null  $setting
     * @return array<string, mixed>
     */
    public function parseAndGenerateDraft(
        string $userPrompt,
        ?array $coordinates = null,
        ?Setting $setting = null,
        ?int $preferredAiProviderId = null
    ): array {
        $setting = $setting ?? Setting::current();
        $configs = $this->resolveActiveAiConfigs($setting, $preferredAiProviderId);

        $preParsed = $this->extractLocalHints($userPrompt, $coordinates, $setting);

        if (empty($configs)) {
            Log::info('AI Provider API Key kosong, menggunakan Smart Heuristic Omni-Channel Copywriter Engine.');

            return $this->normalizeAiResult($preParsed, $userPrompt, $coordinates, $setting);
        }

        $systemPrompt = <<<'PROMPT'
Anda adalah Senior Omni-Channel Media Buyer Indonesia (Meta Ads, TikTok Ads, dan Google/YouTube Ads Architect) serta Direct-Response Copywriter berpengalaman 10+ tahun.
Tugas Anda adalah menganalisis perintah bahasa sehari-hari dari pengguna untuk kampanye iklan video baru, mengekstrak seluruh parameter teknis targeting, serta menulis copywriting bahasa Indonesia berkonversi tinggi untuk ketiga platform (Meta, TikTok, dan Google Ads).

ATURAN EKSTRAKSI PARAMETER:
1. `title`: Nama kampanye yang rapi dan deskriptif (contoh: "Promo Pasang WiFi FTTH - Jambi (WA)").
2. `daily_budget`: Angka bulat dalam Rupiah (IDR). Konversikan kata seperti "50rb", "50k", "50 ribu", "50.000" menjadi integer `50000`. Jika tidak disebut, gunakan `50000`.
3. `age_min` & `age_max`: Rentang umur target audiens (minimal 18, maksimal 65). Default: `age_min` = 21, `age_max` = 50.
4. `genders`: Array integer: `[1]` khusus pria, `[2]` khusus wanita, `[]` semua gender.
5. `destination_type`: `"WHATSAPP"` atau `"WEBSITE"`.
6. `destination_url`: URL tujuan jika ada di dalam perintah. Kosongkan (`""`) jika tidak disebutkan.
7. `objective`:
   - `"OUTCOME_ENGAGEMENT"` jika `destination_type` adalah `"WHATSAPP"`.
   - `"OUTCOME_SALES"` jika `destination_type` adalah `"WEBSITE"` untuk konversi pembelian.
   - `"OUTCOME_TRAFFIC"` jika `destination_type` adalah `"WEBSITE"` untuk kunjungan web.
8. `cta_type`: `"WHATSAPP_MESSAGE"`, `"LEARN_MORE"`, atau `"SHOP_NOW"`.
9. `radius_km`: Angka integer radius lokasi dalam kilometer, WAJIB di rentang `1` sampai `80`. Default: `10`.
10. `interest_keywords`: Array berisi 3 sampai 6 kata kunci minat singkat (1-2 kata per item) yang relevan.
11. `target_platforms`: Array berisi platform yang disebut pengguna (`"META"`, `"TIKTOK"`, `"GOOGLE"`). Jika pengguna tidak membatasi ke 1 platform tertentu, kembalikan `["META", "TIKTOK", "GOOGLE"]`.

ATURAN COPYWRITING OMNI-CHANNEL BAHASA INDONESIA:
1. META ADS (Facebook & Instagram):
   - `ai_primary_text`: Kalimat pertama WAJIB berupa HOOK KUAT, diikuti bullet points keunggulan (✅/🔥), dan diakhiri Call To Action tegas.
   - `ai_headline`: Judul singkat di samping tombol CTA, WAJIB maksimal 40 karakter.
   - `ai_description`: Teks pendukung singkat maksimal 60 karakter.
2. TIKTOK ADS:
   - `tiktok_ad_text`: Caption gaya organik TikTok yang punchy, bikin penasaran, ajakan klik CTA, ditambah 2-3 hashtag singkat relevan. WAJIB maksimal 100 karakter!
3. GOOGLE / YOUTUBE ADS (Demand Gen & Video Action):
   - `google_headline`: Short Headline padat, WAJIB maksimal 30 karakter!
   - `google_long_headline`: Long Headline persuasif, WAJIB maksimal 90 karakter!
   - `google_description`: Deskripsi pendukung konversi, WAJIB maksimal 90 karakter!

Balas HANYA dalam format JSON yang valid sesuai skema yang diminta tanpa teks tambahan di luar JSON.
PROMPT;

        $coordContext = '';
        if (! empty($coordinates)) {
            $coordContext = "\nKoordinat Lokasi dari Pengguna: " . json_encode($coordinates, JSON_UNESCAPED_UNICODE);
        }

        $userMessage = "Perintah Pengguna:\n\"\"\"\n{$userPrompt}\n\"\"\"{$coordContext}";

        $jsonSchema = [
            'name' => 'omni_ads_draft_schema',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'daily_budget' => ['type' => 'integer'],
                    'age_min' => ['type' => 'integer'],
                    'age_max' => ['type' => 'integer'],
                    'genders' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                    'destination_type' => [
                        'type' => 'string',
                        'enum' => ['WHATSAPP', 'WEBSITE'],
                    ],
                    'destination_url' => ['type' => 'string'],
                    'objective' => [
                        'type' => 'string',
                        'enum' => ['OUTCOME_ENGAGEMENT', 'OUTCOME_TRAFFIC', 'OUTCOME_SALES'],
                    ],
                    'cta_type' => [
                        'type' => 'string',
                        'enum' => ['WHATSAPP_MESSAGE', 'LEARN_MORE', 'SHOP_NOW'],
                    ],
                    'radius_km' => ['type' => 'integer'],
                    'interest_keywords' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'target_platforms' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['META', 'TIKTOK', 'GOOGLE'],
                        ],
                    ],
                    'ai_primary_text' => ['type' => 'string'],
                    'ai_headline' => ['type' => 'string'],
                    'ai_description' => ['type' => 'string'],
                    'tiktok_ad_text' => ['type' => 'string'],
                    'google_headline' => ['type' => 'string'],
                    'google_long_headline' => ['type' => 'string'],
                    'google_description' => ['type' => 'string'],
                ],
                'required' => [
                    'title',
                    'daily_budget',
                    'age_min',
                    'age_max',
                    'genders',
                    'destination_type',
                    'destination_url',
                    'objective',
                    'cta_type',
                    'radius_km',
                    'interest_keywords',
                    'target_platforms',
                    'ai_primary_text',
                    'ai_headline',
                    'ai_description',
                    'tiktok_ad_text',
                    'google_headline',
                    'google_long_headline',
                    'google_description',
                ],
                'additionalProperties' => false,
            ],
        ];

        $lastException = null;

        foreach ($configs as $cfg) {
            try {
                $payload = [
                    'model' => $cfg['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0.5,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => $jsonSchema,
                    ],
                ];

                $headers = [
                    'Authorization' => 'Bearer ' . $cfg['api_key'],
                    'HTTP-Referer' => config('app.url', 'http://localhost:8000'),
                    'X-Title' => config('app.name', 'MetaAds AI Publisher'),
                    'Content-Type' => 'application/json',
                ];

                $response = Http::withHeaders($headers)->timeout(60)->post($cfg['endpoint'], $payload);

                if (! $response->successful()) {
                    $payload['response_format'] = ['type' => 'json_object'];
                    $response = Http::withHeaders($headers)->timeout(60)->post($cfg['endpoint'], $payload);
                }

                if (! $response->successful()) {
                    unset($payload['response_format']);
                    $response = Http::withHeaders($headers)->timeout(60)->post($cfg['endpoint'], $payload);
                }

                if (! $response->successful()) {
                    $errorMsg = $response->json('error.message') ?? $response->body();
                    throw new RuntimeException($cfg['provider'] . ' API Error (' . $response->status() . '): ' . $errorMsg);
                }

                $rawContent = (string) ($response->json('choices.0.message.content') ?? '');
                $decoded = $this->decodeJsonFromContent($rawContent);

                if (! is_array($decoded)) {
                    throw new RuntimeException($cfg['provider'] . ' tidak mengembalikan format JSON yang valid. Raw output: ' . Str::limit($rawContent, 300));
                }

                return $this->normalizeAiResult(array_merge($preParsed, $decoded), $userPrompt, $coordinates, $setting);
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("AI Provider {$cfg['name']} ({$cfg['provider']}) gagal, mencoba fallback jika ada: " . $e->getMessage());
            }
        }

        throw $lastException ?? new RuntimeException('Gagal memproses permintaan ke seluruh AI Provider.');
    }

    /**
     * Regenerate persuasive copywriting variation for an existing AdDraft across Meta, TikTok & Google.
     *
     * @return array<string, string>
     */
    public function regenerateCopywriting(AdDraft $draft, ?Setting $setting = null, ?int $preferredAiProviderId = null): array
    {
        $setting = $setting ?? Setting::current();
        $providerId = $preferredAiProviderId ?? ($draft->ai_provider_id ? (int) $draft->ai_provider_id : null);
        $configs = $this->resolveActiveAiConfigs($setting, $providerId);

        if (empty($configs)) {
            $alt = $this->buildHeuristicCopywriting($draft->raw_user_prompt, $draft->destination_type, true);

            return [
                'ai_primary_text' => $alt['ai_primary_text'],
                'ai_headline' => Str::limit($alt['ai_headline'], 40, ''),
                'ai_description' => $alt['ai_description'],
                'tiktok_ad_text' => Str::limit($alt['tiktok_ad_text'], 100, ''),
                'google_headline' => Str::limit($alt['google_headline'], 30, ''),
                'google_long_headline' => Str::limit($alt['google_long_headline'], 90, ''),
                'google_description' => Str::limit($alt['google_description'], 90, ''),
            ];
        }

        $systemPrompt = <<<'PROMPT'
Anda adalah Senior Direct-Response Copywriter Omni-Channel (Meta Ads, TikTok Ads, Google/YouTube Ads) Indonesia.
Buatlah VARIASI BARU copywriting iklan bahasa Indonesia yang berbeda sudut pandang (angle) dari versi sebelumnya, lebih segar, persuasif, dan memicu klik tinggi.
- `ai_primary_text`: Wajib diawali Hook kuat di kalimat pertama, diikuti bullet points keunggulan (✅/🔥), dan diakhiri Call To Action jelas.
- `ai_headline`: Judul singkat Meta Ads, WAJIB maksimal 40 karakter.
- `ai_description`: Teks pendukung singkat Meta Ads, maksimal 60 karakter.
- `tiktok_ad_text`: Caption pendek gaya TikTok + hashtag, WAJIB maksimal 100 karakter.
- `google_headline`: Short Headline Google Ads, WAJIB maksimal 30 karakter.
- `google_long_headline`: Long Headline Google Ads, WAJIB maksimal 90 karakter.
- `google_description`: Deskripsi Google Ads, WAJIB maksimal 90 karakter.
Balas HANYA dalam JSON valid dengan 7 key tersebut.
PROMPT;

        $userPrompt = "Perintah Asli: {$draft->raw_user_prompt}\n"
            . "Tujuan: {$draft->destination_type}\n"
            . "Headline Sebelumnya: {$draft->ai_headline}\n"
            . "Primary Text Sebelumnya:\n{$draft->ai_primary_text}\n\n"
            . "Buat variasi copywriting baru untuk Meta, TikTok, dan Google Ads!";

        $lastException = null;

        foreach ($configs as $cfg) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $cfg['api_key'],
                    'HTTP-Referer' => config('app.url', 'http://localhost:8000'),
                    'X-Title' => config('app.name', 'MetaAds AI Publisher'),
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post($cfg['endpoint'], [
                    'model' => $cfg['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.75,
                ]);

                if (! $response->successful()) {
                    $errorMsg = $response->json('error.message') ?? $response->body();
                    throw new RuntimeException("Gagal membuat ulang copywriting via {$cfg['provider']}: {$errorMsg}");
                }

                $rawContent = (string) ($response->json('choices.0.message.content') ?? '');
                $decoded = $this->decodeJsonFromContent($rawContent);

                if (! is_array($decoded) || empty($decoded['ai_primary_text'])) {
                    throw new RuntimeException("Respons JSON copywriting dari {$cfg['provider']} tidak valid.");
                }

                $headline = Str::limit(trim((string) ($decoded['ai_headline'] ?? $draft->ai_headline)), 40, '');

                return [
                    'ai_primary_text' => trim((string) $decoded['ai_primary_text']),
                    'ai_headline' => $headline,
                    'ai_description' => Str::limit(trim((string) ($decoded['ai_description'] ?? $draft->ai_description)), 60, ''),
                    'tiktok_ad_text' => Str::limit(trim((string) ($decoded['tiktok_ad_text'] ?? $headline . ' Klik sekarang! #promo')), 100, ''),
                    'google_headline' => Str::limit(trim((string) ($decoded['google_headline'] ?? $headline)), 30, ''),
                    'google_long_headline' => Str::limit(trim((string) ($decoded['google_long_headline'] ?? $headline . ' - Klaim Penawaran Hari Ini!')), 90, ''),
                    'google_description' => Str::limit(trim((string) ($decoded['google_description'] ?? $decoded['ai_description'] ?? 'Klik untuk info & promo spesial.')), 90, ''),
                ];
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new RuntimeException('Gagal membuat ulang copywriting dari seluruh AI Provider.');
    }

    protected function decodeJsonFromContent(string $content): ?array
    {
        $trimmed = trim($content);
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', $trimmed);

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected function normalizeAiResult(array $data, string $rawPrompt, ?array $coordinates = null, ?Setting $setting = null): array
    {
        $setting = $setting ?? Setting::current();

        $budget = (int) ($data['daily_budget'] ?? 50000);
        if ($budget < 15000) {
            if ($budget > 0 && $budget <= 1000) {
                $budget *= 1000;
            } else {
                $budget = 50000;
            }
        }

        $ageMin = max(18, min(65, (int) ($data['age_min'] ?? 21)));
        $ageMax = max($ageMin, min(65, (int) ($data['age_max'] ?? 50)));

        $genders = [];
        if (! empty($data['genders']) && is_array($data['genders'])) {
            foreach ($data['genders'] as $g) {
                $gInt = (int) $g;
                if (in_array($gInt, [1, 2], true)) {
                    $genders[] = $gInt;
                }
            }
            $genders = array_values(array_unique($genders));
            if (count($genders) === 2) {
                $genders = [];
            }
        }

        $destinationType = strtoupper(trim((string) ($data['destination_type'] ?? 'WHATSAPP')));
        if (! in_array($destinationType, ['WHATSAPP', 'WEBSITE'], true)) {
            $destinationType = 'WHATSAPP';
        }

        $objective = strtoupper(trim((string) ($data['objective'] ?? '')));
        if ($destinationType === 'WHATSAPP') {
            $objective = AdDraft::OBJECTIVE_ENGAGEMENT;
        } elseif (! in_array($objective, [AdDraft::OBJECTIVE_TRAFFIC, AdDraft::OBJECTIVE_SALES], true)) {
            $objective = ! empty($setting->meta_pixel_id) && preg_match('/\b(beli|order|checkout|sales|jual|toko)\b/i', $rawPrompt)
                ? AdDraft::OBJECTIVE_SALES
                : AdDraft::OBJECTIVE_TRAFFIC;
        }

        $ctaType = strtoupper(trim((string) ($data['cta_type'] ?? '')));
        if ($destinationType === 'WHATSAPP') {
            $ctaType = AdDraft::CTA_WHATSAPP_MESSAGE;
        } elseif (! in_array($ctaType, [AdDraft::CTA_LEARN_MORE, AdDraft::CTA_SHOP_NOW], true)) {
            $ctaType = $objective === AdDraft::OBJECTIVE_SALES ? AdDraft::CTA_SHOP_NOW : AdDraft::CTA_LEARN_MORE;
        }

        $radiusKm = (int) round((float) ($data['radius_km'] ?? 10));
        $radiusKm = max(1, min(80, $radiusKm));

        $destinationUrl = trim((string) ($data['destination_url'] ?? ''));
        if ($destinationUrl === '' && $destinationType === 'WHATSAPP' && ! empty($setting->meta_whatsapp_number)) {
            $wa = preg_replace('/[^0-9]/', '', (string) $setting->meta_whatsapp_number);
            if (str_starts_with($wa, '0')) {
                $wa = '62' . substr($wa, 1);
            }
            if ($wa !== '') {
                $destinationUrl = "https://wa.me/{$wa}";
            }
        }

        $interestKeywords = [];
        if (! empty($data['interest_keywords']) && is_array($data['interest_keywords'])) {
            foreach ($data['interest_keywords'] as $kw) {
                $cleanKw = trim((string) $kw);
                if ($cleanKw !== '') {
                    $interestKeywords[] = $cleanKw;
                }
            }
        }
        if (empty($interestKeywords)) {
            $interestKeywords = $this->extractFallbackInterestKeywords($rawPrompt);
        }

        $customLocations = [];
        if (! empty($coordinates) && is_array($coordinates)) {
            foreach ($coordinates as $idx => $loc) {
                if (isset($loc['latitude'], $loc['longitude'])) {
                    $pinRadius = isset($loc['radius']) ? max(1, min(80, (int) $loc['radius'])) : $radiusKm;
                    $customLocations[] = [
                        'latitude' => round((float) $loc['latitude'], 6),
                        'longitude' => round((float) $loc['longitude'], 6),
                        'radius' => $pinRadius,
                        'distance_unit' => 'kilometer',
                        'name' => $loc['name'] ?? ('Titik Lokasi #' . ($idx + 1)),
                    ];
                }
            }
        }

        if (empty($customLocations)) {
            $customLocations[] = [
                'latitude' => round((float) ($setting->default_latitude ?: -1.610123), 6),
                'longitude' => round((float) ($setting->default_longitude ?: 103.613120), 6),
                'radius' => $radiusKm,
                'distance_unit' => 'kilometer',
                'name' => 'Lokasi Utama',
            ];
        }

        $headline = trim((string) ($data['ai_headline'] ?? 'Promo Spesial Hari Ini!'));
        if (mb_strlen($headline) > 40) {
            $headline = mb_substr($headline, 0, 40);
        }

        $description = Str::limit(trim((string) ($data['ai_description'] ?? 'Klik tombol di bawah untuk info & promo!')), 60, '');

        $tiktokAdText = Str::limit(
            trim((string) ($data['tiktok_ad_text'] ?? ($headline . ' — Klik tombol di bawah untuk klaim promo sekarang! #promo #rekomendasi'))),
            100,
            ''
        );

        $googleHeadline = Str::limit(
            trim((string) ($data['google_headline'] ?? $headline)),
            30,
            ''
        );

        $googleLongHeadline = Str::limit(
            trim((string) ($data['google_long_headline'] ?? ($headline . ' - Penawaran Terbatas Hari Ini!'))),
            90,
            ''
        );

        $googleDescription = Str::limit(
            trim((string) ($data['google_description'] ?? $description)),
            90,
            ''
        );

        $platforms = $this->extractPlatformsFromPrompt($rawPrompt, $data['target_platforms'] ?? []);

        return [
            'title' => trim((string) ($data['title'] ?? 'Kampanye AI - ' . now()->format('d M H:i'))),
            'daily_budget' => $budget,
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'genders' => $genders,
            'destination_type' => $destinationType,
            'destination_url' => $destinationUrl ?: null,
            'objective' => $objective,
            'cta_type' => $ctaType,
            'radius_km' => $radiusKm,
            'interest_keywords' => array_slice(array_values(array_unique($interestKeywords)), 0, 6),
            'target_platforms' => $platforms,
            'custom_locations' => $customLocations,
            'ai_primary_text' => trim((string) ($data['ai_primary_text'] ?? '')),
            'ai_headline' => $headline,
            'ai_description' => $description,
            'tiktok_ad_text' => $tiktokAdText,
            'google_headline' => $googleHeadline,
            'google_long_headline' => $googleLongHeadline,
            'google_description' => $googleDescription,
        ];
    }

    protected function extractPlatformsFromPrompt(string $prompt, array $aiPlatforms = []): array
    {
        $detected = [];
        if (preg_match('/\b(meta|facebook|fb|instagram|ig)\b/i', $prompt)) {
            $detected[] = AdDraft::PLATFORM_META;
        }
        if (preg_match('/\b(tiktok|tt)\b/i', $prompt)) {
            $detected[] = AdDraft::PLATFORM_TIKTOK;
        }
        if (preg_match('/\b(google|youtube|yt|adwords)\b/i', $prompt)) {
            $detected[] = AdDraft::PLATFORM_GOOGLE;
        }

        if (! empty($detected)) {
            return array_values(array_unique($detected));
        }

        $validAi = array_values(array_intersect(
            array_map('strtoupper', $aiPlatforms),
            [AdDraft::PLATFORM_META, AdDraft::PLATFORM_TIKTOK, AdDraft::PLATFORM_GOOGLE]
        ));

        return ! empty($validAi) ? $validAi : [AdDraft::PLATFORM_META, AdDraft::PLATFORM_TIKTOK, AdDraft::PLATFORM_GOOGLE];
    }

    public function extractLocalHints(string $prompt, ?array $coordinates = null, ?Setting $setting = null): array
    {
        $setting = $setting ?? Setting::current();

        $budget = 50000;
        if (preg_match('/(?:budget|anggaran|biaya|iklan)?\s*(?:rp\.?\s*)?(\d+(?:[\.,]\d+)?)\s*(rb|ribu|k|jt|juta)\b/i', $prompt, $m)) {
            $num = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['jt', 'juta'], true)) {
                $budget = (int) round($num * 1000000);
            } else {
                $budget = (int) round($num * 1000);
            }
        } elseif (preg_match('/(?:budget|anggaran|rp\.?)\s*(\d{2,3}(?:\.\d{3})+|\d{5,8})\b/i', $prompt, $m)) {
            $budget = (int) str_replace('.', '', $m[1]);
        }

        $ageMin = 21;
        $ageMax = 50;
        if (preg_match('/(?:umur|usia)\s*(\d{2})\s*(?:-|s\/d|sampai|hingga|ke)\s*(\d{2})/i', $prompt, $m)) {
            $ageMin = max(18, min(65, (int) $m[1]));
            $ageMax = max($ageMin, min(65, (int) $m[2]));
        }

        $radiusKm = 10;
        if (preg_match('/(?:radius|jarak|jangkauan)\s*(\d+(?:[\.,]\d+)?)\s*(?:km|kilometer)?/i', $prompt, $m)) {
            $radiusKm = max(1, min(80, (int) round((float) str_replace(',', '.', $m[1]))));
        } elseif (preg_match('/\b(\d{1,2})\s*(?:km|kilometer)\b/i', $prompt, $m)) {
            $radiusKm = max(1, min(80, (int) $m[1]));
        }

        $genders = [];
        $hasMale = (bool) preg_match('/\b(pria|laki-laki|cowok|bapak-bapak)\b/i', $prompt);
        $hasFemale = (bool) preg_match('/\b(wanita|perempuan|cewek|ibu-ibu|bunda)\b/i', $prompt);
        if ($hasMale && ! $hasFemale) {
            $genders = [1];
        } elseif ($hasFemale && ! $hasMale) {
            $genders = [2];
        }

        $destinationUrl = '';
        if (preg_match('#https?://[^\s,"\']+#i', $prompt, $m)) {
            $destinationUrl = rtrim($m[0], '.,;)');
        }

        $destinationType = 'WHATSAPP';
        if (preg_match('/\b(website|web|landing\s*page|toko\s*online|link\s*web)\b/i', $prompt)) {
            $destinationType = 'WEBSITE';
        } elseif ($destinationUrl !== '' && ! str_contains(strtolower($destinationUrl), 'wa.me') && ! str_contains(strtolower($destinationUrl), 'whatsapp.com')) {
            $destinationType = 'WEBSITE';
        }

        $copy = $this->buildHeuristicCopywriting($prompt, $destinationType, false);
        $shortTopic = Str::limit(trim(preg_replace('/\s+/', ' ', $prompt)), 32, '');

        return [
            'title' => 'Kampanye AI - ' . ($shortTopic ?: now()->format('d M Y')),
            'daily_budget' => $budget,
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'genders' => $genders,
            'destination_type' => $destinationType,
            'destination_url' => $destinationUrl,
            'objective' => $destinationType === 'WHATSAPP' ? AdDraft::OBJECTIVE_ENGAGEMENT : AdDraft::OBJECTIVE_TRAFFIC,
            'cta_type' => $destinationType === 'WHATSAPP' ? AdDraft::CTA_WHATSAPP_MESSAGE : AdDraft::CTA_LEARN_MORE,
            'radius_km' => $radiusKm,
            'interest_keywords' => $this->extractFallbackInterestKeywords($prompt),
            'target_platforms' => $this->extractPlatformsFromPrompt($prompt),
            'ai_primary_text' => $copy['ai_primary_text'],
            'ai_headline' => $copy['ai_headline'],
            'ai_description' => $copy['ai_description'],
            'tiktok_ad_text' => $copy['tiktok_ad_text'],
            'google_headline' => $copy['google_headline'],
            'google_long_headline' => $copy['google_long_headline'],
            'google_description' => $copy['google_description'],
        ];
    }

    /**
     * Extract coordinates if user typed lat, lng inside text (e.g., "-1.6101, 103.6131").
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function extractCoordinatesFromText(string $text): ?array
    {
        if (preg_match('/(-?\d{1,2}\.\d{3,10})\s*[,;\s]\s*(-?\d{1,3}\.\d{3,10})/', $text, $matches)) {
            $lat = (float) $matches[1];
            $lng = (float) $matches[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return [
                    'latitude' => round($lat, 6),
                    'longitude' => round($lng, 6),
                ];
            }
        }

        return null;
    }

    protected function extractFallbackInterestKeywords(string $prompt): array
    {
        $lower = strtolower($prompt);
        $dictionary = [
            'wifi' => ['Internet', 'Wi-Fi', 'Smart home', 'Streaming media'],
            'internet' => ['Internet', 'Wi-Fi', 'Teknologi', 'Komunikasi'],
            'ftth' => ['Internet', 'Wi-Fi', 'Telekomunikasi'],
            'kuliner' => ['Makanan', 'Restoran', 'Kuliner', 'Pesanan makanan'],
            'makanan' => ['Makanan', 'Restoran', 'Kuliner'],
            'kopi' => ['Kopi', 'Kafe', 'Minuman'],
            'skincare' => ['Perawatan kulit', 'Kecantikan', 'Kosmetik', 'Belanja online'],
            'fashion' => ['Pakaian', 'Belanja online', 'Mode'],
            'baju' => ['Pakaian', 'Mode', 'Belanja online'],
            'properti' => ['Properti', 'Rumah', 'Investasi real estat'],
            'rumah' => ['Rumah', 'Properti', 'Desain interior'],
            'mobil' => ['Mobil', 'Otomotif', 'Kendaraan'],
            'motor' => ['Sepeda motor', 'Otomotif'],
            'kursus' => ['Pendidikan', 'Pelatihan', 'Karier'],
        ];

        foreach ($dictionary as $trigger => $keywords) {
            if (str_contains($lower, $trigger)) {
                return $keywords;
            }
        }

        return ['Belanja online', 'Bisnis kecil', 'Wirausaha'];
    }

    /**
     * Build persuasive Indonesian copywriting for Meta, TikTok, and Google Ads.
     *
     * @return array<string, string>
     */
    protected function buildHeuristicCopywriting(string $prompt, string $destinationType, bool $isAlternate = false): array
    {
        $cleanTopic = trim(preg_replace([
            '/(?:radius|jarak)\s*\d+\s*km/i',
            '/(?:umur|usia)\s*\d+\s*(?:-|sampai|s\/d)\s*\d+\s*(?:tahun|thn)?/i',
            '/(?:budget|anggaran)\s*(?:rp\.?\s*)?\d+\s*(?:rb|ribu|k|jt)?(?:\/hari)?/i',
            '/(-?\d{1,2}\.\d{3,10})\s*[,;\s]\s*(-?\d{1,3}\.\d{3,10})/',
        ], '', $prompt));

        $cleanTopic = trim(preg_replace('/\s+/', ' ', $cleanTopic), " ,.-");
        if ($cleanTopic === '') {
            $cleanTopic = 'Layanan & Produk Unggulan Terbaik di Area Anda';
        }

        $ctaLine = $destinationType === 'WHATSAPP'
            ? '📲 Klik tombol "Kirim Pesan WhatsApp" di bawah sekarang untuk konsultasi & klaim promo hari ini!'
            : '🌐 Klik tombol "Selengkapnya" di bawah video ini untuk mengamankan penawaran spesial sekarang juga!';

        if ($isAlternate) {
            $primaryText = "⚡ JANGAN LEWATKAN! Solusi terbaik yang paling banyak dicari minggu ini hadir dekat di lokasi Anda.\n\n"
                . "Kenapa pelanggan memilih layanan kami?\n"
                . "🔥 Kualitas terjamin & pelayanan ekstra cepat\n"
                . "🔥 Harga transparan, hemat, dan ramah di kantong\n"
                . "🔥 Tim profesional siap membantu kebutuhan Anda\n"
                . "🔥 Detail Penawaran: {$cleanTopic}\n\n"
                . $ctaLine;

            $headline = $destinationType === 'WHATSAPP'
                ? 'Tanya & Klaim Promo via WhatsApp!'
                : 'Ambil Penawaran Spesial Hari Ini!';

            return [
                'ai_primary_text' => $primaryText,
                'ai_headline' => Str::limit($headline, 40, ''),
                'ai_description' => 'Slot Promo Terbatas - Klik Sekarang!',
                'tiktok_ad_text' => Str::limit("⚡ {$cleanTopic}! Klik tombol di bawah buat klaim promo spesial hari ini! #promo #viral", 100, ''),
                'google_headline' => Str::limit('Promo Spesial Hari Ini', 30, ''),
                'google_long_headline' => Str::limit("{$headline} — Kualitas Terjamin & Bergaransi Resmi!", 90, ''),
                'google_description' => Str::limit("Dapatkan promo {$cleanTopic} khusus area Anda. Klik sekarang!", 90, ''),
            ];
        }

        $primaryText = "🔥 Kabar gembira untuk Anda yang berada di sekitar lokasi kami! Kini hadir penawaran spesial yang siap menjawab kebutuhan Anda.\n\n"
            . "Keunggulan Utama untuk Anda:\n"
            . "✅ Proses cepat, mudah, dan bergaransi resmi\n"
            . "✅ Harga promo khusus pelanggan baru di area Anda\n"
            . "✅ Layanan responsif & siap bantu sampai tuntas\n"
            . "✅ Info Fokus: {$cleanTopic}\n\n"
            . $ctaLine;

        $headline = $destinationType === 'WHATSAPP'
            ? 'Chat WhatsApp & Klaim Promo Sekarang'
            : 'Cek Info & Promo Spesial Sekarang';

        return [
            'ai_primary_text' => $primaryText,
            'ai_headline' => Str::limit($headline, 40, ''),
            'ai_description' => 'Promo Area Lokal - Kuota Terbatas!',
            'tiktok_ad_text' => Str::limit("🔥 {$cleanTopic}! Buruan klik tombol di bawah sebelum kuota promo habis! #promolokal #fyp", 100, ''),
            'google_headline' => Str::limit('Klaim Promo Sekarang', 30, ''),
            'google_long_headline' => Str::limit("{$headline} — Proses Cepat, Hemat & Bergaransi!", 90, ''),
            'google_description' => Str::limit("Solusi terbaik {$cleanTopic}. Klik untuk konsultasi & klaim promo!", 90, ''),
        ];
    }
}
