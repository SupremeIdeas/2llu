<?php

namespace App\Livewire\Admin;

use App\Models\ApiOrder;
use App\Models\EsimOrder;
use App\Models\OrderLog;
use App\Models\SmsOrder;
use App\Models\User;
use App\Support\ProviderModels;
use App\Support\ProviderStatus;
use App\Support\StaffScopes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin overview (blueprint Sections 13.3, 17 & 27). super_admin/admin see the
 * provider health, product status, and a 30-day profit snapshot (cost figures
 * are admin-only). Staff see a scoped welcome — their granted scopes only, no
 * cost/profit — so the panel entry never leaks business figures to staff.
 */
#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return view('livewire.admin.dashboard', [
                'privileged' => false,
                'myScopes' => array_values(array_intersect(
                    StaffScopes::all(),
                    $user->getPermissionNames()->all()
                )),
                'scopeLabels' => StaffScopes::labels(),
            ]);
        }

        // Revenue = what users paid, across BOTH product lines: eSIM orders
        // (price_charged) and number/verification orders (charged_to_user,
        // excluding timed-out orders — those were auto-refunded).
        $since = now()->subDays(30);
        $esimRevenue = (float) EsimOrder::where('created_at', '>=', $since)->sum('price_charged');
        $smsRevenue = (float) SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])->sum('charged_to_user');
        $revenue = $esimRevenue + $smsRevenue;

        $cost = (float) OrderLog::where('created_at', '>=', $since)->sum('provider_cost')
            + (float) SmsOrder::where('created_at', '>=', $since)
                ->whereNotIn('status', ['timeout', 'cancelled'])->sum('provider_cost');

        // Trend vs the previous 30-day window (for the animated revenue card).
        $prevRevenue = (float) EsimOrder::whereBetween('created_at', [now()->subDays(60), $since])->sum('price_charged')
            + (float) SmsOrder::whereBetween('created_at', [now()->subDays(60), $since])
                ->whereNotIn('status', ['timeout', 'cancelled'])->sum('charged_to_user');
        $revenueDelta = $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100, 1) : null;

        // Last-7-days revenue bars (real daily sums, normalised in the view).
        $revenueBars = collect(range(6, 0))->map(function ($back) {
            $day = now()->subDays($back);

            return [
                'label' => $day->format('D'),
                'value' => (float) EsimOrder::whereDate('created_at', $day->toDateString())->sum('price_charged')
                    + (float) SmsOrder::whereDate('created_at', $day->toDateString())
                        ->whereNotIn('status', ['timeout', 'cancelled'])->sum('charged_to_user'),
            ];
        })->values()->all();

        // Revenue split by product lane (for the donut): eSIM vs permanent
        // numbers (twilio/telnyx) vs verification (getatext/5sim/sms-activate).
        $numberRevenue = (float) SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])
            ->whereIn('provider', ['twilio', 'telnyx'])->sum('charged_to_user');
        $split = [
            ['label' => 'eSIM data', 'value' => round($esimRevenue, 2), 'color' => '#0A6E6E'],
            ['label' => 'Virtual numbers', 'value' => round($numberRevenue, 2), 'color' => '#D4A017'],
            ['label' => 'Verification', 'value' => round($smsRevenue - $numberRevenue, 2), 'color' => '#4C9F9F'],
        ];
        $splitTotal = array_sum(array_column($split, 'value'));

        // conic-gradient stops for the donut (computed here so the view stays dumb).
        $stops = [];
        $acc = 0.0;
        foreach ($split as $seg) {
            $pct = $splitTotal > 0 ? $seg['value'] / $splitTotal * 100 : 0;
            $stops[] = "{$seg['color']} {$acc}% ".($acc + $pct).'%';
            $acc += $pct;
        }
        $splitGradient = 'conic-gradient('.implode(', ', $stops).')';

        // ── Oversight metrics (owner request) ────────────────────────────────
        $weekStart = now()->subDays(7);
        $monthStart = now()->startOfMonth();

        // Users: total registered + new this week.
        $totalUsers = User::count();
        $newUsersWeek = User::where('created_at', '>=', $weekStart)->count();

        // Weekly / monthly profit (revenue − provider cost).
        $profitWindow = function ($from) {
            $rev = (float) EsimOrder::where('created_at', '>=', $from)->sum('price_charged')
                + (float) SmsOrder::where('created_at', '>=', $from)
                    ->whereNotIn('status', ['timeout', 'cancelled'])->sum('charged_to_user');
            $cst = (float) OrderLog::where('created_at', '>=', $from)->sum('provider_cost')
                + (float) SmsOrder::where('created_at', '>=', $from)
                    ->whereNotIn('status', ['timeout', 'cancelled'])->sum('provider_cost');

            return round($rev - $cst, 2);
        };
        $profitWeek = $profitWindow($weekStart);
        $profitMonth = $profitWindow($monthStart);

        // Most-bought numbers by country (top 6, last 30 days).
        $topCountries = SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])
            ->whereNotNull('country')
            ->selectRaw('country, count(*) as n')
            ->groupBy('country')->orderByDesc('n')->limit(6)->get()
            ->map(fn ($r) => ['country' => $r->country, 'count' => (int) $r->n])->all();

        // Most-used NaaraSim models (by orders in the last 30 days).
        $modelCounts = [];
        foreach (EsimOrder::where('created_at', '>=', $since)->count() ? ['naara_data' => EsimOrder::where('created_at', '>=', $since)->count()] : [] as $k => $v) {
            $modelCounts[$k] = $v;
        }
        SmsOrder::where('created_at', '>=', $since)->whereNotIn('status', ['timeout', 'cancelled'])
            ->get(['type', 'provider'])
            ->each(function ($o) use (&$modelCounts) {
                $model = ProviderModels::forNumberType($o->type)
                    ?? ProviderModels::forProvider((string) $o->provider)
                    ?? ProviderModels::find('naara_verify');
                $key = $model['key'] ?? 'naara_verify';
                $modelCounts[$key] = ($modelCounts[$key] ?? 0) + 1;
            });
        arsort($modelCounts);
        $topModels = collect($modelCounts)->take(4)
            ->map(fn ($n, $key) => ['label' => ProviderModels::find($key)['name'] ?? $key, 'count' => $n])
            ->values()->all();

        // Developer API activity this week.
        $apiOrdersWeek = ApiOrder::where('created_at', '>=', $weekStart)->count();
        $apiRevenueWeek = round((float) ApiOrder::where('created_at', '>=', $weekStart)->sum('price_usd'), 2);

        return view('livewire.admin.dashboard', [
            'privileged' => true,
            'totalUsers' => $totalUsers,
            'newUsersWeek' => $newUsersWeek,
            'profitWeek' => $profitWeek,
            'profitMonth' => $profitMonth,
            'topCountries' => $topCountries,
            'topModels' => $topModels,
            'apiOrdersWeek' => $apiOrdersWeek,
            'apiRevenueWeek' => $apiRevenueWeek,
            'health' => Cache::get('providers:health', []),
            'statuses' => ProviderStatus::all(),
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => round($revenue - $cost, 2),
            'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0.0,
            'revenueDelta' => $revenueDelta,
            'revenueBars' => $revenueBars,
            'barPeak' => max(array_column($revenueBars, 'value')) ?: 1,
            'split' => $split,
            'splitTotal' => $splitTotal,
            'splitGradient' => $splitGradient,
        ]);
    }
}
