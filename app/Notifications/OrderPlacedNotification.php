<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued order-confirmation email (transactional emails pass).
 * Sent when a user buys an eSIM or reserves a number. Shows the RETAIL amount
 * the user paid only — never provider cost (money-safety rule 1.2).
 */
class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'esim'|'number'  $product
     */
    public function __construct(
        public string $product,
        public string $itemName,
        public float $amount,
        public string $currency,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isEsim = $this->product === 'esim';
        $subject = $isEsim ? 'Your eSIM order is confirmed' : 'Your number is on the way';

        return (new MailMessage)
            ->subject($subject.' — '.config('app.name'))
            ->view('emails.order-placed', [
                'name' => $notifiable->name ?? null,
                'product' => $this->product,
                'itemName' => $this->itemName,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'url' => url('/dashboard'),
            ]);
    }
}
