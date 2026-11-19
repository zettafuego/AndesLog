<?php

namespace Database\Seeders;

use App\Models\InboundLine;
use App\Models\InboundOrder;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Operator;
use App\Models\PickLine;
use App\Models\PickOrder;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\Sku;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AndesLogSeeder extends Seeder
{
    public function run(): void
    {
        $lima = Warehouse::query()->create(['code' => 'LIM-01', 'name' => 'Almacén Central', 'city' => 'Lima']);
        $casma = Warehouse::query()->create(['code' => 'CAS-01', 'name' => 'Centro de distribución', 'city' => 'Casma']);

        $limaLocations = $this->locations($lima->id, [
            ['REC-01', 'muelle', 'receiving'],
            ['A-01-01', 'rack A', 'storage'],
            ['A-01-02', 'rack A', 'storage'],
            ['PICK-01', 'piso', 'picking'],
            ['STAGE-01', 'despacho', 'staging'],
            ['MUELLE-01', 'andén', 'dock'],
        ]);
        $casmaLocations = $this->locations($casma->id, [
            ['REC-01', 'muelle', 'receiving'],
            ['A-01-01', 'rack A', 'storage'],
        ]);

        $skus = collect([
            ['CASCO-MSA', '7750101000011', 'Casco de seguridad MSA blanco', 0.420, 20],
            ['GUANTE-NIT', '7750101000028', 'Guante nitrilo talla L', 0.080, 40],
            ['FILTRO-HF220', '7750101000035', 'Filtro hidráulico HF-220', 1.150, 10],
            ['ROD-6310', '7750101000042', 'Rodamiento 6310-2RS', 0.360, 15],
            ['VALV-4IN', '7750101000059', 'Válvula de bola 4 pulgadas', 3.800, 6],
            ['CINTA-50', '7750101000066', 'Cinta reflectiva 50 mm', 0.200, 10],
            ['BOTA-42', '7750101000073', 'Bota de seguridad talla 42', 1.400, 10],
            ['GRASA-EP2', '7750101000080', 'Grasa EP-2 balde 18 kg', 18.000, 8],
        ])->mapWithKeys(function (array $row) {
            $sku = Sku::query()->create([
                'code' => $row[0],
                'barcode' => $row[1],
                'name' => $row[2],
                'uom' => 'UND',
                'weight_kg' => $row[3],
                'min_qty' => $row[4],
            ]);

            return [$row[0] => $sku];
        });

        $this->balance($lima->id, $limaLocations['A-01-01'], $skus['CASCO-MSA']->id, 30);
        $this->balance($lima->id, $limaLocations['A-01-01'], $skus['GUANTE-NIT']->id, 16);
        $this->balance($lima->id, $limaLocations['A-01-02'], $skus['FILTRO-HF220']->id, 8);
        $this->balance($lima->id, $limaLocations['A-01-02'], $skus['ROD-6310']->id, 40);
        $this->balance($lima->id, $limaLocations['PICK-01'], $skus['VALV-4IN']->id, 12);
        $this->balance($lima->id, $limaLocations['A-01-02'], $skus['CINTA-50']->id, 22);
        $this->balance($lima->id, $limaLocations['PICK-01'], $skus['BOTA-42']->id, 18);
        $this->balance($lima->id, $limaLocations['STAGE-01'], $skus['GRASA-EP2']->id, 14);
        $this->balance($casma->id, $casmaLocations['A-01-01'], $skus['GUANTE-NIT']->id, 12);
        $this->balance($casma->id, $casmaLocations['REC-01'], $skus['CINTA-50']->id, 4);

        Operator::query()->create([
            'warehouse_id' => $lima->id,
            'code' => 'OP-LIMA-01',
            'name' => 'Ana Quispe',
            'role' => 'supervisora',
            'pin_hash' => Hash::make('1847'),
        ]);
        Operator::query()->create([
            'warehouse_id' => $casma->id,
            'code' => 'OP-CASMA-02',
            'name' => 'Luis Rojas',
            'role' => 'almacenero',
            'pin_hash' => Hash::make('2206'),
        ]);

        $asn = InboundOrder::query()->create([
            'warehouse_id' => $lima->id,
            'number' => 'ASN-2026-1044',
            'supplier' => 'Andes Repuestos S.A.C.',
            'status' => 'receiving',
            'eta' => '2026-11-14',
        ]);
        InboundLine::query()->create([
            'inbound_order_id' => $asn->id,
            'sku_id' => $skus['GUANTE-NIT']->id,
            'qty_expected' => 40,
            'qty_received' => 10,
        ]);
        InboundLine::query()->create([
            'inbound_order_id' => $asn->id,
            'sku_id' => $skus['GRASA-EP2']->id,
            'qty_expected' => 12,
            'qty_received' => 0,
        ]);

        $asnCasma = InboundOrder::query()->create([
            'warehouse_id' => $casma->id,
            'number' => 'ASN-2026-0901',
            'supplier' => 'Textil Pacífico',
            'status' => 'open',
            'eta' => '2026-11-16',
        ]);
        InboundLine::query()->create([
            'inbound_order_id' => $asnCasma->id,
            'sku_id' => $skus['CINTA-50']->id,
            'qty_expected' => 20,
            'qty_received' => 0,
        ]);

        $tms088 = $this->shipment($lima->id, 'TMS-088', 'CD Casma', 'Casma', 'loading', 'ABC-142', 'Héctor Palma');
        $this->shipmentLine($tms088, $skus['CASCO-MSA']->id, 6);
        $this->shipmentLine($tms088, $skus['FILTRO-HF220']->id, 2);
        $this->shipmentLine($tms088, $skus['BOTA-42']->id, 4);

        $tms091 = $this->shipment($lima->id, 'TMS-091', 'Taller Chimbote', 'Chimbote', 'in_transit', 'D4T-220', 'Rosa Meléndez');
        $this->shipmentLine($tms091, $skus['VALV-4IN']->id, 4);

        $tms076 = $this->shipment($lima->id, 'TMS-076', 'Base Huarmey', 'Huarmey', 'delivered', 'V8K-019', 'Miguel Soto');
        $this->shipmentLine($tms076, $skus['ROD-6310']->id, 10);

        $tms102 = $this->shipment($casma->id, 'TMS-102', 'Almacén Central', 'Lima', 'planned', 'B2P-778', 'Elena Vargas');
        $this->shipmentLine($tms102, $skus['CINTA-50']->id, 4);

        $pick = PickOrder::query()->create([
            'warehouse_id' => $lima->id,
            'shipment_id' => $tms088->id,
            'number' => 'PK-2026-0559',
            'status' => 'picking',
        ]);
        $this->pickLine($pick->id, $skus['CASCO-MSA']->id, $limaLocations['A-01-01'], 6, 2);
        $this->pickLine($pick->id, $skus['FILTRO-HF220']->id, $limaLocations['A-01-02'], 2, 0);
        $this->pickLine($pick->id, $skus['BOTA-42']->id, $limaLocations['PICK-01'], 4, 0);
    }

    private function locations(int $warehouseId, array $rows): array
    {
        $ids = [];
        foreach ($rows as [$code, $zone, $kind]) {
            $ids[$code] = Location::query()->create([
                'warehouse_id' => $warehouseId,
                'code' => $code,
                'zone' => $zone,
                'kind' => $kind,
            ])->id;
        }

        return $ids;
    }

    private function balance(int $warehouseId, int $locationId, int $skuId, int $qty): void
    {
        InventoryBalance::query()->create([
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'sku_id' => $skuId,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0,
            'row_version' => 1,
        ]);
    }

    private function shipment(int $originId, string $number, string $name, string $city, string $status, string $plate, string $driver): Shipment
    {
        return Shipment::query()->create([
            'origin_warehouse_id' => $originId,
            'number' => $number,
            'destination_name' => $name,
            'destination_city' => $city,
            'status' => $status,
            'plate' => $plate,
            'driver' => $driver,
            'planned_at' => now()->timezone('America/Lima')->setTime(6, 30),
        ]);
    }

    private function shipmentLine(Shipment $shipment, int $skuId, int $qty): void
    {
        ShipmentLine::query()->create([
            'shipment_id' => $shipment->id,
            'sku_id' => $skuId,
            'qty' => $qty,
        ]);
    }

    private function pickLine(int $pickId, int $skuId, int $locationId, int $required, int $picked): void
    {
        PickLine::query()->create([
            'pick_order_id' => $pickId,
            'sku_id' => $skuId,
            'location_id' => $locationId,
            'qty_required' => $required,
            'qty_picked' => $picked,
        ]);
    }
}
