<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route berdasarkan role, contoh: ->middleware('role:admin').
 * Staff yang dinonaktifkan juga ditolak walaupun tokennya masih ada.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403, __('auth.forbidden'));
        }

        if ($user->isStaff() && ! $user->staffProfile?->is_active) {
            abort(403, __('auth.inactive'));
        }

        return $next($request);
    }
}
