<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\PickLine;
use App\Models\PickOrder;
use App\Models\Sku;
use App\Services\DashboardQuery;
use App\Services\ScanLogger;
use App\Services\StockException;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickController extends Controller
{
    public function index(Request $request)
    {
        $operator = $request->attributes->get('operator');

        $orders = PickOrder::query()
            ->where('warehouse_id', $operator->warehouse_id)
            ->whereIn('status', ['open', 'picking'])
            ->with(['lines.sku', 'lines.location', 'shipment'])
            ->orderBy('number')
            ->get()
            ->map(fn (PickOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'shipment_number' => $order->shipment?->number,
                'lines' => $order->lines->map(fn (PickLine $line) => [
                    'id' => $line->id,
                    'barcode' => $line->sku->barcode,
                    'sku_code' => $line->sku->code,
                    'sku_name' => $line->sku->name,
                    'location_code' => $line->location->code,
                    'qty_required' => $line->qty_required,
                    'qty_picked' => $line->qty_picked,
                ])->all(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function confirm(Request $request, PickOrder $pick, StockService $stock, ScanLogger $scans, DashboardQuery $dashboard)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'qty' => ['required', 'integer', 'min:1'],
        ], [
            'barcode.required' => 'Falta el código de barras.',
            'qty.min' => 'La cantidad tiene que ser mayor que cero.',
        ]);

        $operator = $request->attributes->get('operator');
        if ($pick->warehouse_id !== $operator->warehouse_id) {
            abort(404);
        }

        $sku = Sku::query()->where('barcode', $data['barcode'])->first();
        if (! $sku) {
            $scans->write($operator, $data['barcode'], 'pick', 'reject', 'SKU desconocido');

            return response()->json(['message' => 'Ese código no está en el catálogo.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($pick, $sku, $data, $operator, $stock) {
                $line = PickLine::query()
                    ->where('pick_order_id', $pick->id)
                    ->where('sku_id', $sku->id)
                    ->whereColumn('qty_picked', '<', 'qty_required')
                    ->lockForUpdate()
                    ->first();

                if (! $line) {
                    return ['error' => 'Ese SKU no tiene pendiente en la oleada.'];
                }

                $room = $line->qty_required - $line->qty_picked;
                if ($data['qty'] > $room) {
                    return ['error' => "La línea solo pide {$room} de {$sku->code}."];
                }

                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $pick->warehouse_id)
                    ->where('location_id', $line->location_id)
                    ->where('sku_id', $sku->id)
                    ->first();

                if (! $balance) {
                    return ['error' => 'No hay saldo en la ubicación de la línea.'];
                }

                $stock->pick($balance->id, (int) $data['qty'], $operator->id, $pick->number);
                $line->qty_picked += (int) $data['qty'];
                $line->save();

                $pick->load('lines');
                $pick->status = $pick->lines->every(fn (PickLine $item) => $item->qty_picked >= $item->qty_required)
                    ? 'closed'
                    : 'picking';
                $pick->save();

                return ['line' => $line->fresh('location')];
            });
        } catch (StockException $exception) {
            $scans->write($operator, $data['barcode'], 'pick', 'reject', $exception->getMessage());

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (isset($result['error'])) {
            $scans->write($operator, $data['barcode'], 'pick', 'reject', $result['error']);

            return response()->json(['message' => $result['error']], 422);
        }

        $scans->write($operator, $data['barcode'], 'pick', 'ok', $pick->number);
        $dashboard->forget($operator->warehouse_id);

        return response()->json([
            'ok' => true,
            'order_status' => $pick->fresh()->status,
            'line' => [
                'sku_code' => $sku->code,
                'location_code' => $result['line']->location->code,
                'added' => (int) $data['qty'],
                'qty_picked' => $result['line']->qty_picked,
                'qty_required' => $result['line']->qty_required,
            ],
        ]);
    }
}
