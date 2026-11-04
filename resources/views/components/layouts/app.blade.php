<!DOCTYPE html>
<html lang="id" class="h-full" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && true) }" x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MetaAds AI Publisher' }} — Meta Marketing API v21.0</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS CDN (with Shadcn-inspired configuration) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        border: 'hsl(var(--border))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                    }
                }
            }
        }
    </script>

    <!-- Leaflet.js OpenStreetMap CSS & JS (Free, No API Key Required) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        [x-cloak] { display: none !important; }
        .leaflet-container {
            font-family: 'Inter', sans-serif;
            z-index: 10;
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.7); }
    </style>

    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900 dark:bg-zinc-950 dark:text-zinc-100 antialiased selection:bg-blue-600 selection:text-white transition-colors duration-200">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <!-- Sidebar Navigation -->
        <aside class="w-full lg:w-72 lg:fixed lg:inset-y-0 lg:z-40 bg-white dark:bg-zinc-900/90 border-b lg:border-b-0 lg:border-r border-slate-200 dark:border-zinc-800 flex flex-col justify-between backdrop-blur">
            <div>
                <!-- Brand Header -->
                <div class="h-16 px-6 flex items-center justify-between border-b border-slate-200 dark:border-zinc-800">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-blue-600/25">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-bold text-sm tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                                MetaAds AI
                                <span class="px-1.5 py-0.5 text-[10px] font-mono font-semibold uppercase rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">Omni</span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">Meta • TikTok • Google Ads</p>
                        </div>
                    </a>

                    <!-- Mobile Theme Toggle -->
                    <button @click="darkMode = !darkMode" type="button" class="lg:hidden p-2 rounded-lg border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800">
                        <svg x-show="!darkMode" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        <svg x-show="darkMode" x-cloak class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </button>
                </div>

                <!-- Navigation Menu -->
                <nav class="p-4 space-y-1.5 flex lg:block overflow-x-auto">
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap {{ request()->routeIs('dashboard') ? 'bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800/70 hover:text-slate-900 dark:hover:text-zinc-100' }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span>Ringkasan Dashboard</span>
                    </a>

                    <a href="{{ route('ads.create') }}"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap {{ request()->routeIs('ads.create') ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/30' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800/70 hover:text-slate-900 dark:hover:text-zinc-100' }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        <span>Studio Pembuat Iklan AI</span>
                    </a>

                    <a href="{{ route('ads.index') }}"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap {{ request()->routeIs('ads.index') || request()->routeIs('ads.show') ? 'bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800/70 hover:text-slate-900 dark:hover:text-zinc-100' }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span>Manajemen Kampanye</span>
                    </a>

                    <a href="{{ route('settings.index') }}"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap {{ request()->routeIs('settings.index') ? 'bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800/70 hover:text-slate-900 dark:hover:text-zinc-100' }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Pengaturan Sistem</span>
                    </a>
                </nav>
            </div>

            <!-- Sidebar Footer -->
            <div class="hidden lg:block p-4 border-t border-slate-200 dark:border-zinc-800 space-y-3">
                <div class="p-3 rounded-xl bg-slate-100/80 dark:bg-zinc-800/60 border border-slate-200/70 dark:border-zinc-700/50 text-xs space-y-1.5">
                    <div class="flex items-center justify-between font-medium text-slate-700 dark:text-zinc-300">
                        <span>Webhook Endpoint</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    </div>
                    <div class="font-mono text-[11px] text-slate-500 dark:text-zinc-400 truncate">
                        POST /api/telegram/webhook
                    </div>
                </div>

                <button @click="darkMode = !darkMode" type="button"
                        class="w-full flex items-center justify-between px-3.5 py-2 rounded-xl border border-slate-200 dark:border-zinc-800 text-xs font-medium text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition">
                    <span x-text="darkMode ? 'Mode Gelap (Dark)' : 'Mode Terang (Light)'"></span>
                    <svg x-show="!darkMode" class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg x-show="darkMode" x-cloak class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 lg:pl-72 min-w-0">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
