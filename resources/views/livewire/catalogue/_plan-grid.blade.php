{{-- A paginated grid of plan cards with a skeleton loader + empty state.
     Expects: $plans (paginator), $fmt. --}}
<div wire:loading.flex wire:target="search,gotoPage,nextPage,previousPage,setView,openCountry,openRegion,openGlobal,setTab" class="hidden">
    <x-ui.skeleton-cards :count="6" class="w-full" columns="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" />
</div>

<div wire:loading.remove wire:target="search,gotoPage,nextPage,previousPage,setView,openCountry,openRegion,openGlobal,setTab"
     class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($plans as $plan)
        @include('livewire.catalogue._plan-card', ['plan' => $plan, 'fmt' => $fmt])
    @empty
        <div class="col-span-full rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-[#2D4060] dark:text-slate-400">
            <x-icon name="package" class="mx-auto mb-2 h-8 w-8" />
            No plans here yet. Try another tab or search.
        </div>
    @endforelse
</div>

@if ($plans->hasPages())
    <div class="mt-6">{{ $plans->links() }}</div>
@endif
