<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Kelola shift (jam kerja untuk presensi). Perubahan hanya berlaku untuk jadwal yang
 * di-generate setelahnya; jadwal yang sudah ada menyimpan salinan jam & nama shift-nya.
 */
class ShiftController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ShiftResource::collection(Shift::query()->orderBy('start_time')->orderBy('id')->get());
    }

    public function store(ShiftRequest $request): JsonResponse
    {
        $shift = Shift::query()->create($request->validated());

        return (new ShiftResource($shift->refresh()))
            ->additional(['message' => "{$shift->name} berhasil ditambahkan."])
            ->response()
            ->setStatusCode(201);
    }

    public function update(ShiftRequest $request, Shift $shift): ShiftResource
    {
        $shift->update($request->validated());

        return (new ShiftResource($shift->refresh()))->additional(['message' => "{$shift->name} berhasil diperbarui."]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $shift->delete();

        return response()->json(['message' => "{$shift->name} berhasil dihapus."]);
    }
}
