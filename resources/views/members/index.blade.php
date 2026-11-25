@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold">Member Management</h1>
        <p class="mt-1 text-slate-500">
            Search and manage membership records.
        </p>
    </div>

    <a href="{{ route('members.create') }}"
       class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">
        + Add New Member
    </a>
</div>

<form method="GET"
      action="{{ route('members.index') }}"
      class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow md:grid-cols-4">

    <input name="search"
           value="{{ $search }}"
           placeholder="Name, email, or membership number"
           aria-label="Search members"
           class="rounded-lg border px-3 py-2 md:col-span-2">

    <select name="category"
            aria-label="Filter by category"
            class="rounded-lg border px-3 py-2">

        <option value="">All categories</option>

        @foreach($categories as $option)
            <option value="{{ $option->id }}"
                @selected((string) $category === (string) $option->id)>
                {{ $option->name }}
            </option>
        @endforeach
    </select>

    <select name="level"
            aria-label="Filter by level"
            class="rounded-lg border px-3 py-2">

        <option value="">All levels</option>

        @foreach($levels as $option)
            <option value="{{ $option->id }}"
                @selected((string) $level === (string) $option->id)>
                {{ $option->name }}
            </option>
        @endforeach
    </select>

    <button type="submit"
            class="rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white">
        Search Members
    </button>

    <a href="{{ route('members.index') }}"
       class="rounded-lg border px-4 py-2 text-center">
        Clear
    </a>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="min-w-full text-left">
        <thead class="bg-slate-50 text-sm text-slate-500">
            <tr>
                @foreach(['Member', 'Category', 'Level', 'Recorded fee', 'Status', 'Actions'] as $heading)
                    <th scope="col" class="px-5 py-3">
                        {{ $heading }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody class="divide-y">
            @forelse($members as $member)
                <tr>
                    <td class="px-5 py-4">
                        <div class="font-semibold">
                            {{ $member->full_name }}
                        </div>

                        <div class="text-sm text-slate-500">
                            {{ $member->email }}
                        </div>

                        <div class="mt-1 text-xs">
                            {{ $member->membership_no }}
                        </div>
                    </td>

                    <td class="px-5 py-4">
                        {{ $member->membershipCategory?->name ?? '—' }}
                    </td>

                    <td class="px-5 py-4">
                        {{ $member->membershipLevel?->name ?? '—' }}
                    </td>

                    <td class="px-5 py-4">
                        ₱{{ number_format((float) $member->fee, 2) }}
                    </td>

                    <td class="px-5 py-4">
                        {{ $member->status }}
                    </td>

                    <td class="whitespace-nowrap px-5 py-4">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('members.show', $member) }}"
                               class="text-indigo-600">
                                View
                            </a>

                            <a href="{{ route('members.edit', $member) }}"
                               class="text-slate-600">
                                Edit
                            </a>

                            <form method="POST"
                                  action="{{ route('members.destroy', $member) }}"
                                  onsubmit="return window.confirm('Permanently delete this member? This cannot be undone.');">

                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        aria-label="Delete {{ $member->full_name }}"
                                        class="rounded-lg border px-3 py-2 text-sm font-semibold"
                                        style="color: #b91c1c; border-color: #fecaca; background-color: #fef2f2; cursor: pointer;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"
                        class="px-5 py-12 text-center text-slate-500">
                        No members found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="px-5 py-4">
        {{ $members->links() }}
    </div>
</div>
@endsection