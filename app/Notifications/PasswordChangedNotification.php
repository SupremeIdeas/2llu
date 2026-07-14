<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued security alert sent when a user's password changes (Module
 * 22) — a standard account-security signal so a user notices an unexpected
 * change.
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed — '.config('app.name'))
            ->view('emails.password-changed', [
                'when' => now()->format('j M Y, H:i').' UTC',
            ]);
    }
}
