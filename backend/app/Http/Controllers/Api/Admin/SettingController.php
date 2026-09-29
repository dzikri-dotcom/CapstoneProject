<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function show(SettingService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->all()]);
    }

    public function update(UpdateSettingsRequest $request, SettingService $settings): JsonResponse
    {
        return response()->json([
            'message' => 'Pengaturan berhasil disimpan.',
            'data' => $settings->update($request->validated()),
        ]);
    }
}
