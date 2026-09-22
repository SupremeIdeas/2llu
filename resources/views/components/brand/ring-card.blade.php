{{-- Concentric-ring card — 2LLU's card primitive (Batch 1 Step 3 —
     brand-bible.md / docs/blueprints/01-batch1-clone-strip-foundation.md).
     A 1px hairline border (border-milk-border in light, border-espresso-border
     in dark — the real Tailwind class names the espresso/milk color tokens in
     tailwind.config.js produce for the "milk-line"/"coffee-line" border
     concept in the brand bible) plus a faint concentric-ring glow that
     appears ONLY on hover/focus (.nx-ring-card, resources/css/brand-ring.css)
     — restraint over decoration. Ties the ring visual language to the
     product mechanic (2LLU's circles are a rotation); Batch 3 reuses this
     exact motif for the round/cycle tracker.

     Not wired into any page yet. Usage for later batches:

         <x-brand.ring-card>
             ...card content...
         </x-brand.ring-card>

         <x-brand.ring-card as="button" type="button" class="text-left">
             ...clickable card...
         </x-brand.ring-card>

     :as lets a page render this on something other than a <div> (e.g.
     "button" or "a") when the card itself is the interactive element —
     the hover ring then also responds to keyboard focus via :focus-within
     with no extra wiring. --}}
@props(['as' => 'div'])
<{{ $as }} {{ $attributes->merge(['class' => 'nx-ring-card rounded-2xl border border-milk-border bg-milk-surface p-5 text-espresso transition-colors dark:border-espresso-border dark:bg-espresso-surface dark:text-milk']) }}>
    {{ $slot }}
</{{ $as }}>
