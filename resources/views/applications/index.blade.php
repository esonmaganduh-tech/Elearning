@extends('layouts.app')

@section('content')
<div class="rounded-xl bg-white p-6 shadow">
    <h1 class="text-2xl font-bold">Membership applications</h1>

    <form method="GET"
          action="{{ route('applications.index') }}"
          class="my-6 flex flex-wrap gap-3">
        <input name="q"
               value="{{ $search }}"
               maxlength="150"
               placeholder="Search reference, name, or email"
               class="min-w-0 flex-1 rounded-lg border border-slate-300 p-3">

        <button class="rounded-lg bg-indigo-700 px-5 py-3 text-white">
            Search
        </button>

        <a href="{{ route('applications.index') }}"
           class="rounded-lg border border-slate-300 px-5 py-3">
            Clear
        </a>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50">
                <tr>
                    <th class="p-3">Reference</th>
                    <th class="p-3">Applicant</th>
                    <th class="p-3">Category</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Submitted</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($applications as $application)
                    <tr class="border-b">
                        <td class="p-3 font-mono">
                            {{ $application->reference_no }}
                        </td>

                        <td class="p-3">
                            <div class="font-medium">
                                {{ $application->full_name }}
                            </div>

                            <div class="text-slate-600">
                                {{ $application->email }}
                            </div>
                        </td>

                        <td class="p-3">
                            {{ $application->membershipCategory->name }}
                        </td>

                        <td class="p-3">
                            {{ ucfirst($application->status) }}
                        </td>

                        <td class="p-3">
                            {{ $application->created_at
                                ->timezone('Asia/Manila')
                                ->format('M j, Y g:i A') }}
                        </td>

                        <td class="p-3">
                            <a href="{{ route('applications.show', $application) }}"
                               class="font-medium text-indigo-700 underline">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-slate-600">
                            No applications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $applications->links() }}
    </div>
</div>
@endsection