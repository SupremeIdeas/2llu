<?php

namespace App\Livewire;

use App\Exceptions\EsimProviderException;
use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Services\eSIM\ProviderRouter;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * eSIM checkout (blueprint Sections 13, 14). Debits the wallet at the display
 * retail, then fulfils via the profit-aware ProviderRouter. Money-safety:
 *   - The user is only ever shown final_retail_usd (never cost).
 *   - ProviderRouter refunds itself if no provider can fulfil profitably.
 *   - Orphan-charge guard: if the order succeeds but persisting it fails, the
 *     wallet is refunded and admins alerted.
 */
#[Layout('components.layouts.customer')]
class Checkout extends Component
{
    public EsimPlan $plan;

    public bool $done = false;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(EsimPlan $plan): void
    {
        $this->plan = $plan;
    }

    public function purchase(WalletService $wallet, ProviderRouter $router): void
    {
        $user = auth()->user();

        // Order rate limit: 10/min (blueprint Section 19.2).
        $key = 'orders:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many orders in a short time. Please wait a minute and try again.';

            return;
        }
        RateLimiter::hit($key, 60);

        $retail = (float) $this->plan->final_retail_usd;
        $ref = "esim-checkout:{$this->plan->id}:{$user->id}:".now()->timestamp;

        try {
            $wallet->debit($user, $retail, 'USD', [
                'reference' => $ref,
                'description' => "eSIM: {$this->plan->name}",
            ]);
        } catch (InsufficientBalanceException $e) {
            $this->error = 'Your wallet balance is too low. Please top up and try again.';

            return;
        }

        try {
            $result = $router->orderPlan((string) $this->plan->id, $user, 'USD');
        } catch (EsimProviderException $e) {
            // ProviderRouter already refunded the wallet.
            $this->error = 'No provider could fulfil this plan right now — your wallet was refunded.';

            return;
        }

        try {
            EsimOrder::create([
                'user_id' => $user->id,
                'plan_id' => $this->plan->id,
                'provider' => $result->provider,
                'provider_order_ref' => data_get($result->payload, 'orderReference')
                    ?? data_get($result->payload, 'id'),
                'iccid' => data_get($result->payload, 'iccid')
                    ?? data_get($result->payload, 'esims.0.iccid'),
                'qr_code_url' => data_get($result->payload, 'qr_code')
                    ?? data_get($result->payload, 'qrCodeUrl'),
                'status' => 'processing',
                'price_charged' => $retail,
                'wholesale_cost' => $result->cost,
                'currency' => 'USD',
            ]);
        } catch (Throwable $e) {
            // Orphan-charge guard: charged + provider ordered, but we failed to
            // persist. Refund and alert (money-safety rule 1.2).
            $wallet->refund($user, $retail, 'USD', [
                'reference' => "refund:{$ref}",
                'description' => 'eSIM order could not be saved',
            ]);
            AlertAdminJob::dispatch(
                code: 'esim_order_save_failed',
                message: "eSIM order for user {$user->id} succeeded at {$result->provider} but failed to persist; wallet refunded.",
                context: ['user_id' => $user->id, 'plan_id' => $this->plan->id],
            );
            $this->error = 'Something went wrong finalising your order — your wallet was refunded.';

            return;
        }

        $this->done = true;
        $this->message = 'Success! Your eSIM is being provisioned and will appear on your dashboard shortly.';
    }

    public function render()
    {
        return view('livewire.checkout');
    }
}
