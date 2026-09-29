<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa setiap request API diperlakukan sebagai request JSON, sehingga
 * error validasi / 401 / 404 selalu dikembalikan dalam format JSON
 * (bukan redirect ke halaman HTML).
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
