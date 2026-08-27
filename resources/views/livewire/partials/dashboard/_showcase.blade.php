{{-- First-visit value showcase (Theme Batch 2 §2, extracted verbatim). Only for
     a not-yet-active account (@unless hasAny). --}}
@unless ($hasAny)
    @php($showcase = [
        ['esim-data-plans', 'eSIM Data Plans', 'Local data in 190+ countries — installed before you fly, connected when you land.', route('catalogue'), 'Browse plans', null],
        ['naara-connect', 'Naara Connect', 'Full eSIM — calls, SMS and data on one eSIM, with its own number.', route('catalogue', ['tab' => 'full']), 'See Full eSIMs', null],
        ['verification-numbers', 'Verification Numbers', 'Receive one-time codes for WhatsApp, Google, Facebook and more — in seconds.', route('numbers'), 'Get a number', null],
        ['virtual-numbers', 'Virtual Numbers', 'A permanent second line for calls and SMS, without a second phone.', route('numbers'), 'Explore numbers', null],
    ])
    @if (\App\Support\FeatureFlags::adminEnabled('naara_gift'))
        @php($giftLive = \App\Support\FeatureFlags::configured('naara_gift'))
        @php($showcase[] = ['naara-gift', 'Naara Gift', 'Send gift cards for 1,000+ brands — delivered instantly by email or WhatsApp.', route('gift-cards'), $giftLive ? 'Browse gifts' : 'Coming soon', $giftLive ? null : 'Soon'])
    @endif
    <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($showcase as [$bkey, $title, $text, $url, $cta, $badge])
            @php($bicon = \App\Support\BentoIcons::icon($bkey))
            <a href="{{ $url }}" wire:navigate class="nx-card3d group block">
                <div class="nx-card3d__body nx-card3d__body--bento">
                    <span class="nx-card3d__glass" aria-hidden="true"></span>
                    @if ($bicon)
                        <img src="{{ $bicon }}" alt="" aria-hidden="true"
                             class="nx-card3d__bg pointer-events-none absolute bottom-0 right-0 w-[38%] max-w-[128px] select-none object-contain"
                             style="opacity: {{ \App\Support\BentoIcons::opacityFraction($bkey) }}; transform: scale({{ \App\Support\BentoIcons::scale($bkey) }}); transform-origin: bottom right;">
                    @endif
                    <h3 class="relative flex items-center gap-2 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $title }}
                        @if ($badge)<span class="rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">{{ $badge }}</span>@endif
                    </h3>
                    <p class="relative mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $text }}</p>
                    <span class="relative mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary dark:text-teal-300">
                        {{ $cta }} <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </div>
            </a>
        @endforeach
    </div>
@endunless
