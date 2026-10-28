<?php

namespace App\Models;

use Database\Factories\ShiftSwapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $shift_id
 * @property int $shift_staff_id
 * @property int $offered_by
 * @property int|null $requested_by
 * @property string $status
 * @property string|null $cancellation_reason
 * @property string|null $notes
 * @property string|null $manager_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 * @property-read Shift $shift
 * @property-read ShiftStaff $shiftStaff
 * @property-read User $offerer
 * @property-read User|null $requester
 *
 * @method static ShiftSwapFactory factory($count = null, $state = [])
 */
class ShiftSwap extends Model
{
    /** @use HasFactory<ShiftSwapFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'shift_id',
        'shift_staff_id',
        'offered_by',
        'requested_by',
        'status',
        'cancellation_reason',
        'notes',
        'manager_notes',
    ];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<ShiftStaff, $this>
     */
    public function shiftStaff(): BelongsTo
    {
        return $this->belongsTo(ShiftStaff::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function offerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
