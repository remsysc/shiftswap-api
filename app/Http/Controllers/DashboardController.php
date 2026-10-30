<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShiftResource;
use App\Http\Resources\ShiftSwapResource;
use App\Http\Resources\TimeOffRequestResource;
use App\Models\Business;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\TimeOffRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard data aggregated by role.
     */
    public function __invoke(Request $request, Business $business): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isManagerOrOwner = $user->isManagerOrOwner($business);

        if ($isManagerOrOwner) {
            $next7DaysEnd = now()->addDays(7)->endOfDay();

            $upcomingShifts = Shift::query()
                ->where('business_id', $business->id)
                ->where('start_at', '>=', now())
                ->where('start_at', '<=', $next7DaysEnd)
                ->with(['location', 'assignedStaff.user'])
                ->orderBy('start_at')
                ->get();

            $pendingSwapsCount = ShiftSwap::where('business_id', $business->id)
                ->where('status', 'pending_approval')
                ->whereHas('shift', function (Builder $q): void {
                    $q->where('start_at', '>=', now());
                })
                ->count();

            $pendingTimeOffCount = TimeOffRequest::where('business_id', $business->id)
                ->where('status', 'pending')
                ->count();

            return response()->json([
                'data' => [
                    'upcoming_shifts' => ShiftResource::collection($upcomingShifts),
                    'pending_swaps_count' => $pendingSwapsCount,
                    'pending_time_off_count' => $pendingTimeOffCount,
                ],
            ]);
        }

        // Staff dashboard
        $next14DaysEnd = now()->addDays(14)->endOfDay();

        $upcomingStaffShifts = Shift::query()
            ->where('business_id', $business->id)
            ->where('start_at', '>=', now())
            ->where('start_at', '<=', $next14DaysEnd)
            ->whereHas('shiftStaff', function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->where('status', 'assigned');
            })
            ->with(['location', 'assignedStaff.user'])
            ->orderBy('start_at')
            ->get();

        $pendingTimeOffRequests = TimeOffRequest::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $activeSwaps = ShiftSwap::where('business_id', $business->id)
            ->where(function (Builder $q) use ($user): void {
                $q->where('offered_by', $user->id)
                    ->orWhere('requested_by', $user->id);
            })
            ->whereIn('status', ['offered', 'pending_approval'])
            ->whereHas('shift', function (Builder $q): void {
                $q->where('start_at', '>=', now());
            })
            ->with(['shift.location', 'shift.assignedStaff.user', 'offerer', 'requester'])
            ->latest()
            ->get();

        return response()->json([
            'data' => [
                'upcoming_shifts' => ShiftResource::collection($upcomingStaffShifts),
                'pending_time_off_requests' => TimeOffRequestResource::collection($pendingTimeOffRequests),
                'active_swaps' => ShiftSwapResource::collection($activeSwaps),
            ],
        ]);
    }
}
