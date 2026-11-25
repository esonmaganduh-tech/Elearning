<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt — {{ $transaction->reference_no }}</title>
    <style>
        body { font: 14px Arial, sans-serif; color: #172033; margin: 30px; }
        main { max-width: 850px; margin: auto; }
        h1 { margin-bottom: 6px; }
        .muted { color: #596579; }
        .reference { overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border-bottom: 1px solid #ddd; padding: 10px 6px; text-align: left; }
        .amount { text-align: right; white-space: nowrap; }
        button, .button { padding: 10px 14px; margin-right: 8px; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        @page { size: A4; margin: 16mm; }
    </style>
</head>
<body>
<main>
    <div class="no-print">
        <button onclick="window.print()">Print / Save PDF</button>
        <a class="button" href="{{ route('transactions.show', $transaction) }}">
            Back
        </a>
    </div>

    <h1>Eimerson E-Learning</h1>
    <h2>Membership Receipt</h2>

    <p class="reference"><strong>{{ $transaction->reference_no }}</strong></p>

    <p>
        Member: {{ $transaction->member_name }}<br>
        Membership number: {{ $transaction->membership_no }}<br>
        Category: {{ $transaction->category_name }}<br>
        Level: {{ $transaction->level_name }}<br>
        Type: {{ ucfirst($transaction->type) }}<br>
        Transaction status: {{ ucfirst($transaction->status) }}
    </p>

    <p>
        Payment status:
        @if($totalCents === 0)
            No payment required
        @elseif($balanceCents === 0)
            Fully settled
        @else
            Partially paid
        @endif
    </p>

    <table>
        <tr><td>Category fee</td><td class="amount">₱{{ $transaction->category_fee }}</td></tr>
        <tr><td>Level fee</td><td class="amount">₱{{ $transaction->level_fee }}</td></tr>
        <tr><th>Total</th><th class="amount">₱{{ $transaction->total_amount }}</th></tr>
        <tr><td>Received</td><td class="amount">₱{{ \App\Support\Money::decimal($paidCents) }}</td></tr>
        <tr><td>Outstanding balance</td><td class="amount">₱{{ \App\Support\Money::decimal($balanceCents) }}</td></tr>
    </table>

    <h3>Recorded Payments</h3>
    <table>
        <thead>
            <tr><th>Payment reference</th><th>Method / reference</th><th>Received by</th><th class="amount">Amount</th></tr>
        </thead>
        <tbody>
            @forelse($transaction->payments as $payment)
                <tr>
                    <td class="reference">{{ $payment->reference_no }}</td>
                    <td>
                        {{ ucwords(str_replace('_', ' ', $payment->method)) }}<br>
                        {{ $payment->external_reference }}
                    </td>
                    <td>{{ $payment->receiver->name }}</td>
                    <td class="amount">₱{{ $payment->amount }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No payment required.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p>
        Membership period:
        {{ $transaction->starts_on?->toDateString() ?? 'Awaiting approval' }}
        to
        {{ $transaction->expires_on?->toDateString() ?? 'Awaiting approval' }}
    </p>

    @if($transaction->approved_at)
        <p>
            Approved by {{ $transaction->approver?->name }}<br>
            {{ $transaction->approved_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}
        </p>
    @endif

    <p class="muted">
        Keep the transaction and payment references for your records.
    </p>
</main>
</body>
</html>