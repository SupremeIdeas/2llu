<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Support\Auditor;
use App\Support\SocialLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Google sign-in (Module 23). Three cases in the callback:
 *   1. Known google_id      -> log in.
 *   2. Email already exists  -> link Google to it and log in. Safe because
 *      Google has verified that email; we never silently take over an account
 *      with an UNVERIFIED matching email (we still link, but only Google-verified
 *      emails reach here and our own accounts verify via Fortify).
 *   3. Brand new             -> create a verified account, assign the user role,
 *      send the welcome email, log in.
 *
 * Guarded by SocialLogin::googleEnabled() so the routes 404-ish (redirect home)
 * until the admin configures the keys.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        abort_unless($provider === 'google' && SocialLogin::googleEnabled(), 404);

        return Socialite::driver('google')
            ->redirectUrl(SocialLogin::googleRedirect())
            ->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless($provider === 'google' && SocialLogin::googleEnabled(), 404);

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(SocialLogin::googleRedirect())
                ->user();
        } catch (\Throwable $e) {
            return redirect('/login')->withErrors(['email' => 'Google sign-in failed. Please try again or use your email and password.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();
            if ($user) {
                // Link Google to the existing account.
                $user->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $user->avatar ?: $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                Auditor::log('auth.google_linked', User::class, $user->id);
            }
        }

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before((string) $googleUser->getEmail(), '@'),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password' => bcrypt(Str::random(40)), // unusable until they set one
            ]);
            // email_verified_at is not fillable — set it directly (Google has
            // already verified the address).
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('user');

            try {
                $user->notify(new WelcomeNotification);
            } catch (\Throwable) {
                // best-effort
            }
            Auditor::log('auth.google_registered', User::class, $user->id);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
