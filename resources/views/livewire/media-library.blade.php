<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold mb-2">
                <span>🗄️ Media Storage Library • Multimodal AI Vision • 🪄 AI Video &amp; Image Editor</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Media Storage, AI Vision &amp; Studio Editor
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Unggah Video (MP4/MOV) atau Gambar (JPG/PNG/WEBP), baca isi visualnya dengan AI Vision, dan **edit otomatis menggunakan perintah AI** (Ubah rasio 9:16/1:1, tambah Banner Teks Promo, filter warna, potong durasi, atau ubah Gambar jadi Video MP4).
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

    <!-- AI Media Studio Editor Panel (When an Asset is Selected for Editing) -->
    @if ($editingAsset)
        <div class="rounded-2xl bg-gradient-to-br from-indigo-950 via-slate-900 to-zinc-900 text-white border-2 border-indigo-500/50 p-6 shadow-xl space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[11px] font-bold mb-1">
                        <span>🪄 AI MEDIA STUDIO EDITOR (FFmpeg + GD + Multimodal AI)</span>
                    </div>
                    <h2 class="text-lg font-bold text-white">
                        Edit {{ $editingAsset->isVideo() ? 'Video' : 'Gambar' }}: <span class="text-indigo-300">{{ $editingAsset->title }}</span>
                    </h2>
                    <p class="text-xs text-slate-300">
                        File asli tetap aman di Storage. Hasil edit akan disimpan sebagai aset media baru yang siap dipakai di Studio Iklan.
                    </p>
                </div>
                <button type="button" wire:click="closeEditor"
                        class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-xs font-semibold text-white">
                    ✕ Tutup Editor
                </button>
            </div>

            <!-- 1. AI Natural Language Edit Prompt Box -->
            <div class="p-4 rounded-xl bg-white/5 border border-indigo-400/30 space-y-3">
                <label class="block text-xs font-bold text-indigo-300">
                    💬 Perintah Edit ke AI (Ketik apa yang ingin diubah atau biarkan AI Vision menyarankan Hook &amp; Format):
                </label>
                <div class="flex flex-col md:flex-row gap-3">
                    <input type="text"
                           wire:model="aiEditPrompt"
                           placeholder="Contoh: Ubah jadi vertikal 9:16 untuk TikTok, warna cerah, pasang banner merah di atas: PROMO WIFI 100RB/BLN"
                           class="flex-1 rounded-xl border-white/20 bg-slate-950/80 text-white placeholder-slate-400 text-xs px-3.5 py-2.5" />

                    <button type="button"
                            wire:click="applyAiEditSuggestion"
                            wire:loading.attr="disabled"
                            class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold whitespace-nowrap transition">
                        <span wire:loading.remove wire:target="applyAiEditSuggestion">🤖 Isi Parameter via AI Vision</span>
                        <span wire:loading wire:target="applyAiEditSuggestion">⏳ AI Menganalisa...</span>
                    </button>

                    <button type="button"
                            wire:click="executeAiMediaEdit(true)"
                            wire:loading.attr="disabled"
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold whitespace-nowrap shadow-lg shadow-emerald-600/30 transition">
                        <span wire:loading.remove wire:target="executeAiMediaEdit">⚡ Langsung Render via Perintah AI</span>
                        <span wire:loading wire:target="executeAiMediaEdit">⏳ Merender Media...</span>
                    </button>
                </div>
            </div>

            <!-- 2. Detailed Visual & Technical Controls -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Aspect Ratio & Output Mode -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                    <div class="text-xs font-bold text-indigo-300 uppercase tracking-wider">📐 Format &amp; Ukuran Iklan</div>

                    @if ($editingAsset->isImage())
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Mode Output Gambar</label>
                            <select wire:model.live="editOutputMode"
                                    class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                                <option value="KEEP_TYPE">🖼️ Tetap Gambar (JPG Resolusi Tinggi)</option>
                                <option value="IMAGE_TO_VIDEO">🎬 Ubah Gambar Jadi Video Iklan (MP4 + Audio)</option>
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">Rasio Aspek Iklan</label>
                        <select wire:model="editAspectRatio"
                                class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                            <option value="9:16">📱 9:16 Vertikal (Reels / TikTok / Shorts)</option>
                            <option value="1:1">🟦 1:1 Kotak (Feed Instagram / Facebook)</option>
                            <option value="4:5">🖼️ 4:5 Portrait (Feed Meta Optimal)</option>
                            <option value="16:9">🖥️ 16:9 Landscape (YouTube In-Stream)</option>
                            <option value="ORIGINAL">📏 Pertahankan Resolusi Asli</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">Mode Penyesuaian Layar (Fit Mode)</label>
                        <select wire:model="editFitMode"
                                class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                            <option value="CROP">✂️ Smart Center Crop (Penuh Layar)</option>
                            <option value="PAD">⬛ Pad Canvas (Tampil Utuh Tanpa Terpotong)</option>
                        </select>
                    </div>
                </div>

                <!-- Banner Text Overlay (Hook & Subtext) -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3 lg:col-span-2">
                    <div class="text-xs font-bold text-indigo-300 uppercase tracking-wider">🏷️ Text Banner Overlay (Hook Promo &amp; CTA)</div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Teks Headline Banner Utama (Kosongkan jika tanpa teks)</label>
                            <input type="text" wire:model="editOverlayText" maxlength="48"
                                   placeholder="PROMO PASANG WIFI 100RB/BLN"
                                   class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs" />
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Sub-Teks / Call To Action (Baris ke-2)</label>
                            <input type="text" wire:model="editOverlaySubtext" maxlength="60"
                                   placeholder="Gratis Instalasi Hari Ini - Hubungi WA!"
                                   class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Posisi Banner di Layar</label>
                            <select wire:model="editOverlayPosition"
                                    class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                                <option value="TOP">⬆️ Atas (Top Hook)</option>
                                <option value="CENTER">⏺️ Tengah (Center Focus)</option>
                                <option value="BOTTOM">⬇️ Bawah (Bottom Lower-Third)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Warna Tema Banner</label>
                            <select wire:model="editOverlayTheme"
                                    class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                                <option value="RED">🔴 Merah Promo (Urgent Sale)</option>
                                <option value="EMERALD">🟢 Hijau WhatsApp (Trusted)</option>
                                <option value="INDIGO">🟣 Indigo Modern (Tech/Brand)</option>
                                <option value="AMBER">🟡 Kuning/Emas (Flash Deal)</option>
                                <option value="DARK">⚫ Hitam Elegan (Dark Glass)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Filter Warna Visual (Color Grading)</label>
                            <select wire:model="editColorPreset"
                                    class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                                <option value="VIBRANT">✨ Vibrant (Tajam &amp; Mencolok di Feed)</option>
                                <option value="BRIGHT">☀️ Bright (Cerahkan Visual Gelap)</option>
                                <option value="WARM">🔥 Warm (Nuansa Hangat/Emas)</option>
                                <option value="CINEMATIC">🎬 Cinematic (Kontras Tinggi)</option>
                                <option value="NONE">⚪ Normal (Warna Asli)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Video Duration / Trim / Speed & Output Title -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                    <div class="text-xs font-bold text-indigo-300 uppercase tracking-wider">⏱️ Durasi, Kecepatan &amp; Nama Aset</div>

                    @if ($editingAsset->isVideo())
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-slate-300 mb-1">Mulai Detik</label>
                                <input type="number" step="0.5" min="0" wire:model="editTrimStart"
                                       class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-300 mb-1">Potong Durasi (0=Utuh)</label>
                                <input type="number" step="1" min="0" max="120" wire:model="editTrimDuration"
                                       class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Kecepatan Video (Speed)</label>
                            <select wire:model="editSpeed"
                                    class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                                <option value="1">1.0x (Normal)</option>
                                <option value="1.15">1.15x (Sedikit Lebih Cepat / Dinamis)</option>
                                <option value="1.25">1.25x (Cepat - Gaya TikTok/Reels)</option>
                                <option value="1.5">1.50x (Fast Forward)</option>
                            </select>
                        </div>
                    @elseif ($editOutputMode === 'IMAGE_TO_VIDEO')
                        <div>
                            <label class="block text-[11px] text-slate-300 mb-1">Durasi Video MP4 Hasil Konversi (Detik)</label>
                            <select wire:model="editVideoDuration"
                                    class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs">
                                <option value="6">6 Detik (Bumper / Reels Singkat)</option>
                                <option value="8">8 Detik (Rekomendasi Meta &amp; TikTok)</option>
                                <option value="10">10 Detik (Standar Video Iklan)</option>
                                <option value="15">15 Detik (Full Story / Shorts)</option>
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-[11px] text-slate-300 mb-1">Judul Aset Hasil Edit</label>
                        <input type="text" wire:model="editNewTitle"
                               class="w-full rounded-lg border-white/20 bg-slate-900 text-white text-xs" />
                    </div>
                </div>
            </div>

            <!-- Action Footer -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-white/10">
                <div class="text-xs text-slate-300">
                    💡 Hasil edit akan langsung muncul di daftar <strong>Media Storage Library</strong> di bawah dan siap digunakan di Studio Iklan AI.
                </div>
                <button type="button"
                        wire:click="executeAiMediaEdit(false)"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white text-xs font-bold shadow-lg shadow-indigo-500/30 transition">
                    <span wire:loading.remove wire:target="executeAiMediaEdit">🪄 Render &amp; Simpan Hasil Edit ke Storage</span>
                    <span wire:loading wire:target="executeAiMediaEdit">⏳ Sedang Merender Video/Gambar...</span>
                </button>
            </div>
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
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 border {{ $editingAssetId === $asset->id ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-zinc-800' }} overflow-hidden shadow-sm flex flex-col justify-between">
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
                                        Belum dianalisa dengan AI Vision. Klik tombol "👁️ Vision" atau "🪄 Edit AI" di bawah.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Actions Footer -->
                        <div class="p-4 pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-1.5">
                            <a href="{{ route('ads.create', ['media_id' => $asset->id]) }}"
                               class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition">
                                <span>✨ Pakai</span>
                            </a>

                            <button type="button"
                                    wire:click="openEditor({{ $asset->id }})"
                                    class="px-2.5 py-2 rounded-xl bg-indigo-600/10 border border-indigo-500/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-600 hover:text-white text-xs font-bold transition"
                                    title="Edit Video/Gambar dengan AI (Rasio, Banner Promo, Filter, Image-to-Video)">
                                🪄 Edit AI
                            </button>

                            <button type="button"
                                    wire:click="analyzeAssetVision({{ $asset->id }})"
                                    wire:loading.attr="disabled"
                                    class="px-2.5 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 text-xs font-semibold"
                                    title="Baca & Analisa Visual dengan AI Vision">
                                👁️ Vision
                            </button>

                            <button type="button"
                                    wire:click="deleteAsset({{ $asset->id }})"
                                    class="px-2 py-2 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10 text-xs font-semibold"
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
