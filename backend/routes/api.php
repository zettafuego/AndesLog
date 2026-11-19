<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EtlController;
use App\Http\Controllers\Api\InboundController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PickController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Middleware\AuthenticateOperator;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'app' => 'AndesLog',
        'ok' => true,
        'time' => now()->timezone('America/Lima')->toIso8601String(),
    ]);
});

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware([AuthenticateOperator::class, EnsureIdempotency::class])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::post('/ai/brief', [AiController::class, 'brief']);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/export', [InventoryController::class, 'export']);
    Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);

    Route::get('/inbound', [InboundController::class, 'index']);
    Route::post('/inbound/{order}/receive', [InboundController::class, 'receive']);

    Route::get('/picks', [PickController::class, 'index']);
    Route::post('/picks/{pick}/confirm', [PickController::class, 'confirm']);

    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::post('/shipments/{shipment}/transition', [ShipmentController::class, 'transition']);

    Route::post('/etl/recepciones', [EtlController::class, 'recepciones']);
});
