<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $next($request);
        }

        if (strlen($key) > 80) {
            return response()->json(['message' => 'Idempotency-Key demasiado larga.'], 400);
        }

        $stored = DB::table('idempotency_keys')->where('key', $key)->first();
        if ($stored) {
            return $this->replay($stored);
        }

        $response = $next($request);
        if ($response->getStatusCode() >= 500) {
            return $response;
        }

        try {
            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'route' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
                'body' => $response->getContent() === '' ? '{}' : $response->getContent(),
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $stored = DB::table('idempotency_keys')->where('key', $key)->first();
            if ($stored) {
                return $this->replay($stored);
            }

            throw $exception;
        }

        return $response;
    }

    private function replay(object $stored): Response
    {
        return response($stored->body, (int) $stored->status_code, [
            'Content-Type' => 'application/json',
            'Idempotent-Replay' => 'true',
        ]);
    }
}
