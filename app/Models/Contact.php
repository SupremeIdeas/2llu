<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved contact in the user's in-app address book (Live Voice — Part C). Feeds
 * the dialer's destination field. Auth-scoped — a contact only ever belongs to
 * the user who created it.
 */
class Contact extends Model
{
    protected $fillable = ['user_id', 'name', 'phone_number'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Normalise a raw phone string to a dialable form: keep a single leading '+'
     * and the digits, drop spaces / dashes / parens. Non-E.164 input is left for
     * the dialer to validate at call time (the user can edit it in the field).
     */
    public static function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        $plus = str_starts_with($raw, '+') ? '+' : '';

        return $plus.preg_replace('/\D+/', '', $raw);
    }
}
