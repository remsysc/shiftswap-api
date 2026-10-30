<?php

namespace App\Http\Resources;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shift
 */
class ShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
            'role_label' => $this->role_label,
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'staff' => ShiftStaffResource::collection($this->whenLoaded('assignedStaff')),
            'assigned_staff' => ShiftStaffResource::collection($this->whenLoaded('assignedStaff')),
        ];
    }
}
