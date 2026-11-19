<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sku extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:3',
        ];
    }
}
