<?php

namespace App\Http\Resources;

use App\Models\ShiftStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShiftStaff
 */
class ShiftStaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at->toIso8601String(),
            'removed_at' => $this->removed_at?->toIso8601String(),
            'removed_reason' => $this->removed_reason,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
