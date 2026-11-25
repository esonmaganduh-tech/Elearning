@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl rounded-xl bg-white p-6 shadow">
    <a href="{{ route('applications.index') }}"
       class="text-indigo-700 underline">
        Back to applications
    </a>

    <h1 class="mt-4 text-2xl font-bold">Application details</h1>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div>
            <dt class="text-sm text-slate-500">Reference number</dt>
            <dd class="break-all font-mono">{{ $application->reference_no }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Status</dt>
            <dd>{{ ucfirst($application->status) }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Full name</dt>
            <dd>{{ $application->full_name }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Category</dt>
            <dd>{{ $application->membershipCategory->name }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Email</dt>
            <dd class="break-all">{{ $application->email }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Phone</dt>
            <dd>{{ $application->phone ?? 'Not provided' }}</dd>
        </div>

        <div class="sm:col-span-2">
            <dt class="text-sm text-slate-500">Address</dt>
            <dd class="whitespace-pre-line">{{ $application->address ?? 'Not provided' }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Latitude</dt>
            <dd>{{ $application->latitude ?? 'Not provided' }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Longitude</dt>
            <dd>{{ $application->longitude ?? 'Not provided' }}</dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Notification scheduled</dt>
            <dd>
                {{ $application->verification_sent_at
                    ?->timezone('Asia/Manila')
                    ->format('M j, Y g:i A') ?? 'Not scheduled' }}
            </dd>
        </div>

        <div>
            <dt class="text-sm text-slate-500">Privacy consent recorded</dt>
            <dd>
                {{ $application->privacy_consented_at
                    ->timezone('Asia/Manila')
                    ->format('M j, Y g:i A') }}
            </dd>
        </div>
    </dl>

    <div class="mt-8 border-t border-slate-200 pt-6">
        <h2 class="text-xl font-semibold">Staff processing</h2>

        @if($application->member_id)
            <div class="mt-4 rounded-lg bg-emerald-50 p-4">
                <p>
                    This application is linked to member
                    <span class="font-mono font-medium">
                        {{ $application->member->membership_no }}
                    </span>.
                </p>

                <p class="mt-2 text-sm text-slate-600">
                    Registered by
                    {{ $application->registrar?->name ?? 'Not recorded' }}
                    on
                    {{ $application->registered_at
                        ?->timezone('Asia/Manila')
                        ->format('M j, Y g:i A') ?? 'Not recorded' }}.
                </p>

                <a href="{{ route('members.show', $application->member) }}"
                   class="mt-4 inline-block rounded-lg bg-indigo-700 px-4 py-2 text-white">
                    Open member and transaction
                </a>
            </div>
        @elseif($application->status === 'pending')
            <p class="mt-3 text-sm text-slate-600">
                Review the member details below. Registration creates an
                inactive member and an initial transaction. Payment and
                approval are completed on the transaction screen.
            </p>

            @if($application->membershipCategory->is_active && $levels->isNotEmpty())
                @php
                    $nameParts = preg_split(
                        '/\s+/',
                        trim($application->full_name),
                        2
                    );
                @endphp

                <form method="POST"
                      action="{{ route('applications.register', $application) }}"
                      class="mt-5 space-y-4">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name"
                                   class="mb-1 block font-medium">
                                Member first name
                            </label>

                            <input id="first_name"
                                   name="first_name"
                                   value="{{ old('first_name', $nameParts[0] ?? '') }}"
                                   maxlength="80"
                                   required
                                   class="w-full rounded-lg border border-slate-300 p-3">
                        </div>

                        <div>
                            <label for="last_name"
                                   class="mb-1 block font-medium">
                                Member last name
                            </label>

                            <input id="last_name"
                                   name="last_name"
                                   value="{{ old('last_name', $nameParts[1] ?? '') }}"
                                   maxlength="80"
                                   required
                                   class="w-full rounded-lg border border-slate-300 p-3">
                        </div>
                    </div>

                    <div>
                        <label for="member_email"
                               class="mb-1 block font-medium">
                            Member email
                        </label>

                        <input id="member_email"
                               type="email"
                               name="email"
                               value="{{ old('email', $application->email) }}"
                               maxlength="150"
                               required
                               class="w-full rounded-lg border border-slate-300 p-3">

                        <p class="mt-1 text-xs text-slate-500">
                            Review the name and contact details before registering.
                            The original application remains recorded above.
                        </p>
                    </div>

                    <div>
                        <label for="membership_level_id"
                               class="mb-1 block font-medium">
                            Membership level
                        </label>

                        <select id="membership_level_id"
                                name="membership_level_id"
                                required
                                class="w-full rounded-lg border border-slate-300 p-3">
                            <option value="">Select an active level</option>

                            @foreach($levels as $level)
                                <option value="{{ $level->id }}"
                                    @selected(
                                        (string) old('membership_level_id')
                                        === (string) $level->id
                                    )>
                                    {{ $level->name }}
                                    — ₱{{ $level->fee }}
                                    / {{ $level->validity_days }} days
                                </option>
                            @endforeach
                        </select>

                        <p class="mt-2 text-sm text-slate-600">
                            Current one-time category fee:
                            ₱{{ $application->membershipCategory->fee }}.
                            Review the final total on the generated transaction.
                        </p>
                    </div>

                    <button type="submit"
                            class="rounded-lg bg-indigo-700 px-5 py-3 text-white">
                        Create member and initial transaction
                    </button>
                </form>
            @else
                <p class="mt-4 rounded-lg bg-amber-100 p-4 text-amber-900">
                    Registration requires an active application category
                    and at least one active membership level.
                    Review the catalog settings first.
                </p>
            @endif

            <form method="POST"
                  action="{{ route('applications.cancel', $application) }}"
                  class="mt-6 border-t border-slate-200 pt-4">
                @csrf

                <button type="submit"
                        class="rounded-lg border border-red-300 px-4 py-2 text-red-700">
                    Cancel pending application
                </button>
            </form>
        @else
            <p class="mt-4 text-slate-600">
                This application is {{ $application->status }}
                and cannot be registered.
            </p>
        @endif
    </div>
</div>
@endsection