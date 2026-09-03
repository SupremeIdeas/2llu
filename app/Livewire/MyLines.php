<?php

namespace App\Livewire;

use App\Support\ConnectivityHub;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * My Lines (numbers overhaul follow-up) — the dedicated management home for
 * everything a user owns: active eSIMs (with QR/LPA setup + data meter), active
 * numbers grouped by Model (Naara Line / Rent / Verify) with per-line Call /
 * Message actions, and an Archive holding expired eSIMs and cancelled numbers as
 * a record. Route is numbers.lines so it inherits the Numbers section chrome
 * (§2 nav + wallet header). Reads the shared ConnectivityHub — no logic of its
 * own — so it can never disagree with the dashboard's summary.
 */
#[Layout('components.layouts.customer')]
class MyLines extends Component
{
    public function render()
    {
        $hub = ConnectivityHub::for(auth()->user());

        return view('livewire.my-lines', [
            'esimsActive' => $hub['esimsActive'],
            'esimsArchived' => $hub['esimsArchived'],
            'numberGroups' => $hub['numberGroups'],
            'numbersArchived' => $hub['numbersArchived'],
            'hasAny' => $hub['hasAny'],
        ]);
    }
}
