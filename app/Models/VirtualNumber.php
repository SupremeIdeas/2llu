<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualNumber extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'phone_number',
        'sid',
        'capabilities',
        'monthly_cost',
        'monthly_retail',
        'status',
        'next_billing_date',
        'provisioned_at',
        'expires_at',
    ];

    /** Money-safety rule 1.2: monthly_cost is private. */
    protected $hidden = [
        'monthly_cost',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'monthly_cost' => 'decimal:4',
            'monthly_retail' => 'decimal:4',
            'next_billing_date' => 'date',
            'provisioned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
