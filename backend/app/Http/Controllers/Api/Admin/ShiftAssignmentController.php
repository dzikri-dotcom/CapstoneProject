<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddAssignmentRequest;
use App\Http\Requests\Admin\SwapAssignmentsRequest;
use App\Http\Requests\Admin\UpdateAssignmentRequest;
use App\Models\ShiftAssignment;
use App\Models\ShiftSlot;
use App\Models\StaffProfile;
use App\Services\Scheduling\ScheduleEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Edit manual hasil algoritma: tambah, hapus, pindah, ganti, dan tukar staff.
 * Respons menyertakan `warnings` jika staff sedang kuliah atau melebihi batas jam.
 */
class ShiftAssignmentController extends Controller
{
    public function __construct(private readonly ScheduleEditor $editor)
    {
    }

    public function candidates(ShiftSlot $shiftSlot): JsonResponse
    {
        return response()->json(['data' => $this->editor->candidates($shiftSlot)]);
    }

    public function store(AddAssignmentRequest $request, ShiftSlot $shiftSlot): JsonResponse
    {
        $assignment = $this->editor->add($shiftSlot, StaffProfile::findOrFail($request->validated('staff_profile_id')));

        return response()->json([
            'message' => 'Staff berhasil ditambahkan ke slot.',
            'warnings' => $this->editor->warningsForAssignment($assignment),
        ], 201);
    }

    public function update(UpdateAssignmentRequest $request, ShiftAssignment $shiftAssignment): JsonResponse
    {
        DB::transaction(function () use ($request, $shiftAssignment) {
            if ($request->filled('shift_slot_id')) {
                $this->editor->move($shiftAssignment, ShiftSlot::findOrFail($request->validated('shift_slot_id')));
            }
            if ($request->filled('staff_profile_id')) {
                $this->editor->replace($shiftAssignment, StaffProfile::findOrFail($request->validated('staff_profile_id')));
            }
        });

        return response()->json([
            'message' => 'Penugasan berhasil diperbarui.',
            'warnings' => $this->editor->warningsForAssignment($shiftAssignment->refresh()),
        ]);
    }

    public function swap(SwapAssignmentsRequest $request): JsonResponse
    {
        $first = ShiftAssignment::findOrFail($request->validated('first_assignment_id'));
        $second = ShiftAssignment::findOrFail($request->validated('second_assignment_id'));

        $this->editor->swap($first, $second);

        return response()->json([
            'message' => 'Staff berhasil ditukar.',
            'warnings' => [
                ...$this->editor->warningsForAssignment($first->refresh()),
                ...$this->editor->warningsForAssignment($second->refresh()),
            ],
        ]);
    }

    public function destroy(ShiftAssignment $shiftAssignment): JsonResponse
    {
        $this->editor->remove($shiftAssignment);

        return response()->json(['message' => 'Staff dihapus dari slot.']);
    }
}
