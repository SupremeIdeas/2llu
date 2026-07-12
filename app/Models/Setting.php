<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest (blueprint Section 18.2). Stores structured
            // config (incl. provider keys) as an encrypted JSON blob.
            'value' => 'encrypted:array',
            'is_public' => 'boolean',
        ];
    }
}
