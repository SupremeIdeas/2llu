{{-- Per-theme custom Contact page — "neon-vertex" (Theme visual rebuild,
     owner request 2026-09-07). Same gradient-blob/rounded-card language as
     this theme's landing hero, About, and How It Works pages. Editable text
     ($content) covers headline/subtext; the channels sidebar is structural,
     matching the landing page's own feature-card precedent. The real
     `<livewire:contact-form />` component is reused as-is — a theme reskins
     the surrounding chrome, never the functional form. Fully responsive:
     the two-column layout collapses to one column below lg. --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-gradient-to-br from-primary/25 via-accent/20 to-transparent blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-2xl px-4 pb-10 pt-20 text-center sm:pt-24">
        <h1 class="mx-auto font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>

    <div class="relative mx-auto grid max-w-5xl gap-6 px-4 pb-24 lg:grid-cols-[1fr_320px]">
        <div class="rounded-[2.5rem] border border-slate-200 bg-white p-6 sm:p-8 dark:border-white/10 dark:bg-[#12172a]">
            <h2 class="mb-5 font-display text-xl font-bold text-slate-900 dark:text-white">Send us a message</h2>
            <livewire:contact-form />
        </div>

        <aside class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-400">Other ways to reach us</h2>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white"><x-icon name="message-circle" class="h-4 w-4" /></span>
                <h3 class="mt-3 font-bold text-slate-900 dark:text-white">Live chat</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Usually online — get a real answer in minutes.</p>
                <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Open the chat</a>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white"><x-icon name="mail" class="h-4 w-4" /></span>
                <h3 class="mt-3 font-bold text-slate-900 dark:text-white">Email</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">For anything that needs a paper trail.</p>
                <a href="mailto:{{ config('naara.support.email') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">{{ config('naara.support.email') }}</a>
            </div>

            @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white"><x-icon name="phone" class="h-4 w-4" /></span>
                    <h3 class="mt-3 font-bold text-slate-900 dark:text-white">WhatsApp</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Message us directly for quick questions.</p>
                    <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Chat on WhatsApp</a>
                </div>
            @endif
        </aside>
    </div>
</section>

@include('marketing._reused-sections')
