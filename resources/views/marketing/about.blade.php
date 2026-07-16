<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — About Us'">
    @php($hero = $sections['hero'] ?? null)
    @if ($hero)
        <section class="mx-auto max-w-4xl px-4 pb-14 pt-16 text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $hero['eyebrow'] }}</p>
            <h1 data-reveal class="mt-4 text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $hero['headline'] }}</h1>
            <p data-reveal class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $hero['subtext'] }}</p>
            @if (! empty($hero['image']))
                <img data-reveal src="{{ $hero['image'] }}" alt="" class="mx-auto mt-10 w-full max-w-3xl rounded-3xl shadow-2xl">
            @endif
        </section>
    @endif

    @php($story = $sections['story'] ?? null)
    @if ($story)
        <section class="mx-auto max-w-3xl px-4 py-14">
            <h2 data-reveal class="text-2xl font-bold text-slate-900 dark:text-white">{{ $story['headline'] }}</h2>
            <div class="mt-6 space-y-5 leading-relaxed text-slate-600 dark:text-slate-300">
                @foreach (['p1', 'p2', 'p3', 'p4'] as $p)
                    <p data-reveal>{{ $story[$p] }}</p>
                @endforeach
            </div>
        </section>
    @endif

    @php($mission = $sections['mission'] ?? null)
    @if ($mission)
        <section class="bg-navy px-4 py-20 text-slate-100">
            <div class="mx-auto max-w-5xl">
                <h2 data-reveal class="text-center text-3xl font-bold">{{ $mission['headline'] }}</h2>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    @foreach ([['mission_title', 'mission'], ['vision_title', 'vision'], ['belief_title', 'belief']] as [$t, $b])
                        <div data-reveal class="rounded-2xl border border-white/15 bg-white/5 p-6 backdrop-blur">
                            <p class="text-xs font-semibold uppercase tracking-widest text-accent">{{ $mission[$t] }}</p>
                            <p class="mt-3 text-sm leading-relaxed opacity-95">{{ $mission[$b] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @php($values = $sections['values'] ?? null)
    @if ($values)
        <section class="mx-auto max-w-5xl px-4 py-20">
            <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 dark:text-white">{{ $values['headline'] }}</h2>
            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                @foreach (range(1, 4) as $n)
                    <div data-reveal style="--reveal-delay: {{ ($n - 1) * 0.07 }}s" class="nx-card">
                        <h3 class="font-bold text-primary dark:text-teal-300">{{ $values["v{$n}_title"] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $values["v{$n}_text"] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php($team = $sections['team'] ?? null)
    @if ($team)
        <section class="mx-auto max-w-3xl px-4 pb-24">
            <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 dark:text-white">{{ $team['headline'] }}</h2>
            <div data-reveal class="nx-card mt-10">
                @if (! empty($team['image']))
                    <img src="{{ $team['image'] }}" alt="{{ $team['founder_name'] }}" class="h-20 w-20 rounded-2xl object-cover">
                @endif
                <h3 class="mt-3 text-xl font-bold text-slate-900 dark:text-white">{{ $team['founder_name'] }}</h3>
                <p class="text-sm font-medium text-accent">{{ $team['founder_title'] }}</p>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $team['founder_bio'] }}</p>
                <p class="mt-4 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-400 dark:border-[#2D4060]">{{ $team['company'] }}</p>
            </div>
        </section>
    @endif
</x-layouts.marketing>
