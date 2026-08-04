<div>
    {{-- Premium 4-image interchanging-reveal hero (esim_upgrade Part 2).
         BUILD-8 leaves this hero slider exactly as-is by explicit instruction. --}}
    @include('partials.esim-hero')

    {{-- Per-request USD + local-currency formatter (live FX, never cost). --}}
    @php($fmt = fn ($usd) => app(\App\Services\Pricing\CurrencyService::class)
        ->localPrice((float) $usd, \App\Support\LocaleCurrency::resolve(auth()->user())))

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            {{-- NaaraSim mark now lives in the header (App\Support\BrandContext).
                 Heading + subheading are admin-editable (Admin → eSIM hero). --}}
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ \App\Support\EsimHeroContent::sectionTitle() }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ \App\Support\EsimHeroContent::sectionSubtitle() }}</p>
        </div>
        {{-- Bento action tiles (side by side at every width) — same style as the
             number-section bento cards. Click logic is unchanged. --}}
        <div class="grid w-full grid-cols-2 gap-3 sm:w-auto sm:shrink-0">
            <x-bento-tile bkey="browse-by-country" label="Browse by country"
                          wire:click="browseCountries" class="sm:min-w-[180px]" />
            <x-bento-tile bkey="check-compatibility" label="Check compatibility" variant="primary"
                          @click="$dispatch('open-compatibility')" class="sm:min-w-[180px]" />
        </div>
    </div>

    {{-- One compatibility modal + the ONE shared country picker (S31). --}}
    <livewire:esim-compatibility />
    <livewire:country-picker />

    {{-- eSIM Data / Naara Connect (Full) — the two hard-separated lines (§3.0). --}}
    <div class="mb-5 inline-flex rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <button wire:click="setTab('data')" class="rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $tab === 'data' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
            eSIM Data
        </button>
        <button wire:click="setTab('full')" class="rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $tab === 'full' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
            Naara Connect <span class="text-xs font-normal">(Calls + Data)</span>
        </button>
    </div>

    {{-- ============================ PLAN DETAIL (§3.4) ============================ --}}
    @if ($screen === 'detail')
        <button type="button" wire:click="back"
                class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back
        </button>

        <div class="overflow-hidden rounded-2xl border border-slate-200 nx-glass-tile shadow-sm dark:border-[#2D4060]">
            @if ($banner)
                <div class="h-40 w-full overflow-hidden sm:h-56">
                    <img src="{{ $banner }}" alt="{{ $plan->name }}" class="h-full w-full object-cover">
                </div>
            @else
                <div class="flex h-32 w-full items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5 sm:h-40 dark:from-primary/20 dark:to-transparent">
                    <x-icon name="globe" class="h-14 w-14 text-primary/60" gradient />
                </div>
            @endif

            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
                            <x-icon name="globe" class="h-3.5 w-3.5" /> {{ $plan->type ?? 'Data' }}
                        </span>
                        <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $plan->name }}</h2>
                    </div>
                    @php($price = $fmt((float) $plan->final_retail_usd))
                    <div class="text-right">
                        <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $price['usd'] }}</div>
                        @if ($price['local'])
                            <div class="text-xs text-slate-500 dark:text-slate-400">≈ {{ $price['local'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- AI tooltip (§5) as the primary "what am I buying" copy, when present. --}}
                @if ($plan->display_tooltip)
                    <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-[#152238] dark:text-slate-300">
                        {{ $plan->display_tooltip }}
                    </p>
                @endif

                {{-- Honest facts only — no fabricated feature badges (§3.4). --}}
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($facts as $fact)
                        <div class="flex items-center gap-2 rounded-lg border border-slate-100 bg-white/50 p-3 text-sm text-slate-700 dark:border-[#243352] dark:bg-[#152238] dark:text-slate-300">
                            <x-icon name="{{ $fact['icon'] }}" class="h-4 w-4 shrink-0 text-primary" />
                            <span>{{ $fact['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('checkout', $plan) }}"
                   class="mt-6 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/50 sm:w-auto">
                    Buy this plan <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            </div>
        </div>

    {{-- ============================ ALL OTHER SCREENS ============================ --}}
    @else
        {{-- Popular / Countries / Regions / Global segmented control (§3.1),
             matching the reference's pill tabs. --}}
        <div class="mb-4 inline-flex w-full max-w-xl rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-[#2D4060] dark:bg-[#1A2840]">
            @foreach (['popular' => 'Popular', 'local' => 'Countries', 'regional' => 'Regions', 'global' => 'Global'] as $key => $label)
                <button wire:click="setView('{{ $key }}')"
                        class="flex-1 rounded-full px-3 py-1.5 text-sm font-semibold transition {{ $view === $key ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Live search across country, region and plan names (§3.1). --}}
        <div class="relative mb-6">
            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                <x-icon name="search" class="h-4 w-4" />
            </span>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Where are you travelling to?"
                   class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-11 pr-10 text-sm text-slate-900 placeholder-slate-400 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/30 dark:border-[#2D4060] dark:bg-[#1A2840] dark:text-slate-100">
            <span wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2 text-primary">
                <x-ui.spinner class="h-4 w-4" />
            </span>
        </div>

        @if ($tab === 'full' && $fullCount === 0 && $screen === 'grid')
            <div class="mb-6 flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 py-14 text-center dark:border-[#2D4060]">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="signal" class="h-6 w-6" gradient /></span>
                <p class="font-semibold text-slate-800 dark:text-slate-100">Naara Connect is coming soon</p>
                <p class="max-w-sm text-sm text-slate-500 dark:text-slate-400">Calls + data on one eSIM — with a number, minutes and SMS. We’re finishing the last checks with our voice provider.</p>
            </div>
        @endif

        {{-- -------------------------------- SEARCH -------------------------------- --}}
        @if ($screen === 'search')
            @if (count($countryHits) || count($regionHits))
                <div class="mb-6 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @foreach ($countryHits as $t)
                        @include('livewire.catalogue._country-row', ['t' => $t, 'fmt' => $fmt])
                    @endforeach
                    @foreach ($regionHits as $t)
                        @include('livewire.catalogue._region-row', ['t' => $t, 'fmt' => $fmt])
                    @endforeach
                </div>
            @endif
            @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

        {{-- ---------------------- SELECTED COUNTRY / REGION ----------------------- --}}
        @elseif ($screen === 'country' || $screen === 'region')
            <button type="button" wire:click="back"
                    class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-primary dark:text-slate-400">
                <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back
            </button>

            {{-- Compact header card (reference "Valid in this…" style). Uses the
                 admin banner as a slim strip when set, else a clean flag/icon card. --}}
            <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                @if ($banner)
                    <div class="relative h-28 w-full overflow-hidden sm:h-36">
                        <img src="{{ $banner }}" alt="{{ $selName }}" class="h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                        <div class="absolute bottom-3 left-4 flex items-center gap-2 text-white">
                            @if ($screen === 'country')<x-country-flag :country="$selCode" class="h-6 w-9 rounded shadow" />@endif
                            <h2 class="text-lg font-bold drop-shadow">{{ $selName }}</h2>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3 p-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 dark:bg-[#152238]">
                            @if ($screen === 'country')
                                <x-country-flag :country="$selCode" class="h-7 w-10 rounded shadow-sm" />
                            @else
                                <x-icon name="globe" class="h-7 w-7 text-primary" gradient />
                            @endif
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $selName }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $screen === 'country' ? 'Plans valid in this country' : 'Multi-country plans in this region' }}</p>
                        </div>
                    </div>
                @endif
            </div>

            @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

        {{-- --------------------------------- GRID -------------------------------- --}}
        @else
            @if ($view === 'popular')
                @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

            @elseif ($view === 'local')
                @if (count($grid['local']))
                    <div class="mb-2 flex items-baseline justify-between">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Choose a country</p>
                        <p class="text-xs text-slate-400">{{ count($grid['local']) }} {{ \Illuminate\Support\Str::plural('country', count($grid['local'])) }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        @foreach ($grid['local'] as $t)
                            @include('livewire.catalogue._country-row', ['t' => $t, 'fmt' => $fmt])
                        @endforeach
                    </div>
                @else
                    <x-esim.empty-nav />
                @endif

            @elseif ($view === 'regional')
                @if (count($grid['regions']))
                    <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Pick a region</p>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        @foreach ($grid['regions'] as $t)
                            @include('livewire.catalogue._region-row', ['t' => $t, 'fmt' => $fmt])
                        @endforeach
                    </div>
                @else
                    <x-esim.empty-nav />
                @endif

            @else {{-- global --}}
                @if ($globalCount > 0)
                    {{-- One-plan-everywhere banner (reference Global layout). --}}
                    <div class="relative mb-5 overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/10 to-primary/5 p-5 dark:border-primary/30 dark:from-primary/20 dark:to-transparent">
                        @if ($globalBanner)
                            <img src="{{ $globalBanner }}" alt="Global" class="pointer-events-none absolute -right-6 -top-2 h-32 w-40 object-contain opacity-70">
                        @else
                            <x-icon name="globe" class="pointer-events-none absolute -right-2 top-2 h-28 w-28 text-primary/25" gradient />
                        @endif
                        <p class="text-[11px] font-bold uppercase tracking-wider text-primary dark:text-teal-300">One plan, everywhere</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Global eSIM</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Stay connected across 190+ countries on a single eSIM.</p>
                    </div>
                    @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])
                @else
                    <x-esim.empty-nav />
                @endif
            @endif
        @endif
    @endif
</div>
