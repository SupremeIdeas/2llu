<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client a Merchant V2 manages on behalf of. The client never logs in — the
 * merchant is the sole operator. Holds many eSIM / number orders over time.
 */
class MerchantClient extends Model
{
    protected $fillable = [
        'merchant_id', 'name', 'contact', 'device', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function esimOrders(): HasMany
    {
        return $this->hasMany(EsimOrder::class);
    }

    public function smsOrders(): HasMany
    {
        return $this->hasMany(SmsOrder::class);
    }
}
