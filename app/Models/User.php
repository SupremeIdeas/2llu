<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar',
        'phone',
        'country_code',
        'display_currency',
        'bio',
        'city',
        'address_line',
        'postal_code',
        'date_of_birth',
        'language',
        'timezone',
        'referral_code',
        'referred_by',
        'kyc_status',
        'is_active',
        'role',
        'password',
        'deactivated_at',
        'deletion_requested_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'data_export_ready_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'deletion_approved_at' => 'datetime',
            'merchant_enrollment_paid_at' => 'datetime',
        ];
    }

    // -- Branded, queued auth emails (Module 22) ---------------------------

    /** Use our brand-templated, queued verification email. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }

    /** Use our brand-templated, queued password-reset email. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    /**
     * Profile completeness (owner request) — 0–100%, to nudge users toward a
     * solid profile. Weighted across the fields that make a profile useful.
     */
    public function profileCompleteness(): int
    {
        $fields = [
            'name', 'email', 'phone', 'country_code', 'city',
            'bio', 'avatar', 'date_of_birth', 'address_line',
        ];
        $filled = collect($fields)->filter(fn ($f) => filled($this->{$f}))->count();

        return (int) round($filled / count($fields) * 100);
    }

    // -- Account lifecycle (blueprint Section 26) --------------------------

    /** Self-paused account — can log in only to reactivate. */
    public function isDeactivated(): bool
    {
        return ! $this->is_active;
    }

    /** A deletion request is awaiting super-admin approval. */
    public function hasPendingDeletion(): bool
    {
        return ! is_null($this->deletion_requested_at)
            && is_null($this->deletion_approved_at);
    }

    // -- Relationships -----------------------------------------------------

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserWallet::class);
    }

    public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function esimOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EsimOrder::class);
    }

    public function smsOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SmsOrder::class);
    }

    public function virtualNumbers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VirtualNumber::class);
    }

    /** People this user referred. */
    public function referralsMade(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /** The referral record that brought this user in (if any). */
    public function referral(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    /** The merchant storefront this user OWNS (if they became a merchant). */
    public function merchantAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Merchant::class, 'owner_user_id');
    }

    /** The merchant this user is a CUSTOMER of (co-branding follows this). */
    public function merchant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
