<?php

namespace App\Livewire;

use App\Models\CreditLedger;
use App\Models\EsimOrder;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\CreditSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "My Journey" — two real, data-grounded timelines, no invented metrics:
 *  - Loyalty Milestones: the user's own NaaraCredits earning history
 *    (CreditLedger), grouped into the real mechanisms the app actually grants
 *    credits through (signup, first purchase, daily check-in, ads, other).
 *  - Travel/eSIM Journey: the user's own eSIM purchase history as a
 *    passport-stamp timeline (country, plan, validity) — no usage-over-time
 *    graph, since that requires a snapshot pipeline this platform doesn't
 *    have yet (getUsage() is polled nowhere).
 */
#[Layout('components.layouts.customer')]
class Journey extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'milestones';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['milestones', 'travel'], true) ? $tab : 'milestones';
    }

    /** @return array<int, array{key: string, icon: string, title: string, description: string, status: string, meta: ?string}> */
    private function milestones(User $user): array
    {
        $ledger = CreditLedger::where('user_id', $user->id)->where('type', 'earn')->get();
        $bySource = fn (string $source) => $ledger->where('source', $source);

        $signup = $bySource('signup')->first();
        $firstPurchase = $bySource('first_purchase')->first();
        $checkins = $bySource('checkin');
        $ads = $bySource('ad_reward');

        // Everything that isn't one of the named mechanisms above (social
        // follows, brand videos, admin/staff goodwill, referral rewards) —
        // grouped rather than invented per-type, since new sources can be
        // added over time without this page needing to know every one.
        $known = ['signup', 'first_purchase', 'checkin', 'ad_reward'];
        $other = $ledger->reject(fn ($row) => in_array($row->source, $known, true));

        $items = [
            [
                'key' => 'signup', 'icon' => 'star', 'title' => 'Welcome bonus',
                'description' => 'Joining NaaraSim.',
                'status' => $signup ? 'done' : 'locked',
                'meta' => $signup ? '+'.number_format((float) $signup->amount, 0).' credits · '.$signup->created_at->format('M j, Y') : null,
            ],
            [
                'key' => 'first_purchase', 'icon' => 'sim', 'title' => 'First purchase',
                'description' => 'Buy your first eSIM or number.',
                'status' => $firstPurchase ? 'done' : 'locked',
                'meta' => $firstPurchase ? '+'.number_format((float) $firstPurchase->amount, 0).' credits · '.$firstPurchase->created_at->format('M j, Y') : 'Not yet — head to the catalogue.',
            ],
            [
                'key' => 'checkin', 'icon' => 'zap', 'title' => 'Daily check-in streak',
                'description' => $checkins->isEmpty() ? 'Check in daily from Rewards to start earning.' : 'Keep checking in daily to grow your streak.',
                'status' => $checkins->isEmpty() ? 'locked' : 'ongoing',
                'meta' => $checkins->isEmpty() ? null : $this->checkinStreak($checkins).'-day streak · '.$checkins->count().' total check-ins',
            ],
        ];

        if (CreditSettings::adsActive() || $ads->isNotEmpty()) {
            $items[] = [
                'key' => 'ads', 'icon' => 'play', 'title' => 'Watch & earn',
                'description' => 'Earn credits from Rewards by watching rewarded ads.',
                'status' => $ads->isEmpty() ? 'locked' : 'ongoing',
                'meta' => $ads->isEmpty() ? null : $ads->count().' watched · +'.number_format((float) $ads->sum('amount'), 0).' credits total',
            ];
        }

        if ($other->isNotEmpty()) {
            $items[] = [
                'key' => 'other', 'icon' => 'gift', 'title' => 'Other rewards',
                'description' => 'Social follows, brand videos, and other bonuses.',
                'status' => 'ongoing',
                'meta' => $other->count().' reward'.($other->count() === 1 ? '' : 's').' · +'.number_format((float) $other->sum('amount'), 0).' credits total',
            ];
        }

        return $items;
    }

    /** Consecutive-day streak ending today or yesterday, from real check-in rows. */
    private function checkinStreak(Collection $checkins): int
    {
        $dates = $checkins->map(fn ($row) => $row->created_at->copy()->startOfDay())
            ->unique(fn ($d) => $d->toDateString())
            ->sortByDesc(fn ($d) => $d->toDateString())
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $expected = Carbon::today();
        if ($dates->first()->lt($expected->copy()->subDay())) {
            return 0; // most recent check-in is older than yesterday — streak broken
        }
        if ($dates->first()->equalTo($expected->copy()->subDay())) {
            $expected = $expected->subDay(); // no check-in yet today, but yesterday counts
        }

        $streak = 0;
        foreach ($dates as $date) {
            if ($date->equalTo($expected)) {
                $streak++;
                $expected = $expected->copy()->subDay();
            } elseif ($date->lt($expected)) {
                break;
            }
        }

        return $streak;
    }

    public function render(CreditService $credits)
    {
        $user = Auth::user();

        return view('livewire.journey', [
            'creditsEnabled' => CreditSettings::enabled(),
            'milestones' => CreditSettings::enabled() ? $this->milestones($user) : [],
            'orders' => EsimOrder::where('user_id', $user->id)->with('plan')
                ->orderByDesc('created_at')->get(),
        ]);
    }
}
