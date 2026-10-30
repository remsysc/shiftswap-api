<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagerSwapDecisionRequest;
use App\Http\Requests\StoreShiftSwapRequest;
use App\Http\Resources\ShiftSwapResource;
use App\Models\Business;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\SendSwapApprovedNotification;
use App\Notifications\SendSwapOfferedNotification;
use App\Notifications\SendSwapRejectedNotification;
use App\Notifications\SendSwapRequestedNotification;
use App\Services\ShiftConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShiftSwapController extends Controller
{
    /**
     * List all shift swaps for a business.
     */
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ShiftSwap::class, $business]);

        $status = $request->query('status');

        $swaps = ShiftSwap::where('business_id', $business->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with(['shift.location', 'shift.assignedStaff.user', 'offerer', 'requester'])
            ->latest('created_at')
            ->get();

        return ShiftSwapResource::collection($swaps);
    }

    /**
     * Offer a shift for swap.
     */
    public function store(StoreShiftSwapRequest $request, Business $business): JsonResponse
    {
        Gate::authorize('create', [ShiftSwap::class, $business]);

        /** @var ShiftSwap $swap */
        $swap = ShiftSwap::create([
            'business_id' => $business->id,
            'shift_id' => (int) $request->input('shift_id'),
            'shift_staff_id' => (int) $request->input('shift_staff_id'),
            'offered_by' => $request->user()?->id,
            'status' => 'offered',
            'notes' => $request->input('notes'),
        ]);

        $swap->load(['shift.location', 'shift.assignedStaff.user', 'offerer', 'requester']);

        // FR-42: Notify eligible staff members only
        $conflictService = app(ShiftConflictService::class);
        $eligibleStaff = $business->users()
            ->where('users.id', '!=', $swap->offered_by)
            ->get()
            ->filter(fn (User $u) => ! $conflictService->hasConflict(
                $u,
                $swap->shift->start_at,
                $swap->shift->end_at,
                $swap->shift_id,
                $business->timezone
            ));

        foreach ($eligibleStaff as $staffMember) {
            $staffMember->notify(new SendSwapOfferedNotification($swap));
        }

        return (new ShiftSwapResource($swap))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Request to take an offered swap.
     */
    public function request(Request $request, Business $business, ShiftSwap $swap): ShiftSwapResource
    {
        abort_if($swap->business_id !== $business->id, 403);
        Gate::authorize('request', $swap);

        $user = $request->user();

        DB::transaction(function () use ($business, $swap, $user): void {
            /** @var ShiftSwap $lockedSwap */
            $lockedSwap = ShiftSwap::whereKey($swap->id)->lockForUpdate()->firstOrFail();

            // FR-29: Shift swap must be in 'offered' status
            if ($lockedSwap->status !== 'offered') {
                throw ValidationException::withMessages([
                    'status' => ['This shift is no longer available.'],
                ]);
            }

            // Cannot request a past shift
            if ($lockedSwap->shift->start_at->isPast()) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot claim a swap for a shift that has already started or passed.'],
                ]);
            }

            // Cannot request own offer
            if ($user !== null && $lockedSwap->offered_by === $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => ['You cannot request your own swap offer.'],
                ]);
            }

            // FR-28: Requesting staff member must not have conflicting shift or time-off
            if ($user !== null) {
                $conflictService = app(ShiftConflictService::class);
                if ($conflictService->hasConflict(
                    $user,
                    $lockedSwap->shift->start_at,
                    $lockedSwap->shift->end_at,
                    $lockedSwap->shift_id,
                    $business->timezone
                )) {
                    throw ValidationException::withMessages([
                        'user_id' => ['You have a conflicting shift or approved time-off.'],
                    ]);
                }
            }

            $lockedSwap->update([
                'status' => 'pending_approval',
                'requested_by' => $user?->id,
            ]);
        });

        $swap->refresh()->load(['shift.location', 'shift.assignedStaff.user', 'offerer', 'requester']);

        // FR-43: Dispatch SendSwapRequestedNotification to manager(s)
        $managers = $business->users()->wherePivotIn('role', ['owner', 'manager'])->get();
        foreach ($managers as $manager) {
            $manager->notify(new SendSwapRequestedNotification($swap));
        }

        return new ShiftSwapResource($swap);
    }

    /**
     * Manager approves a shift swap.
     */
    public function approve(ManagerSwapDecisionRequest $request, Business $business, ShiftSwap $swap): ShiftSwapResource
    {
        abort_if($swap->business_id !== $business->id, 403);
        Gate::authorize('approve', $swap);

        DB::transaction(function () use ($business, $request, $swap): void {
            /** @var ShiftSwap $lockedSwap */
            $lockedSwap = ShiftSwap::whereKey($swap->id)
                ->with(['shift', 'shiftStaff', 'requester'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSwap->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => ['Swap request is not pending approval.'],
                ]);
            }

            if ($lockedSwap->shiftStaff->status !== 'assigned') {
                throw ValidationException::withMessages([
                    'status' => ['The original shift assignment is no longer active.'],
                ]);
            }

            // Cannot approve a swap for a past shift
            if ($lockedSwap->shift->start_at->isPast()) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot approve a swap for a shift that has already started or passed.'],
                ]);
            }

            // Re-verify conflict at approval time to prevent TOCTOU double-booking
            if ($lockedSwap->requester !== null) {
                $conflictService = app(ShiftConflictService::class);
                if ($conflictService->hasConflict(
                    $lockedSwap->requester,
                    $lockedSwap->shift->start_at,
                    $lockedSwap->shift->end_at,
                    $lockedSwap->shift_id,
                    $business->timezone
                )) {
                    throw ValidationException::withMessages([
                        'user_id' => ['Requesting staff member now has a conflicting shift or approved time-off.'],
                    ]);
                }
            }

            // Update offerer assignment status to removed
            $lockedSwap->shiftStaff->update([
                'status' => 'removed',
                'removed_at' => now(),
                'removed_reason' => 'swap_approved',
            ]);

            // Create new assignment for requester
            ShiftStaff::create([
                'shift_id' => $lockedSwap->shift_id,
                'user_id' => $lockedSwap->requested_by,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            // Mark swap as approved
            $lockedSwap->update([
                'status' => 'approved',
                'manager_notes' => $request->input('manager_notes'),
            ]);
        });

        $swap->refresh()->load(['shift.location', 'shift.assignedStaff.user', 'offerer', 'requester']);

        // FR-44: Dispatch SendSwapApprovedNotification to both users
        $swap->offerer->notify(new SendSwapApprovedNotification($swap));
        $swap->requester?->notify(new SendSwapApprovedNotification($swap));

        return new ShiftSwapResource($swap);
    }

    /**
     * Manager rejects a shift swap.
     */
    public function reject(ManagerSwapDecisionRequest $request, Business $business, ShiftSwap $swap): ShiftSwapResource
    {
        abort_if($swap->business_id !== $business->id, 403);
        Gate::authorize('reject', $swap);

        if ($swap->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'status' => ['Swap request is not pending approval.'],
            ]);
        }

        $managerNotes = $request->input('manager_notes');
        $requester = $swap->requester;

        // FR-31: Revert status to offered, clear requested_by and manager_notes
        $swap->update([
            'status' => 'offered',
            'requested_by' => null,
            'manager_notes' => null,
        ]);

        $swap->unsetRelation('requester');
        $swap->load(['shift.location', 'shift.assignedStaff.user', 'offerer']);

        // FR-45: Dispatch SendSwapRejectedNotification to requested_by only
        $requester?->notify(new SendSwapRejectedNotification($swap, $managerNotes));

        return new ShiftSwapResource($swap);
    }

    /**
     * Staff cancels their own swap offer.
     */
    public function cancel(Request $request, Business $business, ShiftSwap $swap): ShiftSwapResource
    {
        abort_if($swap->business_id !== $business->id, 403);
        Gate::authorize('cancel', $swap);

        // FR-33: Only 'offered' status may be cancelled
        if ($swap->status !== 'offered') {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a swap that is already being processed.'],
            ]);
        }

        $swap->update([
            'status' => 'cancelled',
            'cancellation_reason' => (string) ($request->input('cancellation_reason') ?? 'offerer_cancelled'),
        ]);

        $swap->load(['shift.location', 'shift.assignedStaff.user', 'offerer', 'requester']);

        return new ShiftSwapResource($swap);
    }

    /**
     * Claimant cancels / withdraws their claim on an offered swap.
     */
    public function withdraw(Request $request, Business $business, ShiftSwap $swap): ShiftSwapResource
    {
        abort_if($swap->business_id !== $business->id, 403);
        Gate::authorize('withdraw', $swap);

        if ($swap->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'status' => ['Cannot withdraw a claim that is not pending approval.'],
            ]);
        }

        $swap->update([
            'status' => 'offered',
            'requested_by' => null,
            'manager_notes' => null,
        ]);

        $swap->unsetRelation('requester');
        $swap->load(['shift.location', 'shift.assignedStaff.user', 'offerer']);

        return new ShiftSwapResource($swap);
    }
}
