<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundLine;
use App\Models\InboundOrder;
use App\Models\Sku;
use App\Services\DashboardQuery;
use Generator;
use Illuminate\Http\Request;
use RuntimeException;

class EtlController extends Controller
{
    public function recepciones(Request $request, DashboardQuery $dashboard)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ], [
            'file.required' => 'Falta el archivo CSV.',
        ]);

        $upload = $request->file('file');
        $name = strtolower((string) $upload->getClientOriginalName());
        if (! str_ends_with($name, '.csv')) {
            return response()->json(['message' => 'El archivo tiene que ser CSV.'], 422);
        }

        $operator = $request->attributes->get('operator');
        $created = 0;
        $lines = 0;
        $skipped = 0;
        $errors = [];

        try {
            foreach ($this->lines($upload->getRealPath()) as $index => $row) {
                try {
                    $outcome = $this->apply($operator->warehouse_id, $row);
                    $lines++;
                    if ($outcome === 'created') {
                        $created++;
                    }
                    if ($outcome === 'skipped') {
                        $skipped++;
                    }
                } catch (RuntimeException $exception) {
                    $errors[] = 'Fila '.($index + 2).': '.$exception->getMessage();
                }
            }
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($created > 0) {
            $dashboard->forget($operator->warehouse_id);
        }

        return response()->json([
            'processed' => $lines + count($errors),
            'created' => $created,
            'lines' => $lines,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    private function apply(int $warehouseId, array $row): string
    {
        foreach (['asn', 'supplier', 'barcode', 'qty'] as $column) {
            if (! isset($row[$column]) || $row[$column] === '') {
                throw new RuntimeException("falta {$column}.");
            }
        }

        if (! ctype_digit($row['qty']) || (int) $row['qty'] < 1) {
            throw new RuntimeException('cantidad inválida.');
        }

        $sku = Sku::query()->where('barcode', $row['barcode'])->first();
        if (! $sku) {
            throw new RuntimeException('código '.$row['barcode'].' no está en el catálogo.');
        }

        $eta = $row['eta'] ?? '';
        if ($eta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $eta)) {
            throw new RuntimeException('ETA tiene que ser AAAA-MM-DD.');
        }

        $order = InboundOrder::query()->firstOrCreate(
            ['number' => $row['asn']],
            [
                'warehouse_id' => $warehouseId,
                'supplier' => $row['supplier'],
                'status' => 'open',
                'eta' => $eta !== '' ? $eta : null,
            ],
        );

        if ($order->warehouse_id !== $warehouseId) {
            throw new RuntimeException('el ASN ya existe en otro almacén.');
        }

        $exists = InboundLine::query()
            ->where('inbound_order_id', $order->id)
            ->where('sku_id', $sku->id)
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        InboundLine::query()->create([
            'inbound_order_id' => $order->id,
            'sku_id' => $sku->id,
            'qty_expected' => (int) $row['qty'],
            'qty_received' => 0,
        ]);

        return $order->wasRecentlyCreated ? 'created' : 'line';
    }

    private function lines(string $path): Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('No se pudo leer el CSV.');
        }

        try {
            $header = fgetcsv($handle);
            if (! $header) {
                throw new RuntimeException('El CSV está vacío.');
            }

            $header = array_map(fn ($name) => strtolower(trim((string) $name)), $header);
            $missing = array_diff(['asn', 'supplier', 'barcode', 'qty', 'eta'], $header);
            if ($missing) {
                throw new RuntimeException('Faltan columnas: '.implode(', ', $missing).'.');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }

                $assoc = [];
                foreach ($header as $index => $name) {
                    $assoc[$name] = trim((string) ($row[$index] ?? ''));
                }

                yield $assoc;
            }
        } finally {
            fclose($handle);
        }
    }
}
