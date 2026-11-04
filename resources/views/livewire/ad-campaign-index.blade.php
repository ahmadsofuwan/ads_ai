<div class="space-y-6" wire:poll.8s>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Manajemen Kampanye Omni-Channel
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Kelola seluruh draft &amp; iklan tayang di Meta Ads, TikTok Ads, dan Google Ads dari satu tempat.
            </p>
        </div>

        <a href="{{ route('ads.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-sm shadow-blue-600/25">
            <span>+ Buat Iklan Baru di Studio AI</span>
        </a>
    </div>

    @if ($flashMessage)
        <div class="p-4 rounded-xl border flex items-start justify-between gap-3 {{ $flashType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400' }}">
            <div class="text-sm font-medium">{{ $flashMessage }}</div>
            <button wire:click="$set('flashMessage', null)" class="text-xs opacity-75 hover:opacity-100">✕</button>
        </div>
    @endif

    <!-- Filters Bar -->
    <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Cari nama kampanye, headline, atau ID Kampanye Meta/TikTok/Google..."
                   class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs text-slate-900 dark:text-zinc-100" />
        </div>

        <div>
            <select wire:model.live="statusFilter"
                    class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs text-slate-900 dark:text-zinc-100">
                <option value="">Semua Status</option>
                <option value="ACTIVE_ON_META">ACTIVE_ON_META</option>
                <option value="PAUSED_ON_META">PAUSED_ON_META</option>
                <option value="ACTIVE_ON_TIKTOK">ACTIVE_ON_TIKTOK</option>
                <option value="PAUSED_ON_TIKTOK">PAUSED_ON_TIKTOK</option>
                <option value="ACTIVE_ON_GOOGLE">ACTIVE_ON_GOOGLE</option>
                <option value="PAUSED_ON_GOOGLE">PAUSED_ON_GOOGLE</option>
                <option value="DRAFT">DRAFT</option>
                <option value="FAILED">FAILED</option>
            </select>
        </div>

        <div>
            <select wire:model.live="destinationFilter"
                    class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs text-slate-900 dark:text-zinc-100">
                <option value="">Semua Tujuan (WA &amp; Web)</option>
                <option value="WHATSAPP">WhatsApp</option>
                <option value="WEBSITE">Website</option>
            </select>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="space-y-4">
        @forelse ($campaigns as $ad)
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-1.5 min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400">
                                {{ $ad->destination_type }} • {{ $ad->objective }}
                            </span>
                            @if($ad->telegram_chat_id)
                                <span class="px-2 py-0.5 rounded text-[11px] bg-sky-500/10 text-sky-600 dark:text-sky-400">
                                    🤖 Via Telegram ({{ $ad->telegram_chat_id }})
                                </span>
                            @endif
                        </div>

                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            <a href="{{ route('ads.show', $ad->id) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                {{ $ad->title }}
                            </a>
                        </h3>

                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-zinc-400">
                            <span>💰 Budget: <strong class="text-slate-800 dark:text-zinc-200">{{ $ad->formatted_budget }}/hari</strong></span>
                            <span>👥 Usia: <strong class="text-slate-800 dark:text-zinc-200">{{ $ad->age_min }}–{{ $ad->age_max }} thn</strong></span>
                            <span>📍 Lokasi: <strong class="text-slate-800 dark:text-zinc-200">{{ is_array($ad->custom_locations) ? count($ad->custom_locations) : 0 }} Titik Koordinat</strong></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('ads.show', $ad->id) }}"
                           class="px-4 py-2 rounded-xl bg-slate-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-bold hover:opacity-90">
                            Detail, Metrik &amp; Kontrol Penuh →
                        </a>

                        <button wire:click="deleteDraft({{ $ad->id }})"
                                wire:confirm="Hapus data kampanye ini dari database lokal?"
                                class="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-500/10"
                                title="Hapus dari daftar lokal">
                            🗑️
                        </button>
                    </div>
                </div>

                <!-- 3 Platform Cards Inside Each Campaign Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <!-- 1. META ADS BOX -->
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-700/60 flex items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-bold text-slate-800 dark:text-zinc-200">📘 Meta Ads (v21.0)</div>
                            <div class="text-[11px] font-mono text-slate-500 dark:text-zinc-400 mt-0.5">
                                Status: <strong>{{ $ad->status }}</strong>
                            </div>
                        </div>
                        <div>
                            @if(in_array($ad->status, ['ACTIVE_ON_META', 'PAUSED_ON_META']))
                                <button wire:click="toggleMetaStatus({{ $ad->id }})"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $ad->status === 'ACTIVE_ON_META' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-300' : 'bg-emerald-600 text-white' }}">
                                    {{ $ad->status === 'ACTIVE_ON_META' ? '⏸️ Pause' : '🔥 Aktifkan' }}
                                </button>
                            @else
                                <button wire:click="publishPlatform({{ $ad->id }}, 'META')"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white">
                                    {{ $ad->status === 'FAILED' ? '🔄 Retry' : '🚀 Publish' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- 2. TIKTOK ADS BOX -->
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-700/60 flex items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-bold text-slate-800 dark:text-zinc-200">🎵 TikTok Ads (v1.3)</div>
                            <div class="text-[11px] font-mono text-slate-500 dark:text-zinc-400 mt-0.5">
                                Status: <strong>{{ $ad->tiktok_status ?: 'DRAFT' }}</strong>
                            </div>
                        </div>
                        <div>
                            @if(in_array($ad->tiktok_status, ['ACTIVE_ON_TIKTOK', 'PAUSED_ON_TIKTOK']))
                                <button wire:click="toggleTiktokStatus({{ $ad->id }})"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $ad->tiktok_status === 'ACTIVE_ON_TIKTOK' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-300' : 'bg-emerald-600 text-white' }}">
                                    {{ $ad->tiktok_status === 'ACTIVE_ON_TIKTOK' ? '⏸️ Pause' : '🔥 Aktifkan' }}
                                </button>
                            @else
                                <button wire:click="publishPlatform({{ $ad->id }}, 'TIKTOK')"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-pink-600 hover:bg-pink-500 text-white">
                                    {{ $ad->tiktok_status === 'FAILED' ? '🔄 Retry' : '🚀 Publish' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- 3. GOOGLE ADS BOX -->
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-700/60 flex items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-bold text-slate-800 dark:text-zinc-200">▶️ Google Ads (v18)</div>
                            <div class="text-[11px] font-mono text-slate-500 dark:text-zinc-400 mt-0.5">
                                Status: <strong>{{ $ad->google_status ?: 'DRAFT' }}</strong>
                            </div>
                        </div>
                        <div>
                            @if(in_array($ad->google_status, ['ACTIVE_ON_GOOGLE', 'PAUSED_ON_GOOGLE']))
                                <button wire:click="toggleGoogleStatus({{ $ad->id }})"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $ad->google_status === 'ACTIVE_ON_GOOGLE' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-300' : 'bg-emerald-600 text-white' }}">
                                    {{ $ad->google_status === 'ACTIVE_ON_GOOGLE' ? '⏸️ Pause' : '🔥 Aktifkan' }}
                                </button>
                            @else
                                <button wire:click="publishPlatform({{ $ad->id }}, 'GOOGLE')"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white">
                                    {{ $ad->google_status === 'FAILED' ? '🔄 Retry' : '🚀 Publish' }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-center space-y-3">
                <div class="text-sm font-semibold text-slate-700 dark:text-zinc-300">Tidak ada data kampanye ditemukan.</div>
                <a href="{{ route('ads.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-semibold">
                    ✨ Buat Iklan Baru
                </a>
            </div>
        @endforelse
    </div>

    <div>
        {{ $campaigns->links() }}
    </div>
</div>
