<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Attendance\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrTokenController extends Controller
{
    /**
     * Token QR yang sedang berlaku untuk ditampilkan di layar ruang logistik.
     */
    public function current(Request $request, QrTokenService $qrTokens): JsonResponse
    {
        $token = $qrTokens->current($request->user());

        return response()->json([
            'data' => [
                'token' => $token->token,
                'expires_at' => $token->expires_at->toIso8601String(),
                'server_time' => now()->toIso8601String(),
                'refresh_seconds' => (int) now()->diffInSeconds($token->expires_at, true),
            ],
        ]);
    }
}
