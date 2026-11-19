<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'pin' => ['required', 'string', 'max:20'],
        ], [
            'code.required' => 'Falta el código del operador.',
            'pin.required' => 'Falta el PIN.',
        ]);

        $operator = Operator::query()->where('code', $data['code'])->first();
        if (! $operator || ! Hash::check($data['pin'], $operator->pin_hash)) {
            return response()->json(['message' => 'Código o PIN incorrecto.'], 422);
        }

        $plain = Str::random(48);
        ApiToken::query()->create([
            'operator_id' => $operator->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(14),
        ]);

        $operator->load('warehouse');

        return response()->json([
            'token' => $plain,
            'operator' => [
                'id' => $operator->id,
                'code' => $operator->code,
                'name' => $operator->name,
                'role' => $operator->role,
                'warehouse' => [
                    'id' => $operator->warehouse->id,
                    'code' => $operator->warehouse->code,
                    'name' => $operator->warehouse->name,
                ],
            ],
        ]);
    }
}
