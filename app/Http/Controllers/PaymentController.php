<?php

namespace App\Http\Controllers;

use App\Models\MembershipTransaction;
use App\Services\MembershipBilling;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(
        Request $request,
        MembershipTransaction $transaction,
        MembershipBilling $billing
    ) {
        $payment = $billing->recordPayment(
            $transaction->id,
            $request->only([
                'amount',
                'method',
                'external_reference',
                'idempotency_key',
            ]),
            $request->user()
        );

        return redirect()
            ->route('transactions.show', $transaction)
            ->with(
                'success',
                'Payment confirmed: '.$payment->reference_no
            );
    }
}