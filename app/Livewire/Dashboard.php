<?php

namespace App\Livewire;

use App\Support\ProviderModels;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Unified "My Connectivity" hub (blueprint Section 12) — organised on the Model
 * layer: active eSIMs and numbers grouped by their public Model (Naara Data /
 * Verify / Rent / Line), with finished/expired items tucked into an Archive so
 * the active view stays clean. The user never sees a provider name or a cost.
 */
#[Layout('components.layouts.customer')]
class Dashboard extends Component
{
    /** A finished/dead number belongs in the Archive, not the active list.
     *  (sms_orders.status enum: pending | waiting | completed | cancelled | timeout.) */
    private const NUMBER_ARCHIVE_STATES = ['cancelled', 'timeout'];

    /** Group display order: permanent first, then rentals, then OTP. */
    private const GROUP_ORDER = ['naara_line', 'naara_rent', 'naara_verify'];

    public function render()
    {
        $user = auth()->user();
        $esims = $user->esimOrders()->latest()->limit(40)->get();
        $numbers = $user->smsOrders()->latest()->limit(60)->get();

        $esimArchived = fn ($e) => $e->status === 'expired'
            || ($e->expires_at !== null && $e->expires_at->isPast());
        $numberArchived = fn ($n) => in_array($n->status, self::NUMBER_ARCHIVE_STATES, true);

        // Active numbers grouped by their public Model, ordered permanent→rental→otp.
        $numberGroups = $numbers->reject($numberArchived)
            ->groupBy(fn ($n) => $this->modelKeyFor($n))
            ->map(fn ($items, $key) => [
                'model' => ProviderModels::find($key) ?? ProviderModels::find('naara_verify'),
                'items' => $items->values(),
            ])
            ->sortBy(fn ($g, $key) => array_search($key, self::GROUP_ORDER) === false
                ? 99 : array_search($key, self::GROUP_ORDER))
            ->values();

        return view('livewire.dashboard', [
            'esimsActive' => $esims->reject($esimArchived)->values(),
            'esimsArchived' => $esims->filter($esimArchived)->values(),
            'numberGroups' => $numberGroups,
            'numbersArchived' => $numbers->filter($numberArchived)->values(),
            'wallet' => $user->wallet,
            'hasAny' => $esims->isNotEmpty() || $numbers->isNotEmpty(),
            // Greeting + fact-of-the-day (owner request): welcome by name and
            // teach the worth of what a NaaraSim number/eSIM can do globally.
            'greeting' => \App\Support\NaaraFacts::greeting($user),
            'greetingAsk' => \App\Support\NaaraFacts::askOfTheDay($user),
            'factOfTheDay' => \App\Support\NaaraFacts::dailyFor($user),
        ]);
    }

    /** Resolve a number's public Model key: prefer the recorded type, else lane. */
    private function modelKeyFor($number): string
    {
        $model = ProviderModels::forNumberType($number->type)
            ?? ProviderModels::forProvider((string) $number->provider);

        return $model['key'] ?? 'naara_verify';
    }
}
