@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold">
            {{ $title }}
        </h1>

        <p class="mt-1 text-slate-500">
            {{ $isLevel ? 'Manage membership levels, fees, validity, and benefits.' : 'Manage membership categories and fees.' }}
        </p>
    </div>

    <a href="{{ route($routePrefix . '.create') }}"
       class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">
        + Add {{ $isLevel ? 'Level' : 'Category' }}
    </a>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="min-w-full text-left">
        <thead class="bg-slate-50 text-sm text-slate-500">
            <tr>
                <th scope="col" class="px-5 py-3">
                    Name
                </th>

                <th scope="col" class="px-5 py-3">
                    Fee
                </th>

                @if($isLevel)
                    <th scope="col" class="px-5 py-3">
                        Validity
                    </th>

                    <th scope="col" class="px-5 py-3">
                        Benefits
                    </th>
                @else
                    <th scope="col" class="px-5 py-3">
                        Level label
                    </th>
                @endif

                <th scope="col" class="px-5 py-3">
                    Members
                </th>

                <th scope="col" class="px-5 py-3">
                    Status
                </th>

                <th scope="col" class="px-5 py-3">
                    Actions
                </th>
            </tr>
        </thead>

        <tbody class="divide-y">
            @forelse($records as $record)
                <tr>
                    <td class="px-5 py-4 font-semibold">
                        {{ $record->name }}
                    </td>

                    <td class="whitespace-nowrap px-5 py-4">
                        ₱{{ number_format((float) $record->fee, 2) }}
                    </td>

                    @if($isLevel)
                        <td class="whitespace-nowrap px-5 py-4">
                            {{ $record->validity_days }} days
                        </td>

                        <td class="px-5 py-4">
                            {{ $record->benefits ?: '—' }}
                        </td>
                    @else
                        <td class="px-5 py-4">
                            {{ $record->level ?: '—' }}
                        </td>
                    @endif

                    <td class="px-5 py-4">
                        {{ $record->members_count }}
                    </td>

                    <td class="px-5 py-4">
                        {{ $record->is_active ? 'Active' : 'Inactive' }}
                    </td>

                    <td class="whitespace-nowrap px-5 py-4">
                        <div class="flex items-center gap-3">
                            <a href="{{ route($routePrefix . '.show', $record) }}"
                               class="text-indigo-600">
                                View
                            </a>

                            <a href="{{ route($routePrefix . '.edit', $record) }}"
                               class="text-slate-600">
                                Edit
                            </a>

                            <form method="POST"
                                  action="{{ route($routePrefix . '.destroy', $record) }}"
                                  onsubmit="return window.confirm('Permanently delete this record? This cannot be undone.');">

                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        aria-label="Delete {{ $record->name }}"
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
                    <td colspan="{{ $isLevel ? 7 : 6 }}"
                        class="px-5 py-12 text-center text-slate-500">
                        {{ $isLevel ? 'No membership levels found.' : 'No membership categories found.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="px-5 py-4">
        {{ $records->links() }}
    </div>
</div>
@endsection