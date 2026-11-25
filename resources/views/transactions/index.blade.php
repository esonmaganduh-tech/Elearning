@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold">Transactions</h1>
        @if($selectedMember)
            <p class="mt-1 text-slate-500">
                History for {{ $selectedMember->full_name }}
            </p>
        @endif
    </div>

    <a href="{{ $selectedMember
        ? route('members.show', $selectedMember)
        : route('members.index') }}"
       class="rounded-lg border px-4 py-2">
        {{ $selectedMember ? 'Member Profile' : 'Find a Member' }}
    </a>
</div>

<form method="GET" class="mb-6 flex flex-wrap gap-3">
    @if($selectedMember)
        <input type="hidden" name="member" value="{{ $selectedMember->id }}">
    @endif

    <select name="status" class="rounded-lg border bg-white px-3 py-2">
        <option value="">All statuses</option>
        @foreach(['pending', 'paid', 'approved', 'expired', 'cancelled'] as $option)
            <option value="{{ $option }}" @selected($status === $option)>
                {{ ucfirst($option) }}
            </option>
        @endforeach
    </select>

    <button class="rounded-lg bg-slate-800 px-4 py-2 text-white">
        Filter
    </button>

    <a href="{{ route('transactions.index') }}"
       class="rounded-lg border px-4 py-2">
        All Transactions
    </a>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="min-w-full text-left">
        <thead class="bg-slate-50 text-sm text-slate-500">
            <tr>
                @foreach(['Reference', 'Member', 'Type', 'Status', 'Total', 'Paid', 'Balance', ''] as $heading)
                    <th class="px-4 py-3">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($transactions as $item)
                <tr>
                    <td class="px-4 py-4 text-xs">{{ $item->reference_no }}</td>
                    <td class="px-4 py-4">{{ $item->member_name }}</td>
                    <td class="px-4 py-4">{{ ucfirst($item->type) }}</td>
                    <td class="px-4 py-4">{{ ucfirst($item->status) }}</td>
                    <td class="px-4 py-4">₱{{ $item->total_amount }}</td>
                    <td class="px-4 py-4">₱{{ \App\Support\Money::decimal($item->paidCents()) }}</td>
                    <td class="px-4 py-4">₱{{ \App\Support\Money::decimal($item->balanceCents()) }}</td>
                    <td class="px-4 py-4">
                        <a href="{{ route('transactions.show', $item) }}"
                           class="text-indigo-600">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                        No transactions found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-4">{{ $transactions->links() }}</div>
</div>
@endsection