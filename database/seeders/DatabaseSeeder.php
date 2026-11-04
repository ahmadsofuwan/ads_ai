<?php

namespace Database\Seeders;

use App\Models\AdDraft;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $setting = Setting::current();

        Storage::disk('public')->makeDirectory('ads_videos');

        $sampleSource = '/root/agy/tutorial-ftth-part1-shorts.mp4';
        $relativeVideoPath = 'ads_videos/sample-promo-ftth.mp4';

        if (file_exists($sampleSource) && ! Storage::disk('public')->exists($relativeVideoPath)) {
            copy($sampleSource, Storage::disk('public')->path($relativeVideoPath));
        }

        if (AdDraft::count() === 0) {
            AdDraft::create([
                'title' => 'Promo Pasang WiFi FTTH Unlimited - Jambi (WA)',
                'raw_user_prompt' => 'Promo pasang WiFi FTTH cepat unlimited untuk keluarga di Jambi, radius 10km, umur 24-45 tahun, budget 50rb/hari arahkan ke WA.',
                'video_local_path' => $relativeVideoPath,
                'target_platforms' => ['META', 'TIKTOK', 'GOOGLE'],
                'objective' => AdDraft::OBJECTIVE_ENGAGEMENT,
                'destination_type' => AdDraft::DESTINATION_WHATSAPP,
                'destination_url' => 'https://wa.me/6281234567890',
                'daily_budget' => 50000,
                'age_min' => 24,
                'age_max' => 45,
                'genders' => [],
                'custom_locations' => [
                    [
                        'latitude' => (float) $setting->default_latitude,
                        'longitude' => (float) $setting->default_longitude,
                        'radius' => 10,
                        'distance_unit' => 'kilometer',
                        'name' => 'Pusat Kota Jambi',
                    ],
                ],
                'excluded_locations' => [],
                'interests' => [
                    ['id' => '6003139266461', 'name' => 'Internet'],
                    ['id' => '6003384248805', 'name' => 'Wi-Fi'],
                    ['id' => '6003277229371', 'name' => 'Smart home'],
                ],
                'ai_primary_text' => "🔥 Internet rumah sering buffering saat jam sibuk? Saatnya beralih ke jaringan Fiber Optik (FTTH) berkecepatan tinggi yang stabil di segala cuaca!\n\nKeunggulan layanan kami untuk keluarga Anda:\n✅ Internet 100% Unlimited tanpa batasan FUP\n✅ Koneksi simetris cepat untuk streaming, kerja & gaming\n✅ Gratis biaya survei & instalasi cepat di area Anda\n✅ Tim teknisi lokal siap siaga membantu 24/7\n\n📲 Klik tombol \"Kirim Pesan WhatsApp\" di bawah sekarang untuk cek cakupan lokasi & klaim promo pemasangan hari ini!",
                'ai_headline' => 'Pasang WiFi Cepat - Chat WA Sekarang!',
                'ai_description' => 'Gratis Instalasi Area Lokal - Kuota Terbatas!',
                'cta_type' => AdDraft::CTA_WHATSAPP_MESSAGE,
                'status' => AdDraft::STATUS_DRAFT,
                'tiktok_ad_text' => '🔥 Pasang WiFi FTTH Unlimited tanpa FUP! Klik tombol di bawah buat chat WA sekarang! #wifi #promo',
                'tiktok_location_ids' => ['1643084'],
                'tiktok_status' => AdDraft::TIKTOK_STATUS_DRAFT,
                'google_headline' => 'Pasang WiFi FTTH Unlimited',
                'google_long_headline' => 'Internet Rumah Fiber Optik Cepat Tanpa FUP — Gratis Instalasi Hari Ini!',
                'google_description' => 'Klik untuk konsultasi via WhatsApp & cek jaringan di lokasi rumah Anda sekarang!',
                'google_status' => AdDraft::GOOGLE_STATUS_DRAFT,
            ]);
        }
    }
}
