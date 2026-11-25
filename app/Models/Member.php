<?php

namespace App\Models;

use App\Concerns\SavesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class Member extends Model
{
    use HasFactory;
    use SavesWithAudit;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'membership_category_id',
        'membership_level_id',
        'fee',
        'validity_period',
        'benefits',
        'status',
        'joined_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'membership_category_id' => 'integer',
            'membership_level_id' => 'integer',
            'joined_at' => 'date',
            'fee' => 'decimal:2',
            'membership_started_on' => 'date',
            'membership_expires_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            $member->membership_no = 'MEM-'.Str::ulid();
        });

        static::updating(function (Member $member): void {
            if ($member->isDirty('membership_no')) {
                throw new LogicException(
                    'Membership numbers cannot be changed.'
                );
            }
        });
    }

    public function membershipCategory(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class);
    }

    public function membershipLevel(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MembershipTransaction::class);
    }

    public function application(): HasOne
    {
        return $this->hasOne(MembershipApplication::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}