<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\ScanEvent;

class ScanLogger
{
    public function write(Operator $operator, string $barcode, string $context, string $result, ?string $detail = null): ScanEvent
    {
        return ScanEvent::query()->create([
            'operator_id' => $operator->id,
            'warehouse_id' => $operator->warehouse_id,
            'barcode' => $barcode,
            'context' => $context,
            'result' => $result,
            'detail' => $detail,
        ]);
    }
}
