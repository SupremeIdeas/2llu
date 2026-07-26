@php($__cards = \App\Support\NumbersBento::cards())
{{--
    Numbers bento grid (Numbers V6 §1). Locked 2/1/2/1 rhythm: featured cards
    (Naara Line, Contact Management) span full width; the rest are 2-up on
    sm+ and single-column on mobile, preserving the exact top-to-bottom order.
    Featured cards use a horizontal internal layout so they still read "wider"
    even in a single column.
--}}
<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
    @foreach ($__cards as $card)
        @php($isRoute = isset($card['link']['route']))
        @php($tag = $isRoute ? 'a' : 'button')
        <{{ $tag }}
            @if ($isRoute) href="{{ route($card['link']['route']) }}" wire:navigate
            @else type="button" x-on:click="$dispatch('open-numbers-modal', { name: '{{ $card['link']['modal'] }}' })" @endif
            wire:key="bento-{{ $card['key'] }}"
            class="nx-bento group relative flex overflow-hidden rounded-3xl border border-slate-200 bg-white text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg dark:border-[#2D4060] dark:bg-[#1A2840]
                   {{ $card['featured'] ? 'sm:col-span-2 flex-row' : 'flex-col' }}">

            {{-- Media --}}
            <div class="relative shrink-0 overflow-hidden {{ $card['featured'] ? 'w-2/5 sm:w-1/3' : 'aspect-[16/10] w-full' }}">
                <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                @if ($card['badge_label'])
                    <span class="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-primary shadow dark:bg-[#0D1B2A] dark:text-teal-300">
                        {{ $card['badge_label'] }}
                    </span>
                @endif
            </div>

            {{-- Content --}}
            <div class="flex flex-1 flex-col p-5">
                <div class="mb-1.5 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                        <x-icon :name="$card['icon']" class="h-4 w-4" gradient />
                    </span>
                    <h3 class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                </div>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $card['subtitle'] }}</p>

                @if (! empty($card['bullets']))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($card['bullets'] as $bullet)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $bullet }}</span>
                        @endforeach
                    </div>
                @endif

                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary dark:text-teal-300">
                    {{ $isRoute ? 'Open' : 'Get started' }}
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                </span>
            </div>
        </{{ $tag }}>
    @endforeach
</div>
