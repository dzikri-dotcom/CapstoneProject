<?php

namespace App\Http\Resources;

use App\Models\PayrollDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rekap gaji satu staff dalam satu periode.
 *
 * @mixin PayrollDetail
 */
class PayrollDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff' => $this->whenLoaded('staffProfile', fn () => [
                'id' => $this->staffProfile->id,
                'name' => $this->staffProfile->user->name,
                'nim' => $this->staffProfile->nim,
                'study_program' => $this->staffProfile->study_program,
            ]),
            'total_shifts' => $this->total_shifts,
            'absent_count' => $this->whenCounted('absent_items'),
            'total_minutes' => $this->total_minutes,
            'total_amount' => (float) $this->total_amount,
            'period' => $this->whenLoaded('payrollPeriod', fn () => [
                'id' => $this->payrollPeriod->id,
                'start_date' => $this->payrollPeriod->start_date->toDateString(),
                'end_date' => $this->payrollPeriod->end_date->toDateString(),
                'status' => $this->payrollPeriod->status->value,
                'settings_snapshot' => $this->payrollPeriod->settings_snapshot,
            ]),
            'items' => PayrollItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
