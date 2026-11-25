@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold">Membership Transaction</h1>
        <p class="mt-1 break-all text-sm text-slate-500">
            {{ $transaction->reference_no }}
        </p>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('members.show', $transaction->member_id) }}"
           class="rounded-lg border px-4 py-2">Member Profile</a>

        @if($canReceipt)
            <a href="{{ route('transactions.receipt', $transaction) }}"
               target="_blank"
               rel="noopener"
               class="rounded-lg bg-slate-800 px-4 py-2 text-white">
                Print Receipt
            </a>
        @endif
    </div>
</div>

<div class="rounded-xl bg-white p-6 shadow">
    <dl class="grid gap-5 md:grid-cols-2">
        @foreach([
            'Member' => $transaction->member_name,
            'Membership number' => $transaction->membership_no,
            'Type' => ucfirst($transaction->type),
            'Status' => ucfirst($transaction->status),
            'Category' => $transaction->category_name,
            'Level' => $transaction->level_name,
            'Category fee' => '₱'.$transaction->category_fee,
            'Level fee' => '₱'.$transaction->level_fee,
            'Total' => '₱'.$transaction->total_amount,
            'Paid' => '₱'.\App\Support\Money::decimal($paidCents),
            'Balance' => '₱'.\App\Support\Money::decimal($balanceCents),
            'Purchased validity' => $transaction->validity_days.' days',
            'Starts' => $transaction->starts_on?->toDateString() ?? 'On approval',
            'Expires' => $transaction->expires_on?->toDateString() ?? 'On approval',
            'Created by' => $transaction->creator->name,
            'Approved by' => $transaction->approver?->name ?? 'Awaiting approval',
        ] as $label => $value)
            <div>
                <dt class="text-sm text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 break-words font-medium">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <p class="mt-5 whitespace-pre-line">{{ $transaction->benefits }}</p>
</div>

<div class="my-6 flex flex-wrap gap-3">
    @if($transaction->status === 'paid')
        <form method="POST"
              action="{{ route('transactions.approve', $transaction) }}"
              onsubmit="return confirm('Approve this membership transaction?')">
            @csrf
            <button class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">
                Approve Membership
            </button>
        </form>
    @endif

    @if(in_array($transaction->status, ['pending', 'paid'], true) && $paidCents === 0)
        <form method="POST"
              action="{{ route('transactions.cancel', $transaction) }}"
              onsubmit="return confirm('Cancel this unpaid transaction?')">
            @csrf
            <button class="rounded-lg bg-red-600 px-4 py-2 font-semibold text-white">
                Cancel Transaction
            </button>
        </form>
    @endif
</div>

@if($transaction->status === 'pending' && $balanceCents > 0)
    <form method="POST"
          action="{{ route('payments.store', $transaction) }}"
          class="mb-6 max-w-3xl space-y-4 rounded-xl bg-white p-6 shadow">
        @csrf

        <input type="hidden"
               name="idempotency_key"
               value="{{ old('idempotency_key', $paymentKey) }}">

        <h2 class="text-xl font-bold">Record Payment</h2>
        <p class="text-sm text-slate-500">
            Record money already received for this transaction.
        </p>

        <label class="block">
            Amount
            <input name="amount"
                   type="number"
                   required
                   min="0.01"
                   step="0.01"
                   max="{{ \App\Support\Money::decimal($balanceCents) }}"
                   value="{{ old('amount', \App\Support\Money::decimal($balanceCents)) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>

        <label class="block">
            Method
            <select name="method"
                    class="mt-1 w-full rounded-lg border px-3 py-2">
                @foreach(['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'e_wallet' => 'E-wallet'] as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('method', 'cash') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            Bank or e-wallet reference
            <input name="external_reference"
                   maxlength="100"
                   value="{{ old('external_reference') }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
            <span class="text-sm text-slate-500">
                Required for bank transfers and e-wallet payments.
            </span>
        </label>

        <button class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white">
            Record Payment
        </button>
    </form>
@endif

<h2 class="mb-3 text-xl font-bold">Payment History</h2>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="min-w-full text-left">
        <thead class="bg-slate-50">
            <tr>
                @foreach(['Reference', 'Amount', 'Method', 'External reference', 'Received by', 'Date'] as $heading)
                    <th class="px-4 py-3">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($transaction->payments as $payment)
                <tr>
                    <td class="px-4 py-3 text-xs">{{ $payment->reference_no }}</td>
                    <td class="px-4 py-3">₱{{ $payment->amount }}</td>
                    <td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $payment->method)) }}</td>
                    <td class="px-4 py-3">{{ $payment->external_reference ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $payment->receiver->name }}</td>
                    <td class="px-4 py-3">{{ $payment->paid_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                        {{ $totalCents === 0 ? 'No payment is required.' : 'No payments recorded.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection