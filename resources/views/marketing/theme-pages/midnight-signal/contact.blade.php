{{-- Per-theme custom Contact page — "midnight-signal" (Theme visual
     rebuild, owner request 2026-09-07). Reuses the dark navy / cyan
     data-readout card language from this theme's other pages. Editable
     text ($content) covers headline/subtext; the channels sidebar is
     structural, matching the landing page's feature-grid precedent. The
     real `<livewire:contact-form />` component is reused as-is — a theme
     reskins the surrounding chrome, never the functional form. Fully
     responsive: the two-column layout collapses to one column below lg. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-[24rem] w-[24rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/20 blur-3xl"></span>
    </div>
    <div class="relative mx-auto max-w-2xl px-4 pb-10 pt-16 text-center sm:pt-24">
        <h1 class="mx-auto font-display text-4xl font-bold leading-tight text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="bg-[#F8F9FA] px-4 py-16 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_320px]">
        <div class="rounded-2xl border border-primary/15 bg-white p-6 sm:p-8 dark:border-primary/20 dark:bg-navy/60">
            <h2 class="mb-5 font-display text-xl font-bold text-slate-900 dark:text-white">Send a signal</h2>
            <livewire:contact-form />
        </div>

        <aside class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-400">Other channels</h2>

            <div class="rounded-2xl border border-primary/10 bg-white p-5 dark:border-primary/15 dark:bg-navy/60">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="message-circle" class="h-4 w-4" /></span>
                <h3 class="mt-3 font-bold text-slate-900 dark:text-white">Live chat</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">The signal room usually replies within the hour.</p>
                <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Open the chat</a>
            </div>

            <div class="rounded-2xl border border-primary/10 bg-white p-5 dark:border-primary/15 dark:bg-navy/60">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="mail" class="h-4 w-4" /></span>
                <h3 class="mt-3 font-bold text-slate-900 dark:text-white">Email</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">For anything that needs a paper trail.</p>
                <a href="mailto:{{ config('naara.support.email') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">{{ config('naara.support.email') }}</a>
            </div>

            @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                <div class="rounded-2xl border border-primary/10 bg-white p-5 dark:border-primary/15 dark:bg-navy/60">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="phone" class="h-4 w-4" /></span>
                    <h3 class="mt-3 font-bold text-slate-900 dark:text-white">WhatsApp</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Message us directly for quick questions.</p>
                    <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Chat on WhatsApp</a>
                </div>
            @endif
        </aside>
    </div>
</section>

@include('marketing._reused-sections')
