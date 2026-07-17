<x-layouts.marketing :title="$post->metaTitle().' — '.\App\Support\BrandSettings::name()"
                     :description="$post->metaDescription()" :og-image="$post->cover_image_url">
    <article class="mx-auto max-w-3xl px-4 py-16">
        <a href="{{ route('blog') }}" class="mb-6 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> All posts
        </a>

        <span class="text-xs font-semibold uppercase tracking-wide text-accent">{{ $post->category }}</span>
        <h1 class="mt-2 font-display text-3xl font-bold leading-tight text-slate-900 sm:text-4xl dark:text-white">{{ $post->title }}</h1>
        <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">
            {{ optional($post->published_at)->format('F j, Y') }}@if ($post->author) · {{ $post->author->name }}@endif
        </p>

        @if ($post->cover_image_url)
            <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover">
        @endif

        @if ($post->excerpt)
            <p class="mt-8 text-lg leading-relaxed text-slate-700 dark:text-slate-200">{{ $post->excerpt }}</p>
        @endif

        <x-prose :body="$post->body" class="mt-6" />
    </article>
</x-layouts.marketing>
