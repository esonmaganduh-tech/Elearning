<?php

namespace App\Observers;

use App\Models\MembershipApplication;
use App\Models\MembershipTransaction;

class MembershipTransactionObserver
{
    public function updated(
        MembershipTransaction $transaction
    ): void {
        if (
            ! $transaction->wasChanged('status')
            || $transaction->status !== 'approved'
            || $transaction->type !== 'membership'
        ) {
            return;
        }

        $application = MembershipApplication::query()
            ->where('member_id', $transaction->member_id)
            ->where('status', 'registered')
            ->lockForUpdate()
            ->first();

        if ($application) {
            $application->forceFill([
                'status' => 'approved',
            ])->save();
        }
    }
}