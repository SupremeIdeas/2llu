<?php

namespace App\Livewire;

use App\Support\ConnectivityHub;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Home dashboard. The full "My Connectivity" management hub now lives on its own
 * dedicated My Lines page (route numbers.lines); the dashboard keeps only a slim
 * at-a-glance summary of it plus the greeting/showcase/wallet. Both read the same
 * ConnectivityHub source so the summary and the full page never disagree.
 */
#[Layout('components.layouts.customer')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $hub = ConnectivityHub::for($user);

        return view('livewire.dashboard', [
            'esimActiveCount' => $hub['esimActiveCount'],
            'numberActiveCount' => $hub['numberActiveCount'],
            'archivedCount' => $hub['archivedCount'],
            'hasAny' => $hub['hasAny'],
            'wallet' => $user->wallet,
            // Greeting + fact-of-the-day (owner request): welcome by name and
            // teach the worth of what a NaaraSim number/eSIM can do globally.
            'greeting' => \App\Support\NaaraFacts::greeting($user),
            'greetingAsk' => \App\Support\NaaraFacts::askOfTheDay($user),
            'factOfTheDay' => \App\Support\NaaraFacts::dailyFor($user),
            // Friendly coupon nudge for a not-yet-purchased account (owner
            // request) — null when off / already bought / no live code.
            'couponNudge' => \App\Support\MarketingCoupons::nudgeFor($user),
        ]);
    }
}
