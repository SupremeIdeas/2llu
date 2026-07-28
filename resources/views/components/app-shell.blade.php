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

<div x-data="{
        moreOpen: false,
        navCollapsed: localStorage.getItem('nx_nav_collapsed') === '1',
        navFloating: localStorage.getItem('nx_nav_floating') !== '0',
     }"
     x-effect="localStorage.setItem('nx_nav_collapsed', navCollapsed ? '1' : '0'); localStorage.setItem('nx_nav_floating', navFloating ? '1' : '0')"
     @nx-nav-style.window="navFloating = $event.detail.floating"
     class="min-h-screen">
    {{-- ============ DESKTOP: Apple-inspired floating side menu ============
         Collapsible: the toggle shrinks it to an icon-only rail and back to
         icons + labels. The choice is remembered in localStorage. --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-72 lg:flex-col lg:p-3 lg:transition-[width] lg:duration-300"
           :class="navCollapsed ? 'lg:!w-24' : ''">
        <div class="flex h-full flex-col rounded-3xl border border-slate-200/70 bg-gradient-to-b from-teal-50 via-slate-50 to-slate-100/70 shadow-sm backdrop-blur-xl dark:border-white/10 dark:from-white/[0.06] dark:via-white/[0.03] dark:to-white/[0.02]">
            <div class="flex items-center py-5" :class="navCollapsed ? 'justify-center px-3' : 'justify-between px-5'">
                <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center" x-show="!navCollapsed">
                    <x-brand-logo variant="product" class="h-9 max-w-[150px]" :fallback-icon="$brandIcon" />
                </a>
                <button type="button" @click="navCollapsed = !navCollapsed"
                        :aria-label="navCollapsed ? 'Expand menu' : 'Collapse menu'" :aria-expanded="(!navCollapsed).toString()"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100/80 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/5">
                    <span class="transition-transform duration-300" :class="navCollapsed ? '' : 'rotate-180'">
                        <x-icon name="chevron-right" class="h-5 w-5" />
                    </span>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-2">
                @foreach ($allItems as $item)
                    @if (! empty($item['heading']))
                        {{-- Section label — groups complementary items (collapsed
                             sidebar shows a divider instead). --}}
                        <p x-show="!navCollapsed" class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $item['heading'] }}</p>
                        <div x-show="navCollapsed" x-cloak class="mx-auto my-2 h-px w-6 bg-slate-200 dark:bg-white/10"></div>
                    @else
                        <a href="{{ route($item['route']) }}" wire:navigate
                           :class="navCollapsed && 'justify-center'"
                           :title="navCollapsed ? @js($item['label']) : null"
                           @class([
                               'nx-navlink group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium',
                               'is-active' => $isActive($item['route']),
                               'text-slate-600 hover:bg-slate-100/80 dark:text-slate-300 dark:hover:bg-white/5' => ! $isActive($item['route']),
                           ])>
                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                            <span class="truncate" x-show="!navCollapsed">{{ $item['label'] }}</span>
                            @if ($item['badge'] ?? null)
                                <span x-show="!navCollapsed" class="ml-auto rounded-full bg-accent/15 px-1.5 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="flex gap-2 border-t border-slate-200/70 p-3 dark:border-white/10"
                 :class="navCollapsed ? 'flex-col items-center' : 'items-center justify-between'">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" :title="navCollapsed ? 'Sign out' : null"
                            class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100/80 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-200">
                        <x-icon name="log-out" class="h-5 w-5 shrink-0" /> <span x-show="!navCollapsed">Sign out</span>
                    </button>
                </form>
                <x-theme-toggle />
            </div>
        </div>
    </aside>

    {{-- ============ MOBILE: top brand bar ============ --}}
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200/70 bg-white/80 px-4 py-3 backdrop-blur-xl lg:hidden dark:border-white/10 dark:bg-[#0D1B2A]/80">
        <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center">
            <x-brand-logo variant="product" class="h-8 max-w-[150px]" :fallback-icon="$brandIcon" />
        </a>
        <div class="flex items-center gap-1">
            {{ $headerActions ?? '' }}
            <x-theme-toggle />
        </div>
    </header>

    {{-- ============ Page content ============ --}}
    <div class="lg:pl-72 lg:transition-[padding] lg:duration-300" :class="navCollapsed ? 'lg:!pl-24' : ''">
        {{-- Desktop top strip: header actions (e.g. the notification bell) sit
             top-right of the content, mirroring the mobile header. A SEPARATE
             slot from the mobile one so each Livewire instance has its own id. --}}
        @isset($headerActionsDesktop)
            <div class="sticky top-0 z-30 hidden items-center justify-end gap-1 border-b border-slate-200/60 bg-white/70 px-8 py-3 backdrop-blur-xl lg:flex dark:border-white/10 dark:bg-[#0D1B2A]/70">
                {{ $headerActionsDesktop }}
            </div>
        @endisset
        <main class="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:px-8 lg:py-10 lg:pb-10">
            {{ $slot }}
        </main>
    </div>

    {{-- ============ MOBILE: bottom navigation (owner request — premium) ============
         Two styles the user chooses between: FLOATING (default) — a rounded-3xl
         pill lifted off the bottom edge with a shadow on all sides; or DOCKED —
         flush to the bottom with only the top corners rounded. The little grab
         handle toggles between them (also settable from account settings). --}}
    <nav class="fixed z-40 border border-slate-200/70 bg-white/90 backdrop-blur-xl transition-all duration-300 lg:hidden dark:border-white/10 dark:bg-[#0D1B2A]/90"
         :class="navFloating
            ? 'inset-x-3 bottom-3 rounded-[1.75rem] shadow-[0_10px_40px_rgba(13,27,42,0.16)] dark:shadow-[0_10px_40px_rgba(0,0,0,0.5)]'
            : 'inset-x-0 bottom-0 rounded-t-3xl border-b-0 shadow-[0_-10px_30px_rgba(13,27,42,0.10)] dark:shadow-[0_-10px_30px_rgba(0,0,0,0.4)]'"
         style="padding-bottom: env(safe-area-inset-bottom);">
        {{-- Grab handle — tap to switch floating ⇄ docked. --}}
        <button type="button" @click="navFloating = !navFloating"
                :aria-label="navFloating ? 'Dock the navigation bar' : 'Float the navigation bar'"
                class="absolute left-1/2 top-1 flex h-4 w-12 -translate-x-1/2 items-center justify-center">
            <span class="h-1 w-9 rounded-full bg-slate-300 transition dark:bg-white/20"></span>
        </button>

        <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
            @foreach ([$slots[0], $slots[1]] as $item)
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
            @endforeach

            {{-- Centre "More" button --}}
            <div class="flex justify-center">
                <button type="button" @click="moreOpen = true" aria-label="More"
                        class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-dark text-white shadow-lg shadow-primary/30 ring-4 ring-[#F8F9FA] transition active:scale-95 dark:ring-navy">
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
                    @continue(! empty($item['heading'])) {{-- headings are desktop-sidebar only --}}
                    <a href="{{ route($item['route']) }}" wire:navigate @click="moreOpen = false"
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
