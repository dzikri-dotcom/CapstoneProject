<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SchedulePeriodStatus;
use App\Enums\SchedulerLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchedulePeriodRequest;
use App\Http\Resources\SchedulePeriodResource;
use App\Http\Resources\SchedulerLogResource;
use App\Models\Attendance;
use App\Models\RoundRobinState;
use App\Models\SchedulePeriod;
use App\Services\Scheduling\ScheduleEditor;
use App\Services\Scheduling\ScheduleGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SchedulePeriodController extends Controller
{
    public function __construct(
        private readonly ScheduleGenerator $generator,
        private readonly ScheduleEditor $editor,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $periods = SchedulePeriod::query()
            ->withCount([
                'shiftSlots',
                'shiftAssignments',
                'shiftSlots as understaffed_slots_count' => fn (Builder $query) => $query->whereRaw(
                    '(select count(*) from shift_assignments where shift_assignments.shift_slot_id = shift_slots.id) < shift_slots.required_staff'
                ),
            ])
            ->withSum('shiftSlots as required_staff_total', 'required_staff')
            ->orderByDesc('start_date')
            ->paginate(10);

        $next = RoundRobinState::current()->nextStaff()->with('user:id,name')->first();

        return SchedulePeriodResource::collection($periods)->additional([
            'next_pointer' => $next ? ['id' => $next->id, 'name' => $next->user->name] : null,
        ]);
    }

    public function store(StoreSchedulePeriodRequest $request): JsonResponse
    {
        $period = SchedulePeriod::query()->create([
            ...$request->validated(),
            'status' => SchedulePeriodStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        return (new SchedulePeriodResource($period))
            ->additional(['message' => 'Periode jadwal berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Detail periode: slot & penugasan, peringatan per penugasan, dan rekap jam per staff.
     */
    public function show(SchedulePeriod $schedulePeriod): SchedulePeriodResource
    {
        $schedulePeriod->load([
            'shiftSlots.assignments' => fn ($query) => $query
                ->with('staffProfile.user:id,name')
                ->withExists('attendance')
                ->orderBy('id'),
            'startPointerStaff.user:id,name',
            'endPointerStaff.user:id,name',
        ]);

        return (new SchedulePeriodResource($schedulePeriod))->additional([
            'meta' => $this->editor->analyze($schedulePeriod),
        ]);
    }

    public function destroy(SchedulePeriod $schedulePeriod): JsonResponse
    {
        $this->generator->delete($schedulePeriod);

        return response()->json(['message' => 'Periode jadwal berhasil dihapus.']);
    }

    /**
     * Jalankan algoritma Round Robin untuk periode ini (hasil berstatus draft).
     */
    public function generate(SchedulePeriod $schedulePeriod): JsonResponse
    {
        $result = $this->generator->generate($schedulePeriod);
        $understaffed = count($result->understaffed);

        return response()->json([
            'message' => sprintf(
                'Jadwal berhasil di-generate: %d dari %d slot terpenuhi%s.',
                $result->filledSlotCount(),
                $result->slotCount(),
                $understaffed ? ", {$understaffed} slot kurang staff" : '',
            ),
            'summary' => [
                'slots' => $result->slotCount(),
                'filled' => $result->filledSlotCount(),
                'understaffed' => $understaffed,
                'steps' => count($result->logs) - 1,
            ],
        ]);
    }

    public function publish(SchedulePeriod $schedulePeriod): JsonResponse
    {
        if (! $schedulePeriod->isDraft()) {
            throw ValidationException::withMessages(['period' => 'Periode ini sudah dipublikasikan.']);
        }
        if (! $schedulePeriod->shiftSlots()->exists()) {
            throw ValidationException::withMessages(['period' => 'Generate jadwal terlebih dahulu sebelum dipublikasikan.']);
        }

        $schedulePeriod->update(['status' => SchedulePeriodStatus::Published, 'published_at' => now()]);

        return response()->json(['message' => 'Jadwal dipublikasikan dan sekarang terlihat oleh staff.']);
    }

    public function unpublish(SchedulePeriod $schedulePeriod): JsonResponse
    {
        if ($schedulePeriod->isDraft()) {
            throw ValidationException::withMessages(['period' => 'Periode ini masih berstatus draft.']);
        }

        $hasAttendance = Attendance::query()
            ->whereHas('shiftAssignment.shiftSlot', fn ($query) => $query->where('schedule_period_id', $schedulePeriod->id))
            ->exists();
        if ($hasAttendance) {
            throw ValidationException::withMessages([
                'period' => 'Sudah ada presensi pada periode ini sehingga tidak bisa dikembalikan ke draft.',
            ]);
        }

        $schedulePeriod->update(['status' => SchedulePeriodStatus::Draft, 'published_at' => null]);

        return response()->json(['message' => 'Jadwal dikembalikan ke draft dan disembunyikan dari staff.']);
    }

    /**
     * Log proses algoritma langkah demi langkah (bukti untuk laporan).
     */
    public function logs(Request $request, SchedulePeriod $schedulePeriod): AnonymousResourceCollection
    {
        $logs = $schedulePeriod->schedulerLogs()->with(['shiftSlot', 'staffProfile.user:id,name'])->get();
        $skipped = $logs->where('action', SchedulerLogAction::Skipped);

        return SchedulerLogResource::collection($logs)->additional([
            'summary' => [
                'checks' => $logs->whereIn('action', [SchedulerLogAction::Assigned, SchedulerLogAction::Skipped])->count(),
                'assigned' => $logs->where('action', SchedulerLogAction::Assigned)->count(),
                'skipped' => $skipped->count(),
                'understaffed' => $logs->where('action', SchedulerLogAction::Understaffed)->count(),
                'skip_reasons' => $skipped->countBy('reason'),
            ],
            'period' => new SchedulePeriodResource($schedulePeriod->load(['startPointerStaff.user:id,name', 'endPointerStaff.user:id,name'])),
        ]);
    }
}
