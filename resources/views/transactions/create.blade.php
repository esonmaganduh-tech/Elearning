@extends('layouts.app')

@section('content')
<h1 class="mb-2 text-3xl font-bold">
    {{ $type === 'renewal' ? 'Renew Membership' : 'New Membership Transaction' }}
</h1>

<p class="mb-6 text-slate-500">
    {{ $member->full_name }} — {{ $member->membership_no }}
</p>

<form method="POST"
      action="{{ route('transactions.store', $member) }}"
      class="max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">

    @if($planLocked)
        <input type="hidden" name="membership_category_id"
               value="{{ $member->membership_category_id }}">
        <input type="hidden" name="membership_level_id"
               value="{{ $member->membership_level_id }}">
    @endif

    <label class="block">
        Category
        <select name="membership_category_id"
                required
                @disabled($planLocked)
                class="mt-1 w-full rounded-lg border px-3 py-2">
            <option value="">Select a category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}"
                    @selected(
                        (string) old('membership_category_id', $member->membership_category_id)
                        === (string) $category->id
                    )>
                    {{ $category->name }}
                    {{ $category->is_active ? '' : '(inactive)' }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="block">
        Level
        <select name="membership_level_id"
                required
                @disabled($planLocked)
                class="mt-1 w-full rounded-lg border px-3 py-2">
            <option value="">Select a level</option>
            @foreach($levels as $level)
                <option value="{{ $level->id }}"
                    @selected(
                        (string) old('membership_level_id', $member->membership_level_id)
                        === (string) $level->id
                    )>
                    {{ $level->name }} — ₱{{ $level->fee }}
                    / {{ $level->validity_days }} days
                    {{ $level->is_active ? '' : '(inactive)' }}
                </option>
            @endforeach
        </select>
    </label>

    @if($type === 'membership')
        <div class="rounded-lg bg-slate-50 p-4">
            <p>Category fee: ₱{{ $member->membershipCategory->fee }}</p>
            <p>Level fee: ₱{{ $member->membershipLevel->fee }}</p>
            <p class="mt-2 font-semibold">Current total: ₱{{ $initialTotal }}</p>
        </div>

        <p class="text-sm text-slate-600">
            Creating this transaction makes the member inactive until
            payment is completed and the transaction is approved.
        </p>
    @else
        <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            Renewal charges the selected level fee only.
            An unexpired membership keeps its current plan and remaining days.
            Review the final quote before recording payment.
        </p>
    @endif

    @unless($available)
        <p class="text-sm text-red-700">
            An active category and level are required.
            Update the membership settings before continuing.
        </p>
    @endunless

    <div class="flex flex-wrap gap-3">
        <button @disabled(!$available)
                class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white disabled:opacity-50">
            Create Transaction
        </button>
        <a href="{{ route('members.show', $member) }}"
           class="rounded-lg border px-5 py-2">
            Back
        </a>
    </div>
</form>
@endsection