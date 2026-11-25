<?php

namespace App\Models;

use App\Concerns\SavesWithAudit;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class MembershipTransaction extends Model
{
    use SavesWithAudit;

    protected $fillable = [
        'member_id',
        'membership_category_id',
        'membership_level_id',
        'type',
        'status',
        'member_name',
        'membership_no',
        'category_name',
        'level_name',
        'category_fee',
        'level_fee',
        'total_amount',
        'validity_days',
        'benefits',
        'starts_on',
        'expires_on',
        'created_by',
        'approved_by',
        'cancelled_by',
        'approved_at',
        'cancelled_at',
    ];

    private const QUOTE_FIELDS = [
        'reference_no',
        'member_id',
        'membership_category_id',
        'membership_level_id',
        'type',
        'member_name',
        'membership_no',
        'category_name',
        'level_name',
        'category_fee',
        'level_fee',
        'total_amount',
        'validity_days',
        'benefits',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'category_fee' => 'decimal:2',
            'level_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'validity_days' => 'integer',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->reference_no = 'TXN-'.Str::ulid();
        });

        static::updating(function (self $transaction): void {
            if ($transaction->isDirty(self::QUOTE_FIELDS)) {
                throw new LogicException(
                    'An issued transaction quote cannot be changed.'
                );
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Transactions must be cancelled rather than deleted.'
            );
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function paidCents(): int
    {
        return $this->payments->sum(
            fn (Payment $payment) => Money::cents($payment->amount)
        );
    }

    public function balanceCents(): int
    {
        return max(
            Money::cents($this->total_amount) - $this->paidCents(),
            0
        );
    }
}