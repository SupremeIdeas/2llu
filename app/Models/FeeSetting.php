<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Admin-tunable fee percentages/flats used by later batches' fee-split math
 * (FeeCalculator, Batch 2). Bounded by min_value/max_value so a bad edit
 * can't silently break the money path — setValue() refuses anything outside
 * bounds rather than clamping it silently. No admin UI yet (2LLU Batch 1 §5d).
 */
class FeeSetting extends Model
{
    protected $fillable = [
        'key', 'value', 'min_value', 'max_value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'min_value' => 'decimal:4',
            'max_value' => 'decimal:4',
        ];
    }

    /**
     * Read a fee setting's current value by key. Used by later batches as
     * FeeSetting::value('contribution_fee_percent').
     *
     * @throws InvalidArgumentException if the key does not exist.
     */
    public static function value(string $key): float
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            throw new InvalidArgumentException("Unknown fee setting: {$key}");
        }

        return (float) $setting->value;
    }

    /**
     * Update a fee setting's value, refusing anything outside its declared
     * min_value/max_value bounds.
     *
     * @throws InvalidArgumentException if the key does not exist or the
     *   value is out of bounds.
     */
    public static function setValue(string $key, float $value): void
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            throw new InvalidArgumentException("Unknown fee setting: {$key}");
        }

        if ($value < (float) $setting->min_value || $value > (float) $setting->max_value) {
            throw new InvalidArgumentException(
                "Value {$value} for fee setting '{$key}' is outside allowed bounds ".
                "[{$setting->min_value}, {$setting->max_value}]"
            );
        }

        $setting->update(['value' => $value]);
    }
}
