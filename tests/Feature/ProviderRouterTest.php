<?php

namespace Tests\Feature;

use App\Exceptions\EsimProviderException;
use App\Jobs\AlertAdminJob;
use App\Models\EsimPlan;
use App\Models\OrderLog;
use App\Models\User;
use App\Services\eSIM\EsimOrderResult;
use App\Services\eSIM\ProviderRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

class ProviderRouterTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $provider, float $cost, array $extra = []): EsimPlan
    {
        static $n = 0;
        $n++;

        return EsimPlan::create(array_merge([
            'provider' => $provider,
            'provider_plan_id' => "{$provider}-plan-{$n}",
            'name' => "{$provider} US 1GB",
            'data_mb' => 1000,
            'validity_days' => 30,
            'countries' => ['US'],
            'cost_price_usd' => $cost,
        ], $extra));
    }

    public function test_fails_over_esimgo_to_airalo_to_quibity(): void
    {
        // User bought this plan; final_retail_usd = 20 is what they paid.
        $chosen = $this->plan('esimgo', 5.0, ['computed_retail_usd' => 20.0])->fresh();
        $this->plan('airalo', 8.0);
        $this->plan('quibity', 10.0);

        $esimgo = new FakeEsimProvider(shouldThrow: true);   // primary fails
        $airalo = new FakeEsimProvider(shouldThrow: true);   // secondary fails
        $quibity = new FakeEsimProvider(shouldThrow: false, orderResponse: ['iccid' => '8944']);
        app()->instance('esim.esimgo', $esimgo);
        app()->instance('esim.airalo', $airalo);
        app()->instance('esim.quibity', $quibity);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertInstanceOf(EsimOrderResult::class, $result);
        $this->assertSame('quibity', $result->provider);
        $this->assertSame('8944', $result->payload['iccid']);
        $this->assertSame(1, $esimgo->orderCalls);
        $this->assertSame(1, $airalo->orderCalls);
        $this->assertSame(1, $quibity->orderCalls);

        // Profit tracked: charged 20 - cost 10 = 10.
        $log = OrderLog::where('provider', 'quibity')->firstOrFail();
        $this->assertSame('success', $log->result);
        $this->assertSame('10.0000', (string) $log->profit);
    }

    public function test_margin_eating_fallback_is_skipped_and_the_wallet_is_refunded(): void
    {
        Queue::fake();

        // User paid only 8.00; the sole provider's cost (8.00) leaves less than
        // the 0.50 minimum profit, so it must be SKIPPED (not attempted).
        $chosen = $this->plan('airalo', 8.0, ['computed_retail_usd' => 8.0])->fresh();
        $this->assertSame(8.0, (float) $chosen->final_retail_usd);

        $airalo = new FakeEsimProvider(shouldThrow: false);
        app()->instance('esim.airalo', $airalo);

        $user = User::factory()->create();

        try {
            app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user, 'NGN');
            $this->fail('Expected EsimProviderException');
        } catch (EsimProviderException $e) {
            $this->assertStringContainsString('refunded', strtolower($e->getMessage()));
        }

        // Provider was skipped, never ordered.
        $this->assertSame(0, $airalo->orderCalls);
        // No success logged.
        $this->assertSame(0, OrderLog::count());
        // Wallet refunded 8.00 (credit of type refund).
        $refund = $user->walletTransactions()->where('type', 'refund')->firstOrFail();
        $this->assertSame('8.0000', (string) $refund->amount);
        $this->assertSame('8.00', (string) $user->wallet->fresh()->ngn_balance);

        Queue::assertPushed(AlertAdminJob::class);
    }

    public function test_a_provider_without_an_equivalent_plan_is_passed_over(): void
    {
        // Only quibity has a matching plan; esimgo/airalo have none.
        $chosen = $this->plan('quibity', 5.0, ['computed_retail_usd' => 20.0])->fresh();
        $quibity = new FakeEsimProvider(orderResponse: ['iccid' => 'Q-1']);
        app()->instance('esim.quibity', $quibity);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertSame('quibity', $result->provider);
        $this->assertSame(1, $quibity->orderCalls);
    }

    public function test_equivalent_plan_must_cover_country_data_and_validity(): void
    {
        $router = app(ProviderRouter::class);
        $need = $this->plan('esimgo', 5.0, ['countries' => ['US'], 'data_mb' => 1000, 'validity_days' => 30]);

        // Smaller data -> not equivalent.
        $this->plan('airalo', 3.0, ['countries' => ['US'], 'data_mb' => 500, 'validity_days' => 30]);
        $this->assertNull($router->findEquivalentPlan($need, 'airalo'));

        // Missing country coverage -> not equivalent.
        $this->plan('quibity', 3.0, ['countries' => ['FR'], 'data_mb' => 2000, 'validity_days' => 30]);
        $this->assertNull($router->findEquivalentPlan($need, 'quibity'));

        // Covers all three dimensions -> equivalent.
        $ok = $this->plan('airalo', 6.0, ['countries' => ['US', 'CA'], 'data_mb' => 2000, 'validity_days' => 60]);
        $this->assertTrue($router->findEquivalentPlan($need, 'airalo')->is($ok));
    }
}
