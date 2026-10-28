<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\TimeOffRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ShiftConflictService
{
    /**
     * Determine if a user has an overlapping shift during the given time window.
     * Overlap rule: new_start < existing_end AND new_end > existing_start
     * Boundary equality (new_start == existing_end OR new_end == existing_start) is NOT an overlap.
     */
    public function hasShiftOverlap(
        User $user,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $excludeShiftId = null
    ): bool {
        return Shift::query()
            ->whereHas('shiftStaff', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->where('status', 'assigned');
            })
            ->when($excludeShiftId !== null, fn (Builder $query) => $query->where('id', '!=', $excludeShiftId))
            ->where(function (Builder $query) use ($startAt, $endAt): void {
                $query->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();
    }

    /**
     * Determine if a user has an approved time-off request overlapping the time window.
     */
    public function hasTimeOffConflict(
        User $user,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?string $businessTimezone = null
    ): bool {
        $timezone = $businessTimezone ?? 'Asia/Manila';

        $localStartDate = $startAt->copy()->setTimezone($timezone)->toDateString();
        $localEndDate = $endAt->copy()->setTimezone($timezone)->toDateString();

        return TimeOffRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $localEndDate)
            ->where('end_date', '>=', $localStartDate)
            ->exists();
    }

    /**
     * Determine if the user has any conflict (overlapping shift or approved time off).
     */
    public function hasConflict(
        User $user,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $excludeShiftId = null,
        ?string $businessTimezone = null
    ): bool {
        return $this->hasShiftOverlap($user, $startAt, $endAt, $excludeShiftId)
            || $this->hasTimeOffConflict($user, $startAt, $endAt, $businessTimezone);
    }
}
