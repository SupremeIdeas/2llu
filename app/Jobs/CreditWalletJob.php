<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Credits a verified wallet top-up (blueprint Sections 14.2 & 19.3).
 *
 * Exactly-once is guaranteed two ways: ShouldBeUnique keeps duplicate jobs off
 * the queue, and WalletService credits idempotently on the reference — so even
 * if the webhook is delivered twice (or the job runs twice), the money moves
 * once.
 */
class CreditWalletJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public string $gateway,
        public string $reference,
        public int $userId,
        public float $amount,
        public string $currency,
    ) {
    }

    public function uniqueId(): string
    {
        return "credit:{$this->gateway}:{$this->reference}";
    }

    public function handle(WalletService $wallet): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        $wallet->credit($user, $this->amount, $this->currency, [
            'reference' => "topup:{$this->gateway}:{$this->reference}",
            'description' => "Wallet top-up via {$this->gateway}",
        ]);
    }
}
