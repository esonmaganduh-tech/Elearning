<?php

namespace App\Http\Controllers;

use App\Models\MembershipApplication;
use App\Services\RegisterMembershipApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApplicationRegistrationController extends Controller
{
    public function store(
        Request $request,
        MembershipApplication $application,
        RegisterMembershipApplication $service
    ): RedirectResponse {
        Gate::authorize('access-admin');

        $member = $service->register(
            $application->id,
            $request->only([
                'first_name',
                'last_name',
                'email',
                'membership_level_id',
            ]),
            $request->user()
        );

        return redirect()
            ->route('members.show', $member)
            ->with(
                'success',
                'The member record is ready. Open the initial transaction to record payment and complete approval.'
            );
    }

    public function cancel(
        MembershipApplication $application
    ): RedirectResponse {
        Gate::authorize('access-admin');

        DB::transaction(function () use ($application): void {
            $locked = MembershipApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($locked->status === 'cancelled') {
                return;
            }

            if (
                $locked->status !== 'pending'
                || $locked->member_id !== null
            ) {
                throw ValidationException::withMessages([
                    'application' =>
                        'Only an unregistered pending application can be cancelled here. Use the member transaction screens after registration.',
                ]);
            }

            $locked->forceFill([
                'status' => 'cancelled',
            ])->save();
        }, attempts: 3);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'The pending application was cancelled.');
    }
}