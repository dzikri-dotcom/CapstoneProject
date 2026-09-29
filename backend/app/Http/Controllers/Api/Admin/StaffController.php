<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Staff\ListStaffRequest;
use App\Http\Requests\Admin\Staff\ResetStaffPasswordRequest;
use App\Http\Requests\Admin\Staff\StoreStaffRequest;
use App\Http\Requests\Admin\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\StaffProfile;
use App\Services\StaffService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staffService)
    {
    }

    /**
     * Daftar staff dengan pencarian (nama/email/NIM), filter status, dan paginasi.
     */
    public function index(ListStaffRequest $request): AnonymousResourceCollection
    {
        $staff = StaffProfile::query()
            ->select('staff_profiles.*')
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->with('user')
            ->withCount(['classSchedules', 'shiftAssignments'])
            ->when($request->validated('search'), function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('staff_profiles.nim', 'like', "%{$search}%");
                });
            })
            ->when($request->validated('status'), function (Builder $query, string $status) {
                $query->where('staff_profiles.is_active', $status === 'active');
            })
            ->orderBy('users.name')
            ->paginate($request->validated('per_page') ?? 10)
            ->withQueryString();

        return StaffResource::collection($staff)->additional([
            'summary' => [
                'total' => StaffProfile::count(),
                'active' => StaffProfile::active()->count(),
                'inactive' => StaffProfile::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $staff = $this->staffService->create($request->validated());

        return (new StaffResource($staff))
            ->additional(['message' => 'Staff berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(StaffProfile $staffProfile): StaffResource
    {
        return new StaffResource(
            $staffProfile->load(['user', 'classSchedules'])->loadCount(['classSchedules', 'shiftAssignments'])
        );
    }

    public function update(UpdateStaffRequest $request, StaffProfile $staffProfile): StaffResource
    {
        $staff = $this->staffService->update($staffProfile, $request->validated());

        return (new StaffResource($staff))->additional(['message' => 'Data staff berhasil diperbarui.']);
    }

    public function destroy(StaffProfile $staffProfile): JsonResponse
    {
        $this->staffService->delete($staffProfile);

        return response()->json(['message' => 'Data staff berhasil dihapus.']);
    }

    public function resetPassword(ResetStaffPasswordRequest $request, StaffProfile $staffProfile): JsonResponse
    {
        $this->staffService->resetPassword($staffProfile, $request->validated('password'));

        return response()->json(['message' => 'Password staff berhasil direset.']);
    }
}
