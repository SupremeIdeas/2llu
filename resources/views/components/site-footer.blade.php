@props(['variant' => 'full'])

{{-- Assignable footer (Module 28). Admin-managed link columns + legal row via
     SiteChrome; "Supreme Ideas Agency" attribution is a brand constant and is
     always shown. `variant="slim"` renders just the attribution + legal bar
     (used at the bottom of the auth pages); `full` adds the link columns. --}}
@php
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
@endphp

<footer {{ $attributes->merge(['class' => 'border-t border-white/10 bg-navy text-slate-300']) }}>
    @if ($variant === 'full')
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="product" class="h-9 max-w-[170px]" />
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-accent">{{ $col['heading'] }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="transition hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <span>&copy; {{ date('Y') }} {{ $brand }}. A product of <span class="text-slate-300">Supreme Ideas Agency</span>. All rights reserved.</span>
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
