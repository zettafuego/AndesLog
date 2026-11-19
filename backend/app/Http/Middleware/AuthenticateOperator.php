<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        if ($plain === '') {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $token = ApiToken::query()->where('token_hash', hash('sha256', $plain))->first();
        if (! $token || $token->expires_at->isPast()) {
            return response()->json(['message' => 'La sesión del turno venció.'], 401);
        }

        $request->attributes->set('operator', $token->operator);

        return $next($request);
    }
}
