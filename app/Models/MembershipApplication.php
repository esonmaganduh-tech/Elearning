<?php

namespace App\Models;

use App\Concerns\SavesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use LogicException;

class MembershipApplication extends Model
{
    use Notifiable;
    use SavesWithAudit;

    protected $fillable = [
        'submission_key',
        'membership_category_id',
        'full_name',
        'email',
        'phone',
        'address',
        'latitude',
        'longitude',
        'verification_sent_at',
        'privacy_consented_at',
    ];

    protected $hidden = [
        'submission_key',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'membership_category_id' => 'integer',
            'member_id' => 'integer',
            'registered_by' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verification_sent_at' => 'datetime',
            'privacy_consented_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MembershipApplication $application): void {
            $application->reference_no = 'APP-'.Str::ulid();
        });

        static::updating(function (MembershipApplication $application): void {
            if ($application->isDirty('reference_no')) {
                throw new LogicException(
                    'Application reference numbers cannot be changed.'
                );
            }
        });
    }

    public function membershipCategory(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function routeNotificationForMail(object $notification): array
    {
        return [$this->email => $this->full_name];
    }
}