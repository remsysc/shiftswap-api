<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagerTimeOffDecisionRequest;
use App\Http\Requests\StoreTimeOffRequest;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\TimeOffRequestResource;
use App\Models\Business;
use App\Models\Shift;
use App\Models\TimeOffRequest;
use App\Notifications\SendTimeOffStatusNotification;
use App\Notifications\SendTimeOffSubmittedNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TimeOffRequestController extends Controller
{
    /**
     * List all time-off requests.
     */
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [TimeOffRequest::class, $business]);

        $user = $request->user();
        $isManagerOrOwner = $user !== null && $user->isManagerOrOwner($business);

        $status = $request->query('status');
        $targetUserId = $request->query('user_id');

        $requests = TimeOffRequest::where('business_id', $business->id)
            ->when(! $isManagerOrOwner, fn (Builder $q) => $q->where('user_id', $user?->id))
            ->when($isManagerOrOwner && $targetUserId !== null, fn (Builder $q) => $q->where('user_id', (int) $targetUserId))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->with('user')
            ->latest()
            ->get();

        return TimeOffRequestResource::collection($requests);
    }

    /**
     * Submit a new time-off request.
     */
    public function store(StoreTimeOffRequest $request, Business $business): JsonResponse
    {
        Gate::authorize('create', [TimeOffRequest::class, $business]);

        /** @var TimeOffRequest $timeOff */
        $timeOff = TimeOffRequest::create([
            'business_id' => $business->id,
            'user_id' => $request->user()?->id,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
        ]);

        $timeOff->load('user');

        // FR-46: Dispatch SendTimeOffSubmittedNotification to managers
        $managers = $business->users()->wherePivotIn('role', ['owner', 'manager'])->get();
        foreach ($managers as $manager) {
            $manager->notify(new SendTimeOffSubmittedNotification($timeOff));
        }

        return (new TimeOffRequestResource($timeOff))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific time-off request with overlapping shift conflicts.
     */
    public function show(Business $business, TimeOffRequest $timeOffRequest): JsonResponse
    {
        abort_if($timeOffRequest->business_id !== $business->id, 403);
        Gate::authorize('view', $timeOffRequest);

        $timezone = $business->timezone ?: 'Asia/Manila';
        $startUtc = Carbon::parse($timeOffRequest->start_date->toDateString().' 00:00:00', $timezone)->utc();
        $endUtc = Carbon::parse($timeOffRequest->end_date->toDateString().' 23:59:59', $timezone)->utc();

        $conflicts = Shift::query()
            ->where('business_id', $business->id)
            ->whereHas('shiftStaff', function (Builder $q) use ($timeOffRequest): void {
                $q->where('user_id', $timeOffRequest->user_id)->where('status', 'assigned');
            })
            ->where('start_at', '<=', $endUtc)
            ->where('end_at', '>=', $startUtc)
            ->with(['location', 'assignedStaff.user'])
            ->get();

        return response()->json([
            'data' => new TimeOffRequestResource($timeOffRequest->load('user')),
            'conflicts' => ShiftResource::collection($conflicts),
        ]);
    }

    /**
     * Approve a time-off request.
     */
    public function approve(ManagerTimeOffDecisionRequest $request, Business $business, TimeOffRequest $timeOffRequest): TimeOffRequestResource
    {
        abort_if($timeOffRequest->business_id !== $business->id, 403);
        Gate::authorize('approve', $timeOffRequest);

        if ($timeOffRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Time-off request has already been processed.'],
            ]);
        }

        $timeOffRequest->update([
            'status' => 'approved',
            'manager_notes' => $request->input('manager_notes'),
        ]);

        // FR-47: Dispatch SendTimeOffStatusNotification to requesting staff member
        $timeOffRequest->user->notify(new SendTimeOffStatusNotification($timeOffRequest));

        return new TimeOffRequestResource($timeOffRequest->load('user'));
    }

    /**
     * Reject a time-off request.
     */
    public function reject(ManagerTimeOffDecisionRequest $request, Business $business, TimeOffRequest $timeOffRequest): TimeOffRequestResource
    {
        abort_if($timeOffRequest->business_id !== $business->id, 403);
        Gate::authorize('reject', $timeOffRequest);

        if ($timeOffRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Time-off request has already been processed.'],
            ]);
        }

        $timeOffRequest->update([
            'status' => 'rejected',
            'manager_notes' => $request->input('manager_notes'),
        ]);

        // FR-47: Dispatch SendTimeOffStatusNotification to requesting staff member
        $timeOffRequest->user->notify(new SendTimeOffStatusNotification($timeOffRequest));

        return new TimeOffRequestResource($timeOffRequest->load('user'));
    }
}
