<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundLine;
use App\Models\InboundOrder;
use App\Models\Location;
use App\Models\Sku;
use App\Services\DashboardQuery;
use App\Services\ScanLogger;
use App\Services\StockException;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboundController extends Controller
{
    public function index(Request $request)
    {
        $operator = $request->attributes->get('operator');

        $orders = InboundOrder::query()
            ->where('warehouse_id', $operator->warehouse_id)
            ->whereIn('status', ['open', 'receiving'])
            ->with('lines.sku')
            ->orderBy('number')
            ->get()
            ->map(fn (InboundOrder $order) => $this->payload($order));

        return response()->json(['orders' => $orders]);
    }

    public function receive(Request $request, InboundOrder $order, StockService $stock, ScanLogger $scans, DashboardQuery $dashboard)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'qty' => ['required', 'integer', 'min:1'],
            'location_code' => ['required', 'string', 'max:40'],
        ], [
            'barcode.required' => 'Falta el código de barras.',
            'qty.min' => 'La cantidad tiene que ser mayor que cero.',
            'location_code.required' => 'Falta la ubicación.',
        ]);

        $operator = $request->attributes->get('operator');
        if ($order->warehouse_id !== $operator->warehouse_id) {
            abort(404);
        }

        $sku = Sku::query()->where('barcode', $data['barcode'])->first();
        if (! $sku) {
            $scans->write($operator, $data['barcode'], 'receive', 'reject', 'SKU desconocido');

            return response()->json(['message' => 'Ese código no está en el catálogo.'], 422);
        }

        $location = Location::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->where('code', $data['location_code'])
            ->first();
        if (! $location) {
            $scans->write($operator, $data['barcode'], 'receive', 'reject', 'Ubicación inexistente');

            return response()->json(['message' => 'La ubicación no existe en este almacén.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($order, $sku, $location, $data, $operator, $stock) {
                $line = InboundLine::query()
                    ->where('inbound_order_id', $order->id)
                    ->where('sku_id', $sku->id)
                    ->lockForUpdate()
                    ->first();

                if (! $line) {
                    return ['error' => 'Ese SKU no está en el ASN.'];
                }

                $room = $line->qty_expected - $line->qty_received;
                if ($data['qty'] > $room) {
                    return ['error' => "Solo faltan {$room} unidades de {$sku->code}."];
                }

                $balance = $stock->receive(
                    $order->warehouse_id,
                    $location->id,
                    $sku->id,
                    (int) $data['qty'],
                    $operator->id,
                    $order->number,
                );

                $line->qty_received += (int) $data['qty'];
                $line->save();

                $order->load('lines');
                $done = $order->lines->every(fn (InboundLine $item) => $item->qty_received >= $item->qty_expected);
                $order->status = $done ? 'closed' : 'receiving';
                $order->save();

                return ['line' => $line->fresh(), 'balance' => $balance];
            });
        } catch (StockException $exception) {
            $scans->write($operator, $data['barcode'], 'receive', 'reject', $exception->getMessage());

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (isset($result['error'])) {
            $scans->write($operator, $data['barcode'], 'receive', 'reject', $result['error']);

            return response()->json(['message' => $result['error']], 422);
        }

        $scans->write($operator, $data['barcode'], 'receive', 'ok', $order->number);
        $dashboard->forget($operator->warehouse_id);

        return response()->json([
            'ok' => true,
            'order_status' => $order->fresh()->status,
            'line' => [
                'sku_code' => $sku->code,
                'added' => (int) $data['qty'],
                'qty_received' => $result['line']->qty_received,
                'qty_expected' => $result['line']->qty_expected,
            ],
        ]);
    }

    private function payload(InboundOrder $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'supplier' => $order->supplier,
            'status' => $order->status,
            'eta' => optional($order->eta)->toDateString(),
            'lines' => $order->lines->map(fn (InboundLine $line) => [
                'id' => $line->id,
                'sku_code' => $line->sku->code,
                'sku_name' => $line->sku->name,
                'barcode' => $line->sku->barcode,
                'qty_expected' => $line->qty_expected,
                'qty_received' => $line->qty_received,
            ])->all(),
        ];
    }
}
