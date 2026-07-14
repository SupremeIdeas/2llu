<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Branded, queued email-verification notification (Module 22). Reuses Laravel's
 * signed verification URL (via the parent) but renders it through our brand
 * template instead of the framework default. Queued so it never blocks the
 * request (money rule 8 applies to all outbound calls).
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirm your email — '.config('app.name'))
            ->view('emails.verify', [
                'url' => $url,
                'name' => $notifiable->name ?? null,
            ]);
    }
}
