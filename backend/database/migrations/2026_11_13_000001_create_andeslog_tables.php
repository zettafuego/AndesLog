<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('city', 80);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('zone', 40);
            $table->string('kind', 20);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('barcode', 32)->unique();
            $table->string('name');
            $table->string('uom', 12)->default('UND');
            $table->decimal('weight_kg', 8, 3)->default(0);
            $table->unsignedInteger('min_qty')->default(0);
            $table->timestamps();
        });

        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('role', 40);
            $table->string('pin_hash');
            $table->timestamps();
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('sku_id')->constrained();
            $table->unsignedInteger('qty_on_hand')->default(0);
            $table->unsignedInteger('qty_reserved')->default(0);
            $table->unsignedInteger('row_version')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'location_id', 'sku_id'], 'balances_location_sku_unique');
            $table->index(['warehouse_id', 'sku_id'], 'balances_warehouse_sku_idx');
        });

        Schema::create('inbound_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('number', 40)->unique();
            $table->string('supplier');
            $table->string('status', 20)->default('open');
            $table->date('eta')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('inbound_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();
            $table->unsignedInteger('qty_expected');
            $table->unsignedInteger('qty_received')->default(0);
            $table->timestamps();
            $table->unique(['inbound_order_id', 'sku_id']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_warehouse_id')->constrained('warehouses');
            $table->string('number', 40)->unique();
            $table->string('destination_name');
            $table->string('destination_city', 80);
            $table->string('status', 20)->default('planned');
            $table->string('plate', 16);
            $table->string('driver');
            $table->timestamp('planned_at')->nullable();
            $table->timestamps();
            $table->index(['origin_warehouse_id', 'status', 'planned_at'], 'shipments_origin_status_idx');
        });

        Schema::create('shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();
            $table->unsignedInteger('qty');
            $table->timestamps();
        });

        Schema::create('pick_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('pick_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->unsignedInteger('qty_required');
            $table->unsignedInteger('qty_picked')->default(0);
            $table->timestamps();
            $table->index(['pick_order_id', 'sku_id']);
        });

        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('sku_id')->constrained();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('qty');
            $table->string('type', 20);
            $table->string('reference', 80);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['warehouse_id', 'created_at']);
        });

        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('barcode', 64);
            $table->string('context', 40);
            $table->string('result', 20);
            $table->string('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['warehouse_id', 'created_at']);
            $table->index('barcode');
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('route', 160);
            $table->unsignedSmallInteger('status_code');
            $table->json('body');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('scan_events');
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('pick_lines');
        Schema::dropIfExists('pick_orders');
        Schema::dropIfExists('shipment_lines');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('inbound_lines');
        Schema::dropIfExists('inbound_orders');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('operators');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('warehouses');
    }
};
