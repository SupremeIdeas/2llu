<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A cached currency-pair exchange rate. The daily FX sync job that keeps
 * `rate` fresh lands in Batch 2 — this model is schema-only for now
 * (2LLU Batch 1 §5b). Bigint auto-increment id (not UUID) and no created_at
 * column — each pair is upserted in place, so only updated_at is tracked.
 */
class FxRate extends Model
{
    public const CREATED_AT = null;

    protected $fillable = [
        'from_currency', 'to_currency', 'rate',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
        ];
    }
}
