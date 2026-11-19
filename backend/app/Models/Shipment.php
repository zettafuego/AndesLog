<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    public const EDGES = [
        'planned' => ['loading', 'exception'],
        'loading' => ['in_transit', 'exception'],
        'in_transit' => ['delivered', 'exception'],
        'exception' => ['loading', 'planned'],
        'delivered' => [],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_at' => 'datetime',
        ];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public static function transitionsFor(string $status): array
    {
        return self::EDGES[$status] ?? [];
    }
}
