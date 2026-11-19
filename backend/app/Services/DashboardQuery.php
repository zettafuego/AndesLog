<?php

namespace App\Services;

use App\Models\InboundOrder;
use App\Models\PickLine;
use App\Models\ScanEvent;
use App\Models\Shipment;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardQuery
{
    public function forWarehouse(int $warehouseId): array
    {
        return Cache::remember("dashboard:wh:{$warehouseId}", 30, function () use ($warehouseId) {
            return $this->build($warehouseId);
        });
    }

    public function forget(int $warehouseId): void
    {
        Cache::forget("dashboard:wh:{$warehouseId}");
    }

    private function build(int $warehouseId): array
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        $inbound = InboundOrder::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['open', 'receiving'])
            ->with('lines')
            ->orderBy('number')
            ->get()
            ->map(fn (InboundOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier' => $order->supplier,
                'status' => $order->status,
                'expected' => (int) $order->lines->sum('qty_expected'),
                'received' => (int) $order->lines->sum('qty_received'),
            ])
            ->all();

        $shipments = Shipment::query()
            ->where('origin_warehouse_id', $warehouseId)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Shipment $trip) => [
                'id' => $trip->id,
                'number' => $trip->number,
                'destination_city' => $trip->destination_city,
                'status' => $trip->status,
                'plate' => $trip->plate,
            ])
            ->all();

        $below = DB::table('skus')
            ->join('inventory_balances', 'inventory_balances.sku_id', '=', 'skus.id')
            ->where('inventory_balances.warehouse_id', $warehouseId)
            ->groupBy('skus.id', 'skus.code', 'skus.name', 'skus.min_qty')
            ->havingRaw('SUM(inventory_balances.qty_on_hand - inventory_balances.qty_reserved) < skus.min_qty')
            ->orderBy('skus.code')
            ->get([
                'skus.code',
                'skus.name',
                'skus.min_qty',
                DB::raw('SUM(inventory_balances.qty_on_hand - inventory_balances.qty_reserved) as available'),
            ])
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'min_qty' => (int) $row->min_qty,
                'available' => (int) $row->available,
            ])
            ->all();

        $pendingPicks = PickLine::query()
            ->whereColumn('qty_picked', '<', 'qty_required')
            ->whereHas('pickOrder', fn ($query) => $query
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', ['open', 'picking']))
            ->count();

        $scans = ScanEvent::query()
            ->where('warehouse_id', $warehouseId)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (ScanEvent $scan) => [
                'id' => $scan->id,
                'barcode' => $scan->barcode,
                'context' => $scan->context,
                'result' => $scan->result,
                'detail' => $scan->detail,
            ])
            ->all();

        return [
            'warehouse' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'city' => $warehouse->city,
            ],
            'generated_at' => now()->timezone('America/Lima')->format('H:i:s'),
            'kpis' => [
                'open_inbound' => count($inbound),
                'pending_pick_lines' => $pendingPicks,
                'in_transit' => Shipment::query()
                    ->where('origin_warehouse_id', $warehouseId)
                    ->where('status', 'in_transit')
                    ->count(),
                'below_min' => count($below),
            ],
            'inbound' => $inbound,
            'shipments' => $shipments,
            'below_min' => $below,
            'scans' => $scans,
        ];
    }
}
