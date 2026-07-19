<?php

namespace Tests\Feature;

use App\Livewire\Wizard;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WizardSession;
use App\Services\SMS\OtpStatus;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakePermanentProvider;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * The NaaraSim Wizard core (roadmap §3) — a buttons-only state machine over the
 * Model registry + real routers. These lock the money-safety + supplier-masking
 * invariants of the guided flow (no LLM involved).
 */
class WizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        Queue::fake(); // PollSmsOtpJob is dispatched, not run
    }

    private function configureOtpLane(): void
    {
        config(['services.fivesim.api_key' => 'test-key']);
        \App\Support\ProviderKeys::flush();
    }

    private function configurePermanentLane(): void
    {
        config(['services.twilio.account_sid' => 'AC', 'services.twilio.auth_token' => 'tok']);
        \App\Support\ProviderKeys::flush();
    }

    public function test_only_available_models_appear_as_purposes(): void
    {
        // No provider keys at all → no purposes offered (nothing to sell).
        config(['services.fivesim.api_key' => null, 'services.esimgo.api_key' => null,
                'services.getatext.api_key' => null, 'services.smsactivate.api_key' => null,
                'services.twilio.account_sid' => null, 'services.telnyx.api_key' => null,
                'services.airalo.client_id' => null, 'services.quibity.api_key' => null]);
        \App\Support\ProviderKeys::flush();

        $comp = Livewire::actingAs(User::factory()->create())->test(Wizard::class);
        $this->assertCount(0, $comp->instance()->purposes());

        // Configure the OTP/rental lane → Verify + Rent appear, Line does not.
        $this->configureOtpLane();
        $comp = Livewire::actingAs(User::factory()->create())->test(Wizard::class);
        $keys = collect($comp->instance()->purposes())->pluck('key')->all();
        $this->assertContains('naara_verify', $keys);
        $this->assertContains('naara_rent', $keys);
        $this->assertNotContains('naara_line', $keys);
    }

    public function test_otp_flow_charges_reserves_and_never_exposes_the_supplier(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-1', 'number' => '+2348010000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        $comp = Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')
            ->assertSet('step', 'country')
            ->call('chooseCountry', 'nigeria')
            ->assertSet('step', 'service')
            ->call('chooseService', 'whatsapp')
            ->assertSet('step', 'review')
            ->call('purchase')
            ->assertSet('step', 'result');

        // A waiting order exists and belongs to the user.
        $order = SmsOrder::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('waiting', $order->status);
        Queue::assertPushed(\App\Jobs\PollSmsOtpJob::class);

        // The wallet was charged retail (> cost).
        $this->assertLessThan(20.0, (float) $user->wallet->fresh()->usd_balance);

        // Supplier masking: the real provider is never rendered nor held in any
        // public (dehydrated) property that reaches the browser.
        $comp->assertDontSee('fivesim')->assertDontSee('5sim');
        $props = $comp->instance()->all();
        $this->assertStringNotContainsString('fivesim', strtolower(json_encode($props)));
    }

    public function test_insufficient_balance_routes_to_topup_without_charging(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20));
        $user = User::factory()->create(); // no funds

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')
            ->call('chooseCountry', 'nigeria')
            ->call('chooseService', 'whatsapp')
            ->call('purchase')
            ->assertSet('step', 'topup');

        $this->assertSame(0, SmsOrder::count());
        // No debit transaction was written (the user was never charged).
        $this->assertSame(0, $user->walletTransactions()->count());
    }

    public function test_permanent_flow_provisions_a_number_via_the_wizard(): void
    {
        $this->configurePermanentLane();
        app()->instance('number.twilio', new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15550001234', 'locality' => 'New York'],
        ]));

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_line')
            ->call('chooseCountry', 'usa')
            ->assertSet('step', 'pick')
            ->call('provisionPermanent', '+15550001234')
            ->assertSet('step', 'result');

        $vn = VirtualNumber::where('user_id', $user->id)->first();
        $this->assertNotNull($vn);
        $this->assertSame('active', $vn->status);
        $this->assertSame('+15550001234', $vn->phone_number);
        $this->assertLessThan(20.0, (float) $user->wallet->fresh()->usd_balance);
    }

    public function test_esim_purpose_guides_to_the_catalogue(): void
    {
        config(['services.esimgo.api_key' => 'test-key']);
        \App\Support\ProviderKeys::flush();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_data')
            ->call('chooseCountry', 'nigeria')
            ->assertSet('step', 'device')
            ->call('goToEsims')
            ->assertRedirect(route('catalogue', ['q' => 'Nigeria']));
    }

    public function test_progress_is_saved_and_restored_across_remounts(): void
    {
        $this->configureOtpLane();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')
            ->call('chooseCountry', 'ghana')
            ->assertSet('step', 'service');

        // A fresh mount (e.g. after navigating to top up) rehydrates the state.
        $this->assertDatabaseHas('wizard_sessions', ['user_id' => $user->id, 'step' => 'service']);
        Livewire::actingAs($user)->test(Wizard::class)
            ->assertSet('step', 'service')
            ->assertSet('model', 'naara_verify')
            ->assertSet('country', 'ghana');

        // Completing a purchase clears the saved session.
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-9', 'number' => '+233200000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));
        app(WalletService::class)->credit($user, 20, 'USD');
        Livewire::actingAs($user)->test(Wizard::class)
            ->call('chooseService', 'whatsapp')
            ->call('purchase')
            ->assertSet('step', 'result');
        $this->assertDatabaseMissing('wizard_sessions', ['user_id' => $user->id]);
    }
}
