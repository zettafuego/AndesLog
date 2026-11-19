<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\DashboardQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $operator = $request->attributes->get('operator');

        $shipments = Shipment::query()
            ->where('origin_warehouse_id', $operator->warehouse_id)
            ->with('lines.sku')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Shipment $trip) => $this->payload($trip));

        return response()->json(['shipments' => $shipments]);
    }

    public function transition(Request $request, Shipment $shipment, DashboardQuery $dashboard)
    {
        $operator = $request->attributes->get('operator');
        if ($shipment->origin_warehouse_id !== $operator->warehouse_id) {
            abort(404);
        }

        $allowed = Shipment::transitionsFor($shipment->status);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in($allowed ?: ['__none__'])],
        ], [
            'status.in' => 'Ese estado no sigue al actual ('.$shipment->status.').',
        ]);

        $shipment->status = $data['status'];
        $shipment->save();
        $dashboard->forget($operator->warehouse_id);

        return response()->json(['shipment' => $this->payload($shipment->fresh('lines.sku'))]);
    }

    private function payload(Shipment $trip): array
    {
        return [
            'id' => $trip->id,
            'number' => $trip->number,
            'destination_name' => $trip->destination_name,
            'destination_city' => $trip->destination_city,
            'status' => $trip->status,
            'plate' => $trip->plate,
            'driver' => $trip->driver,
            'planned_at' => optional($trip->planned_at)->toIso8601String(),
            'transitions' => Shipment::transitionsFor($trip->status),
            'lines' => $trip->lines->map(fn (ShipmentLine $line) => [
                'id' => $line->id,
                'sku_code' => $line->sku->code,
                'sku_name' => $line->sku->name,
                'qty' => $line->qty,
            ])->all(),
        ];
    }
}
