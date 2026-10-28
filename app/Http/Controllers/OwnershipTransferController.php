<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOwnershipTransferRequest;
use App\Http\Resources\OwnershipTransferResource;
use App\Models\Business;
use App\Models\OwnershipTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnershipTransferController extends Controller
{
    /**
     * Initiate an ownership transfer.
     */
    public function store(StoreOwnershipTransferRequest $request, Business $business): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isOwner($business), 403, 'Only the owner can transfer ownership.');

        $successorId = (int) $request->input('successor_id');
        $plainToken = Str::random(40);
        $tokenHash = hash('sha256', $plainToken);

        // Cancel previous pending transfers for this business
        $business->ownershipTransfers()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        /** @var OwnershipTransfer $transfer */
        $transfer = $business->ownershipTransfers()->create([
            'current_owner_id' => $user->id,
            'successor_id' => $successorId,
            'token_hash' => $tokenHash,
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        $transfer->plain_token = $plainToken;
        $transfer->load(['currentOwner', 'successor']);

        return (new OwnershipTransferResource($transfer))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Successor accepts ownership transfer.
     */
    public function accept(Request $request, string $token): Response
    {
        $tokenHash = hash('sha256', $token);

        /** @var OwnershipTransfer|null $transfer */
        $transfer = OwnershipTransfer::where('token_hash', $tokenHash)->first();

        if ($transfer === null || ! $transfer->isPending()) {
            if ($transfer !== null && $transfer->isExpired() && $transfer->status === 'pending') {
                $transfer->update(['status' => 'expired']);
            }

            throw ValidationException::withMessages([
                'token' => ['Invalid or expired ownership transfer.'],
            ]);
        }

        $user = $request->user();
        abort_unless($user !== null && $user->id === $transfer->successor_id, 403, 'Only the designated successor can accept this transfer.');

        DB::transaction(function () use ($transfer): void {
            // Promote successor to owner
            $transfer->business->users()->updateExistingPivot($transfer->successor_id, [
                'role' => 'owner',
            ]);

            // Demote former owner to manager
            $transfer->business->users()->updateExistingPivot($transfer->current_owner_id, [
                'role' => 'manager',
            ]);

            // Mark transfer as accepted
            $transfer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        });

        return response()->noContent();
    }
}
