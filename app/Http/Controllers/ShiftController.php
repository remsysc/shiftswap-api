<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListShiftsRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Business;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShiftController extends Controller
{
    /**
     * List all shifts for a business within a date range with eager-loaded staff.
     */
    public function index(ListShiftsRequest $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Shift::class, $business]);

        $startDate = Carbon::parse((string) $request->input('start_date'))->startOfDay();
        $endDate = Carbon::parse((string) $request->input('end_date'))->endOfDay();

        $shifts = $business->shifts()
            ->where('start_at', '<=', $endDate)
            ->where('end_at', '>=', $startDate)
            ->with(['assignedStaff.user', 'location'])
            ->orderBy('start_at')
            ->get();

        return ShiftResource::collection($shifts);
    }

    /**
     * Create a new shift for a business.
     */
    public function store(StoreShiftRequest $request, Business $business): JsonResponse
    {
        Gate::authorize('create', [Shift::class, $business]);

        /** @var Shift $shift */
        $shift = $business->shifts()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        $shift->load(['assignedStaff.user', 'location']);

        return (new ShiftResource($shift))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific shift.
     */
    public function show(Business $business, Shift $shift): ShiftResource
    {
        abort_if($shift->business_id !== $business->id, 403);
        Gate::authorize('view', $shift);

        $shift->load(['assignedStaff.user', 'location']);

        return new ShiftResource($shift);
    }

    /**
     * Update an existing shift.
     */
    public function update(UpdateShiftRequest $request, Business $business, Shift $shift): ShiftResource
    {
        abort_if($shift->business_id !== $business->id, 403);
        Gate::authorize('update', $shift);

        $shift->update($request->validated());
        $shift->load(['assignedStaff.user', 'location']);

        return new ShiftResource($shift);
    }

    /**
     * Delete a shift (soft delete) and cascade cancel open swaps.
     */
    public function destroy(Business $business, Shift $shift): Response
    {
        abort_if($shift->business_id !== $business->id, 403);
        Gate::authorize('delete', $shift);

        DB::transaction(function () use ($shift): void {
            $shift->swaps()
                ->whereIn('status', ['offered', 'pending_approval'])
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Shift deleted',
                ]);

            $shift->delete();
        });

        return response()->noContent();
    }
}
