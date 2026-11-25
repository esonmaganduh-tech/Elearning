<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MembershipBilling
{
    public function createInitial(
    int $memberId,
    User $actor
): MembershipTransaction {
    return $this->createQuote(
        $memberId,
        'membership',
        null,
        null,
        $actor
    );
}

public function createRenewal(
    int $memberId,
    int $categoryId,
    int $levelId,
    User $actor
): MembershipTransaction {
    return $this->createQuote(
        $memberId,
        'renewal',
        $categoryId,
        $levelId,
        $actor
    );
}

private function createQuote(
    int $memberId,
    string $type,
    ?int $categoryId,
    ?int $levelId,
    User $actor
): MembershipTransaction {
    Gate::forUser($actor)->authorize('access-admin');

    return DB::transaction(function () use (
        $memberId,
        $type,
        $categoryId,
        $levelId,
        $actor
    ) {
        $member = Member::query()
            ->lockForUpdate()
            ->findOrFail($memberId);

        $hasInitial = $member->transactions()
            ->where('type', 'membership')
            ->whereIn('status', ['approved', 'expired'])
            ->exists();

        if ($type === 'membership' && $hasInitial) {
            $this->reject(
                'transaction',
                'An initial membership is already approved. Use a renewal.'
            );
        }

        if ($type === 'renewal' && ! $hasInitial) {
            $this->reject(
                'transaction',
                'Approve an initial membership before creating a renewal.'
            );
        }

        $existing = $member->transactions()
            ->whereIn('status', ['pending', 'paid'])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($type === 'membership') {
            $categoryId = $member->membership_category_id;
            $levelId = $member->membership_level_id;
        }

        $covered = $member->membership_expires_on
            && $member->membership_expires_on->toDateString()
                >= today('Asia/Manila')->toDateString();

        if (
            $type === 'renewal'
            && $covered
            && (
                $categoryId !== $member->membership_category_id
                || $levelId !== $member->membership_level_id
            )
        ) {
            $this->reject(
                'transaction',
                'An unexpired membership must renew its current plan.'
            );
        }

        $category = MembershipCategory::query()
            ->lockForUpdate()
            ->findOrFail($categoryId);

        $level = MembershipLevel::query()
            ->lockForUpdate()
            ->findOrFail($levelId);

        if (! $category->is_active || ! $level->is_active) {
            $this->reject(
                'transaction',
                'An active category and level are required.'
            );
        }

        if ($level->validity_days < 1 || $level->validity_days > 36500) {
            $this->reject(
                'transaction',
                'The membership level has an invalid duration.'
            );
        }

        $categoryFee = $type === 'membership'
            ? $category->fee
            : '0.00';

        $totalCents = Money::cents($categoryFee)
            + Money::cents($level->fee);

        $transaction = MembershipTransaction::create([
            'member_id' => $member->id,
            'membership_category_id' => $category->id,
            'membership_level_id' => $level->id,
            'type' => $type,
            'status' => $totalCents === 0 ? 'paid' : 'pending',
            'member_name' => $member->full_name,
            'membership_no' => $member->membership_no,
            'category_name' => $category->name,
            'level_name' => $level->name,
            'category_fee' => $categoryFee,
            'level_fee' => $level->fee,
            'total_amount' => Money::decimal($totalCents),
            'validity_days' => $level->validity_days,
            'benefits' => $level->benefits,
            'created_by' => $actor->id,
        ]);

        if ($type === 'membership') {
            $member->update(['status' => 'Inactive']);
        }

        return $transaction;
    }, attempts: 3);
}

    public function recordPayment(
        int $transactionId,
        array $input,
        User $actor
    ): Payment {
        Gate::forUser($actor)->authorize('access-admin');

        if (is_string($input['external_reference'] ?? null)) {
            $input['external_reference'] = strtoupper(
                trim($input['external_reference'])
            );
        }

        if (is_string($input['idempotency_key'] ?? null)) {
            $input['idempotency_key'] = strtolower(
                trim($input['idempotency_key'])
            );
        }

        $data = Validator::make($input, [
            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:199999999.98',
            ],
            'method' => [
                'required',
                Rule::in(['cash', 'bank_transfer', 'e_wallet']),
            ],
            'external_reference' => [
                'required_unless:method,cash',
                'nullable',
                'string',
                'max:100',
            ],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $reference = $data['external_reference'] ?? null;
        $reference = $reference === '' ? null : $reference;

        try {
            return DB::transaction(function () use (
                $transactionId,
                $data,
                $reference,
                $actor
            ) {
                [, $transaction] = $this->lockTransaction($transactionId);

                $amountCents = Money::cents((string) $data['amount']);

                $existing = Payment::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $samePayment =
                        (int) $existing->membership_transaction_id
                            === (int) $transaction->id
                        && Money::cents($existing->amount) === $amountCents
                        && $existing->method === $data['method']
                        && $existing->external_reference === $reference;

                    if (! $samePayment) {
                        $this->reject(
                            'idempotency_key',
                            'This payment form has already been used. '
                            .'Reload the transaction before another payment.'
                        );
                    }

                    return $existing;
                }

                if ($transaction->status !== 'pending') {
                    $this->reject(
                        'transaction',
                        'This transaction is not accepting payments.'
                    );
                }

                $paidCents = $this->lockedPaidCents($transaction);
                $totalCents = Money::cents($transaction->total_amount);
                $balanceCents = $totalCents - $paidCents;

                if ($amountCents > $balanceCents) {
                    $this->reject(
                        'amount',
                        'The payment exceeds the remaining balance.'
                    );
                }

                if ($reference !== null) {
                    $referenceExists = Payment::query()
                        ->where('method', $data['method'])
                        ->where('external_reference', $reference)
                        ->lockForUpdate()
                        ->exists();

                    if ($referenceExists) {
                        $this->reject(
                            'external_reference',
                            'This payment reference has already been recorded.'
                        );
                    }
                }

                $payment = Payment::create([
                    'membership_transaction_id' => $transaction->id,
                    'idempotency_key' => $data['idempotency_key'],
                    'amount' => Money::decimal($amountCents),
                    'method' => $data['method'],
                    'external_reference' => $reference,
                    'received_by' => $actor->id,
                    'paid_at' => now(),
                ]);

                if ($paidCents + $amountCents === $totalCents) {
                    $transaction->update(['status' => 'paid']);
                }

                return $payment;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $this->reject(
                'payment',
                'A duplicate payment reference or submission was detected. '
                .'Reload the transaction and check its payments.'
            );
        }
    }

    public function approve(
    int $transactionId,
    User $actor
): MembershipTransaction {
    Gate::forUser($actor)->authorize('access-admin');

    return DB::transaction(function () use ($transactionId, $actor) {
        [$member, $transaction] = $this->lockTransaction($transactionId);

        if ($transaction->status === 'approved') {
            return $transaction;
        }

        $paidCents = $this->lockedPaidCents($transaction);
        $totalCents = Money::cents($transaction->total_amount);

        if (
            $transaction->status !== 'paid'
            || $paidCents !== $totalCents
        ) {
            $this->reject(
                'transaction',
                'Only a fully paid transaction can be approved.'
            );
        }

        $today = today('Asia/Manila');

        $covered = $member->membership_expires_on
            && $member->membership_expires_on->toDateString()
                >= $today->toDateString();

        if ($transaction->type === 'renewal' && $covered) {
            if (
                (int) $transaction->membership_category_id
                    !== $member->membership_category_id
                || (int) $transaction->membership_level_id
                    !== $member->membership_level_id
            ) {
                $this->reject(
                    'transaction',
                    'The renewal plan no longer matches the current membership.'
                );
            }

            $start = $member->membership_expires_on->copy()->addDay();

            $memberStart = $member->membership_started_on?->toDateString()
                ?? $today->toDateString();
        } else {
            $start = $today;
            $memberStart = $start->toDateString();
        }

        $end = $start->copy()->addDays(
            $transaction->validity_days - 1
        );

        $transaction->update([
            'status' => 'approved',
            'starts_on' => $start->toDateString(),
            'expires_on' => $end->toDateString(),
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $member->forceFill([
            'membership_category_id' => $transaction->membership_category_id,
            'membership_level_id' => $transaction->membership_level_id,
            'fee' => $transaction->level_fee,
            'validity_period' => $transaction->validity_days.' days',
            'benefits' => $transaction->benefits,
            'status' => 'Active',
            'membership_started_on' => $memberStart,
            'membership_expires_on' => $end->toDateString(),
        ])->save();

        return $transaction;
    }, attempts: 3);
}

    public function cancel(
        int $transactionId,
        User $actor
    ): MembershipTransaction {
        Gate::forUser($actor)->authorize('access-admin');

        return DB::transaction(function () use ($transactionId, $actor) {
            [, $transaction] = $this->lockTransaction($transactionId);

            if ($transaction->status === 'cancelled') {
                return $transaction;
            }

            if (
                ! in_array($transaction->status, ['pending', 'paid'], true)
                || $this->lockedPaidCents($transaction) !== 0
            ) {
                $this->reject(
                    'transaction',
                    'Only an unapproved transaction without payments '
                    .'can be cancelled.'
                );
            }

            $transaction->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            return $transaction;
        }, attempts: 3);
    }

    public function expireMemberships(): array
{
    $today = today('Asia/Manila')->toDateString();

    $counts = [
        'transactions' => 0,
        'members' => 0,
    ];

    Member::query()
        ->whereNotNull('membership_expires_on')
        ->select('id')
        ->chunkById(100, function ($members) use (
            $today,
            &$counts
        ): void {
            foreach ($members as $record) {
                $changed = DB::transaction(function () use (
                    $record,
                    $today
                ): array {
                    $member = Member::query()
                        ->lockForUpdate()
                        ->find($record->id);

                    if (! $member) {
                        return [
                            'transactions' => 0,
                            'members' => 0,
                        ];
                    }

                    $transactions = $member->transactions()
                        ->where('status', 'approved')
                        ->whereDate('expires_on', '<', $today)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($transactions as $transaction) {
                        $transaction->update([
                            'status' => 'expired',
                        ]);
                    }

                    $deactivated = 0;

                    if (
                        $member->status === 'Active'
                        && $member->membership_expires_on !== null
                        && $member->membership_expires_on
                            ->toDateString() < $today
                    ) {
                        $member->forceFill([
                            'status' => 'Inactive',
                        ])->save();

                        $deactivated = 1;
                    }

                    return [
                        'transactions' => $transactions->count(),
                        'members' => $deactivated,
                    ];
                }, attempts: 3);

                $counts['transactions'] += $changed['transactions'];
                $counts['members'] += $changed['members'];
            }
        });

    return $counts;
}

    private function lockTransaction(int $transactionId): array
    {
        $memberId = MembershipTransaction::query()
            ->whereKey($transactionId)
            ->value('member_id');

        abort_if($memberId === null, 404);

        $member = Member::query()
            ->lockForUpdate()
            ->findOrFail($memberId);

        $transaction = MembershipTransaction::query()
            ->lockForUpdate()
            ->findOrFail($transactionId);

        return [$member, $transaction];
    }

    private function lockedPaidCents(
        MembershipTransaction $transaction
    ): int {
        return $transaction->payments()
            ->lockForUpdate()
            ->get()
            ->sum(
                fn (Payment $payment) => Money::cents($payment->amount)
            );
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}