<?php

namespace App\Models;

use Database\Factories\TimeOffRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string|null $reason
 * @property string $status
 * @property string|null $manager_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 * @property-read User $user
 *
 * @method static TimeOffRequestFactory factory($count = null, $state = [])
 */
class TimeOffRequest extends Model
{
    /** @use HasFactory<TimeOffRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'manager_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
