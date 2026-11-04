<div class="space-y-8"
     x-data="{
        pinMode: 'include',
        previewPlatform: 'META',
        searchPlaceQuery: '',
        searchingPlace: false,
        videoPreviewUrl: null,
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.videoPreviewUrl = URL.createObjectURL(file);
            }
        },
        handleFileDrop(event) {
            const file = event.dataTransfer.files[0];
            if (file && file.type.startsWith('video/')) {
                const input = this.$refs.videoInput;
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                this.videoPreviewUrl = URL.createObjectURL(file);
            }
        },
        async searchLocationOnMap() {
            if (!this.searchPlaceQuery.trim()) return;
            this.searchingPlace = true;
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(this.searchPlaceQuery));
                const data = await res.json();
                if (data && data.length > 0 && window.studioLeafletMap) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    window.studioLeafletMap.setView([lat, lon], 12);
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.searchingPlace = false;
            }
        }
     }">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 text-xs font-semibold mb-2">
                <span>✨ Omni-Channel AI Media Buyer (Meta • TikTok • Google Ads)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Studio Pembuat Iklan AI
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Unggah 1 video, klik multi-pin lokasi pada peta, ketik 1 perintah natural, dan terbitkan ke Meta Ads, TikTok Ads &amp; Google Ads sekaligus.
            </p>
        </div>
        <a href="{{ route('ads.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-800">
            <span>Daftar Semua Kampanye →</span>
        </a>
    </div>

    @if ($statusMessage)
        <div class="p-4 rounded-xl border flex items-start justify-between gap-3 {{ $statusType === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400' }}">
            <div class="text-sm font-medium">{{ $statusMessage }}</div>
            <button wire:click="$set('statusMessage', null)" class="text-xs opacity-75 hover:opacity-100">✕</button>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs space-y-1">
            <div class="font-semibold text-sm">Mohon periksa kembali input berikut:</div>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- STEP 1 & 2: VIDEO UPLOAD + INTERACTIVE LEAFLET MULTI-PIN MAP -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Column: Video Drag & Drop + Preview Player (5 cols) -->
        <div class="lg:col-span-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm flex flex-col justify-between space-y-5">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">1</span>
                        <span>Materi Video / Gambar &amp; AI Vision</span>
                    </h2>
                    <a href="{{ route('media.index') }}" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                        🗄️ Buka Storage Library →
                    </a>
                </div>

                <!-- Drag and Drop Upload Box -->
                <div @dragover.prevent
                     @drop.prevent="handleFileDrop($event)"
                     class="relative border-2 border-dashed border-slate-300 dark:border-zinc-700 hover:border-blue-500 dark:hover:border-blue-500 rounded-2xl p-5 text-center transition cursor-pointer bg-slate-50/50 dark:bg-zinc-950/40"
                     @click="$refs.videoInput.click()">
                    <input x-ref="videoInput"
                           type="file"
                           wire:model="videoFile"
                           accept="video/mp4,video/quicktime,video/webm,image/jpeg,image/png,image/webp,video/*,image/*"
                           @change="handleFileSelect($event)"
                           class="hidden" />

                    <div class="space-y-2">
                        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-zinc-200">
                            Klik atau Drag &amp; Drop Video / Gambar ke Sini
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">
                            Otomatis disimpan ke Storage Library &amp; diekstrak frame-nya agar bisa dilihat oleh AI Vision.
                        </p>
                    </div>

                    <div wire:loading wire:target="videoFile" class="mt-3 text-xs font-semibold text-blue-600 dark:text-blue-400">
                        ⏳ Menyimpan ke Storage Library &amp; mengekstrak frame visual...
                    </div>
                </div>

                <!-- Media Preview Player -->
                <div class="mt-4 space-y-3">
                    <template x-if="videoPreviewUrl">
                        <div class="rounded-xl overflow-hidden bg-black border border-slate-200 dark:border-zinc-800">
                            <video :src="videoPreviewUrl" controls class="w-full max-h-64 mx-auto object-contain"></video>
                        </div>
                    </template>

                    @if($existingVideoPath)
                        @php
                            $existingUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($existingVideoPath);
                            $isImageExt = preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $existingVideoPath);
                        @endphp
                        <div x-show="!videoPreviewUrl" class="rounded-xl overflow-hidden bg-black border border-slate-200 dark:border-zinc-800">
                            @if ($isImageExt)
                                <img src="{{ $existingUrl }}" alt="Preview Media" class="w-full max-h-64 mx-auto object-contain" />
                            @else
                                <video src="{{ $existingUrl }}" controls class="w-full max-h-64 mx-auto object-contain"></video>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-zinc-800 space-y-3">
                @if (count($availableVideos) > 0)
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-400 mb-1.5">
                            🗄️ Pilih dari Media Storage Library (Video &amp; Gambar Tersimpan):
                        </label>
                        <select wire:model.live="existingVideoPath"
                                @change="videoPreviewUrl = null"
                                class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs text-slate-800 dark:text-zinc-200 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Pilih Media dari Storage Library --</option>
                            @foreach ($availableVideos as $vid)
                                <option value="{{ $vid['path'] }}">{{ $vid['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-2">
                    <button type="button"
                            wire:click="analyzeCurrentMediaWithVision"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600/10 hover:bg-indigo-600/20 border border-indigo-500/30 text-indigo-600 dark:text-indigo-400 text-xs font-bold transition">
                        <span wire:loading.remove wire:target="analyzeCurrentMediaWithVision">👁️ Baca &amp; Lihat Isi Video/Gambar dengan AI Vision</span>
                        <span wire:loading wire:target="analyzeCurrentMediaWithVision">⏳ AI sedang mengamati visual media...</span>
                    </button>
                </div>

                @if ($aiVisualAnalysis)
                    <div class="p-3 rounded-xl bg-indigo-500/5 border border-indigo-500/25 space-y-1">
                        <div class="text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400">
                            👁️ Hasil Penglihatan AI Vision (Otomatis disertakan ke Prompt):
                        </div>
                        <textarea wire:model="aiVisualAnalysis"
                                  rows="2"
                                  class="w-full rounded-lg border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs text-slate-700 dark:text-zinc-200"></textarea>
                    </div>
                @endif

                <!-- Quick AI Media Editor (Video / Image / Image-to-Video) -->
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-800 dark:text-zinc-200">
                            🪄 Edit Video/Gambar dengan Perintah AI
                        </span>
                        <a href="{{ route('media.index') }}" class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Editor Lengkap →
                        </a>
                    </div>
                    <div class="flex gap-1.5">
                        <input type="text"
                               wire:model="quickEditPrompt"
                               placeholder="Contoh: Ubah 9:16 TikTok + banner merah: PROMO WIFI 100RB / Jadikan video MP4"
                               class="flex-1 rounded-lg border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs px-2.5 py-1.5" />
                        <button type="button"
                                wire:click="quickAiEditCurrentMedia"
                                wire:loading.attr="disabled"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold whitespace-nowrap transition">
                            <span wire:loading.remove wire:target="quickAiEditCurrentMedia">🪄 Edit AI</span>
                            <span wire:loading wire:target="quickAiEditCurrentMedia">⏳...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Leaflet.js Multi-Pin Map & Radius Sliders (7 cols) -->
        <div class="lg:col-span-7 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">2</span>
                    <span>Peta Targeting Titik Koordinat (Multi-Pin &amp; Radius)</span>
                </h2>

                <div class="inline-flex rounded-xl p-1 bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700">
                    <button type="button"
                            @click="pinMode = 'include'"
                            :class="pinMode === 'include' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-zinc-400'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                        <span>+ Pin Include (Biru)</span>
                    </button>
                    <button type="button"
                            @click="pinMode = 'exclude'"
                            :class="pinMode === 'exclude' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-600 dark:text-zinc-400'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-300"></span>
                        <span>- Pin Exclude (Merah)</span>
                    </button>
                </div>
            </div>

            <div class="flex gap-2">
                <input type="text"
                       x-model="searchPlaceQuery"
                       @keydown.enter.prevent="searchLocationOnMap()"
                       placeholder="Cari kota, kecamatan, atau jalan di peta (contoh: Jambi, Palembang, Kemang)..."
                       class="flex-1 rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/80 text-xs text-slate-900 dark:text-zinc-100" />
                <button type="button"
                        @click="searchLocationOnMap()"
                        class="px-3.5 py-2 rounded-xl bg-slate-900 dark:bg-zinc-700 text-white text-xs font-semibold hover:opacity-90 transition">
                    <span x-show="!searchingPlace">🔍 Cari Peta</span>
                    <span x-show="searchingPlace" x-cloak>...</span>
                </button>
            </div>

            <div wire:ignore class="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-700">
                <div id="studio-leaflet-map" class="w-full h-80 sm:h-96"></div>
                <div class="absolute bottom-2.5 left-2.5 z-[400] px-3 py-1.5 rounded-lg bg-slate-900/80 text-white text-[11px] backdrop-blur pointer-events-none">
                    💡 Klik di mana saja pada peta untuk menaruh titik pin (<span x-text="pinMode === 'include' ? 'Mode Include Biru' : 'Mode Exclude Merah'"></span>)
                </div>
            </div>

            <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                @foreach ($customLocations as $index => $loc)
                    <div class="p-3.5 rounded-xl bg-blue-500/5 border border-blue-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-600 text-white">Include #{{ $index + 1 }}</span>
                                <input type="text"
                                       wire:model.blur="customLocations.{{ $index }}.name"
                                       class="text-xs font-semibold bg-transparent border-0 p-0 focus:ring-0 text-slate-900 dark:text-white" />
                            </div>
                            <div class="mt-1 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                                Lat: {{ $loc['latitude'] }}, Lng: {{ $loc['longitude'] }}
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-44 sm:w-52">
                                <div class="flex justify-between text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">
                                    <span>Radius Target</span>
                                    <span>{{ $loc['radius'] }} km</span>
                                </div>
                                <input type="range"
                                       min="1"
                                       max="80"
                                       value="{{ $loc['radius'] }}"
                                       wire:model.live="customLocations.{{ $index }}.radius"
                                       @input="window.updateStudioCircleRadius && window.updateStudioCircleRadius('include', {{ $index }}, $event.target.value)"
                                       class="w-full accent-blue-600 cursor-pointer" />
                            </div>

                            @if(count($customLocations) > 1)
                                <button type="button"
                                        wire:click="removeIncludePin({{ $index }})"
                                        class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10"
                                        title="Hapus titik ini">
                                    ✕
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @foreach ($excludedLocations as $index => $exLoc)
                    <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-600 text-white">Exclude #{{ $index + 1 }}</span>
                                <input type="text"
                                       wire:model.blur="excludedLocations.{{ $index }}.name"
                                       class="text-xs font-semibold bg-transparent border-0 p-0 focus:ring-0 text-slate-900 dark:text-white" />
                            </div>
                            <div class="mt-1 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                                Lat: {{ $exLoc['latitude'] }}, Lng: {{ $exLoc['longitude'] }}
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-44 sm:w-52">
                                <div class="flex justify-between text-xs font-semibold text-rose-600 dark:text-rose-400 mb-1">
                                    <span>Radius Pengecualian</span>
                                    <span>{{ $exLoc['radius'] }} km</span>
                                </div>
                                <input type="range"
                                       min="1"
                                       max="80"
                                       value="{{ $exLoc['radius'] }}"
                                       wire:model.live="excludedLocations.{{ $index }}.radius"
                                       @input="window.updateStudioCircleRadius && window.updateStudioCircleRadius('exclude', {{ $index }}, $event.target.value)"
                                       class="w-full accent-rose-600 cursor-pointer" />
                            </div>

                            <button type="button"
                                    wire:click="removeExcludePin({{ $index }})"
                                    class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10"
                                    title="Hapus zona exclude ini">
                                ✕
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- STEP 3: NATURAL LANGUAGE AI COMMAND PROMPT -->
    <div class="rounded-2xl bg-gradient-to-br from-blue-600/10 via-indigo-600/5 to-transparent dark:from-blue-500/10 dark:via-indigo-500/5 border border-blue-500/25 p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">3</span>
                <span>Perintah AI Bahasa Sehari-hari (Multi-Provider AI Media Buyer Agent)</span>
            </h2>
            <div class="flex items-center gap-2">
                @if (! empty($aiProviders) && count($aiProviders) > 0)
                    <select wire:model="selectedAiProviderId"
                            class="rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs font-semibold text-slate-800 dark:text-zinc-200 py-1.5">
                        <option value="">🤖 Auto / Default AI Provider (Fallback Aktif)</option>
                        @foreach ($aiProviders as $aiProv)
                            <option value="{{ $aiProv->id }}">
                                [{{ $aiProv->provider }}] {{ $aiProv->name }} ({{ $aiProv->model }})
                            </option>
                        @endforeach
                    </select>
                @else
                    <span class="text-xs text-slate-500 dark:text-zinc-400">
                        Otomatis menyusun copywriting khusus untuk Meta Ads, TikTok Ads &amp; Google Ads
                    </span>
                @endif
            </div>
        </div>

        <div class="space-y-3">
            <textarea wire:model="rawUserPrompt"
                      rows="3"
                      placeholder="Contoh: Iklankan video promo pasang WiFi rumah cepat unlimited di titik koordinat ini radius 12km, target umur 24-45 tahun, budget 50rb/hari, arahkan ke chat WhatsApp..."
                      class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 focus:ring-blue-500 focus:border-blue-500 shadow-sm"></textarea>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-1.5 text-[11px] text-slate-500 dark:text-zinc-400">
                    <span>Contoh cepat:</span>
                    <button type="button"
                            wire:click="$set('rawUserPrompt', 'Promo pasang WiFi FTTH cepat unlimited untuk keluarga, radius 10km, umur 24-50 tahun, budget 50rb/hari arahkan ke WA.')"
                            class="px-2 py-0.5 rounded bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:border-blue-500">
                        📡 Promo WiFi Rumah (WA)
                    </button>
                    <button type="button"
                            wire:click="$set('rawUserPrompt', 'Paket Skincare Glowing BPOM khusus wanita umur 21-40 tahun, radius 15km, budget 100rb/hari arahkan ke Website https://tokoskincare.id')"
                            class="px-2 py-0.5 rounded bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:border-blue-500">
                        ✨ Skincare Wanita (Website)
                    </button>
                </div>

                <button type="button"
                        wire:click="generateWithAi"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold shadow-md shadow-blue-600/25 transition">
                    <span wire:loading.remove wire:target="generateWithAi">✨ Buat Draft &amp; Copywriting dengan AI</span>
                    <span wire:loading wire:target="generateWithAi" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Menyusun Strategi Omni-Channel AI...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 4: AI RESULT EDITOR + LIVE OMNI-CHANNEL AD PREVIEW -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left 8 cols: Full Campaign, Copywriting & Targeting Editor -->
        <div class="lg:col-span-8 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 pb-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">4</span>
                    <span>Pilih Platform &amp; Editor Copywriting AI</span>
                </h2>
                <button type="button"
                        wire:click="regenerateCopyOnly"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800">
                    <span wire:loading.remove wire:target="regenerateCopyOnly">🔄 Buat Ulang Copywriting</span>
                    <span wire:loading wire:target="regenerateCopyOnly">⏳ Membuat variasi...</span>
                </button>
            </div>

            <!-- Multi-Platform & Multi-Account Checkboxes -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-700 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-300 mb-2">
                        Platform Tujuan Penerbitan (Pilih 1 atau Ketiganya Sekaligus):
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-3 p-3 rounded-xl border bg-white dark:bg-zinc-900 cursor-pointer transition {{ in_array('META', $targetPlatforms) ? 'border-blue-500 ring-1 ring-blue-500/30' : 'border-slate-200 dark:border-zinc-700' }}">
                            <input type="checkbox" value="META" wire:model.live="targetPlatforms" class="rounded text-blue-600 focus:ring-blue-500" />
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">📘 Meta Ads (v21.0)</div>
                                <div class="text-[10px] text-slate-500">Facebook &amp; Instagram</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-xl border bg-white dark:bg-zinc-900 cursor-pointer transition {{ in_array('TIKTOK', $targetPlatforms) ? 'border-pink-500 ring-1 ring-pink-500/30' : 'border-slate-200 dark:border-zinc-700' }}">
                            <input type="checkbox" value="TIKTOK" wire:model.live="targetPlatforms" class="rounded text-pink-600 focus:ring-pink-500" />
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">🎵 TikTok Ads (v1.3)</div>
                                <div class="text-[10px] text-slate-500">TikTok For Business Feed</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-xl border bg-white dark:bg-zinc-900 cursor-pointer transition {{ in_array('GOOGLE', $targetPlatforms) ? 'border-amber-500 ring-1 ring-amber-500/30' : 'border-slate-200 dark:border-zinc-700' }}">
                            <input type="checkbox" value="GOOGLE" wire:model.live="targetPlatforms" class="rounded text-amber-600 focus:ring-amber-500" />
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">▶️ Google Ads (v18)</div>
                                <div class="text-[10px] text-slate-500">YouTube Shorts &amp; Demand Gen</div>
                            </div>
                        </label>
                    </div>
                </div>

                @if ((!empty($metaAccounts) && count($metaAccounts) > 0) || (!empty($tiktokAccounts) && count($tiktokAccounts) > 0) || (!empty($googleAccounts) && count($googleAccounts) > 0))
                    <div class="pt-3 border-t border-slate-200 dark:border-zinc-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-300">
                                🏢 Pilih Akun API Iklan per Platform (Mendukung Multi-Akun Sekaligus):
                            </span>
                            <a href="{{ route('settings.index') }}" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                + Kelola Akun API
                            </a>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- Meta Accounts -->
                            <div class="p-3 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 space-y-2">
                                <div class="text-[11px] font-bold text-blue-600 dark:text-blue-400">📘 Akun Meta Ads</div>
                                @if (!empty($metaAccounts) && count($metaAccounts) > 0)
                                    <div class="space-y-1.5 max-h-28 overflow-y-auto">
                                        @foreach ($metaAccounts as $mAcc)
                                            <label class="flex items-start gap-2 text-xs cursor-pointer">
                                                <input type="checkbox" value="{{ $mAcc->id }}" wire:model.live="selectedMetaAccountIds" class="mt-0.5 rounded text-blue-600" />
                                                <span class="leading-tight">
                                                    <span class="font-semibold text-slate-800 dark:text-zinc-200">{{ $mAcc->name }}</span>
                                                    <span class="block text-[10px] font-mono text-slate-400">{{ $mAcc->meta_ad_account_id }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400">Menggunakan kredensial utama Settings</div>
                                @endif
                            </div>

                            <!-- TikTok Accounts -->
                            <div class="p-3 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 space-y-2">
                                <div class="text-[11px] font-bold text-pink-600 dark:text-pink-400">🎵 Akun TikTok Ads</div>
                                @if (!empty($tiktokAccounts) && count($tiktokAccounts) > 0)
                                    <div class="space-y-1.5 max-h-28 overflow-y-auto">
                                        @foreach ($tiktokAccounts as $tAcc)
                                            <label class="flex items-start gap-2 text-xs cursor-pointer">
                                                <input type="checkbox" value="{{ $tAcc->id }}" wire:model.live="selectedTiktokAccountIds" class="mt-0.5 rounded text-pink-600" />
                                                <span class="leading-tight">
                                                    <span class="font-semibold text-slate-800 dark:text-zinc-200">{{ $tAcc->name }}</span>
                                                    <span class="block text-[10px] font-mono text-slate-400">Adv: {{ $tAcc->tiktok_advertiser_id }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400">Menggunakan kredensial utama Settings</div>
                                @endif
                            </div>

                            <!-- Google Accounts -->
                            <div class="p-3 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 space-y-2">
                                <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400">▶️ Akun Google Ads</div>
                                @if (!empty($googleAccounts) && count($googleAccounts) > 0)
                                    <div class="space-y-1.5 max-h-28 overflow-y-auto">
                                        @foreach ($googleAccounts as $gAcc)
                                            <label class="flex items-start gap-2 text-xs cursor-pointer">
                                                <input type="checkbox" value="{{ $gAcc->id }}" wire:model.live="selectedGoogleAccountIds" class="mt-0.5 rounded text-amber-600" />
                                                <span class="leading-tight">
                                                    <span class="font-semibold text-slate-800 dark:text-zinc-200">{{ $gAcc->name }}</span>
                                                    <span class="block text-[10px] font-mono text-slate-400">CID: {{ $gAcc->google_customer_id }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400">Menggunakan kredensial utama Settings</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Campaign Name & Destination -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Nama Kampanye</label>
                    <input type="text"
                           wire:model="title"
                           placeholder="Contoh: Promo Pasang WiFi Rumah - Jambi (WA)"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-slate-900 dark:text-zinc-100" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Tujuan Iklan (Destination)</label>
                    <select wire:model.live="destinationType"
                            class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-slate-900 dark:text-zinc-100">
                        <option value="WHATSAPP">💬 WhatsApp (Klik ke Chat WA)</option>
                        <option value="WEBSITE">🌐 Website / Landing Page</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Objective Kampanye</label>
                    <select wire:model="objective"
                            class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-slate-900 dark:text-zinc-100">
                        <option value="OUTCOME_ENGAGEMENT">OUTCOME_ENGAGEMENT (Konversi Chat WA)</option>
                        <option value="OUTCOME_TRAFFIC">OUTCOME_TRAFFIC (Kunjungan Website / Klik)</option>
                        <option value="OUTCOME_SALES">OUTCOME_SALES (Konversi Penjualan / Pixel)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">Tombol Call To Action (CTA)</label>
                    <select wire:model.live="ctaType"
                            class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-slate-900 dark:text-zinc-100">
                        <option value="WHATSAPP_MESSAGE">WHATSAPP_MESSAGE (Kirim Pesan WhatsApp)</option>
                        <option value="LEARN_MORE">LEARN_MORE (Selengkapnya)</option>
                        <option value="SHOP_NOW">SHOP_NOW (Beli Sekarang)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">URL Tujuan (Link Website / Link WA)</label>
                    <input type="text"
                           wire:model="destinationUrl"
                           placeholder="https://wa.me/628123456789 atau https://websiteanda.com"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-slate-900 dark:text-zinc-100" />
                </div>
            </div>

            <!-- Copywriting Section A: META ADS -->
            <div class="p-4 rounded-xl bg-blue-500/5 border border-blue-500/20 space-y-4">
                <div class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                    📘 Copywriting Meta Ads (Facebook &amp; Instagram)
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                        Primary Text (Hook, Keunggulan &amp; CTA)
                    </label>
                    <textarea wire:model.live="aiPrimaryText"
                              rows="5"
                              placeholder="Teks copywriting utama Meta Ads..."
                              class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            <span>Headline Meta (Maks 40 Karakter)</span>
                            <span class="font-mono {{ mb_strlen($aiHeadline) > 40 ? 'text-rose-500' : 'text-slate-400' }}">
                                {{ mb_strlen($aiHeadline) }}/40
                            </span>
                        </div>
                        <input type="text" maxlength="40" wire:model.live="aiHeadline"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            Description Meta
                        </label>
                        <input type="text" wire:model.live="aiDescription"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm" />
                    </div>
                </div>
            </div>

            <!-- Copywriting Section B: TIKTOK ADS -->
            <div class="p-4 rounded-xl bg-pink-500/5 border border-pink-500/20 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-pink-600 dark:text-pink-400">
                        🎵 Copywriting &amp; Parameter TikTok Ads
                    </span>
                    <span class="font-mono text-xs text-slate-400">{{ mb_strlen($tiktokAdText) }}/100</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            TikTok Ad Text / Caption (Maks 100 Karakter)
                        </label>
                        <input type="text" maxlength="100" wire:model.live="tiktokAdText"
                               placeholder="Promo spesial hari ini! Klik tombol di bawah #promo #fyp"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            TikTok Location ID
                        </label>
                        <input type="text" wire:model="tiktokLocationId"
                               placeholder="1643084 (Indonesia)"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                    </div>
                </div>
            </div>

            <!-- Copywriting Section C: GOOGLE / YOUTUBE ADS -->
            <div class="p-4 rounded-xl bg-amber-500/5 border border-amber-500/20 space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                    ▶️ Copywriting &amp; Video Asset Google / YouTube Ads (v18)
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            <span>Short Headline (Maks 30 Karakter)</span>
                            <span class="font-mono text-slate-400">{{ mb_strlen($googleHeadline) }}/30</span>
                        </div>
                        <input type="text" maxlength="30" wire:model.live="googleHeadline"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            YouTube Video ID / URL (Kosongkan = Auto-Upload Unlisted)
                        </label>
                        <input type="text" wire:model="youtubeVideoId"
                               placeholder="Otomatis diunggah ke YouTube jika dikosongkan"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs" />
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            <span>Long Headline (Maks 90 Karakter)</span>
                            <span class="font-mono text-slate-400">{{ mb_strlen($googleLongHeadline) }}/90</span>
                        </div>
                        <input type="text" maxlength="90" wire:model.live="googleLongHeadline"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm" />
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                            <span>Description Google (Maks 90 Karakter)</span>
                            <span class="font-mono text-slate-400">{{ mb_strlen($googleDescription) }}/90</span>
                        </div>
                        <input type="text" maxlength="90" wire:model.live="googleDescription"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm" />
                    </div>
                </div>
            </div>

            <!-- Budget, Age, Gender -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-zinc-800">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                        Budget Harian (Rupiah / IDR)
                    </label>
                    <input type="number" step="5000" min="15000" wire:model.live="dailyBudget"
                           class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm font-semibold" />
                    <div class="mt-1.5 flex flex-wrap gap-1">
                        @foreach ([30000 => '30rb', 50000 => '50rb', 100000 => '100rb', 200000 => '200rb'] as $amt => $lbl)
                            <button type="button"
                                    wire:click="$set('dailyBudget', {{ $amt }})"
                                    class="px-2 py-0.5 rounded text-[11px] font-medium border border-slate-200 dark:border-zinc-700 hover:bg-blue-500/10">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                        Rentang Umur Audiens (18 – 65)
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" min="18" max="65" wire:model="ageMin"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-center" />
                        <span class="text-xs text-slate-400">s/d</span>
                        <input type="number" min="18" max="65" wire:model="ageMax"
                               class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm text-center" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300 mb-1">
                        Target Gender
                    </label>
                    <select wire:model="genderSelection"
                            class="w-full rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm">
                        <option value="all">Semua Gender (Pria &amp; Wanita)</option>
                        <option value="male">Pria Saja ([1])</option>
                        <option value="female">Wanita Saja ([2])</option>
                    </select>
                </div>
            </div>

            <!-- Meta Targeting Interests Section -->
            <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                        Daftar Interest (Hasil Meta Targeting Search API)
                    </label>
                    <span class="text-[11px] text-slate-400">Opsional</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    @forelse ($interests as $idx => $interest)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                            <span>{{ $interest['name'] }}</span>
                            <span class="font-mono text-[10px] opacity-60">({{ $interest['id'] }})</span>
                            <button type="button" wire:click="removeInterestItem({{ $idx }})" class="hover:text-rose-500 ml-1">✕</button>
                        </span>
                    @empty
                        <span class="text-xs text-slate-400 italic">Belum ada spesifik interest (Broad Geo-Targeting).</span>
                    @endforelse
                </div>

                <div class="flex gap-2">
                    <input type="text"
                           wire:model="interestSearchQuery"
                           wire:keydown.enter.prevent="searchMetaInterests"
                           placeholder="Cari kata kunci minat di Meta API (contoh: Internet, Belanja online, Kopi)..."
                           class="flex-1 rounded-xl border-slate-300 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs" />
                    <button type="button"
                           wire:click="searchMetaInterests"
                           class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-zinc-800">
                        Cari Interest
                    </button>
                </div>

                @if (!empty($interestSearchResults))
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-700 space-y-1.5">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($interestSearchResults as $res)
                                <button type="button"
                                        wire:click="addInterestItem('{{ $res['id'] }}', '{{ addslashes($res['name']) }}')"
                                        class="px-2.5 py-1 rounded-lg text-xs bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 hover:border-blue-500">
                                    + {{ $res['name'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-slate-200 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-end gap-3">
                <button type="button"
                        wire:click="saveDraft(false)"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-5 py-3 rounded-xl border border-slate-300 dark:border-zinc-700 text-sm font-semibold text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition">
                    💾 Simpan sebagai Draft
                </button>

                <button type="button"
                        wire:click="saveAndPublish"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold shadow-lg shadow-emerald-600/25 transition">
                    <span>🚀 Terbitkan ke Platform Terpilih (Mode Paused)</span>
                </button>
            </div>
        </div>

        <!-- Right 4 cols: Live Multi-Platform Ad Preview -->
        <div class="lg:col-span-4 space-y-4">
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 shadow-sm sticky top-6 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Preview Iklan</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        Mode: PAUSED
                    </span>
                </div>

                <!-- Platform Preview Tabs -->
                <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-xs font-semibold">
                    <button type="button" @click="previewPlatform = 'META'"
                            :class="previewPlatform === 'META' ? 'bg-white dark:bg-zinc-900 text-blue-600 shadow-sm' : 'text-slate-500'"
                            class="py-1.5 rounded-lg transition">📘 Meta</button>
                    <button type="button" @click="previewPlatform = 'TIKTOK'"
                            :class="previewPlatform === 'TIKTOK' ? 'bg-white dark:bg-zinc-900 text-pink-600 shadow-sm' : 'text-slate-500'"
                            class="py-1.5 rounded-lg transition">🎵 TikTok</button>
                    <button type="button" @click="previewPlatform = 'GOOGLE'"
                            :class="previewPlatform === 'GOOGLE' ? 'bg-white dark:bg-zinc-900 text-amber-600 shadow-sm' : 'text-slate-500'"
                            class="py-1.5 rounded-lg transition">▶️ Google</button>
                </div>

                <!-- 1. META PREVIEW -->
                <div x-show="previewPlatform === 'META'" class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-950 overflow-hidden">
                    <div class="p-3.5 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-xs">M</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900 dark:text-white">Halaman Bisnis Anda</div>
                            <div class="text-[10px] text-slate-400">Bersponsor • 🌍 Meta Ads</div>
                        </div>
                    </div>
                    <div class="px-3.5 pb-3 text-xs text-slate-800 dark:text-zinc-200 whitespace-pre-line leading-relaxed">
                        {{ $aiPrimaryText ?: 'Primary Text Meta Ads Anda tampil di sini...' }}
                    </div>
                    <div class="aspect-video bg-zinc-900 flex items-center justify-center text-zinc-500 text-xs">
                        <template x-if="videoPreviewUrl">
                            <video :src="videoPreviewUrl" muted loop autoplay playsinline class="w-full h-full object-cover"></video>
                        </template>
                        <div x-show="!videoPreviewUrl">🎬 Video Meta Feed / Reels</div>
                    </div>
                    <div class="p-3.5 bg-slate-100 dark:bg-zinc-900 flex items-center justify-between gap-3 border-t border-slate-200 dark:border-zinc-800">
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $aiHeadline ?: 'Headline Meta Ads' }}</div>
                            <div class="text-[11px] text-slate-500 truncate">{{ $aiDescription ?: 'Deskripsi singkat' }}</div>
                        </div>
                        <span class="shrink-0 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold">
                            {{ $ctaType === 'WHATSAPP_MESSAGE' ? 'WhatsApp' : 'Selengkapnya' }}
                        </span>
                    </div>
                </div>

                <!-- 2. TIKTOK PREVIEW -->
                <div x-show="previewPlatform === 'TIKTOK'" x-cloak class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-zinc-950 text-white overflow-hidden p-4 space-y-3">
                    <div class="aspect-video bg-zinc-900 rounded-xl flex items-center justify-center text-zinc-500 text-xs overflow-hidden">
                        <template x-if="videoPreviewUrl">
                            <video :src="videoPreviewUrl" muted loop autoplay playsinline class="w-full h-full object-cover"></video>
                        </template>
                        <div x-show="!videoPreviewUrl">🎵 TikTok In-Feed Video Ad</div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold">@AkunBisnisAnda</span>
                            <span class="px-1.5 py-0.5 text-[10px] rounded bg-white/15">Sponsored</span>
                        </div>
                        <p class="text-xs text-zinc-200">{{ $tiktokAdText ?: 'Caption TikTok Ads maks 100 karakter tampil di sini...' }}</p>
                        <div class="pt-2">
                            <div class="w-full py-2 rounded-lg bg-pink-600 text-center text-xs font-bold">
                                {{ $destinationType === 'WHATSAPP' ? 'Hubungi Kami (WhatsApp) →' : 'Selengkapnya →' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. GOOGLE / YOUTUBE PREVIEW -->
                <div x-show="previewPlatform === 'GOOGLE'" x-cloak class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-950 overflow-hidden p-4 space-y-3">
                    <div class="aspect-video bg-zinc-900 rounded-xl flex items-center justify-center text-zinc-500 text-xs overflow-hidden">
                        <template x-if="videoPreviewUrl">
                            <video :src="videoPreviewUrl" muted loop autoplay playsinline class="w-full h-full object-cover"></video>
                        </template>
                        <div x-show="!videoPreviewUrl">▶️ YouTube Shorts / Demand Gen Ad</div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold">Bersponsor</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $googleHeadline ?: 'Headline Google (30 chr)' }}</span>
                        </div>
                        <div class="text-xs font-medium text-slate-700 dark:text-zinc-300">{{ $googleLongHeadline ?: 'Long Headline Google Ads tampil di sini' }}</div>
                        <div class="text-[11px] text-slate-500">{{ $googleDescription ?: 'Deskripsi pendukung Google Ads' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet Map Initialization Script -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            const mapEl = document.getElementById('studio-leaflet-map');
            if (!mapEl) return;

            let initialInclude = @json($customLocations);
            let initialExclude = @json($excludedLocations);

            const startLat = initialInclude.length > 0 ? parseFloat(initialInclude[0].latitude) : -1.610123;
            const startLng = initialInclude.length > 0 ? parseFloat(initialInclude[0].longitude) : 103.613120;

            const map = L.map('studio-leaflet-map').setView([startLat, startLng], 11);
            window.studioLeafletMap = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            let includeMarkers = [];
            let includeCircles = [];
            let excludeMarkers = [];
            let excludeCircles = [];

            function clearLayers() {
                includeMarkers.forEach(m => map.removeLayer(m));
                includeCircles.forEach(c => map.removeLayer(c));
                excludeMarkers.forEach(m => map.removeLayer(m));
                excludeCircles.forEach(c => map.removeLayer(c));
                includeMarkers = [];
                includeCircles = [];
                excludeMarkers = [];
                excludeCircles = [];
            }

            function renderAllPins(incList, excList) {
                clearLayers();

                (incList || []).forEach((loc, idx) => {
                    const lat = parseFloat(loc.latitude);
                    const lng = parseFloat(loc.longitude);
                    const radKm = parseFloat(loc.radius || 10);

                    const marker = L.marker([lat, lng]).addTo(map);
                    marker.bindPopup(`<b>Include #${idx + 1}: ${loc.name || 'Target'}</b><br>Radius: ${radKm} km`);

                    const circle = L.circle([lat, lng], {
                        color: '#2563eb',
                        fillColor: '#3b82f6',
                        fillOpacity: 0.2,
                        weight: 2,
                        radius: radKm * 1000
                    }).addTo(map);

                    includeMarkers.push(marker);
                    includeCircles.push(circle);
                });

                (excList || []).forEach((loc, idx) => {
                    const lat = parseFloat(loc.latitude);
                    const lng = parseFloat(loc.longitude);
                    const radKm = parseFloat(loc.radius || 5);

                    const marker = L.marker([lat, lng]).addTo(map);
                    marker.bindPopup(`<b>Exclude #${idx + 1}: ${loc.name || 'Zona Exclude'}</b><br>Radius: ${radKm} km`);

                    const circle = L.circle([lat, lng], {
                        color: '#e11d48',
                        fillColor: '#f43f5e',
                        fillOpacity: 0.25,
                        weight: 2,
                        dashArray: '6, 6',
                        radius: radKm * 1000
                    }).addTo(map);

                    excludeMarkers.push(marker);
                    excludeCircles.push(circle);
                });
            }

            renderAllPins(initialInclude, initialExclude);

            window.updateStudioCircleRadius = function(type, index, radiusKm) {
                const meters = parseFloat(radiusKm) * 1000;
                if (type === 'include' && includeCircles[index]) {
                    includeCircles[index].setRadius(meters);
                } else if (type === 'exclude' && excludeCircles[index]) {
                    excludeCircles[index].setRadius(meters);
                }
            };

            map.on('click', function(e) {
                const rootAlpine = mapEl.closest('[x-data]');
                const mode = rootAlpine && rootAlpine._x_dataStack ? rootAlpine._x_dataStack[0].pinMode : 'include';
                @this.addMapPin(e.latlng.lat, e.latlng.lng, mode, mode === 'include' ? 10 : 5);
            });

            Livewire.on('pins-updated', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                if (data) {
                    renderAllPins(data.customLocations || [], data.excludedLocations || []);
                }
            });
        });
    </script>
</div>
