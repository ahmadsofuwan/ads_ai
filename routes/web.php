<?php

use App\Livewire\AdCampaignDetail;
use App\Livewire\AdCampaignIndex;
use App\Livewire\AdStudio;
use App\Livewire\DashboardOverview;
use App\Livewire\MediaLibrary;
use App\Livewire\SystemSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardOverview::class)->name('dashboard');
Route::get('/ads/create', AdStudio::class)->name('ads.create');
Route::get('/media', MediaLibrary::class)->name('media.index');
Route::get('/ads', AdCampaignIndex::class)->name('ads.index');
Route::get('/ads/{adDraft}', AdCampaignDetail::class)->name('ads.show');
Route::get('/settings', SystemSettings::class)->name('settings.index');
