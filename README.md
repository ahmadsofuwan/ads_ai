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

3. **Peta Multi-Pin & Radius Real-Time (Leaflet.js OpenStreetMap)**:
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
