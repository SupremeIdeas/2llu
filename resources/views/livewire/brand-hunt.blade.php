<div class="mx-auto max-w-3xl px-4 py-6"
     x-data="{ bg: '' }" :style="bg ? `background-color:${bg}` : ''"
     style="transition: background-color .7s ease">

    <div class="mb-6">
        <a href="{{ route('rewards') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to Rewards
        </a>
        <h1 class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">The Hunt</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Follow to earn surprise NaaraCredits. Each follow is a one-time reward — the amount is revealed after you follow.</p>
    </div>

    @if ($flash)
        <div class="mb-5 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-200">{{ $flash }}</div>
    @endif

    {{-- Platform: follow NaaraSim's own handles. --}}
    @if ($platformHandles->isNotEmpty())
        <section class="mb-10">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Follow NaaraSim</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($platformHandles as $h)
                    @include('livewire.partials.hunt-handle-card', [
                        'handle' => $h,
                        'claimed' => isset($claimedHandles[$h->id]),
                        'action' => 'followHandle',
                    ])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Brand partners — each drives the page background as it scrolls into view. --}}
    @forelse ($brands as $brand)
        <section class="mb-10 scroll-mt-6"
                 x-intersect.threshold.40="bg = '{{ $brand->background_color }}1a'"
                 wire:key="brand-{{ $brand->id }}">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#16233d]">
                @if ($brand->fallback_image)
                    <img src="{{ $brand->fallback_image }}" alt="{{ $brand->brand_name }}" class="h-40 w-full object-cover">
                @else
                    <div class="h-24 w-full" style="background: linear-gradient(135deg, {{ $brand->background_color }}, {{ $brand->background_color }}99)"></div>
                @endif
                <div class="p-5">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $brand->brand_name }}</h3>
                    @if ($brand->short_description)
                        <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $brand->short_description }}</p>
                    @endif
                    @if ($brand->handles->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($brand->handles as $h)
                                @include('livewire.partials.hunt-handle-card', [
                                    'handle' => $h,
                                    'claimed' => isset($claimedBrandHandles[$h->id]),
                                    'action' => 'followBrandHandle',
                                ])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @empty
        @if ($platformHandles->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-[#2D4060] dark:text-slate-400">
                No brands to follow yet — check back soon.
            </div>
        @endif
    @endforelse
</div>
