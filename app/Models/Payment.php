<?php

namespace App\Models;

use App\Concerns\SavesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class Payment extends Model
{
    use SavesWithAudit;

    protected $fillable = [
        'membership_transaction_id',
        'idempotency_key',
        'amount',
        'method',
        'external_reference',
        'received_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $payment->reference_no = 'PAY-'.Str::ulid();
        });

        static::updating(function (): void {
            throw new LogicException(
                'Recorded payments cannot be edited.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Recorded payments cannot be deleted.'
            );
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            MembershipTransaction::class,
            'membership_transaction_id'
        );
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}