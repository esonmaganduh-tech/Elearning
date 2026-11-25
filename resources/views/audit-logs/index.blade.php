@extends('layouts.app')

@section('content')
<div class="rounded-xl bg-white p-6 shadow">
    <h1 class="text-2xl font-bold">Audit history</h1>

    <p class="mt-2 text-sm text-slate-600">
        Recorded membership activity. Contact field values,
        location coordinates, and form tokens are omitted.
    </p>

    <form method="GET"
          action="{{ route('audit-logs.index') }}"
          class="my-6 grid gap-3 md:grid-cols-4">
        <input name="q"
               value="{{ $filters['q'] ?? '' }}"
               maxlength="100"
               placeholder="Reference or staff name"
               class="rounded-lg border border-slate-300 p-3">

        <select name="type"
                class="rounded-lg border border-slate-300 p-3">
            <option value="">All record types</option>

            @foreach($subjects as $value => $label)
                <option value="{{ $value }}"
                    @selected(($filters['type'] ?? '') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <select name="action"
                class="rounded-lg border border-slate-300 p-3">
            <option value="">All actions</option>

            @foreach(['created', 'updated', 'deleted'] as $action)
                <option value="{{ $action }}"
                    @selected(($filters['action'] ?? '') === $action)>
                    {{ ucfirst($action) }}
                </option>
            @endforeach
        </select>

        <div class="flex gap-2">
            <button class="rounded-lg bg-indigo-700 px-4 py-3 text-white">
                Filter
            </button>

            <a href="{{ route('audit-logs.index') }}"
               class="rounded-lg border border-slate-300 px-4 py-3">
                Clear
            </a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50">
                <tr>
                    <th class="p-3">When</th>
                    <th class="p-3">Who</th>
                    <th class="p-3">Record</th>
                    <th class="p-3">Action</th>
                    <th class="p-3">Changes</th>
                </tr>
            </thead>

            <tbody>
                @forelse($logs as $log)
                    <tr class="border-b align-top">
                        <td class="whitespace-nowrap p-3">
                            {{ $log->created_at
                                ->timezone('Asia/Manila')
                                ->format('M j, Y g:i:s A') }}
                        </td>

                        <td class="p-3">
                            <div>{{ $log->actor_name }}</div>

                            <div class="text-xs text-slate-500">
                                {{ ucfirst($log->actor_kind) }}
                            </div>
                        </td>

                        <td class="p-3">
                            <div>
                                {{ $subjects[$log->subject_type] ?? $log->subject_type }}
                            </div>

                            <div class="break-all font-mono text-xs">
                                {{ $log->reference_no ?? '#'.$log->subject_id }}
                            </div>
                        </td>

                        <td class="p-3">
                            {{ ucfirst($log->action) }}
                        </td>

                        <td class="min-w-64 p-3">
                            <details>
                                <summary class="cursor-pointer text-indigo-700">
                                    View changes
                                </summary>

                                <p class="mt-3 text-xs text-slate-600">
                                    Fields:
                                    {{ implode(', ', $log->changed_fields ?? []) }}
                                </p>

                                <p class="mt-3 font-medium">Before</p>
                                <pre class="mt-1 overflow-x-auto rounded bg-slate-100 p-3 text-xs">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

                                <p class="mt-3 font-medium">After</p>
                                <pre class="mt-1 overflow-x-auto rounded bg-slate-100 p-3 text-xs">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"
                            class="p-6 text-center text-slate-500">
                            No matching audit entries.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
</div>
@endsection