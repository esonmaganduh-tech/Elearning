@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="mt-1 text-slate-600">
            Membership activity and recorded payments.
        </p>
    </div>

    <a href="{{ route('applications.index') }}"
       class="rounded-lg bg-indigo-700 px-4 py-2 text-white">
        Review applications
    </a>
</div>

@php
    $cards = [
        ['label' => 'Total members', 'value' => $stats['total_members']],
        ['label' => 'Active members', 'value' => $stats['active_members']],
        ['label' => 'Pending applications', 'value' => $stats['pending_applications']],
        ['label' => 'Pending payment', 'value' => $stats['pending_transactions']],
        ['label' => 'Awaiting approval', 'value' => $stats['awaiting_approval']],
        ['label' => 'Collected payments', 'value' => '₱'.$stats['collected']],
    ];
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($cards as $card)
        <div class="rounded-xl bg-white p-6 shadow">
            <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
            <p class="mt-2 break-all text-3xl font-bold">
                {{ $card['value'] }}
            </p>
        </div>
    @endforeach
</div>

<p class="mt-3 text-sm text-slate-500">
    Collected payments include partial payments already received.
</p>

<div class="mt-8 rounded-xl bg-white p-6 shadow">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Recent transactions</h2>

        <a href="{{ route('transactions.index') }}"
           class="text-indigo-700 underline">
            View all transactions
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50">
                <tr>
                    <th class="p-3">Reference</th>
                    <th class="p-3">Member</th>
                    <th class="p-3">Type</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Amount due</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($recentTransactions as $transaction)
                    <tr class="border-b">
                        <td class="p-3 font-mono">
                            {{ $transaction->reference_no }}
                        </td>

                        <td class="p-3">
                            {{ $transaction->member_name }}
                        </td>

                        <td class="p-3">
                            {{ ucfirst($transaction->type) }}
                        </td>

                        <td class="p-3">
                            {{ ucfirst($transaction->status) }}
                        </td>

                        <td class="p-3">
                            ₱{{ $transaction->total_amount }}
                        </td>

                        <td class="p-3">
                            <a href="{{ route('transactions.show', $transaction) }}"
                               class="text-indigo-700 underline">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"
                            class="p-6 text-center text-slate-500">
                            No transactions recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection