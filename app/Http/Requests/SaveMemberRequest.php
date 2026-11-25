<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    public function rules(): array
    {
        $member = $this->route('member');

        $categoryRule = Rule::exists('membership_categories', 'id');
        $levelRule = Rule::exists('membership_levels', 'id');

        if (
            ! $member ||
            (int) $this->input('membership_category_id')
                !== $member->membership_category_id
        ) {
            $categoryRule->where('is_active', true);
        }

        if (
            ! $member ||
            (int) $this->input('membership_level_id')
                !== $member->membership_level_id
        ) {
            $levelRule->where('is_active', true);
        }

        $planLocked = $member
            && $member->transactions()
                ->where('status', '!=', 'cancelled')
                ->exists();

        if ($planLocked) {
            $categoryRule = Rule::in([$member->membership_category_id]);
            $levelRule = Rule::in([$member->membership_level_id]);
        }

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('members', 'email')->ignore($member),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'membership_category_id' => [
                'required',
                'integer',
                $categoryRule,
            ],
            'membership_level_id' => [
                'required',
                'integer',
                $levelRule,
            ],
            'joined_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'status' => ['exclude'],
            'membership_no' => ['missing'],
            'fee' => ['missing'],
            'validity_period' => ['missing'],
            'benefits' => ['missing'],
            'membership_started_on' => ['missing'],
            'membership_expires_on' => ['missing'],
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_category_id' => 'membership category',
            'membership_level_id' => 'membership level',
        ];
    }

    public function messages(): array
    {
        return [
            'membership_category_id.in' =>
                'Use a renewal to change an established membership plan.',
            'membership_level_id.in' =>
                'Use a renewal to change an established membership plan.',
        ];
    }
}