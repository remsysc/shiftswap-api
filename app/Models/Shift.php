<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $location_id
 * @property string|null $role_label
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property int $created_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 * @property-read Location|null $location
 * @property-read User $creator
 * @property-read Collection<int, ShiftStaff> $shiftStaff
 * @property-read Collection<int, ShiftStaff> $assignedStaff
 * @property-read Collection<int, ShiftSwap> $swaps
 *
 * @method static ShiftFactory factory($count = null, $state = [])
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'location_id',
        'role_label',
        'start_at',
        'end_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ShiftStaff, $this>
     */
    public function shiftStaff(): HasMany
    {
        return $this->hasMany(ShiftStaff::class);
    }

    /**
     * @return HasMany<ShiftStaff, $this>
     */
    public function assignedStaff(): HasMany
    {
        return $this->hasMany(ShiftStaff::class)->where('status', 'assigned');
    }

    /**
     * @return HasMany<ShiftSwap, $this>
     */
    public function swaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class);
    }
}
