@php($__cards = \App\Support\NumbersBento::cards())
{{--
    Numbers bento grid (Numbers V6 §1) — compact, premium, theme-aware cards on
    an asymmetric 6-column rhythm: Verify (4) + Rent (2) · Line (3) + Internet
    Calls (3) · Call Forwarding (3) + Contact Management (3). Light cards on the
    light theme, dark-glass on dark. Collapses to one column on mobile, order
    preserved. Small "NAARA" kicker + compact title, short copy, tidy bullets,
    3D illustration (transparent PNG) to the side.
--}}
<div class="mb-8 grid grid-cols-1 gap-3 md:grid-cols-6">
    @foreach ($__cards as $card)
        @php($isRoute = isset($card['link']['route']))
        @php($tag = $isRoute ? 'a' : 'button')
        @php($wide = $card['span'] >= 3)
        @php($spanClass = ['4' => 'md:col-span-4', '3' => 'md:col-span-3', '2' => 'md:col-span-2'][$card['span']] ?? 'md:col-span-3')
        @php($minH = $card['tall'] ? 'md:min-h-[208px]' : 'md:min-h-[150px]')
        @php([$w1, $w2] = array_pad(explode(' ', $card['title'], 2), 2, ''))

        <{{ $tag }}
            @if ($isRoute) href="{{ route($card['link']['route']) }}" wire:navigate
            @else type="button" x-on:click="$dispatch('open-numbers-modal', { name: '{{ $card['link']['modal'] }}' })" @endif
            wire:key="bento-{{ $card['key'] }}"
            class="nx-bento group relative flex overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md dark:border-white/10 dark:bg-gradient-to-br dark:from-[#0C2434] dark:to-[#081521] dark:shadow-[0_12px_40px_-18px_rgba(0,0,0,0.7)] dark:hover:border-teal-400/40 {{ $spanClass }} {{ $minH }}">

            {{-- Ambient brand glow --}}
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl dark:bg-teal-500/10"></div>

            {{-- Badge --}}
            @if ($card['badge_label'])
                <span class="absolute right-3 top-3 z-20 inline-flex items-center gap-1 rounded-full border border-amber-300/50 bg-amber-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-600 dark:border-amber-300/40 dark:bg-amber-400/10 dark:text-amber-300">
                    <x-icon name="star" class="h-2.5 w-2.5" /> {{ $card['badge_label'] }}
                </span>
            @endif

            <div class="relative z-10 flex h-full w-full gap-3 {{ $wide ? 'items-center' : 'flex-col' }}">
                {{-- Text column --}}
                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="flex items-center gap-2.5 {{ $card['badge_label'] ? 'pr-20' : '' }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15 dark:bg-teal-500/15 dark:text-teal-300 dark:ring-teal-400/30">
                            <x-icon :name="$card['icon']" class="h-4 w-4" />
                        </span>
                        <h3 class="min-w-0 font-display leading-none">
                            @if ($w2)
                                <span class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-white/55">{{ $w1 }}</span>
                                <span class="mt-0.5 block font-extrabold uppercase tracking-wide text-primary dark:text-teal-300 {{ $card['tall'] ? 'text-xl' : 'text-base' }}">{{ $w2 }}</span>
                            @else
                                <span class="block font-extrabold uppercase tracking-wide text-primary dark:text-teal-300 {{ $card['tall'] ? 'text-xl' : 'text-base' }}">{{ $w1 }}</span>
                            @endif
                        </h3>
                    </div>

                    <p class="mt-2 line-clamp-2 max-w-xs text-xs leading-relaxed text-slate-500 dark:text-slate-300/90">{{ $card['subtitle'] }}</p>

                    @if (! empty($card['bullets']))
                        @if ($card['key'] === 'verify')
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach ($card['bullets'] as $b)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:border-teal-400/25 dark:bg-teal-500/5 dark:text-slate-200">
                                        <x-icon name="badge-check" class="h-2.5 w-2.5 text-primary dark:text-teal-300" /> {{ $b }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <ul class="mt-2.5 space-y-1">
                                @foreach ($card['bullets'] as $b)
                                    <li class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-200">
                                        <x-icon name="badge-check" class="h-3.5 w-3.5 shrink-0 text-primary dark:text-teal-300" /> {{ $b }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>

                {{-- Illustration (transparent PNG — works on light + dark) --}}
                @if ($wide)
                    <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                         class="hidden shrink-0 self-stretch object-contain object-right sm:block sm:w-[36%] sm:max-w-[170px]">
                @else
                    <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                         class="mt-2 h-16 w-full shrink-0 object-contain object-center">
                @endif
            </div>
        </{{ $tag }}>
    @endforeach
</div>
