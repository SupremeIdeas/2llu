{{-- Global glass sidebar (BUILD-3 §7). A slide-out, separate from the bottom
     "More" sheet, that always reaches the legal/compliance pages an app-store
     review needs — its contents are server-rendered here (and cached), so it
     works with no live network path through deep app state. Premium
     glassmorphism, reusing the app's existing translucent/blur treatment. --}}
@php
    $legal = \App\Support\SidebarMenu::legalLinks();
    $custom = \App\Support\SidebarMenu::customLinks();
    $reviews = \App\Support\SidebarMenu::reviewsUrl();
    $mode = \App\Support\SidebarMenu::displayMode();
    $social = \App\Support\SocialLinks::all();
    $posts = \App\Support\SidebarMenu::blogPosts();
    $linkGridClass = $mode === 'grid' ? 'grid grid-cols-2 gap-2' : 'flex flex-col gap-1.5';
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false" @open-global-sidebar.window="open = true">
    {{-- Trigger (sits beside the bell + theme toggle). --}}
    <button type="button" @click="open = true" aria-label="Open menu"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    {{-- Backdrop --}}
    <div x-show="open" x-cloak x-transition.opacity @click="open = false"
         class="fixed inset-0 z-[70] bg-black/40 backdrop-blur-sm"></div>

    {{-- Panel — glassmorphism. --}}
    <aside x-show="open" x-cloak
           x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-[71] flex w-[85vw] max-w-sm flex-col border-l border-white/30 bg-white/75 shadow-2xl backdrop-blur-2xl dark:border-white/10 dark:bg-[#0D1B2A]/80"
           style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom);">

        <div class="flex items-center justify-between border-b border-white/30 px-5 py-4 dark:border-white/10">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Menu</p>
            <button type="button" @click="open = false" aria-label="Close menu"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100/70 dark:hover:bg-white/10">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto overscroll-contain px-5 py-5">
            {{-- Legal & policies + any admin custom links --}}
            <section>
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Legal &amp; policies</h3>
                <div class="{{ $linkGridClass }}">
                    @foreach ($legal as $l)
                        <a href="{{ $l['url'] }}" wire:navigate @click="open = false"
                           class="flex items-center gap-2.5 rounded-xl border border-white/40 bg-white/50 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-white/80 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10">
                            <x-icon name="shield" class="h-4 w-4 shrink-0 text-primary" /> <span class="truncate">{{ $l['label'] }}</span>
                        </a>
                    @endforeach
                    @foreach ($custom as $c)
                        <a href="{{ $c['url'] }}" @click="open = false"
                           class="flex items-center gap-2.5 rounded-xl border border-white/40 bg-white/50 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-white/80 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10">
                            <x-icon :name="$c['icon'] ?: 'chevron-right'" class="h-4 w-4 shrink-0 text-primary" /> <span class="truncate">{{ $c['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Ratings / reviews (app-store credibility). --}}
            @if ($reviews)
                <a href="{{ $reviews }}" target="_blank" rel="noopener" @click="open = false"
                   class="flex items-center justify-center gap-2 rounded-xl bg-accent/15 px-3 py-2.5 text-sm font-semibold text-accent transition hover:bg-accent/25 dark:text-amber-300">
                    <x-icon name="star" class="h-4 w-4" /> Rate &amp; review us
                </a>
            @endif

            {{-- Blog widget (admin-toggled) — live from the Blog model. --}}
            @if ($posts->isNotEmpty())
                <section>
                    <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">From the blog</h3>
                    <div class="space-y-1.5">
                        @foreach ($posts as $post)
                            <a href="{{ route('blog.show', $post->slug) }}" wire:navigate @click="open = false"
                               class="block rounded-xl border border-white/40 bg-white/50 px-3 py-2 text-sm text-slate-700 transition hover:bg-white/80 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10">
                                <span class="line-clamp-2 font-medium">{{ $post->title }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Social handles. --}}
            @if (! empty($social))
                <section>
                    <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Follow us</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($social as $key => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($key) }}"
                               class="flex h-10 w-10 items-center justify-center rounded-full border border-white/40 bg-white/50 text-slate-600 transition hover:bg-white/80 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10">
                                <x-service-icon :slug="$key" class="h-5 w-5" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Account — deletion is fast + prominent (both app stores require it). --}}
        <div class="border-t border-white/30 px-5 py-4 dark:border-white/10">
            <a href="{{ route('account') }}" wire:navigate @click="open = false"
               class="flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50/70 px-3 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-100/70 dark:border-red-500/30 dark:bg-red-950/30 dark:text-red-300">
                <x-icon name="x" class="h-4 w-4" /> Delete my account
            </a>
        </div>
    </aside>
</div>
