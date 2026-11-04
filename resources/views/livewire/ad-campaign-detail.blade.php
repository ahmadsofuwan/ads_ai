<div class="space-y-6" wire:poll.6s="loadInsights">
    <!-- Top Breadcrumb -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('ads.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">← Manajemen Kampanye</a>
                <span>/</span>
                <span>Detail Iklan Omni-Channel #{{ $draft->id }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ $draft->title }}
            </h1>
        </div>
    </div>

    @if ($flashMessage)
        <div class="p-4 rounded-xl border flex items-start justify-between gap-3 {{ $flashType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400' }}">
            <div class="text-sm font-medium">{{ $flashMessage }}</div>
            <button wire:click="$set('flashMessage', null)" class="text-xs opacity-75 hover:opacity-100">✕</button>
        </div>
    @endif

    <!-- 3 OMNI-CHANNEL CONTROL PANELS (META + TIKTOK + GOOGLE ADS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- 1. META ADS PANEL -->
        <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm flex flex-col justify-between space-y-4">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-900 dark:text-white">📘 Meta Ads (v21.0)</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $draft->status === 'ACTIVE_ON_META' ? 'bg-emerald-500/15 text-emerald-600' : ($draft->status === 'PAUSED_ON_META' ? 'bg-amber-500/15 text-amber-600' : ($draft->status === 'FAILED' ? 'bg-rose-500/15 text-rose-600' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300')) }}">
                        {{ $draft->status }}
                    </span>
                </div>

                <div class="space-y-1 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                    @if (!empty($metaAccounts) && count($metaAccounts) > 0)
                        <div class="pb-1">
                            <select wire:model="metaAccountId" wire:change="saveChanges"
                                    class="w-full rounded-lg border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-[11px] font-sans font-semibold py-1">
                                <option value="">🏢 Default Meta Account</option>
                                @foreach ($metaAccounts as $mAcc)
                                    <option value="{{ $mAcc->id }}">{{ $mAcc->name }} ({{ $mAcc->meta_ad_account_id }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>Video ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->meta_video_id ?: '-' }}</strong></div>
                    <div>Campaign ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->meta_campaign_id ?: '-' }}</strong></div>
                    <div>AdSet ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->meta_adset_id ?: '-' }}</strong></div>
                    <div>Creative ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->meta_creative_id ?: '-' }}</strong></div>
                    <div>Ad ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->meta_ad_id ?: '-' }}</strong></div>
                </div>

                @if ($draft->error_log)
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/25 text-[11px] font-mono text-rose-600 dark:text-rose-400 whitespace-pre-wrap max-h-40 overflow-y-auto">{{ $draft->error_log }}</div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex flex-wrap gap-2">
                @if(in_array($draft->status, ['ACTIVE_ON_META', 'PAUSED_ON_META']))
                    <button wire:click="toggleMetaStatus"
                            class="flex-1 py-2 px-3 rounded-xl text-xs font-bold {{ $draft->status === 'ACTIVE_ON_META' ? 'bg-amber-500 text-slate-950' : 'bg-emerald-600 text-white' }}">
                        {{ $draft->status === 'ACTIVE_ON_META' ? '⏸️ Pause Meta Ads' : '🔥 Aktifkan Meta Ads' }}
                    </button>
                @else
                    <button wire:click="retryPublishQueue"
                            class="flex-1 py-2 px-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold">
                        {{ $draft->status === 'FAILED' ? '🔄 Retry Queue' : '🚀 Publish Queue' }}
                    </button>
                    <button wire:click="retryPublishSync"
                            class="py-2 px-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-zinc-800">
                        ⚡ Sync
                    </button>
                @endif
            </div>
        </div>

        <!-- 2. TIKTOK ADS PANEL -->
        <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm flex flex-col justify-between space-y-4">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-900 dark:text-white">🎵 TikTok Ads (v1.3)</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $draft->tiktok_status === 'ACTIVE_ON_TIKTOK' ? 'bg-emerald-500/15 text-emerald-600' : ($draft->tiktok_status === 'PAUSED_ON_TIKTOK' ? 'bg-amber-500/15 text-amber-600' : ($draft->tiktok_status === 'FAILED' ? 'bg-rose-500/15 text-rose-600' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300')) }}">
                        {{ $draft->tiktok_status ?: 'DRAFT' }}
                    </span>
                </div>

                <div class="space-y-1 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                    @if (!empty($tiktokAccounts) && count($tiktokAccounts) > 0)
                        <div class="pb-1">
                            <select wire:model="tiktokAccountId" wire:change="saveChanges"
                                    class="w-full rounded-lg border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-[11px] font-sans font-semibold py-1">
                                <option value="">🏢 Default TikTok Account</option>
                                @foreach ($tiktokAccounts as $tAcc)
                                    <option value="{{ $tAcc->id }}">{{ $tAcc->name }} (Adv: {{ $tAcc->tiktok_advertiser_id }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>Video ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->tiktok_video_id ?: '-' }}</strong></div>
                    <div>Cover Image ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->tiktok_image_id ?: '-' }}</strong></div>
                    <div>Campaign ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->tiktok_campaign_id ?: '-' }}</strong></div>
                    <div>AdGroup ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->tiktok_adgroup_id ?: '-' }}</strong></div>
                    <div>Ad ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->tiktok_ad_id ?: '-' }}</strong></div>
                </div>

                @if ($draft->tiktok_error_log)
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/25 text-[11px] font-mono text-rose-600 dark:text-rose-400 whitespace-pre-wrap max-h-40 overflow-y-auto">{{ $draft->tiktok_error_log }}</div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex flex-wrap gap-2">
                @if(in_array($draft->tiktok_status, ['ACTIVE_ON_TIKTOK', 'PAUSED_ON_TIKTOK']))
                    <button wire:click="toggleTiktokStatus"
                            class="flex-1 py-2 px-3 rounded-xl text-xs font-bold {{ $draft->tiktok_status === 'ACTIVE_ON_TIKTOK' ? 'bg-amber-500 text-slate-950' : 'bg-emerald-600 text-white' }}">
                        {{ $draft->tiktok_status === 'ACTIVE_ON_TIKTOK' ? '⏸️ Pause TikTok Ads' : '🔥 Aktifkan TikTok Ads' }}
                    </button>
                @else
                    <button wire:click="retryTiktokQueue"
                            class="flex-1 py-2 px-3 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-xs font-bold">
                        {{ $draft->tiktok_status === 'FAILED' ? '🔄 Retry Queue' : '🚀 Publish Queue' }}
                    </button>
                    <button wire:click="retryTiktokSync"
                            class="py-2 px-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-zinc-800">
                        ⚡ Sync
                    </button>
                @endif
            </div>
        </div>

        <!-- 3. GOOGLE ADS PANEL -->
        <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm flex flex-col justify-between space-y-4">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-900 dark:text-white">▶️ Google Ads (v18)</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $draft->google_status === 'ACTIVE_ON_GOOGLE' ? 'bg-emerald-500/15 text-emerald-600' : ($draft->google_status === 'PAUSED_ON_GOOGLE' ? 'bg-amber-500/15 text-amber-600' : ($draft->google_status === 'FAILED' ? 'bg-rose-500/15 text-rose-600' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300')) }}">
                        {{ $draft->google_status ?: 'DRAFT' }}
                    </span>
                </div>

                <div class="space-y-1 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                    @if (!empty($googleAccounts) && count($googleAccounts) > 0)
                        <div class="pb-1">
                            <select wire:model="googleAccountId" wire:change="saveChanges"
                                    class="w-full rounded-lg border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-[11px] font-sans font-semibold py-1">
                                <option value="">🏢 Default Google Ads Account</option>
                                @foreach ($googleAccounts as $gAcc)
                                    <option value="{{ $gAcc->id }}">{{ $gAcc->name }} (CID: {{ $gAcc->google_customer_id }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>YouTube Video ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->youtube_video_id ?: '-' }}</strong></div>
                    <div>Video Asset: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->google_video_asset_resource ?: '-' }}</strong></div>
                    <div>Campaign ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->google_campaign_id ?: '-' }}</strong></div>
                    <div>AdGroup ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->google_adgroup_id ?: '-' }}</strong></div>
                    <div>Ad ID: <strong class="text-slate-800 dark:text-zinc-200">{{ $draft->google_ad_id ?: '-' }}</strong></div>
                </div>

                @if ($draft->google_error_log)
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/25 text-[11px] font-mono text-rose-600 dark:text-rose-400 whitespace-pre-wrap max-h-40 overflow-y-auto">{{ $draft->google_error_log }}</div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex flex-wrap gap-2">
                @if(in_array($draft->google_status, ['ACTIVE_ON_GOOGLE', 'PAUSED_ON_GOOGLE']))
                    <button wire:click="toggleGoogleStatus"
                            class="flex-1 py-2 px-3 rounded-xl text-xs font-bold {{ $draft->google_status === 'ACTIVE_ON_GOOGLE' ? 'bg-amber-500 text-slate-950' : 'bg-emerald-600 text-white' }}">
                        {{ $draft->google_status === 'ACTIVE_ON_GOOGLE' ? '⏸️ Pause Google Ads' : '🔥 Aktifkan Google Ads' }}
                    </button>
                @else
                    <button wire:click="retryGoogleQueue"
                            class="flex-1 py-2 px-3 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">
                        {{ $draft->google_status === 'FAILED' ? '🔄 Retry Queue' : '🚀 Publish Queue' }}
                    </button>
                    <button wire:click="retryGoogleSync"
                            class="py-2 px-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-zinc-800">
                        ⚡ Sync
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Combined Insights Metrics -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Performa &amp; Metrik Gabungan (Meta + TikTok + Google Insights)</h2>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Akumulasi performa dari semua platform aktif pada kampanye ini.</p>
            </div>
            <select wire:model.live="datePreset"
                    class="rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs font-semibold text-slate-800 dark:text-zinc-200">
                <option value="today">Hari Ini (Today)</option>
                <option value="yesterday">Kemarin (Yesterday)</option>
                <option value="last_7d">7 Hari Terakhir</option>
                <option value="maximum">Sepanjang Waktu (Maximum)</option>
            </select>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/60">
                <div class="text-[11px] text-slate-500 dark:text-zinc-400">Spend (Biaya)</div>
                <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">
                    Rp {{ number_format((float)($insights['spend'] ?? 0), 0, ',', '.') }}
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/60">
                <div class="text-[11px] text-slate-500 dark:text-zinc-400">Impressions</div>
                <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">
                    {{ number_format((int)($insights['impressions'] ?? 0), 0, ',', '.') }}
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/60">
                <div class="text-[11px] text-slate-500 dark:text-zinc-400">Total Klik</div>
                <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">
                    {{ number_format((int)($insights['clicks'] ?? 0), 0, ',', '.') }}
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/60">
                <div class="text-[11px] text-slate-500 dark:text-zinc-400">CPC Rata-rata</div>
                <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">
                    Rp {{ number_format((float)($insights['cpc'] ?? 0), 0, ',', '.') }}
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-zinc-800/60">
                <div class="text-[11px] text-slate-500 dark:text-zinc-400">CTR (%)</div>
                <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">
                    {{ number_format((float)($insights['ctr'] ?? 0), 2, ',', '.') }}%
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400">Konversi / Chat</div>
                <div class="mt-1 text-base font-bold text-emerald-700 dark:text-emerald-300">
                    {{ number_format((int)($insights['conversations'] ?? 0), 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Main Detail Grid: Edit Form (7 cols) + Video & Map Preview (5 cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left 7 cols: Parameter & Copywriting Editor -->
        <div class="lg:col-span-7 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Editor Parameter &amp; Copywriting Omni-Channel</h3>
                <button type="button"
                        wire:click="regenerateCopywriting"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-zinc-800">
                    🔄 Buat Ulang Copywriting AI
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Judul Kampanye</label>
                    <input type="text" wire:model="title"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Budget Harian (IDR)</label>
                    <input type="number" step="5000" wire:model="dailyBudget"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm font-semibold" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">URL Tujuan</label>
                    <input type="text" wire:model="destinationUrl"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">📘 Meta Primary Text</label>
                    <textarea wire:model="aiPrimaryText" rows="4"
                              class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">📘 Meta Headline (Maks 40)</label>
                    <input type="text" maxlength="40" wire:model="aiHeadline"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">📘 Meta Description</label>
                    <input type="text" wire:model="aiDescription"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-pink-600 dark:text-pink-400 mb-1">🎵 TikTok Ad Text / Caption (Maks 100)</label>
                    <input type="text" maxlength="100" wire:model="tiktokAdText"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-amber-600 dark:text-amber-400 mb-1">▶️ Google Short Headline (Maks 30)</label>
                    <input type="text" maxlength="30" wire:model="googleHeadline"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-amber-600 dark:text-amber-400 mb-1">▶️ YouTube Video ID (Opsional)</label>
                    <input type="text" wire:model="youtubeVideoId"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 font-mono text-xs" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-amber-600 dark:text-amber-400 mb-1">▶️ Google Long Headline (Maks 90)</label>
                    <input type="text" maxlength="90" wire:model="googleLongHeadline"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-amber-600 dark:text-amber-400 mb-1">▶️ Google Description (Maks 90)</label>
                    <input type="text" maxlength="90" wire:model="googleDescription"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm" />
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-zinc-800 flex items-center justify-end">
                <button type="button"
                        wire:click="saveChanges"
                        class="px-5 py-2.5 rounded-xl bg-slate-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-bold hover:opacity-90">
                    💾 Simpan Perubahan
                </button>
            </div>
        </div>

        <!-- Right 5 cols: Video Player, Geo-Locations Map & Interests -->
        <div class="lg:col-span-5 space-y-6">
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Video Iklan</h3>
                @if($draft->video_url)
                    <div class="rounded-xl overflow-hidden bg-black border border-slate-200 dark:border-zinc-800">
                        <video src="{{ $draft->video_url }}" controls class="w-full max-h-64 mx-auto object-contain"></video>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Peta Koordinat &amp; Radius Kampanye</h3>
                <div wire:ignore class="rounded-xl overflow-hidden border border-slate-200 dark:border-zinc-700">
                    <div id="detail-leaflet-map" class="w-full h-60"></div>
                </div>
                <div class="space-y-1.5 text-xs">
                    @foreach (($draft->custom_locations ?? []) as $loc)
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-700 dark:text-blue-300">
                            <span>🔵 {{ $loc['name'] ?? 'Include' }} ({{ $loc['latitude'] }}, {{ $loc['longitude'] }})</span>
                            <span class="font-bold">{{ $loc['radius'] ?? 10 }} km</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            const el = document.getElementById('detail-leaflet-map');
            if (!el) return;

            const inc = @json($draft->custom_locations ?? []);
            const exc = @json($draft->excluded_locations ?? []);

            const lat0 = inc.length > 0 ? parseFloat(inc[0].latitude) : -1.610123;
            const lng0 = inc.length > 0 ? parseFloat(inc[0].longitude) : 103.613120;

            const map = L.map('detail-leaflet-map').setView([lat0, lng0], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            inc.forEach((loc) => {
                L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)]).addTo(map);
                L.circle([parseFloat(loc.latitude), parseFloat(loc.longitude)], {
                    color: '#2563eb',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.2,
                    radius: parseFloat(loc.radius || 10) * 1000
                }).addTo(map);
            });

            exc.forEach((loc) => {
                L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)]).addTo(map);
                L.circle([parseFloat(loc.latitude), parseFloat(loc.longitude)], {
                    color: '#e11d48',
                    fillColor: '#f43f5e',
                    fillOpacity: 0.25,
                    dashArray: '5, 5',
                    radius: parseFloat(loc.radius || 5) * 1000
                }).addTo(map);
            });
        });
    </script>
</div>
