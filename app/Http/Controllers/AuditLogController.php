<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('access-admin');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => [
                'nullable',
                Rule::in(array_keys(AuditLog::SUBJECTS)),
            ],
            'action' => [
                'nullable',
                Rule::in(['created', 'updated', 'deleted']),
            ],
        ]);

        $search = trim($filters['q'] ?? '');

        $logs = AuditLog::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference_no', 'like', '%'.$search.'%')
                        ->orWhere('actor_name', 'like', '%'.$search.'%');
                });
            })
            ->when(
                $filters['type'] ?? null,
                fn ($query, $type) => $query->where('subject_type', $type)
            )
            ->when(
                $filters['action'] ?? null,
                fn ($query, $action) => $query->where('action', $action)
            )
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'subjects' => AuditLog::SUBJECTS,
        ]);
    }
}