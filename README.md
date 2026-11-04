# Omni-Channel Ads AI Publisher (Meta Ads • TikTok Ads • Google Ads + Multi-Provider AI + Telegram Bot)

Aplikasi web berbasis **Laravel + Livewire + Tailwind CSS + Leaflet.js** dan **Bot Telegram Webhook** untuk mengotomatisasi pembuatan serta manajemen kampanye iklan video lintas platform (**Meta Ads v21.0**, **TikTok Marketing API v1.3**, dan **Google Ads API v18 & YouTube Data API v3**) dengan dukungan **Multi-Account per Platform** dan **Multi-Provider AI LLM**.

---

## Fitur Utama

1. **Multi-Account Ads API per Platform (`ad_accounts`)**:
   - Menyimpan **banyak akun iklan (Unlimited Ad Accounts)** untuk masing-masing platform:
     - **Meta Ads v21.0** (Facebook & Instagram Ads — `act_` Ad Account ID, Page ID, WhatsApp, Pixel ID)
     - **TikTok Marketing API v1.3** (Advertiser ID, Identity ID, Location ID, Pixel ID)
     - **Google Ads API v18 & YouTube Data API v3** (Developer Token, OAuth2 Client, Refresh Token, Customer ID, MCC Login Customer ID)
   - Mendukung **Multi-Account Broadcast** di Studio Iklan: centang beberapa akun iklan sekaligus dalam satu pembuatan kampanye.

2. **Multi-Provider AI Media Buyer & Auto-Fallback (`ai_providers`)**:
   - Mendukung banyak provider AI sekaligus: **OpenRouter**, **OpenAI Official**, **Google Gemini**, **Groq Cloud**, **DeepSeek**, dan **Custom OpenAI-Compatible Endpoint**.
   - **Auto-Fallback berdasarkan prioritas**: jika provider AI utama terkena *rate-limit* atau gangguan, sistem otomatis beralih ke provider AI aktif berikutnya, serta dilengkapi *Smart Heuristic Engine* lokal sebagai cadangan terakhir.
   - Mengekstrak parameter targeting otomatis dari perintah bahasa sehari-hari (`title`, `daily_budget`, `age_min`, `age_max`, `genders`, `destination_type`, `objective`, `radius_km`, `interest_keywords`, `target_platforms`) dan menulis copywriting khusus untuk Meta, TikTok, dan Google Ads.

3. **Media Storage Library & Multimodal AI Vision (`/media`)**:
   - **Upload Sekali, Pakai Berkali-kali**: Unggah koleksi **Video** (`MP4`, `MOV`, `WEBM`) maupun **Gambar/Poster** (`JPG`, `PNG`, `WEBP`) ke Storage Library terlebih dahulu lengkap dengan judul dan catatan konteks produk.
   - **AI Vision (Melihat Isi Video & Gambar)**: Otomatis mengekstrak 3 *keyframe* representatif dari video via `FFmpeg` (15%, 50%, 85% durasi) atau mengonversi gambar ke format multimodal `image_url` (Base64) sehingga AI Vision dapat **membaca teks/harga promo di dalam video/gambar (OCR)** dan mengamati visual produk saat menyusun copywriting iklan.

4. **AI Video & Image Studio Editor (`MediaEditorService`)**:
   - **Edit via Perintah Chat AI (*Prompt-to-Edit*)**: Ketik instruksi natural (contoh: *"Ubah ke vertikal 9:16 untuk TikTok, warna cerah, potong 12 detik, dan pasang banner merah: PROMO WIFI 100RB/BLN"*).
   - **Ubah Gambar Jadi Video Iklan MP4 (`Image-to-Video`)**: Konversi 1 gambar/poster statis menjadi file video `.mp4` (`H.264 + AAC stereo audio`) berdurasi 6–15 detik agar langsung kompatibel dengan endpoint Video Ads Meta, TikTok, dan YouTube Shorts.
   - **Auto Aspect Ratio (`9:16`, `1:1`, `4:5`, `16:9`)**, **Hook Text Banner Overlay & CTA**, **Color Grading (`VIBRANT`, `BRIGHT`, `WARM`, `CINEMATIC`)**, serta **Trim Durasi & Speed Control (`1.0x`–`1.5x`)**.

5. **Peta Multi-Pin & Radius Real-Time (Leaflet.js OpenStreetMap)**:
   - Klik di peta untuk menaruh banyak pin lokasi sekaligus (Mode **Include Biru** & **Exclude Merah**).
   - Slider radius `1 km – 80 km` per pin yang langsung menggambar lingkaran cakupan di atas peta secara real-time (digunakan pada Meta `custom_locations` dan Google Ads `Proximity Geo-Targeting`).

4. **Bot Telegram Lengkap (`/api/telegram/webhook`)**:
   - Proteksi akses dengan `allowed_telegram_chat_ids`.
   - Dukungan **Share Location** (`message.location`) & deteksi koordinat di teks.
   - Terima video + caption perintah -> susun draft AI Omni-Channel -> kirim ringkasan dengan tombol Inline Keyboard untuk menerbitkan ke Meta, TikTok, Google, atau ketiganya sekaligus.

---

## Cara Menjalankan Aplikasi

1. **Instalasi & Migrasi Database**:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```

2. **Jalankan Web Server & Queue Worker**:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   php artisan queue:work --tries=1 --timeout=600
   ```

3. **Pengujian Otomatis (PHPUnit)**:
   ```bash
   php artisan test
   ```
