<?php

namespace App\Services;

use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitMembershipApplication
{
    public function submit(array $input): MembershipApplication
    {
        return DB::transaction(function () use ($input): MembershipApplication {
            $category = MembershipCategory::query()
                ->whereKey($input['membership_category_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $category) {
                throw ValidationException::withMessages([
                    'membership_category_id' =>
                        'This category is no longer accepting applications.',
                ]);
            }

            $details = Arr::only($input, [
                'membership_category_id',
                'full_name',
                'email',
                'phone',
                'address',
                'latitude',
                'longitude',
            ]);

            $application = MembershipApplication::firstOrCreate(
                ['submission_key' => $input['submission_key']],
                [
                    ...$details,
                    'privacy_consented_at' => now(),
                    'verification_sent_at' => now(),
                ]
            );

            if ($application->wasRecentlyCreated) {
                $application->notify(
                    (new MembershipApplicationSubmitted($application))
                        ->afterCommit()
                );
            }

            return $application;
        }, attempts: 3);
    }
}