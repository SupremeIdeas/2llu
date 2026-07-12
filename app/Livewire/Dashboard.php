<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Unified "My Connectivity" hub (blueprint Section 12): active eSIMs and every
 * kind of number side by side. The user never sees a provider name or a cost.
 */
#[Layout('components.layouts.customer')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        return view('livewire.dashboard', [
            'esims' => $user->esimOrders()->latest()->limit(10)->get(),
            'numbers' => $user->smsOrders()->latest()->limit(10)->get(),
            'wallet' => $user->wallet,
        ]);
    }
}
