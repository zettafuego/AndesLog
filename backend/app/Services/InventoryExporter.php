<?php

namespace App\Services;

use App\Models\InventoryBalance;
use Generator;

class InventoryExporter
{
    public function rows(int $warehouseId): Generator
    {
        $balances = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->with(['sku:id,code,name,barcode', 'location:id,code'])
            ->orderBy('id')
            ->lazy(200);

        foreach ($balances as $balance) {
            yield [
                $balance->sku->code,
                $balance->sku->name,
                $balance->sku->barcode,
                $balance->location->code,
                $balance->qty_on_hand,
                $balance->qty_reserved,
                $balance->qty_on_hand - $balance->qty_reserved,
            ];
        }
    }
}
