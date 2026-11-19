<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\StockMove;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function receive(int $warehouseId, int $locationId, int $skuId, int $qty, ?int $operatorId, string $reference): InventoryBalance
    {
        $this->assertQty($qty);

        return DB::transaction(function () use ($warehouseId, $locationId, $skuId, $qty, $operatorId, $reference) {
            $balance = InventoryBalance::query()->firstOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'sku_id' => $skuId,
                ],
                [
                    'qty_on_hand' => 0,
                    'qty_reserved' => 0,
                    'row_version' => 0,
                ],
            );

            $this->lockRow($balance->id);

            $balance->refresh();
            $balance->qty_on_hand += $qty;
            $balance->row_version += 1;
            $balance->save();

            $this->move($balance, $operatorId, $qty, 'receive', $reference);

            return $balance->refresh();
        });
    }

    public function reserve(int $balanceId, int $qty, ?int $operatorId): InventoryBalance
    {
        $this->assertQty($qty);

        return DB::transaction(function () use ($balanceId, $qty, $operatorId) {
            $this->lockRow($balanceId);

            $updated = DB::update(
                'UPDATE inventory_balances
                 SET qty_reserved = qty_reserved + ?, row_version = row_version + 1, updated_at = ?
                 WHERE id = ? AND (qty_on_hand - qty_reserved) >= ?',
                [$qty, now()->format('Y-m-d H:i:s'), $balanceId, $qty],
            );

            if ($updated !== 1) {
                throw new StockException('No hay disponible suficiente para reservar.');
            }

            $balance = InventoryBalance::query()->findOrFail($balanceId);
            $this->move($balance, $operatorId, $qty, 'reserve', 'reserva');

            return $balance;
        });
    }

    public function pick(int $balanceId, int $qty, ?int $operatorId, string $reference): InventoryBalance
    {
        $this->assertQty($qty);

        return DB::transaction(function () use ($balanceId, $qty, $operatorId, $reference) {
            $this->lockRow($balanceId);

            $updated = DB::update(
                'UPDATE inventory_balances
                 SET qty_on_hand = qty_on_hand - ?, row_version = row_version + 1, updated_at = ?
                 WHERE id = ? AND (qty_on_hand - qty_reserved) >= ?',
                [$qty, now()->format('Y-m-d H:i:s'), $balanceId, $qty],
            );

            if ($updated !== 1) {
                throw new StockException('Stock libre insuficiente en la ubicación. Puede estar reservado.');
            }

            $balance = InventoryBalance::query()->findOrFail($balanceId);
            $this->move($balance, $operatorId, -$qty, 'pick', $reference);

            return $balance;
        });
    }

    private function assertQty(int $qty): void
    {
        if ($qty < 1) {
            throw new StockException('La cantidad tiene que ser mayor que cero.');
        }
    }

    private function lockRow(int $balanceId): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::select('SELECT id FROM inventory_balances WITH (UPDLOCK, ROWLOCK) WHERE id = ?', [$balanceId]);

            return;
        }

        InventoryBalance::query()->whereKey($balanceId)->lockForUpdate()->first();
    }

    private function move(InventoryBalance $balance, ?int $operatorId, int $qty, string $type, string $reference): void
    {
        StockMove::query()->create([
            'warehouse_id' => $balance->warehouse_id,
            'location_id' => $balance->location_id,
            'sku_id' => $balance->sku_id,
            'operator_id' => $operatorId,
            'qty' => $qty,
            'type' => $type,
            'reference' => $reference,
        ]);
    }
}
