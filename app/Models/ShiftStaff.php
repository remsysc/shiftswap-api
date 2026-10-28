<?php

namespace App\Models;

use Database\Factories\ShiftStaffFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shift_id
 * @property int $user_id
 * @property string $status
 * @property Carbon $assigned_at
 * @property Carbon|null $removed_at
 * @property string|null $removed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shift $shift
 * @property-read User $user
 * @property-read Collection<int, ShiftSwap> $swaps
 *
 * @method static ShiftStaffFactory factory($count = null, $state = [])
 */
class ShiftStaff extends Model
{
    /** @use HasFactory<ShiftStaffFactory> */
    use HasFactory;

    protected $table = 'shift_staff';

    protected $fillable = [
        'shift_id',
        'user_id',
        'status',
        'assigned_at',
        'removed_at',
        'removed_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ShiftSwap, $this>
     */
    public function swaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'shift_staff_id');
    }
}
