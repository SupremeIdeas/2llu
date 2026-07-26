<?php

namespace App\Models;

use App\Services\Pricing\CurrencyService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsimPlan extends Model
{
    protected $fillable = [
        'provider',
        'provider_plan_id',
        'name',
        'type',
        'has_voice',
        'data_mb',
        'countries',
        'validity_days',
        'cost_price_usd',
        'airalo_min_price',
        'markup_pct',
        'override_markup_pct',
        'computed_retail_usd',
        'manual_retail_usd',
        'is_active',
        'is_featured',
        'synced_at',
        // NOTE: final_retail_usd is a generated column and is intentionally NOT
        // fillable — the database computes COALESCE(manual, computed).
    ];

    /**
     * Money-safety rule 1.2: never expose cost. These columns must never
     * appear in a user-facing payload, so they are hidden from array/JSON.
     */
    protected $hidden = [
        'cost_price_usd',
        'airalo_min_price',
        'markup_pct',
        'override_markup_pct',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'data_mb' => 'integer',
            'validity_days' => 'integer',
            'cost_price_usd' => 'decimal:4',
            'airalo_min_price' => 'decimal:4',
            'markup_pct' => 'decimal:3',
            'override_markup_pct' => 'decimal:3',
            'computed_retail_usd' => 'decimal:4',
            'manual_retail_usd' => 'decimal:4',
            'final_retail_usd' => 'decimal:4',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'has_voice' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EsimOrder::class, 'plan_id');
    }

    /**
     * The ONLY price ever shown to users (blueprint Section 13.4): the
     * generated final_retail_usd formatted as USD + NGN. Never exposes cost.
     * Computed on access (not appended) so serialization stays cheap.
     *
     * @return Attribute<array{usd: string, ngn: string, usd_amount: float, ngn_amount: float}, never>
     */
    protected function displayPrice(): Attribute
    {
        return Attribute::get(fn () => app(CurrencyService::class)
            ->displayPrice((float) $this->final_retail_usd));
    }
}

