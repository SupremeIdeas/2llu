{{-- eSIM storefront header + action tiles (Theme Batch 2 §2, extracted verbatim).
     Shared by both structural variants of the eSIM page — only its position
     relative to the hero changes per theme. --}}
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        {{-- NaaraSim mark now lives in the header (App\Support\BrandContext).
             Heading + subheading are admin-editable (Admin → eSIM hero). --}}
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ \App\Support\EsimHeroContent::sectionTitle() }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ \App\Support\EsimHeroContent::sectionSubtitle() }}</p>
    </div>
    {{-- Bento action tiles (side by side at every width) — same style as the
         number-section bento cards. Click logic is unchanged. --}}
    <div class="grid w-full grid-cols-2 gap-3 sm:w-auto sm:shrink-0">
        <x-bento-tile bkey="browse-by-country" label="Browse by country"
                      wire:click="browseCountries" class="sm:min-w-[180px]" />
        <x-bento-tile bkey="check-compatibility" label="Check compatibility" variant="primary"
                      @click="$dispatch('open-compatibility')" class="sm:min-w-[180px]" />
    </div>
</div>
