@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold">Member Profile</h1>
        <p class="mt-1 break-all text-slate-500">
            {{ $member->membership_no }}
        </p>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('members.edit', $member) }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">
            Edit Details
        </a>

        @if($member->application)
            <a href="{{ route('applications.show', $member->application) }}"
               class="inline-block rounded-lg border border-slate-300 px-4 py-2">
                View source application
            </a>
        @endif

        <a href="{{ route('members.index') }}"
           class="rounded-lg border px-4 py-2">
            Back
        </a>
    </div>
</div>

<div class="mb-6 flex flex-wrap gap-3">
    @if($openTransaction)
        <a href="{{ route('transactions.show', $openTransaction) }}"
           class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">
            Open Transaction
        </a>
    @else
        <a href="{{ route('transactions.create', [
            'member' => $member,
            'type' => $hasInitial ? 'renewal' : 'membership',
        ]) }}"
           class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">
            {{ $hasInitial ? 'Renew Membership' : 'New Membership Transaction' }}
        </a>
    @endif

    <a href="{{ route('transactions.index', ['member' => $member->id]) }}"
       class="rounded-lg border px-4 py-2">
        Transaction History
    </a>
</div>

<div class="rounded-xl bg-white p-6 shadow">
    <dl class="grid gap-6 md:grid-cols-2">
        @foreach([
            'Full name' => $member->full_name,
            'Email' => $member->email,
            'Phone' => $member->phone,
            'Category' => $member->membershipCategory->name,
            'Level' => $member->membershipLevel->name,
            'Status' => $member->status,
            'Joined' => $member->joined_at->format('F d, Y'),
            'Membership started' => $member->membership_started_on?->format('F d, Y') ?? 'Not recorded',
            'Membership expires' => $member->membership_expires_on?->format('F d, Y') ?? 'Not recorded',
            'Recorded fee' => '₱'.number_format((float) $member->fee, 2),
            'Recorded validity' => $member->validity_period,
        ] as $label => $value)
            <div>
                <dt class="text-sm text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 font-medium">{{ $value ?? 'Not provided' }}</dd>
            </div>
        @endforeach

        <div class="md:col-span-2">
            <dt class="text-sm text-slate-500">Recorded benefits</dt>
            <dd class="mt-1 whitespace-pre-line">{{ $member->benefits ?? 'No benefits listed.' }}</dd>
        </div>

        <div class="md:col-span-2">
            <dt class="text-sm text-slate-500">Notes</dt>
            <dd class="mt-1 whitespace-pre-line">{{ $member->notes ?? 'No notes.' }}</dd>
        </div>
    </dl>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    @if($member->status === 'Active')
        <form method="POST"
              action="{{ route('members.deactivate', $member) }}"
              onsubmit="return confirm('Deactivate this member?')">
            @csrf
            @method('PATCH')

            <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">
                Deactivate Member
            </button>
        </form>
    @endif

    <form method="POST"
          action="{{ route('members.destroy', $member) }}"
          onsubmit="return confirm('Permanently delete this member? This cannot be undone.')">
        @csrf
        @method('DELETE')

        <button @disabled($member->transactions_count > 0)
                class="rounded-lg bg-red-600 px-4 py-2 font-semibold text-white disabled:opacity-50">
            Delete Member Permanently
        </button>
    </form>
</div>
@endsection