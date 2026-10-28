<?php

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Collection<int, Invitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read Collection<int, Location> $locations
 * @property-read int|null $locations_count
 * @property-read Collection<int, Shift> $shifts
 * @property-read int|null $shifts_count
 * @property-read Collection<int, ShiftSwap> $shiftSwaps
 * @property-read int|null $shift_swaps_count
 * @property-read Collection<int, TimeOffRequest> $timeOffRequests
 * @property-read int|null $time_off_requests_count
 * @property-read Collection<int, OwnershipTransfer> $ownershipTransfers
 * @property-read int|null $ownership_transfers_count
 *
 * @method static \Database\Factories\BusinessFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'timezone'];

    /**
     * @return BelongsToMany<User, $this, BusinessUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(BusinessUser::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * @return HasMany<ShiftSwap, $this>
     */
    public function shiftSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class);
    }

    /**
     * @return HasMany<TimeOffRequest, $this>
     */
    public function timeOffRequests(): HasMany
    {
        return $this->hasMany(TimeOffRequest::class);
    }

    /**
     * @return HasMany<OwnershipTransfer, $this>
     */
    public function ownershipTransfers(): HasMany
    {
        return $this->hasMany(OwnershipTransfer::class);
    }
}
