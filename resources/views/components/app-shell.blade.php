@props([
    'primary' => [],      // up to 4 items for the mobile bottom bar + top of sidebar
    'more' => [],         // secondary items: the mobile "More" sheet + lower sidebar
    'brandLabel' => 'NaaraSim',
    'brandRoute' => null,
    'brandIcon' => 'signal',
    'promo' => false,     // customer shell only: promo card / banner in the More sheet
])

@php
    $isActive = fn ($route) => request()->routeIs($route);
    $allItems = array_merge($primary, $more);
    // Pad primary to 4 so the bottom bar stays balanced around the centre button.
    $slots = array_pad(array_slice($primary, 0, 4), 4, null);
@endphp

<div x-data="{ moreOpen: false }" class="min-h-screen">
    {{-- ============ DESKTOP: Apple-inspired floating side menu ============ --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-72 lg:flex-col lg:p-3">
        <div class="flex h-full flex-col rounded-3xl border border-slate-200/70 bg-white/70 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-white/[0.04]">
            <a href="{{ $brandRoute ?? '#' }}" class="flex items-center px-5 py-5">
                <x-brand-logo variant="product" class="h-9 max-w-[180px]" :fallback-icon="$brandIcon" />
            </a>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                @foreach ($allItems as $item)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition',
                           'bg-primary/10 text-primary-dark shadow-sm dark:bg-primary/20 dark:text-primary' => $isActive($item['route']),
                           'text-slate-600 hover:bg-slate-100/80 dark:text-slate-300 dark:hover:bg-white/5' => ! $isActive($item['route']),
                       ])>
                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center justify-between gap-2 border-t border-slate-200/70 p-3 dark:border-white/10">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100/80 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-200">
                        <x-icon name="log-out" class="h-5 w-5" /> Sign out
                    </button>
                </form>
                <x-theme-toggle />
            </div>
        </div>
    </aside>

    {{-- ============ MOBILE: top brand bar ============ --}}
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200/70 bg-white/80 px-4 py-3 backdrop-blur-xl lg:hidden dark:border-white/10 dark:bg-[#0D1B2A]/80">
        <a href="{{ $brandRoute ?? '#' }}" class="flex items-center">
            <x-brand-logo variant="product" class="h-8 max-w-[150px]" :fallback-icon="$brandIcon" />
        </a>
        <x-theme-toggle />
    </header>

    {{-- ============ Page content ============ --}}
    <div class="lg:pl-72">
        <main class="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:px-8 lg:py-10 lg:pb-10">
            {{ $slot }}
        </main>
    </div>

    {{-- ============ MOBILE: bottom navigation ============ --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/70 bg-white/85 backdrop-blur-xl lg:hidden dark:border-white/10 dark:bg-[#0D1B2A]/85"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1">
            @foreach ([$slots[0], $slots[1]] as $item)
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
            @endforeach

            {{-- Centre "More" button --}}
            <div class="flex justify-center">
                <button type="button" @click="moreOpen = true" aria-label="More"
                        class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 ring-4 ring-[#F8F9FA] transition active:scale-95 dark:ring-navy">
                    <x-icon name="grid" class="h-6 w-6" />
                </button>
            </div>

            @foreach ([$slots[2], $slots[3]] as $item)
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
            @endforeach
        </div>
    </nav>

    {{-- ============ MOBILE: "More" sheet ============ --}}
    <div x-show="moreOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" style="display:none;">
        <div x-show="moreOpen" x-transition.opacity @click="moreOpen = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div x-show="moreOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 rounded-t-3xl border-t border-slate-200/70 bg-white p-5 pb-9 shadow-2xl dark:border-white/10 dark:bg-[#0D1B2A]"
             style="padding-bottom: calc(env(safe-area-inset-bottom) + 1.5rem);">
            <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20"></div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">More</h2>
                <button type="button" @click="moreOpen = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"><x-icon name="x" class="h-5 w-5" /></button>
            </div>

            @if ($promo)
                @php($menuBanners = \App\Support\Banners::for('menu_sheet'))
                @if ($menuBanners->isNotEmpty())
                    <x-banner-zone placement="menu_sheet" class="mb-4" />
                @else
                    {{-- Default promo (Module 32 pick — ayman-ashine floating-light
                         card, rebuilt on brand): shown until the admin publishes a
                         banner for this zone. --}}
                    <div class="nx-float-card mb-4" aria-hidden="true">
                        <span class="nx-float-card__light"></span>
                        <span class="nx-float-card__ring"></span>
                        <div class="relative">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">{{ \App\Support\BrandSettings::name() }}</p>
                            <p class="mt-1.5 font-display text-lg font-bold leading-snug text-white">Stay Connected. No&nbsp;Borders. No&nbsp;Swaps.</p>
                            <p class="mt-1 text-xs text-slate-300">eSIM data + numbers for 190+ countries, in one wallet.</p>
                        </div>
                    </div>
                @endif
            @endif

            <div class="grid grid-cols-4 gap-3">
                @foreach ($more as $item)
                    <a href="{{ route($item['route']) }}" @click="moreOpen = false"
                       @class([
                           'flex flex-col items-center gap-1.5 rounded-2xl border p-3 text-center transition',
                           'border-primary/30 bg-primary/10 dark:border-primary/40 dark:bg-primary/20' => $isActive($item['route']),
                           'border-slate-200 bg-slate-50 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10' => ! $isActive($item['route']),
                       ])>
                        <x-icon :name="$item['icon']" class="h-6 w-6 text-primary" />
                        <span class="text-[11px] font-medium leading-tight text-slate-600 dark:text-slate-300">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-5">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10">
                    <x-icon name="log-out" class="h-5 w-5" /> Sign out
                </button>
            </form>
        </div>
    </div>
</div>
