<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\InboundLine;
use App\Models\InboundOrder;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Operator;
use App\Models\PickLine;
use App\Models\PickOrder;
use App\Models\Shipment;
use App\Models\Sku;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Location $location;

    private Sku $sku;

    private InventoryBalance $balance;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::query()->create([
            'code' => 'LIM-01',
            'name' => 'Almacén Central',
            'city' => 'Lima',
        ]);
        $this->location = Location::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'A-01-01',
            'zone' => 'rack A',
            'kind' => 'storage',
        ]);
        Location::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'REC-01',
            'zone' => 'muelle',
            'kind' => 'receiving',
        ]);
        $this->sku = Sku::query()->create([
            'code' => 'CASCO-MSA',
            'barcode' => '7750101000011',
            'name' => 'Casco',
            'min_qty' => 20,
        ]);
        $this->balance = InventoryBalance::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'sku_id' => $this->sku->id,
            'qty_on_hand' => 10,
            'qty_reserved' => 4,
            'row_version' => 1,
        ]);
        $operator = Operator::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'OP-LIMA-01',
            'name' => 'Ana Quispe',
            'role' => 'supervisora',
            'pin_hash' => Hash::make('1847'),
        ]);
        $this->token = 'token-de-prueba-andeslog';
        ApiToken::query()->create([
            'operator_id' => $operator->id,
            'token_hash' => hash('sha256', $this->token),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_health_is_public(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJson(['app' => 'AndesLog', 'ok' => true]);
    }

    public function test_login_rejects_a_wrong_pin(): void
    {
        $this->postJson('/api/auth/login', ['code' => 'OP-LIMA-01', 'pin' => '0000'])
            ->assertStatus(422);
    }

    public function test_reserve_is_idempotent_and_refuses_oversell(): void
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => 'reserva-1',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/inventory/reserve', ['balance_id' => $this->balance->id, 'qty' => 2])
            ->assertOk()
            ->assertJson(['qty_reserved' => 6]);

        $this->withHeaders($headers)
            ->postJson('/api/inventory/reserve', ['balance_id' => $this->balance->id, 'qty' => 2])
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame(6, $this->balance->fresh()->qty_reserved);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => 'reserva-2',
        ])->postJson('/api/inventory/reserve', ['balance_id' => $this->balance->id, 'qty' => 20])
            ->assertStatus(422);

        $this->assertSame(6, $this->balance->fresh()->qty_reserved);
    }

    public function test_pick_cannot_take_reserved_stock(): void
    {
        $pick = PickOrder::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'number' => 'PK-1',
            'status' => 'open',
        ]);
        PickLine::query()->create([
            'pick_order_id' => $pick->id,
            'sku_id' => $this->sku->id,
            'location_id' => $this->location->id,
            'qty_required' => 8,
            'qty_picked' => 0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => 'pick-1',
        ])->postJson("/api/picks/{$pick->id}/confirm", [
            'barcode' => '7750101000011',
            'qty' => 7,
        ])->assertStatus(422);

        $this->assertSame(10, $this->balance->fresh()->qty_on_hand);
    }

    public function test_receive_increases_stock_once_per_key(): void
    {
        $order = InboundOrder::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'number' => 'ASN-1',
            'supplier' => 'Andes Repuestos',
            'status' => 'open',
        ]);
        InboundLine::query()->create([
            'inbound_order_id' => $order->id,
            'sku_id' => $this->sku->id,
            'qty_expected' => 5,
            'qty_received' => 0,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => 'recv-1',
        ];
        $body = ['barcode' => '7750101000011', 'qty' => 2, 'location_code' => 'REC-01'];

        $this->withHeaders($headers)->postJson("/api/inbound/{$order->id}/receive", $body)->assertOk();
        $this->withHeaders($headers)->postJson("/api/inbound/{$order->id}/receive", $body)
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true');

        $line = InboundLine::query()->where('inbound_order_id', $order->id)->first();
        $this->assertSame(2, $line->qty_received);

        $received = InventoryBalance::query()
            ->where('sku_id', $this->sku->id)
            ->whereHas('location', fn ($query) => $query->where('code', 'REC-01'))
            ->first();
        $this->assertSame(2, $received->qty_on_hand);
    }

    public function test_shipment_rejects_an_illegal_jump(): void
    {
        $trip = Shipment::query()->create([
            'origin_warehouse_id' => $this->warehouse->id,
            'number' => 'TMS-1',
            'destination_name' => 'CD Casma',
            'destination_city' => 'Casma',
            'status' => 'planned',
            'plate' => 'ABC-142',
            'driver' => 'Héctor Palma',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => 'trip-1',
        ])->postJson("/api/shipments/{$trip->id}/transition", ['status' => 'delivered'])
            ->assertStatus(422);

        $this->assertSame('planned', $trip->fresh()->status);
    }

    public function test_export_streams_the_sku(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)->get('/api/inventory/export');
        $response->assertOk();
        $this->assertStringContainsString('CASCO-MSA', $response->streamedContent());
    }

    public function test_etl_opens_an_asn_from_csv(): void
    {
        $csv = "asn,supplier,barcode,qty,eta\nASN-9,Textil Pacífico,7750101000011,3,2026-11-20\n";
        $file = UploadedFile::fake()->createWithContent('asn.csv', $csv);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => 'etl-1',
        ])->post('/api/etl/recepciones', ['file' => $file])->assertOk()->assertJson(['created' => 1, 'lines' => 1]);

        $this->assertDatabaseHas('inbound_orders', ['number' => 'ASN-9', 'warehouse_id' => $this->warehouse->id]);
    }

    public function test_brief_uses_ollama_when_it_answers_and_a_local_rule_when_it_does_not(): void
    {
        Http::fake([
            '*' => Http::response(['response' => 'Atender el muelle antes del mediodía.'], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/ai/brief', [])
            ->assertOk()
            ->assertJson(['source' => 'ollama', 'brief' => 'Atender el muelle antes del mediodía.']);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('apagado');
        });

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/ai/brief', [])
            ->assertOk()
            ->assertJson(['source' => 'local']);
    }

    public function test_memory_command_walks_balances(): void
    {
        $this->artisan('andes:memoria')->assertOk()->expectsOutputToContain('filas=1');
    }
}
