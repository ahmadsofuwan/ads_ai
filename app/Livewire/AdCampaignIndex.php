<?php

namespace App\Livewire;

use App\Jobs\PublishGoogleAdJob;
use App\Jobs\PublishMetaAdJob;
use App\Jobs\PublishTiktokAdJob;
use App\Models\AdDraft;
use App\Services\GoogleAdsService;
use App\Services\MetaMarketingService;
use App\Services\TikTokMarketingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Manajemen Kampanye Omni-Channel — MetaAds AI Publisher')]
class AdCampaignIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $destinationFilter = '';
    public ?string $flashMessage = null;
    public string $flashType = 'success';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDestinationFilter(): void
    {
        $this->resetPage();
    }

    public function toggleMetaStatus(int $draftId): void
    {
        $draft = AdDraft::find($draftId);
        if (! $draft || empty($draft->meta_campaign_id)) {
            return;
        }

        try {
            $targetStatus = $draft->status === AdDraft::STATUS_ACTIVE_ON_META ? 'PAUSED' : 'ACTIVE';
            app(MetaMarketingService::class)->updateStatus($draft, $targetStatus);
            $this->flashType = 'success';
            $this->flashMessage = "Status Meta Ads \"{$draft->title}\" diubah ke {$targetStatus}.";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal mengubah status Meta Ads: ' . $e->getMessage();
        }
    }

    public function toggleTiktokStatus(int $draftId): void
    {
        $draft = AdDraft::find($draftId);
        if (! $draft || empty($draft->tiktok_campaign_id)) {
            return;
        }

        try {
            $targetStatus = $draft->tiktok_status === AdDraft::TIKTOK_STATUS_ACTIVE ? 'PAUSED' : 'ACTIVE';
            app(TikTokMarketingService::class)->updateStatus($draft, $targetStatus);
            $this->flashType = 'success';
            $this->flashMessage = "Status TikTok Ads \"{$draft->title}\" diubah ke {$targetStatus}.";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal mengubah status TikTok Ads: ' . $e->getMessage();
        }
    }

    public function toggleGoogleStatus(int $draftId): void
    {
        $draft = AdDraft::find($draftId);
        if (! $draft || empty($draft->google_campaign_id)) {
            return;
        }

        try {
            $targetStatus = $draft->google_status === AdDraft::GOOGLE_STATUS_ACTIVE ? 'PAUSED' : 'ACTIVE';
            app(GoogleAdsService::class)->updateStatus($draft, $targetStatus);
            $this->flashType = 'success';
            $this->flashMessage = "Status Google Ads \"{$draft->title}\" diubah ke {$targetStatus}.";
        } catch (\Throwable $e) {
            $this->flashType = 'error';
            $this->flashMessage = 'Gagal mengubah status Google Ads: ' . $e->getMessage();
        }
    }

    public function publishPlatform(int $draftId, string $platform): void
    {
        $draft = AdDraft::find($draftId);
        if (! $draft) {
            return;
        }

        $platform = strtoupper($platform);

        if ($platform === 'META') {
            $draft->update(['status' => AdDraft::STATUS_UPLOADING_VIDEO, 'error_log' => null]);
            PublishMetaAdJob::dispatch($draft->id, $draft->telegram_chat_id);
        } elseif ($platform === 'TIKTOK') {
            $draft->update(['tiktok_status' => AdDraft::TIKTOK_STATUS_UPLOADING, 'tiktok_error_log' => null]);
            PublishTiktokAdJob::dispatch($draft->id, $draft->telegram_chat_id);
        } elseif ($platform === 'GOOGLE') {
            $draft->update(['google_status' => AdDraft::GOOGLE_STATUS_UPLOADING_YT, 'google_error_log' => null]);
            PublishGoogleAdJob::dispatch($draft->id, $draft->telegram_chat_id);
        }

        $this->flashType = 'success';
        $this->flashMessage = "Job penerbitan {$platform} untuk \"{$draft->title}\" telah dikirim ke Queue.";
    }

    public function deleteDraft(int $draftId): void
    {
        $draft = AdDraft::find($draftId);
        if (! $draft) {
            return;
        }

        $title = $draft->title;
        $draft->delete();

        $this->flashType = 'success';
        $this->flashMessage = "Data iklan \"{$title}\" berhasil dihapus dari database lokal.";
    }

    public function render()
    {
        $query = AdDraft::query()->orderByDesc('created_at');

        if (trim($this->search) !== '') {
            $q = '%' . trim($this->search) . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', $q)
                    ->orWhere('ai_headline', 'like', $q)
                    ->orWhere('meta_campaign_id', 'like', $q)
                    ->orWhere('tiktok_campaign_id', 'like', $q)
                    ->orWhere('google_campaign_id', 'like', $q);
            });
        }

        if ($this->statusFilter !== '') {
            $query->where(function ($sub) {
                $sub->where('status', $this->statusFilter)
                    ->orWhere('tiktok_status', $this->statusFilter)
                    ->orWhere('google_status', $this->statusFilter);
            });
        }

        if ($this->destinationFilter !== '') {
            $query->where('destination_type', $this->destinationFilter);
        }

        return view('livewire.ad-campaign-index', [
            'campaigns' => $query->paginate(12),
        ]);
    }
}
