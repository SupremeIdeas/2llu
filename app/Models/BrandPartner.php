<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brand partner shown on the Brand Partner Hunt page (BUILD-6 §C.4). Each
 * brand drives its own scroll-tied section background_color.
 */
class BrandPartner extends Model
{
    protected $fillable = [
        'brand_name', 'short_description', 'fallback_image',
        'background_color', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function handles(): HasMany
    {
        return $this->hasMany(BrandPartnerHandle::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
