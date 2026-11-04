<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    public const PROVIDER_OPENROUTER = 'OPENROUTER';
    public const PROVIDER_OPENAI = 'OPENAI';
    public const PROVIDER_GEMINI = 'GEMINI';
    public const PROVIDER_GROQ = 'GROQ';
    public const PROVIDER_DEEPSEEK = 'DEEPSEEK';
    public const PROVIDER_CUSTOM = 'CUSTOM';

    public const ENDPOINTS = [
        self::PROVIDER_OPENROUTER => 'https://openrouter.ai/api/v1/chat/completions',
        self::PROVIDER_OPENAI => 'https://api.openai.com/v1/chat/completions',
        self::PROVIDER_GEMINI => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
        self::PROVIDER_GROQ => 'https://api.groq.com/openai/v1/chat/completions',
        self::PROVIDER_DEEPSEEK => 'https://api.deepseek.com/chat/completions',
    ];

    public const DEFAULT_MODELS = [
        self::PROVIDER_OPENROUTER => 'openai/gpt-4o-mini',
        self::PROVIDER_OPENAI => 'gpt-4o-mini',
        self::PROVIDER_GEMINI => 'gemini-2.0-flash',
        self::PROVIDER_GROQ => 'llama-3.3-70b-versatile',
        self::PROVIDER_DEEPSEEK => 'deepseek-chat',
        self::PROVIDER_CUSTOM => 'gpt-4o-mini',
    ];

    protected $table = 'ai_providers';

    protected $fillable = [
        'name',
        'provider',
        'base_url',
        'api_key',
        'model',
        'is_default',
        'is_active',
        'priority',
        'last_tested_at',
        'last_test_ok',
        'last_test_message',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'last_test_ok' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function hasValidCredentials(): bool
    {
        return ! empty(trim((string) $this->api_key));
    }

    public function resolveEndpointUrl(): string
    {
        $custom = trim((string) $this->base_url);
        if ($custom !== '') {
            if (! str_ends_with($custom, '/chat/completions')) {
                return rtrim($custom, '/') . '/chat/completions';
            }

            return $custom;
        }

        $prov = strtoupper(trim((string) $this->provider));

        return self::ENDPOINTS[$prov] ?? self::ENDPOINTS[self::PROVIDER_OPENROUTER];
    }

    public function resolveModelName(): string
    {
        $m = trim((string) $this->model);
        if ($m !== '') {
            return $m;
        }

        $prov = strtoupper(trim((string) $this->provider));

        return self::DEFAULT_MODELS[$prov] ?? 'openai/gpt-4o-mini';
    }

    public function markAsDefault(): void
    {
        static::query()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true, 'is_active' => true]);
    }

    public static function defaultEndpointForProvider(string $provider, ?string $customBaseUrl = null): string
    {
        $custom = trim((string) $customBaseUrl);
        if ($custom !== '') {
            return str_ends_with($custom, '/chat/completions')
                ? $custom
                : rtrim($custom, '/') . '/chat/completions';
        }

        $prov = strtoupper(trim($provider));

        return self::ENDPOINTS[$prov] ?? self::ENDPOINTS[self::PROVIDER_OPENROUTER];
    }
}
