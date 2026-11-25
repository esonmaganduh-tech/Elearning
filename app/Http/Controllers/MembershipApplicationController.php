<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMembershipApplicationRequest;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Services\SubmitMembershipApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MembershipApplicationController extends Controller
{
    public function create(): View
    {
        return view('membership.create', [
            'categories' => MembershipCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'submissionKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        StoreMembershipApplicationRequest $request,
        SubmitMembershipApplication $service
    ): RedirectResponse {
        $application = $service->submit($request->validated());

        return redirect()
            ->route('membership.submitted')
            ->with('application_reference', $application->reference_no);
    }

    public function submitted(): View|RedirectResponse
    {
        $reference = session('application_reference');

        if (! is_string($reference)) {
            return redirect()->route('membership.create');
        }

        return view('membership.submitted', [
            'reference' => $reference,
        ]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('access-admin');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $search = trim($filters['q'] ?? '');

        $applications = MembershipApplication::query()
            ->with('membershipCategory')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference_no', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('applications.index', compact(
            'applications',
            'search'
        ));
    }

    public function show(MembershipApplication $application): View
    {
        Gate::authorize('access-admin');

        $application->load([
            'membershipCategory',
            'member',
            'registrar',
        ]);

        $levels = MembershipLevel::query()
            ->where('is_active', true)
            ->orderBy('fee')
            ->get();

        return view('applications.show', compact(
            'application',
            'levels'
        ));
    }
}