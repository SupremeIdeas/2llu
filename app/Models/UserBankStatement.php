<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uploaded bank statement backing an advanced-tier plan's
 * requires_bank_statement gate. The Claude-based UnderwritingService that
 * populates ai_analysis and approves/rejects lands in Batch 2 — this model
 * is schema-only for now (2LLU Batch 1 §5b).
 */
class UserBankStatement extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'plan_id', 'pdf_url', 'file_size_bytes', 'ai_analysis', 'status',
    ];

    protected function casts(): array
    {
        return [
            'ai_analysis' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CirclePlan::class, 'plan_id');
    }
}
