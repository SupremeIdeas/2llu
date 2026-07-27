@php($__cards = \App\Support\NumbersBento::cards())
{{--
    Numbers bento grid (Numbers V6 §1) — premium dark-glass cards on an
    asymmetric 6-column rhythm: Verify (4) + Rent (2) · Line (3) + Internet
    Calls (3) · Call Forwarding (3) + Contact Management (3). Collapses to a
    single column on mobile, preserving order. Each card: teal icon + two-tone
    title + badge, a neat bullet list, and its 3D illustration to the side.
--}}
<div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-6">
    @foreach ($__cards as $card)
        @php($isRoute = isset($card['link']['route']))
        @php($tag = $isRoute ? 'a' : 'button')
        @php($wide = $card['span'] >= 3)
        @php($spanClass = ['4' => 'md:col-span-4', '3' => 'md:col-span-3', '2' => 'md:col-span-2'][$card['span']] ?? 'md:col-span-3')
        @php($minH = $card['tall'] ? 'md:min-h-[280px]' : 'md:min-h-[188px]')
        @php([$w1, $w2] = array_pad(explode(' ', $card['title'], 2), 2, ''))

        <{{ $tag }}
            @if ($isRoute) href="{{ route($card['link']['route']) }}" wire:navigate
            @else type="button" x-on:click="$dispatch('open-numbers-modal', { name: '{{ $card['link']['modal'] }}' })" @endif
            wire:key="bento-{{ $card['key'] }}"
            class="nx-bento group relative flex overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-[#0C2434] to-[#081521] p-5 text-left shadow-[0_12px_40px_-18px_rgba(0,0,0,0.7)] transition-all duration-300 hover:-translate-y-0.5 hover:border-teal-400/40 hover:shadow-[0_16px_50px_-18px_rgba(20,184,166,0.35)] {{ $spanClass }} {{ $minH }}">

            {{-- Ambient teal glow --}}
            <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-teal-500/10 blur-3xl"></div>

            {{-- Badge --}}
            @if ($card['badge_label'])
                <span class="absolute right-4 top-4 z-20 inline-flex items-center gap-1 rounded-full border border-amber-300/40 bg-amber-400/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                    <x-icon name="star" class="h-3 w-3" /> {{ $card['badge_label'] }}
                </span>
            @endif

            <div class="relative z-10 flex h-full w-full gap-4 {{ $wide ? 'items-center' : 'flex-col' }}">
                {{-- Text column --}}
                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="flex items-center gap-3 {{ $card['badge_label'] ? 'pr-24' : '' }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-teal-500/15 text-teal-300 ring-1 ring-teal-400/30">
                            <x-icon :name="$card['icon']" class="h-5 w-5" />
                        </span>
                        <h3 class="min-w-0 font-display leading-none">
                            @if ($w2)
                                <span class="block text-[11px] font-bold uppercase tracking-[0.22em] text-white/55">{{ $w1 }}</span>
                                <span class="mt-1 block font-extrabold uppercase tracking-wide text-teal-300 {{ $card['tall'] ? 'text-2xl' : 'text-lg' }}">{{ $w2 }}</span>
                            @else
                                <span class="block font-extrabold uppercase tracking-wide text-teal-300 {{ $card['tall'] ? 'text-2xl' : 'text-lg' }}">{{ $w1 }}</span>
                            @endif
                        </h3>
                    </div>

                    <p class="mt-3 max-w-sm text-sm leading-relaxed text-slate-300/90">{{ $card['subtitle'] }}</p>

                    @if (! empty($card['bullets']))
                        @if ($card['key'] === 'verify')
                            {{-- Service chips for Verify (short names). --}}
                            <div class="mt-4 flex flex-wrap gap-1.5">
                                @foreach ($card['bullets'] as $b)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-teal-400/25 bg-teal-500/5 px-2.5 py-1 text-xs font-medium text-slate-200">
                                        <x-icon name="badge-check" class="h-3 w-3 text-teal-300" /> {{ $b }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <ul class="mt-4 space-y-1.5">
                                @foreach ($card['bullets'] as $b)
                                    <li class="flex items-center gap-2 text-sm text-slate-200">
                                        <x-icon name="badge-check" class="h-4 w-4 shrink-0 text-teal-300" /> {{ $b }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif

                    <span class="mt-auto pt-4 inline-flex items-center gap-1 text-xs font-semibold text-teal-300/90 opacity-0 transition group-hover:opacity-100">
                        {{ $isRoute ? 'Open' : 'Get started' }}
                        <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </div>

                {{-- Illustration --}}
                @if ($wide)
                    <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                         class="hidden shrink-0 self-stretch object-contain object-right sm:block sm:w-[40%] sm:max-w-[230px]">
                @else
                    <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                         class="mt-3 h-24 w-full shrink-0 object-contain object-center opacity-95">
                @endif
            </div>
        </{{ $tag }}>
    @endforeach
</div>
