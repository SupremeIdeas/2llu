@props(['step' => 1, 'title' => 'Install'])
@php
    $steps = ['Requirements', 'Database', 'Application', 'Providers'];
@endphp
<div class="mx-auto max-w-2xl px-4 py-12">
    <div class="mb-8 text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">from Supreme Ideas</p>
        <h1 class="mt-1 text-3xl font-bold text-primary-dark dark:text-primary">Install NaaraSim</h1>
    </div>

    <ol class="mb-8 flex items-center justify-center gap-2 text-xs">
        @foreach ($steps as $i => $label)
            <li @class([
                'flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold',
                'bg-primary text-white' => $i + 1 === $step,
                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $i + 1 < $step,
                'bg-slate-100 text-slate-500 dark:bg-[#243352] dark:text-slate-400' => $i + 1 > $step,
            ])>
                @if ($i + 1 < $step) <x-icon name="check" class="h-3.5 w-3.5" /> @else {{ $i + 1 }} @endif
                <span class="hidden sm:inline">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        {{ $slot }}
    </div>
</div>
