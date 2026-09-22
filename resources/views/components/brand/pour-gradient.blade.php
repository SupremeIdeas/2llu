{{-- "The Pour" — 2LLU's signature landing-hero treatment (Batch 1 Step 3 —
     brand-bible.md / docs/blueprints/01-batch1-clone-strip-foundation.md): a
     soft mesh gradient, butter → caramel → base, evoking milk poured into
     coffee. Drop-in wrapper so a future page can use it without re-deriving
     the `bg-pour-light dark:bg-pour-dark` classes by hand.

     Purely a background layer — renders its own inset gradient div behind
     the slot content, so it composes with whatever the caller already put
     on the outer element (padding, min-height, existing background-color
     for the pre-paint flash guard, etc.) without fighting it. Not wired
     into any page yet; usage for later batches:

         <x-brand.pour-gradient class="min-h-[560px] rounded-b-[30px]">
             ...hero content...
         </x-brand.pour-gradient>

     :as lets a page render this on something other than a <div> (e.g.
     "section") when semantics call for it. --}}
@props(['as' => 'div'])
<{{ $as }} {{ $attributes->merge(['class' => 'relative isolate overflow-hidden bg-milk dark:bg-espresso']) }}>
    <div class="pointer-events-none absolute inset-0 bg-pour-light dark:bg-pour-dark" aria-hidden="true"></div>
    <div class="relative">
        {{ $slot }}
    </div>
</{{ $as }}>
