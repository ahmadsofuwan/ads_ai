<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 text-xs font-semibold mb-2">
                <span>⚡ Multi-Provider &amp; Multi-API Account Architecture</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Pengaturan Sistem &amp; Kredensial Omni-Channel
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Kelola banyak akun iklan (Multi Ad Account) untuk Meta, TikTok &amp; Google Ads, serta Multi-Provider AI (OpenRouter, OpenAI, Gemini, Groq, DeepSeek).
            </p>
        </div>
        <button type="button"
                wire:click="saveSettings"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold shadow-md shadow-blue-600/25 transition">
            <span>💾 Simpan Pengaturan Utama</span>
        </button>
    </div>

    @if ($feedbackMessage)
        <div class="p-4 rounded-xl border flex items-start justify-between gap-3 {{ $feedbackType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400' }}">
            <div class="text-sm font-medium">{{ $feedbackMessage }}</div>
            <button wire:click="$set('feedbackMessage', null)" class="text-xs opacity-75 hover:opacity-100">✕</button>
        </div>
    @endif

    <!-- =================================================================== -->
    <!-- SECTION A: MULTI-ACCOUNT ADS API MANAGER (UNLIMITED ACCOUNTS/PROVIDER) -->
    <!-- =================================================================== -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏢 Manajemen Multi-Akun API Iklan (Meta • TikTok • Google Ads)</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-zinc-400">
                    Tambahkan banyak Ad Account / Business Manager per platform. Anda dapat memilih akun spesifik atau broadcast ke beberapa akun sekaligus di Studio Iklan.
                </p>
            </div>
            @if ($editingAdAccountId)
                <button type="button"
                        wire:click="resetAdAccountForm"
                        class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-zinc-700 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800">
                    + Batal Edit / Buat Akun Baru
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Form Tambah / Edit AdAccount (5 Cols) -->
            <div class="lg:col-span-5 p-5 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-700 space-y-4">
                <div class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ $editingAdAccountId ? '✏️ Edit Akun Iklan #' . $editingAdAccountId : '➕ Tambah Akun API Iklan Baru' }}
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Nama / Label Akun Iklan
                        </label>
                        <input type="text" wire:model="accountName"
                               placeholder="Contoh: Meta BM 1 - Cabang Jambi / TikTok Shop Resmi"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs" />
                        @error('accountName') <span class="text-[11px] text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                Platform / Provider Ads
                            </label>
                            <select wire:model.live="accountProvider"
                                    class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs font-semibold">
                                <option value="META">📘 META ADS (v21.0)</option>
                                <option value="TIKTOK">🎵 TIKTOK ADS (v1.3)</option>
                                <option value="GOOGLE">▶️ GOOGLE ADS (v18)</option>
                            </select>
                        </div>

                        <div class="flex flex-col justify-end gap-1.5 pb-1">
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-zinc-300 cursor-pointer">
                                <input type="checkbox" wire:model="accountIsDefault" class="rounded text-blue-600" />
                                <span>Jadikan Default Platform</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-zinc-300 cursor-pointer">
                                <input type="checkbox" wire:model="accountIsActive" class="rounded text-emerald-600" />
                                <span>Status Aktif</span>
                            </label>
                        </div>
                    </div>

                    @if ($accountProvider === 'META')
                        <div class="pt-2 border-t border-slate-200 dark:border-zinc-700 space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Meta Access Token</label>
                                <textarea wire:model="accMetaAccessToken" rows="2" placeholder="EAABsbCS1iHgBO..."
                                          class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Ad Account ID (act_)</label>
                                    <input type="text" wire:model="accMetaAdAccountId" placeholder="act_123456789"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Facebook Page ID</label>
                                    <input type="text" wire:model="accMetaPageId" placeholder="10987654321"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">No. WhatsApp (Opsional)</label>
                                    <input type="text" wire:model="accMetaWhatsappNumber" placeholder="628123456789"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Meta Pixel ID (Opsional)</label>
                                    <input type="text" wire:model="accMetaPixelId" placeholder="987654321"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                            </div>
                        </div>
                    @elseif ($accountProvider === 'TIKTOK')
                        <div class="pt-2 border-t border-slate-200 dark:border-zinc-700 space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">TikTok Access Token</label>
                                <input type="password" wire:model="accTiktokAccessToken" placeholder="Access-Token TikTok Business API..."
                                       class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Advertiser ID</label>
                                    <input type="text" wire:model="accTiktokAdvertiserId" placeholder="71234567890"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Identity ID (Opsional)</label>
                                    <input type="text" wire:model="accTiktokIdentityId" placeholder="ID Identity"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Default Location ID</label>
                                    <input type="text" wire:model="accTiktokDefaultLocationId" placeholder="1643084"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">TikTok Pixel ID</label>
                                    <input type="text" wire:model="accTiktokPixelId" placeholder="Opsional"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                            </div>
                        </div>
                    @elseif ($accountProvider === 'GOOGLE')
                        <div class="pt-2 border-t border-slate-200 dark:border-zinc-700 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Developer Token</label>
                                    <input type="password" wire:model="accGoogleDeveloperToken" placeholder="ABcdeFG..."
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Customer ID</label>
                                    <input type="text" wire:model="accGoogleCustomerId" placeholder="123-456-7890"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">OAuth2 Client ID</label>
                                    <input type="text" wire:model="accGoogleClientId" placeholder="xxx.apps.googleusercontent.com"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">OAuth2 Client Secret</label>
                                    <input type="password" wire:model="accGoogleClientSecret" placeholder="GOCSPX-..."
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">OAuth2 Refresh Token</label>
                                    <input type="password" wire:model="accGoogleRefreshToken" placeholder="1//0g..."
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Login Customer ID (MCC)</label>
                                    <input type="text" wire:model="accGoogleLoginCustomerId" placeholder="Opsional"
                                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                                </div>
                            </div>
                        </div>
                    @endif

                    <button type="button"
                            wire:click="saveAdAccount"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-sm transition">
                        <span>{{ $editingAdAccountId ? '💾 Perbarui Akun API Iklan' : '➕ Simpan Akun API Iklan Baru' }}</span>
                    </button>
                </div>
            </div>

            <!-- Daftar Akun Terdaftar (7 Cols) -->
            <div class="lg:col-span-7 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                        Daftar Akun API Iklan Tersimpan ({{ count($adAccounts) }} Akun)
                    </span>
                </div>

                @if (count($adAccounts) === 0)
                    <div class="p-8 rounded-2xl border border-dashed border-slate-300 dark:border-zinc-700 text-center space-y-2">
                        <div class="text-sm font-semibold text-slate-700 dark:text-zinc-300">
                            Belum ada akun tambahan di tabel Multi-Account
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 max-w-md mx-auto">
                            Sistem saat ini menggunakan kredensial utama di bawah. Tambahkan akun di formulir sebelah kiri jika Anda ingin mengelola banyak Ad Account untuk Meta, TikTok, atau Google Ads.
                        </p>
                    </div>
                @else
                    <div class="space-y-2.5 max-h-[460px] overflow-y-auto pr-1">
                        @foreach ($adAccounts as $acc)
                            <div class="p-4 rounded-xl border {{ $acc->is_default ? 'border-blue-500/40 bg-blue-500/5' : 'border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-950/40' }} flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="space-y-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                            {{ $acc->provider === 'META' ? 'bg-blue-600 text-white' : ($acc->provider === 'TIKTOK' ? 'bg-pink-600 text-white' : 'bg-amber-600 text-white') }}">
                                            {{ $acc->provider_badge_label }}
                                        </span>
                                        <span class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $acc->name }}</span>
                                        @if ($acc->is_default)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                                ⭐ Default {{ $acc->provider }}
                                            </span>
                                        @endif
                                        @if (! $acc->is_active)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-500/15 text-slate-500">
                                                Nonaktif
                                            </span>
                                        @endif
                                    </div>
                                    <div class="font-mono text-xs text-slate-500 dark:text-zinc-400">
                                        {{ $acc->identifier_label }}
                                    </div>
                                    @if ($acc->last_tested_at)
                                        <div class="text-[11px] {{ $acc->last_test_ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                                            {{ $acc->last_test_ok ? '● Terhubung:' : '● Gagal:' }} {{ \Illuminate\Support\Str::limit($acc->last_test_message, 90) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                                    <button type="button"
                                            wire:click="testSingleAdAccount({{ $acc->id }})"
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-900 dark:bg-zinc-700 text-white text-[11px] font-semibold hover:opacity-90">
                                        🔌 Tes API
                                    </button>
                                    @if (! $acc->is_default)
                                        <button type="button"
                                                wire:click="setDefaultAdAccount({{ $acc->id }})"
                                                class="px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-zinc-700 text-[11px] font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800">
                                            ⭐ Default
                                        </button>
                                    @endif
                                    <button type="button"
                                            wire:click="editAdAccount({{ $acc->id }})"
                                            class="px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-zinc-700 text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-500/10">
                                        Edit
                                    </button>
                                    <button type="button"
                                            wire:click="deleteAdAccount({{ $acc->id }})"
                                            class="px-2.5 py-1.5 rounded-lg border border-rose-500/30 text-[11px] font-semibold text-rose-500 hover:bg-rose-500/10">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- SECTION B: MULTI-PROVIDER AI LLM MANAGER (OPENROUTER/OPENAI/GEMINI/GROQ/DEEPSEEK) -->
    <!-- =================================================================== -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🤖 Manajemen Multi-Provider AI &amp; Auto-Fallback LLM</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-zinc-400">
                    Mendukung banyak provider AI sekaligus (OpenRouter, OpenAI, Google Gemini, Groq, DeepSeek, Custom Endpoint). Jika provider utama limit/gangguan, sistem otomatis beralih ke provider aktif berikutnya.
                </p>
            </div>
            @if ($editingAiProviderId)
                <button type="button"
                        wire:click="resetAiProviderForm"
                        class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-zinc-700 text-xs font-semibold text-slate-700 dark:text-zinc-300">
                    + Batal Edit / Tambah Provider Baru
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Form AI Provider (5 Cols) -->
            <div class="lg:col-span-5 p-5 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-700 space-y-3">
                <div class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ $editingAiProviderId ? '✏️ Edit Profil AI Provider #' . $editingAiProviderId : '➕ Tambah Profil AI Provider Baru' }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Nama Profil AI</label>
                        <input type="text" wire:model="aiProfileName" placeholder="Contoh: Groq Llama 3.3 Cepat"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs" />
                        @error('aiProfileName') <span class="text-[11px] text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Jenis Provider AI</label>
                        <select wire:model.live="aiProfileProvider"
                                class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs font-semibold">
                            <option value="OPENROUTER">OpenRouter.ai</option>
                            <option value="OPENAI">OpenAI Official</option>
                            <option value="GEMINI">Google Gemini API</option>
                            <option value="GROQ">Groq Cloud (Fast LPU)</option>
                            <option value="DEEPSEEK">DeepSeek API</option>
                            <option value="CUSTOM">Custom OpenAI-Compatible</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">API Key Provider</label>
                    <input type="password" wire:model="aiProfileApiKey" placeholder="sk-... / gsk_... / AIza..."
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Model ID</label>
                        <input type="text" wire:model="aiProfileModel" placeholder="openai/gpt-4o-mini"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Prioritas</label>
                        <input type="number" min="1" max="999" wire:model="aiProfilePriority"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                    </div>
                </div>

                @if ($aiProfileProvider === 'CUSTOM')
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Custom Base URL (OpenAI Compatible)</label>
                        <input type="text" wire:model="aiProfileBaseUrl" placeholder="https://api.provider-anda.com/v1/chat/completions"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                    </div>
                @endif

                <div class="flex items-center gap-4 pt-1">
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-zinc-300 cursor-pointer">
                        <input type="checkbox" wire:model="aiProfileIsDefault" class="rounded text-blue-600" />
                        <span>Jadikan Default Utama</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-zinc-300 cursor-pointer">
                        <input type="checkbox" wire:model="aiProfileIsActive" class="rounded text-emerald-600" />
                        <span>Aktif (Ikut Fallback)</span>
                    </label>
                </div>

                <button type="button"
                        wire:click="saveAiProvider"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm transition">
                    <span>{{ $editingAiProviderId ? '💾 Perbarui Profil AI Provider' : '➕ Simpan Profil AI Provider' }}</span>
                </button>
            </div>

            <!-- Daftar AI Providers (7 Cols) -->
            <div class="lg:col-span-7 space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                    Daftar Provider AI Terdaftar ({{ count($aiProviders) }} Provider)
                </div>

                @if (count($aiProviders) === 0)
                    <div class="p-8 rounded-2xl border border-dashed border-slate-300 dark:border-zinc-700 text-center space-y-2">
                        <div class="text-sm font-semibold text-slate-700 dark:text-zinc-300">
                            Belum ada profil AI tambahan di tabel Multi-Provider
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 max-w-md mx-auto">
                            Anda dapat menambahkan OpenRouter, OpenAI, Gemini, Groq, atau DeepSeek di sebelah kiri sebagai provider utama maupun cadangan otomatis (auto-fallback).
                        </p>
                    </div>
                @else
                    <div class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                        @foreach ($aiProviders as $prov)
                            <div class="p-4 rounded-xl border {{ $prov->is_default ? 'border-indigo-500/40 bg-indigo-500/5' : 'border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-950/40' }} flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="space-y-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-600 text-white">
                                            {{ $prov->provider }}
                                        </span>
                                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $prov->name }}</span>
                                        @if ($prov->is_default)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                                ⭐ Default
                                            </span>
                                        @endif
                                        <span class="text-[11px] font-mono text-slate-400">Prioritas #{{ $prov->priority }}</span>
                                    </div>
                                    <div class="font-mono text-xs text-slate-500 dark:text-zinc-400">
                                        Model: {{ $prov->model }} • Endpoint: {{ \Illuminate\Support\Str::limit($prov->resolveEndpointUrl(), 55) }}
                                    </div>
                                    @if ($prov->last_tested_at)
                                        <div class="text-[11px] {{ $prov->last_test_ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                                            {{ $prov->last_test_ok ? '● Terhubung:' : '● Gagal:' }} {{ \Illuminate\Support\Str::limit($prov->last_test_message, 90) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                                    <button type="button"
                                            wire:click="testSingleAiProvider({{ $prov->id }})"
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-900 dark:bg-zinc-700 text-white text-[11px] font-semibold">
                                        🤖 Tes AI
                                    </button>
                                    @if (! $prov->is_default)
                                        <button type="button"
                                                wire:click="setDefaultAiProvider({{ $prov->id }})"
                                                class="px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-zinc-700 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">
                                            ⭐ Default
                                        </button>
                                    @endif
                                    <button type="button"
                                            wire:click="editAiProvider({{ $prov->id }})"
                                            class="px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-zinc-700 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                                        Edit
                                    </button>
                                    <button type="button"
                                            wire:click="deleteAiProvider({{ $prov->id }})"
                                            class="px-2.5 py-1.5 rounded-lg border border-rose-500/30 text-[11px] font-semibold text-rose-500">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- SECTION C: DEFAULT / FALLBACK SYSTEM CREDENTIALS & TELEGRAM WEBHOOK -->
    <!-- =================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left 7 Cols: Default Meta API + TikTok API + Google Ads API -->
        <div class="lg:col-span-7 space-y-6">
            <!-- 1. Meta Marketing API v21.0 Card -->
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">1. 📘 Kredensial Utama Meta Graph API v21.0</h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">Digunakan sebagai fallback/utama jika tidak memilih akun spesifik di tabel Multi-Account.</p>
                    </div>
                    <button type="button"
                            wire:click="testMetaConnection"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition shrink-0">
                        <span wire:loading.remove wire:target="testMetaConnection">🔌 Tes Koneksi Meta API</span>
                        <span wire:loading wire:target="testMetaConnection">⏳ Menguji...</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Meta Access Token (System User Token Permanen)
                        </label>
                        <textarea wire:model="metaAccessToken"
                                  rows="2"
                                  placeholder="EAABsbCS1iHgBO..."
                                  class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs text-slate-900 dark:text-zinc-100"></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                Meta Ad Account ID (Otomatis awalan <code class="text-blue-500">act_</code>)
                            </label>
                            <input type="text" wire:model="metaAdAccountId" placeholder="act_123456789012345"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                Meta Facebook Page ID
                            </label>
                            <input type="text" wire:model="metaPageId" placeholder="109876543210987"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                Nomor WhatsApp Bisnis (Opsional)
                            </label>
                            <input type="text" wire:model="metaWhatsappNumber" placeholder="6281234567890"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                Meta Pixel ID / Dataset ID (Opsional)
                            </label>
                            <input type="text" wire:model="metaPixelId" placeholder="987654321012345"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. TikTok Business / Marketing API v1.3 Card -->
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">2. 🎵 Kredensial Utama TikTok Marketing API v1.3</h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">Otomatisasi upload video &amp; pembuatan Campaign/AdGroup/Ad di TikTok For Business.</p>
                    </div>
                    <button type="button"
                            wire:click="testTiktokConnection"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-xs font-bold transition shrink-0">
                        <span wire:loading.remove wire:target="testTiktokConnection">🔌 Tes Koneksi TikTok API</span>
                        <span wire:loading wire:target="testTiktokConnection">⏳ Menguji...</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            TikTok Access Token (<code class="text-pink-500">Access-Token</code> Header)
                        </label>
                        <input type="password" wire:model="tiktokAccessToken" placeholder="Masukkan Long-Term Access Token dari TikTok Marketing API..."
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                TikTok Advertiser ID
                            </label>
                            <input type="text" wire:model="tiktokAdvertiserId" placeholder="7123456789012345678"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                TikTok Identity ID (Custom User / Spark Ads)
                            </label>
                            <input type="text" wire:model="tiktokIdentityId" placeholder="Opsional: ID Identity TikTok"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                TikTok Default Location ID
                            </label>
                            <input type="text" wire:model="tiktokDefaultLocationId" placeholder="1643084 (Indonesia)"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                TikTok Pixel ID (Opsional)
                            </label>
                            <input type="text" wire:model="tiktokPixelId" placeholder="C1234567890"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Google Ads API v18 & YouTube Data API v3 Card -->
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">3. ▶️ Kredensial Utama Google Ads API v18 &amp; YouTube OAuth2</h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">Mendukung Proximity Geo-Radius (Titik Koordinat + KM) &amp; Auto-Upload YouTube Unlisted.</p>
                    </div>
                    <button type="button"
                            wire:click="testGoogleConnection"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition shrink-0">
                        <span wire:loading.remove wire:target="testGoogleConnection">🔌 Tes Koneksi Google Ads</span>
                        <span wire:loading wire:target="testGoogleConnection">⏳ Menguji...</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Google Ads Developer Token
                        </label>
                        <input type="password" wire:model="googleDeveloperToken" placeholder="ABcdeFG12345..."
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Google Ads Customer ID (Tanpa/Dengan Strip)
                        </label>
                        <input type="text" wire:model="googleCustomerId" placeholder="123-456-7890"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            OAuth2 Client ID (Google Cloud Console)
                        </label>
                        <input type="text" wire:model="googleClientId" placeholder="xxx.apps.googleusercontent.com"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            OAuth2 Client Secret
                        </label>
                        <input type="password" wire:model="googleClientSecret" placeholder="GOCSPX-..."
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            OAuth2 Refresh Token (Scope: <code class="text-amber-500">adwords</code> &amp; <code class="text-amber-500">youtube.upload</code>)
                        </label>
                        <input type="password" wire:model="googleRefreshToken" placeholder="1//0g..."
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Login Customer ID / Manager MCC ID (Opsional)
                        </label>
                        <input type="text" wire:model="googleLoginCustomerId" placeholder="Kosongkan jika bukan lewat akun MCC"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Right 5 Cols: Primary AI + Telegram Webhook + Default Map Coordinates -->
        <div class="lg:col-span-5 space-y-6">
            <!-- 4. Primary AI Configuration Card -->
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">4. 🤖 Konfigurasi AI Provider Utama</h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">Penyusun copywriting Meta, TikTok &amp; Google Ads.</p>
                    </div>
                    <button type="button"
                            wire:click="testOpenRouterConnection"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-300 dark:border-zinc-700 text-xs font-bold text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 shrink-0">
                        <span wire:loading.remove wire:target="testOpenRouterConnection">🤖 Tes AI Utama</span>
                        <span wire:loading wire:target="testOpenRouterConnection">⏳ Menguji...</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Provider AI Default
                        </label>
                        <select wire:model.live="aiProvider"
                                class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs font-semibold">
                            <option value="OPENROUTER">OpenRouter.ai (Multi-Model Gateway)</option>
                            <option value="OPENAI">OpenAI Official API</option>
                            <option value="GEMINI">Google Gemini OpenAI-Compatible API</option>
                            <option value="GROQ">Groq Cloud API</option>
                            <option value="DEEPSEEK">DeepSeek Official API</option>
                            <option value="CUSTOM">Custom Endpoint URL</option>
                        </select>
                    </div>

                    @if ($aiProvider === 'CUSTOM')
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                                Custom AI Base URL
                            </label>
                            <input type="text" wire:model="aiBaseUrl" placeholder="https://api.custom-llm.com/v1/chat/completions"
                                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            API Key Utama ({{ $aiProvider }})
                        </label>
                        <input type="password" wire:model="openrouterApiKey" placeholder="sk-or-v1-... / sk-..."
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Model ID Utama
                        </label>
                        <input type="text" wire:model="openrouterModel"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-sm" />
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach (['openai/gpt-4o-mini', 'anthropic/claude-3.5-sonnet', 'google/gemini-2.0-flash-001', 'gpt-4o-mini', 'llama-3.3-70b-versatile', 'deepseek-chat'] as $presetModel)
                                <button type="button"
                                        wire:click="$set('openrouterModel', '{{ $presetModel }}')"
                                        class="px-2 py-0.5 rounded-lg text-[11px] font-mono border {{ $openrouterModel === $presetModel ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-400' }}">
                                    {{ $presetModel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Telegram Bot & Webhook Installer Card -->
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
                <div class="border-b border-slate-200 dark:border-zinc-800 pb-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">5. 📡 Bot Telegram &amp; Webhook Otomatis</h2>
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Terima video iklan, Share Location, dan terbitkan ke Meta/TikTok/Google langsung dari Telegram.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Telegram Bot Token (@BotFather)
                        </label>
                        <input type="text" wire:model="telegramBotToken" placeholder="1234567890:AAHxyz..."
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Daftar ID Telegram yang Diizinkan (<code class="text-blue-500">allowed_telegram_chat_ids</code>)
                        </label>
                        <input type="text" wire:model="allowedTelegramChatIdsText" placeholder="Contoh: 123456789, 987654321"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700 space-y-3">
                        <label class="block text-xs font-bold text-slate-800 dark:text-zinc-200">
                            📡 Pasang Webhook Telegram Otomatis
                        </label>
                        <input type="text" wire:model="webhookDomainUrl" placeholder="https://domain-anda.com/api/telegram/webhook"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                        <button type="button"
                                wire:click="installTelegramWebhook"
                                wire:loading.attr="disabled"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold shadow-sm transition">
                            <span wire:loading.remove wire:target="installTelegramWebhook">📡 Pasang Webhook Telegram Otomatis</span>
                            <span wire:loading wire:target="installTelegramWebhook">⏳ Memanggil Endpoint setWebhook...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 6. Default Geo Coordinates Card -->
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-4">
                <div class="border-b border-slate-200 dark:border-zinc-800 pb-3">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">6. 📍 Titik Koordinat Lokasi Default</h2>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Default Latitude</label>
                        <input type="number" step="0.000001" wire:model.live="defaultLatitude"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Default Longitude</label>
                        <input type="number" step="0.000001" wire:model.live="defaultLongitude"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                    </div>
                </div>

                <div wire:ignore class="rounded-xl overflow-hidden border border-slate-200 dark:border-zinc-700">
                    <div id="settings-leaflet-map" class="w-full h-48"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            const mapEl = document.getElementById('settings-leaflet-map');
            if (!mapEl) return;

            const initLat = parseFloat(@json($defaultLatitude)) || -1.610123;
            const initLng = parseFloat(@json($defaultLongitude)) || 103.613120;

            const map = L.map('settings-leaflet-map').setView([initLat, initLng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            let marker = L.marker([initLat, initLng]).addTo(map);
            let circle = L.circle([initLat, initLng], {
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.2,
                radius: 10000
            }).addTo(map);

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                circle.setLatLng(e.latlng);
                @this.setDefaultCoordsFromMap(e.latlng.lat, e.latlng.lng);
            });
        });
    </script>
</div>
