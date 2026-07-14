<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;

/**
 * Branded, queued password-reset notification (Module 22). Builds the reset URL
 * exactly as Laravel does (token + email) and renders it through our brand
 * template.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use InteractsWithQueue;

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expires = Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset your password — '.config('app.name'))
            ->view('emails.reset', [
                'url' => $url,
                'expires' => $expires,
            ]);
    }
}
