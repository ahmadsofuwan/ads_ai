<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold mb-2">
                <span>🗄️ Media Storage Library &amp; Multimodal AI Vision (Video Keyframe + Image OCR)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Media Storage &amp; AI Vision Library
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Unggah koleksi Video (MP4/MOV) maupun Gambar (JPG/PNG/WEBP) terlebih dahulu. Sistem otomatis mengekstrak frame video agar AI dapat melihat isi visual &amp; membaca teks di dalam media.
            </p>
        </div>
        <a href="{{ route('ads.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-md shadow-blue-600/20">
            <span>✨ Buka Studio Pembuat Iklan AI →</span>
        </a>
    </div>

    @if ($flashMessage)
        <div class="p-4 rounded-xl border flex items-start justify-between gap-3 {{ $flashType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400' }}">
            <div class="text-sm font-medium">{{ $flashMessage }}</div>
            <button wire:click="$set('flashMessage', null)" class="text-xs opacity-75 hover:opacity-100">✕</button>
        </div>
    @endif

    <!-- Upload Card -->
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">📤 Unggah Media Baru ke Storage Library</h2>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Mendukung file Video (`.mp4`, `.mov`, `.webm`) dan Gambar (`.jpg`, `.png`, `.webp`). Untuk video, sistem otomatis mengekstrak 3 keyframe utama via FFmpeg untuk dibaca AI Vision.</p>
            </div>
            @if (!empty($aiProviders) && count($aiProviders) > 0)
                <select wire:model="selectedAiProviderId"
                        class="rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs font-semibold">
                    <option value="">🤖 Default AI Vision Provider</option>
                    @foreach ($aiProviders as $prov)
                        <option value="{{ $prov->id }}">[{{ $prov->provider }}] {{ $prov->name }} ({{ $prov->model }})</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
            <div class="lg:col-span-5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1.5">
                    Pilih File Video atau Gambar
                </label>
                <input type="file"
                       wire:model="mediaFile"
                       accept="video/mp4,video/quicktime,video/webm,image/jpeg,image/png,image/webp,video/*,image/*"
                       class="block w-full text-xs text-slate-600 dark:text-zinc-300 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer border border-slate-200 dark:border-zinc-700 rounded-xl p-1.5 bg-slate-50 dark:bg-zinc-800/50" />
                @error('mediaFile') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror

                <div wire:loading wire:target="mediaFile" class="mt-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                    ⏳ Mengunggah file ke memori sementara...
                </div>
            </div>

            <div class="lg:col-span-4 space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                        Judul / Nama Aset Media (Opsional)
                    </label>
                    <input type="text" wire:model="uploadTitle"
                           placeholder="Contoh: Video Promo FTTH Jambi / Poster Diskon"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                        Catatan Konteks untuk AI (Opsional)
                    </label>
                    <input type="text" wire:model="uploadNotes"
                           placeholder="Contoh: Paket 50Mbps Rp 199rb/bln gratis pasang"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs" />
                </div>
            </div>

            <div class="lg:col-span-3 flex flex-col justify-between gap-3 pt-1">
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-zinc-300 cursor-pointer">
                    <input type="checkbox" wire:model="autoAnalyzeVision" class="rounded text-indigo-600" />
                    <span>👁️ Langsung Analisa Visual dengan AI Vision saat di-upload</span>
                </label>

                <button type="button"
                        wire:click="uploadToStorage"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition">
                    <span wire:loading.remove wire:target="uploadToStorage">💾 Simpan ke Storage Library</span>
                    <span wire:loading wire:target="uploadToStorage">⏳ Memproses &amp; Membaca Visual...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Filter & Gallery -->
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="$set('typeFilter', '')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ $typeFilter === '' ? 'bg-slate-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400' }}">
                    Semua Media ({{ count($assets) }})
                </button>
                <button type="button" wire:click="$set('typeFilter', 'VIDEO')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ $typeFilter === 'VIDEO' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400' }}">
                    🎬 Video
                </button>
                <button type="button" wire:click="$set('typeFilter', 'IMAGE')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ $typeFilter === 'IMAGE' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400' }}">
                    🖼️ Gambar
                </button>
            </div>

            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Cari judul media, nama file, atau isi analisa visual AI..."
                   class="w-full sm:w-80 rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs" />
        </div>

        @if (count($assets) === 0)
            <div class="p-12 rounded-2xl bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 text-center space-y-2">
                <div class="text-base font-bold text-slate-800 dark:text-zinc-200">Belum ada media di Storage Library</div>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Unggah video atau gambar di atas agar siap digunakan kapan saja di Studio Iklan AI.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($assets as $asset)
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 overflow-hidden shadow-sm flex flex-col justify-between">
                        <div>
                            <!-- Preview Player / Image -->
                            <div class="relative bg-black h-48 flex items-center justify-center overflow-hidden">
                                @if ($asset->isVideo())
                                    <video src="{{ $asset->url }}" controls preload="metadata" class="w-full h-full object-contain"></video>
                                @else
                                    <img src="{{ $asset->url }}" alt="{{ $asset->title }}" class="w-full h-full object-contain" />
                                @endif
                                <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $asset->isVideo() ? 'bg-blue-600 text-white' : 'bg-emerald-600 text-white' }}">
                                    {{ $asset->isVideo() ? '🎬 VIDEO' : '🖼️ IMAGE' }}
                                </span>
                                <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded bg-black/75 text-white text-[10px] font-mono">
                                    {{ $asset->formatted_size }}
                                    @if ($asset->duration_seconds) • {{ $asset->duration_seconds }}s @endif
                                </span>
                            </div>

                            <!-- Extracted Keyframes Strip for Video Vision -->
                            @if ($asset->isVideo() && count($asset->frame_urls) > 0)
                                <div class="px-4 pt-3">
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">
                                        🎞️ Frame Video yang Dilihat AI Vision ({{ count($asset->frame_urls) }} Keyframes):
                                    </div>
                                    <div class="grid grid-cols-3 gap-1.5">
                                        @foreach ($asset->frame_urls as $fUrl)
                                            <img src="{{ $fUrl }}" alt="Frame" class="h-12 w-full object-cover rounded border border-slate-200 dark:border-zinc-700" />
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Details & AI Visual Analysis -->
                            <div class="p-4 space-y-2.5">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $asset->title }}</h3>
                                    <div class="text-[11px] font-mono text-slate-400 truncate">
                                        {{ $asset->file_name }}
                                        @if ($asset->width && $asset->height) • {{ $asset->width }}x{{ $asset->height }}px @endif
                                    </div>
                                </div>

                                @if ($asset->notes)
                                    <div class="text-xs text-slate-600 dark:text-zinc-300 bg-slate-50 dark:bg-zinc-800/60 p-2 rounded-lg">
                                        📝 {{ $asset->notes }}
                                    </div>
                                @endif

                                @if ($asset->ai_visual_analysis)
                                    <div class="p-3 rounded-xl bg-indigo-500/5 border border-indigo-500/20 space-y-1">
                                        <div class="text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400">
                                            👁️ Hasil Penglihatan AI Vision:
                                        </div>
                                        <p class="text-xs text-slate-700 dark:text-zinc-300 leading-relaxed line-clamp-4">
                                            {{ $asset->ai_visual_analysis }}
                                        </p>
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400 italic">
                                        Belum dianalisa dengan AI Vision. Klik tombol "👁️ Analisa Visual" di bawah.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Actions Footer -->
                        <div class="p-4 pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-2">
                            <a href="{{ route('ads.create', ['media_id' => $asset->id]) }}"
                               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition">
                                <span>✨ Pakai di Studio AI</span>
                            </a>

                            <button type="button"
                                    wire:click="analyzeAssetVision({{ $asset->id }})"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-2 rounded-xl border border-indigo-500/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/10 text-xs font-semibold"
                                    title="Baca & Analisa Visual dengan AI Vision">
                                👁️ Vision
                            </button>

                            <button type="button"
                                    wire:click="deleteAsset({{ $asset->id }})"
                                    class="px-2.5 py-2 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10 text-xs font-semibold"
                                    title="Hapus dari Storage">
                                ✕
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
