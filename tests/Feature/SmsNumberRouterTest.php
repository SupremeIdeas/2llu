<?php

namespace Tests\Feature;

use App\Exceptions\LowBalanceException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use App\Jobs\AlertAdminJob;
use App\Models\SmsOrder;
use App\Models\User;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\OtpStatus;
use App\Services\SMS\SmsNumberRouter;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

class SmsNumberRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
    }

    private function request(string $country, string $type = NumberRequest::TYPE_OTP, ?User $user = null, ?float $charged = 5.0): NumberRequest
    {
        return new NumberRequest(
            country: $country,
            type: $type,
            service: 'whatsapp',
            user: $user ?? User::factory()->create(),
            currency: 'USD',
            charged: $charged,
        );
    }

    public function test_lane_map_matches_the_blueprint(): void
    {
        $router = app(SmsNumberRouter::class);

        $this->assertSame(['getatext', 'fivesim', 'smsactivate'], $router->laneFor('US', 'otp'));
        $this->assertSame(['fivesim', 'smsactivate'], $router->laneFor('nigeria', 'otp'));
        $this->assertSame(['getatext', 'fivesim'], $router->laneFor('usa', 'rental'));
        $this->assertSame(['fivesim', 'smsactivate'], $router->laneFor('ghana', 'rental'));
        $this->assertSame(['twilio', 'telnyx'], $router->laneFor('US', 'permanent'));
    }

    public function test_non_us_otp_routes_to_5sim_and_skips_getatext(): void
    {
        // Getatext must never be touched for a Nigeria request.
        $getatext = new FakeSmsProvider(price: new OutOfStockException('should not be called'));
        $fivesim = new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-1', 'number' => '2348010000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]);
        app()->instance('number.getatext', $getatext);
        app()->instance('number.fivesim', $fivesim);

        $result = app(SmsNumberRouter::class)->order($this->request('nigeria'));

        $this->assertSame('fivesim', $result->provider);
        $this->assertSame(0, $getatext->buyCalls, 'Getatext must be skipped for non-US');
        $this->assertSame(1, $fivesim->buyCalls);
        $this->assertDatabaseHas('sms_orders', ['provider' => 'fivesim', 'getatext_id' => '5S-1']);
    }

    public function test_out_of_stock_falls_back_within_the_same_lane(): void
    {
        // US OTP: Getatext out of stock -> 5sim serves it.
        $getatext = new FakeSmsProvider(price: new OutOfStockException('Service is out of stock'));
        $fivesim = new FakeSmsProvider(price: 0.30, buyResponse: [
            'provider_ref' => '5S-2', 'number' => '15551234567', 'cost' => 0.30, 'status' => OtpStatus::PENDING,
        ]);
        app()->instance('number.getatext', $getatext);
        app()->instance('number.fivesim', $fivesim);

        $result = app(SmsNumberRouter::class)->order($this->request('US'));

        $this->assertSame('fivesim', $result->provider);
        $this->assertSame(0, $getatext->buyCalls);
        $this->assertSame(1, $fivesim->buyCalls);
    }

    public function test_provider_whose_cost_eats_margin_is_skipped(): void
    {
        // Retail for a 6.00 cost at 55% markup = 9.30; a provider quoting 9.30
        // would leave < min profit, so it is skipped without buying.
        $fivesim = new FakeSmsProvider(price: 100.0); // absurd cost
        app()->instance('number.fivesim', $fivesim);
        app()->instance('number.smsactivate', new FakeSmsProvider(price: new OutOfStockException('n/a')));

        Queue::fake();
        $user = User::factory()->create();

        try {
            app(SmsNumberRouter::class)->order($this->request('nigeria', user: $user));
            $this->fail('Expected SmsException');
        } catch (SmsException $e) {
            // expected
        }

        $this->assertSame(0, $fivesim->buyCalls, 'margin-eating provider must not buy');
        // Refunded the charged amount.
        $this->assertSame('5.0000', (string) $user->walletTransactions()->where('type', 'refund')->first()->amount);
        Queue::assertPushed(AlertAdminJob::class);
    }

    public function test_exhausted_lane_refunds_and_alerts(): void
    {
        Queue::fake();
        app()->instance('number.fivesim', new FakeSmsProvider(price: new OutOfStockException('out')));
        app()->instance('number.smsactivate', new FakeSmsProvider(price: new OutOfStockException('out')));

        $user = User::factory()->create();

        try {
            app(SmsNumberRouter::class)->order($this->request('kenya', user: $user, charged: 4.0));
            $this->fail('Expected SmsException');
        } catch (SmsException $e) {
            // expected
        }

        // Charged amount refunded to the USD wallet.
        $this->assertSame('4.0000', (string) $user->walletTransactions()->where('type', 'refund')->first()->amount);
        $this->assertSame('4.0000', (string) $user->wallet->fresh()->usd_balance);
        Queue::assertPushed(AlertAdminJob::class);
    }

    public function test_permanent_lane_is_defined_but_not_yet_orderable(): void
    {
        $this->expectException(SmsException::class);
        app(SmsNumberRouter::class)->order($this->request('US', NumberRequest::TYPE_PERMANENT, charged: null));
    }
}
