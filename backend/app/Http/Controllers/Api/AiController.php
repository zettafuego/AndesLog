<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShiftBrief;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function brief(Request $request, ShiftBrief $brief)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $operator = $request->attributes->get('operator');

        return response()->json($brief->make($operator->warehouse_id, $data['note'] ?? null));
    }
}
