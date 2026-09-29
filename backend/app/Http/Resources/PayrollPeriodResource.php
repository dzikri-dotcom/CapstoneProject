<?php

namespace App\Http\Resources;

use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollPeriod
 */
class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_amount' => (float) $this->total_amount,
            'settings_snapshot' => $this->settings_snapshot,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'finalized_by' => $this->whenLoaded('finalizer', fn () => $this->finalizer?->name),
            'details_count' => $this->whenCounted('details'),
            'details' => PayrollDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
