<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Services\DashboardQuery;
use App\Services\InventoryExporter;
use App\Services\StockException;
use App\Services\StockService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $operator = $request->attributes->get('operator');
        $term = trim((string) $request->query('q', ''));

        $rows = InventoryBalance::query()
            ->where('warehouse_id', $operator->warehouse_id)
            ->with(['sku:id,code,name,barcode', 'location:id,code'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('sku', function ($sku) use ($term) {
                        $sku->where('code', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%");
                    })->orWhereHas('location', fn ($location) => $location->where('code', 'like', "%{$term}%"));
                });
            })
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (InventoryBalance $row) => [
                'id' => $row->id,
                'sku_code' => $row->sku->code,
                'sku_name' => $row->sku->name,
                'barcode' => $row->sku->barcode,
                'location_code' => $row->location->code,
                'qty_on_hand' => $row->qty_on_hand,
                'qty_reserved' => $row->qty_reserved,
                'available' => $row->qty_on_hand - $row->qty_reserved,
            ]);

        return response()->json(['rows' => $rows]);
    }

    public function export(Request $request, InventoryExporter $exporter)
    {
        $operator = $request->attributes->get('operator');

        return response()->streamDownload(function () use ($exporter, $operator) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'nombre', 'barcode', 'ubicacion', 'en_mano', 'reservado', 'disponible']);
            foreach ($exporter->rows($operator->warehouse_id) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'inventario.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reserve(Request $request, StockService $stock, DashboardQuery $dashboard)
    {
        $data = $request->validate([
            'balance_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
        ], [
            'qty.min' => 'La cantidad tiene que ser mayor que cero.',
        ]);

        $operator = $request->attributes->get('operator');
        $balance = InventoryBalance::query()->with('sku')->find($data['balance_id']);
        if (! $balance || $balance->warehouse_id !== $operator->warehouse_id) {
            return response()->json(['message' => 'Ese saldo no está en tu almacén.'], 404);
        }

        try {
            $updated = $stock->reserve($balance->id, (int) $data['qty'], $operator->id);
        } catch (StockException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $dashboard->forget($operator->warehouse_id);

        return response()->json([
            'balance_id' => $updated->id,
            'sku_code' => $balance->sku->code,
            'qty' => (int) $data['qty'],
            'qty_reserved' => $updated->qty_reserved,
            'available' => $updated->qty_on_hand - $updated->qty_reserved,
        ]);
    }
}
