<?php

namespace App\Http\Resources;

use App\Models\OwnershipTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OwnershipTransfer
 *
 * @property-read string|null $plain_token
 */
class OwnershipTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'current_owner' => new UserResource($this->whenLoaded('currentOwner')),
            'successor' => new UserResource($this->whenLoaded('successor')),
            'status' => $this->status,
            'token' => $this->when($this->plain_token !== null, $this->plain_token),
            'expires_at' => $this->expires_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
