<?php

namespace App\Http\Resources;

use App\Models\ShiftSwap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShiftSwap
 */
class ShiftSwapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'shift_id' => $this->shift_id,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'offered_by' => new UserResource($this->whenLoaded('offerer')),
            'requested_by' => new UserResource($this->whenLoaded('requester')),
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'manager_notes' => $this->manager_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
