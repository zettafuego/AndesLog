<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('andes:memoria', function () {
    $before = memory_get_usage(true);
    $count = 0;

    \App\Models\InventoryBalance::query()
        ->with(['sku:id,code', 'location:id,code'])
        ->lazy(200)
        ->each(function () use (&$count) {
            $count++;
        });

    $after = memory_get_usage(true);
    $this->info("filas={$count}");
    $this->info('memoria_antes_kb='.(int) ($before / 1024));
    $this->info('memoria_despues_kb='.(int) ($after / 1024));
    $this->info('delta_kb='.(int) (($after - $before) / 1024));
})->purpose('Recorre el inventario con LazyCollection y muestra la RAM usada');
