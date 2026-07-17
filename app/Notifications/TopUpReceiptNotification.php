<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued wallet top-up receipt (transactional emails pass). Sent once
 * a verified payment credits the wallet.
 */
class TopUpReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $currency,
        public string $gateway,
        public float $newBalance,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Wallet topped up — '.config('app.name'))
            ->view('emails.top-up', [
                'name' => $notifiable->name ?? null,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'gateway' => ucfirst($this->gateway),
                'newBalance' => $this->newBalance,
                'url' => url('/wallet'),
            ]);
    }
}
