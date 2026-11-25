@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-3xl font-bold">{{ $title }}</h1>

    <div class="flex gap-3">
        <a href="{{ route($routePrefix.'.edit', $record) }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">
            Edit
        </a>

        <a href="{{ route($routePrefix.'.index') }}"
           class="rounded-lg border px-4 py-2">
            Back
        </a>
    </div>
</div>

<div class="rounded-xl bg-white p-6 shadow">
    <dl class="grid gap-6 md:grid-cols-2">
        <div>
            <dt class="text-sm text-slate-500">Name</dt>
            <dd class="mt-1 text-lg font-semibold">{{ $record->name }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Fee</dt>
            <dd class="mt-1">
                ₱{{ number_format((float) $record->fee, 2) }}
            </dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Assigned members</dt>
            <dd class="mt-1">{{ $record->members_count }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Status</dt>
            <dd class="mt-1">
                {{ $record->is_active ? 'Active' : 'Inactive' }}
            </dd>
        </div>

        @if($isLevel)
            <div>
                <dt class="text-sm text-slate-500">Validity</dt>
                <dd class="mt-1">{{ $record->validity_days }} days</dd>
            </div>

            <div class="md:col-span-2">
                <dt class="text-sm text-slate-500">Benefits</dt>
                <dd class="mt-1 whitespace-pre-line">{{ $record->benefits ?? 'No benefits listed.' }}</dd>
            </div>
        @else
            <div>
                <dt class="text-sm text-slate-500">Category label</dt>
                <dd class="mt-1">{{ $record->level ?? 'Not provided' }}</dd>
            </div>
        @endif
    </dl>
</div>

@if($record->members_count > 0)
    <p class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
        This item is assigned to existing members.
        Edit it and clear “Available for new assignments” to deactivate it.
    </p>
@endif

<form method="POST"
      action="{{ route($routePrefix.'.destroy', $record) }}"
      onsubmit="return confirm('Permanently delete this item?')"
      class="mt-5">
    @csrf
    @method('DELETE')

    <button type="submit"
            @disabled($record->members_count > 0)
            class="rounded-lg bg-red-600 px-4 py-2 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
        Delete {{ $isLevel ? 'Level' : 'Category' }}
    </button>
</form>
@endsection