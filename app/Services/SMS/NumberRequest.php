<?php

namespace App\Services\SMS;

use App\Models\User;

/**
 * A number purchase request (blueprint Section 11.2). Carries what the user
 * asked for — country + type + service — plus the user and (once debited) the
 * amount charged, which the router refunds if the whole lane is exhausted.
 */
class NumberRequest
{
    public const TYPE_OTP = 'otp';
    public const TYPE_RENTAL = 'rental';
    public const TYPE_PERMANENT = 'permanent';

    /** Rental "any service" (full rent) — receive SMS from every service. */
    public const SERVICE_ANY = 'any';

    public function __construct(
        public readonly string $country,   // ISO code or provider slug
        public readonly string $type,      // otp | rental | permanent
        public readonly string $service,   // e.g. whatsapp, google, telegram
        public readonly User $user,
        public readonly string $currency = 'USD',
        public readonly ?float $charged = null,
        // Optional specific network chosen from the Step-3 operator comparison;
        // null/'any' lets the provider pick the best (cheapest in-stock).
        public readonly ?string $operator = null,
    ) {
    }

    public function isUs(): bool
    {
        return in_array(strtolower($this->country), ['usa', 'us'], true);
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'type' => $this->type,
            'service' => $this->service,
            'user_id' => $this->user->id,
            'currency' => $this->currency,
            'operator' => $this->operator,
        ];
    }
}
