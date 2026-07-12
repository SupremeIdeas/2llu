<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\PollSmsOtpJob;
use App\Models\SmsOrder;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use App\Services\Wallet\WalletService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Get-a-Number flow (blueprint Section 12.1). Country + service + type, routed
 * by the capability router. Debits the live retail, orders through the lane,
 * then polls for the code. The user never sees a provider name or a cost.
 */
#[Layout('components.layouts.customer')]
class GetNumber extends Component
{
    public string $country = 'usa';

    public string $service = 'whatsapp';

    public string $type = 'otp';

    public ?int $orderId = null;

    public ?string $error = null;

    /** Curated list until provider country/service sync (5sim /guest/*) runs. */
    public array $countries = [
        'usa' => 'United States', 'nigeria' => 'Nigeria', 'ghana' => 'Ghana',
        'kenya' => 'Kenya', 'south africa' => 'South Africa', 'england' => 'United Kingdom',
    ];

    public array $services = ['whatsapp', 'google', 'telegram', 'facebook', 'instagram', 'tiktok'];

    public function order(WalletService $wallet, SmsNumberRouter $router): void
    {
        $this->error = null;
        $user = auth()->user();

        try {
            $quote = $router->quote(new NumberRequest($this->country, $this->type, $this->service, $user));
        } catch (SmsException $e) {
            $this->error = 'No number available for that country and service right now. Try another.';

            return;
        }

        $retail = $quote['retail'];
        $ref = "number-checkout:{$user->id}:".now()->timestamp;

        try {
            $wallet->debit($user, $retail, 'USD', ['reference' => $ref, 'description' => "Number: {$this->service}"]);
        } catch (InsufficientBalanceException $e) {
            $this->error = 'Your wallet balance is too low. Please top up and try again.';

            return;
        }

        try {
            $result = $router->order(new NumberRequest(
                $this->country, $this->type, $this->service, $user, 'USD', $retail
            ));
        } catch (SmsException $e) {
            // The router already refunded (charged was set).
            $this->error = 'Could not reserve a number — your wallet was refunded.';

            return;
        }

        PollSmsOtpJob::dispatch($result->order->id, 'USD');
        $this->orderId = $result->order->id;
    }

    public function reset_(): void
    {
        $this->reset('orderId', 'error');
    }

    public function render()
    {
        $order = $this->orderId ? SmsOrder::find($this->orderId) : null;

        return view('livewire.get-number', ['order' => $order]);
    }
}
