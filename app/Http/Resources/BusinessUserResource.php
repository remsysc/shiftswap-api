<?php

namespace App\Http\Resources;

use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BusinessUser|null $pivot
 *
 * @mixin User
 */
class BusinessUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->pivot;

        return [
            'id' => $pivot instanceof BusinessUser ? $pivot->id : $this->id,
            'user' => new UserResource($this->resource),
            'role' => $pivot instanceof BusinessUser ? $pivot->role : null,
            'created_at' => ($pivot instanceof BusinessUser ? $pivot->created_at : $this->created_at)?->toIso8601String(),
        ];
    }
}
