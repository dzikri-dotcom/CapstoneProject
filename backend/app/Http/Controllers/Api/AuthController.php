<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login dengan email & password, mengembalikan token Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with('staffProfile')->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->isStaff() && ! $user->staffProfile?->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.inactive')]);
        }

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('staffProfile'));
    }

    /**
     * Logout hanya dari perangkat ini (token yang sedang dipakai).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Anda telah keluar.']);
    }

    /**
     * Ganti password sendiri. Sesi di perangkat lain otomatis dikeluarkan.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Password berhasil diubah.']);
    }
}
