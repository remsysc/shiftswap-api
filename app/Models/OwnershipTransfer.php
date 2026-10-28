<?php

namespace App\Models;

use Database\Factories\OwnershipTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $current_owner_id
 * @property int $successor_id
 * @property string $token_hash
 * @property string $status
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $plain_token
 * @property-read Business $business
 * @property-read User $currentOwner
 * @property-read User $successor
 *
 * @method static OwnershipTransferFactory factory($count = null, $state = [])
 */
class OwnershipTransfer extends Model
{
    /** @use HasFactory<OwnershipTransferFactory> */
    use HasFactory;

    public ?string $plain_token = null;

    protected $fillable = [
        'business_id',
        'current_owner_id',
        'successor_id',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
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
    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'successor_id');
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }
}
