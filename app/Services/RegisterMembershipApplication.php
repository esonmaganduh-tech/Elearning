<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterMembershipApplication
{
    public function register(
        int $applicationId,
        array $input,
        User $actor
    ): Member {
        Gate::forUser($actor)->authorize('access-admin');

        try {
            return DB::transaction(function () use (
                $applicationId,
                $input,
                $actor
            ): Member {
                $application = MembershipApplication::query()
                    ->lockForUpdate()
                    ->findOrFail($applicationId);

                // A repeated request returns the existing member.
                if ($application->member_id !== null) {
                    return Member::findOrFail($application->member_id);
                }

                if ($application->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'application' =>
                            'Only pending applications can be registered.',
                    ]);
                }

                $data = Validator::make($input, [
                    'first_name' => ['required', 'string', 'max:80'],
                    'last_name' => ['required', 'string', 'max:80'],
                    'email' => [
                        'required',
                        'email',
                        'max:150',
                        Rule::unique('members', 'email'),
                    ],
                    'membership_level_id' => [
                        'required',
                        'integer',
                        Rule::exists('membership_levels', 'id')
                            ->where('is_active', true),
                    ],
                ])->validate();

                $category = MembershipCategory::query()
                    ->whereKey($application->membership_category_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $category) {
                    throw ValidationException::withMessages([
                        'application' =>
                            'The application category is inactive. Review the category before registering.',
                    ]);
                }

                $level = MembershipLevel::query()
                    ->whereKey($data['membership_level_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $level) {
                    throw ValidationException::withMessages([
                        'membership_level_id' =>
                            'Please select an active membership level.',
                    ]);
                }

                $member = Member::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $application->phone,
                    'membership_category_id' => $category->id,
                    'membership_level_id' => $level->id,
                    ...$level->terms(),
                    'status' => 'Inactive',
                    'joined_at' => today('Asia/Manila')->toDateString(),
                    'notes' => 'Registered from application '
                        .$application->reference_no,
                ]);

                // Reuse the existing financial workflow.
                app(MembershipBilling::class)->createInitial(
                    $member->id,
                    $actor
                );

                $application->forceFill([
                    'member_id' => $member->id,
                    'registered_by' => $actor->id,
                    'registered_at' => now(),
                    'status' => 'registered',
                ])->save();

                return $member;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (
                str_contains(
                    $exception->getMessage(),
                    'members_email_unique'
                )
                || str_contains(
                    $exception->getMessage(),
                    'members.email'
                )
            ) {
                throw ValidationException::withMessages([
                    'email' =>
                        'A member already uses this email. Review that member record before registering another member.',
                ]);
            }

            throw $exception;
        }
    }
}