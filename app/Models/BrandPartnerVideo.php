<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A brand's premium video preview (BUILD-9 §2.4). */
class BrandPartnerVideo extends Model
{
    protected $fillable = ['brand_partner_id', 'video_url', 'platform', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
