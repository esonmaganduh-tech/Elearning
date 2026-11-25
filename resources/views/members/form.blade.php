<form method="POST"
      action="{{ $member ? route('members.update', $member) : route('members.store') }}"
      class="space-y-6 rounded-xl bg-white p-6 shadow">
    @csrf

    @if($planLocked)
    <input type="hidden"
           name="membership_category_id"
           value="{{ $member->membership_category_id }}">

    <input type="hidden"
           name="membership_level_id"
           value="{{ $member->membership_level_id }}">

    <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
        Membership plan changes are processed through renewal transactions.
    </p>
@endif

    @if($member)
        @method('PUT')

        <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Membership number</p>
            <p class="break-all font-semibold">
                {{ $member->membership_no }}
            </p>
        </div>
    @else
        <p class="text-sm text-slate-500">
            A membership number will be generated after registration.
        </p>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        <label>
            First name
            <input name="first_name"
                   required
                   maxlength="80"
                   value="{{ old('first_name', $member?->first_name) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>

        <label>
            Last name
            <input name="last_name"
                   required
                   maxlength="80"
                   value="{{ old('last_name', $member?->last_name) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>

        <label>
            Email
            <input name="email"
                   type="email"
                   required
                   maxlength="150"
                   value="{{ old('email', $member?->email) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>

        <label>
            Phone
            <input name="phone"
                   maxlength="30"
                   value="{{ old('phone', $member?->phone) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>

        <label>
            Membership category
            <select name="membership_category_id"
                    required
                     @disabled($planLocked)
                    class="mt-1 w-full rounded-lg border px-3 py-2">
                <option value="">Select a category</option>

                @foreach($categories as $option)
                    <option value="{{ $option->id }}"
                        @selected(
                            (string) old('membership_category_id', $member?->membership_category_id)
                            === (string) $option->id
                        )>
                        {{ $option->name }}
                        {{ $option->is_active ? '' : '(inactive)' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Membership level
            <select name="membership_level_id"
                    required
                    @disabled($planLocked)
                    class="mt-1 w-full rounded-lg border px-3 py-2">
                <option value="">Select a level</option>

                @foreach($levels as $option)
                    <option value="{{ $option->id }}"
                        @selected(
                            (string) old('membership_level_id', $member?->membership_level_id)
                            === (string) $option->id
                        )>
                        {{ $option->name }}
                        — ₱{{ number_format((float) $option->fee, 2) }}
                        / {{ $option->validity_days }} days
                        {{ $option->is_active ? '' : '(inactive)' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Joined date
            <input name="joined_at"
                   type="date"
                   required
                   value="{{ old('joined_at', $member?->joined_at?->format('Y-m-d') ?? now()->toDateString()) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>

        <div>
    <p>Status</p>
    <p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 font-medium">
        {{ $member?->status ?? 'Inactive' }}
    </p>
    <p class="mt-1 text-sm text-slate-500">
        Approval of a fully paid transaction activates the membership.
    </p>
</div>
    </div>

    @if($member)
        <p class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
            Changing the membership level replaces the recorded fee,
            validity, and benefits with the selected level’s current settings.
        </p>
    @endif

    <label class="block">
        Notes
        <textarea name="notes"
                  rows="4"
                  maxlength="1000"
                  class="mt-1 w-full rounded-lg border px-3 py-2">{{ old('notes', $member?->notes) }}</textarea>
    </label>

    @if($categories->isEmpty() || $levels->isEmpty())
        <p class="text-sm text-red-700">
            An available category and level are required to register a member.
        </p>
    @endif

    <div class="flex flex-wrap gap-3">
        <button type="submit"
                @disabled($categories->isEmpty() || $levels->isEmpty())
                class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white disabled:opacity-50">
            {{ $button }}
        </button>

        <a href="{{ route('members.index') }}"
           class="rounded-lg border px-5 py-2">
            Cancel
        </a>
    </div>
</form>