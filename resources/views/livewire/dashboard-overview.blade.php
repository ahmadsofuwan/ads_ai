<div class="space-y-8" wire:poll.10s>
    <!-- Top Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Ringkasan Dashboard Omni-Channel
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Pusat kendali otomatisasi iklan video Meta Ads v21.0, TikTok Ads v1.3, Google Ads v18, OpenRouter AI &amp; Bot Telegram.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="verifyAllConnections" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-semibold text-slate-700 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition shadow-sm">
                <svg wire:loading.remove wire:target="verifyAllConnections" class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg wire:loading wire:target="verifyAllConnections" class="w-4 h-4 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                <span>Cek Semua Koneksi &amp; Sinkronisasi</span>
            </button>

            <a href="{{ route('ads.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-sm shadow-blue-600/25 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Buat Iklan Baru dengan AI</span>
            </a>
        </div>
    </div>

    <!-- Alert Banner -->
    @if ($flashMessage)
        <div class="p-4 rounded-xl border flex items-start justify-between gap-3 {{ $flashType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400' }}">
            <div class="text-sm font-medium">{{ $flashMessage }}</div>
            <button wire:click="$set('flashMessage', null)" class="text-xs opacity-70 hover:opacity-100">✕</button>
        </div>
    @endif

    <!-- 4 Key Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">Total Iklan Aktif (LIVE)</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $totalActive }}</span>
                <span class="text-xs text-slate-500 dark:text-zinc-400">kampanye aktif</span>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400">
                <span>Paused: <strong class="text-slate-700 dark:text-zinc-200">{{ $totalPaused }}</strong></span>
                <span>Draft: <strong class="text-slate-700 dark:text-zinc-200">{{ $totalDrafts }}</strong></span>
                @if($totalFailed > 0)
                    <span class="text-rose-500 font-medium">Gagal: {{ $totalFailed }}</span>
                @endif
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">Total Pengeluaran Hari Ini</span>
                <span class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                    Rp {{ number_format($todaySpend, 0, ',', '.') }}
                </span>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zinc-800/80 text-xs text-slate-500 dark:text-zinc-400">
                Alokasi Budget Aktif: <strong class="text-slate-700 dark:text-zinc-200">Rp {{ number_format($activeDailyBudget, 0, ',', '.') }}/hari</strong>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">Total Klik &amp; Konversi Hari Ini</span>
                <span class="w-8 h-8 rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-3">
                <span class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($todayClicks, 0, ',', '.') }}</span>
                <span class="text-xs text-slate-500 dark:text-zinc-400">klik</span>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zinc-800/80 text-xs text-slate-500 dark:text-zinc-400">
                Konversi / Chat: <strong class="text-emerald-600 dark:text-emerald-400">{{ number_format($todayChats, 0, ',', '.') }}</strong>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">Tayangan (Impressions)</span>
                <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ number_format($todayImpressions, 0, ',', '.') }}</span>
                <span class="text-xs text-slate-500 dark:text-zinc-400">impresi</span>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zinc-800/80 text-xs text-slate-500 dark:text-zinc-400">
                Platform: <strong class="font-mono text-slate-700 dark:text-zinc-200">Meta • TikTok • Google</strong>
            </div>
        </div>
    </div>

    <!-- 5 API Connection Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach ([
            'meta' => ['title' => '📘 Meta API v21.0', 'dot' => $apiStatus['meta']['ok']],
            'tiktok' => ['title' => '🎵 TikTok API v1.3', 'dot' => $apiStatus['tiktok']['ok']],
            'google' => ['title' => '▶️ Google Ads v18', 'dot' => $apiStatus['google']['ok']],
            'openrouter' => ['title' => '🤖 OpenRouter AI', 'dot' => $apiStatus['openrouter']['ok']],
            'telegram' => ['title' => '📡 Telegram Bot', 'dot' => $apiStatus['telegram']['ok']],
        ] as $key => $card)
            <div class="p-3.5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 flex items-start gap-2.5">
                <div class="mt-1 w-2.5 h-2.5 rounded-full shrink-0 {{ $card['dot'] ? 'bg-emerald-500 shadow-sm shadow-emerald-500/50' : 'bg-amber-500' }}"></div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400">{{ $card['title'] }}</h3>
                        <a href="{{ route('settings.index') }}" class="text-[10px] text-blue-600 dark:text-blue-400 hover:underline">Atur</a>
                    </div>
                    <p class="mt-1 text-xs font-medium text-slate-800 dark:text-zinc-200 truncate" title="{{ $apiStatus[$key]['label'] }}">
                        {{ $apiStatus[$key]['label'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Recent Ads Table -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-zinc-800 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Daftar Iklan &amp; Draft Omni-Channel Terbaru</h2>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Menampilkan status kampanye di Meta Ads, TikTok Ads, dan Google Ads.</p>
            </div>
            <a href="{{ route('ads.index') }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                Lihat Semua Kampanye →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-900/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                        <th class="py-3.5 px-6">Kampanye &amp; Headline</th>
                        <th class="py-3.5 px-4">Tujuan &amp; Budget</th>
                        <th class="py-3.5 px-4">Lokasi &amp; Usia</th>
                        <th class="py-3.5 px-4">Status Platform (Meta / TikTok / Google)</th>
                        <th class="py-3.5 px-6 text-right">Aksi Cepat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-zinc-800 text-sm">
                    @forelse ($recentAds as $ad)
                        @php
                            $firstLoc = $ad->custom_locations[0] ?? null;
                            $pinCount = is_array($ad->custom_locations) ? count($ad->custom_locations) : 0;
                            $platforms = $ad->platforms;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-zinc-800/40 transition">
                            <td class="py-4 px-6">
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    <a href="{{ route('ads.show', $ad->id) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ $ad->title }}
                                    </a>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 line-clamp-1">
                                    {{ $ad->ai_headline }}
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $ad->destination_type === 'WHATSAPP' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400' }}">
                                        {{ $ad->destination_type }}
                                    </span>
                                    <span class="font-bold text-slate-900 dark:text-zinc-100">{{ $ad->formatted_budget }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-xs text-slate-600 dark:text-zinc-300">
                                @if($firstLoc)
                                    <div>📍 {{ $pinCount }} Titik ({{ $firstLoc['radius'] ?? 10 }} km)</div>
                                @endif
                                <div class="text-slate-400 dark:text-zinc-500 mt-0.5">Usia {{ $ad->age_min }}–{{ $ad->age_max }} thn</div>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                    @if(in_array('META', $platforms))
                                        <span class="px-2 py-0.5 rounded-md border {{ $ad->status === 'ACTIVE_ON_META' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : ($ad->status === 'PAUSED_ON_META' ? 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400' : ($ad->status === 'FAILED' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600' : 'bg-slate-100 dark:bg-zinc-800 border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300')) }}">
                                            📘 Meta: {{ str_replace('_ON_META', '', $ad->status) }}
                                        </span>
                                    @endif

                                    @if(in_array('TIKTOK', $platforms) || $ad->tiktok_campaign_id)
                                        <span class="px-2 py-0.5 rounded-md border {{ $ad->tiktok_status === 'ACTIVE_ON_TIKTOK' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : ($ad->tiktok_status === 'PAUSED_ON_TIKTOK' ? 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400' : ($ad->tiktok_status === 'FAILED' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600' : 'bg-slate-100 dark:bg-zinc-800 border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300')) }}">
                                            🎵 TikTok: {{ str_replace('_ON_TIKTOK', '', $ad->tiktok_status ?: 'DRAFT') }}
                                        </span>
                                    @endif

                                    @if(in_array('GOOGLE', $platforms) || $ad->google_campaign_id)
                                        <span class="px-2 py-0.5 rounded-md border {{ $ad->google_status === 'ACTIVE_ON_GOOGLE' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : ($ad->google_status === 'PAUSED_ON_GOOGLE' ? 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400' : ($ad->google_status === 'FAILED' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600' : 'bg-slate-100 dark:bg-zinc-800 border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300')) }}">
                                            ▶️ Google: {{ str_replace('_ON_GOOGLE', '', $ad->google_status ?: 'DRAFT') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button wire:click="publishNow({{ $ad->id }})"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-500 text-white transition">
                                        🚀 Terbitkan
                                    </button>
                                    <a href="{{ route('ads.show', $ad->id) }}"
                                       class="px-3 py-1.5 rounded-lg text-xs font-medium border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800">
                                        Detail &amp; Kontrol
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-500 dark:text-zinc-400">
                                <div class="max-w-sm mx-auto space-y-3">
                                    <p class="text-sm font-medium text-slate-700 dark:text-zinc-300">Belum ada kampanye atau draft iklan.</p>
                                    <a href="{{ route('ads.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-semibold">
                                        ✨ Buka Studio Pembuat Iklan AI
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
