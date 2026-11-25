<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // This is a public application form.
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_key' => ['required', 'uuid'],

            'membership_category_id' => [
                'required',
                'integer',
                Rule::exists('membership_categories', 'id')
                    ->where('is_active', true),
            ],

            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],

            'latitude' => [
                'nullable',
                'required_with:longitude',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'required_with:latitude',
                'numeric',
                'between:-180,180',
            ],

            'privacy_consent' => ['required', 'accepted'],

            // A hidden field that ordinary applicants leave empty.
            'website' => ['prohibited'],

            // These values are controlled by the server.
            'reference_no' => ['missing'],
            'status' => ['missing'],
            'verification_sent_at' => ['missing'],
            'privacy_consented_at' => ['missing'],
            'member_id' => ['missing'],
            'registered_by' => ['missing'],
            'registered_at' => ['missing'],
            'created_at' => ['missing'],
            'updated_at' => ['missing'],
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_category_id' => 'membership category',
            'full_name' => 'full name',
            'privacy_consent' => 'privacy consent',
        ];
    }

    public function messages(): array
    {
        return [
            'membership_category_id.exists' =>
                'Please select a category that is accepting applications.',

            'privacy_consent.accepted' =>
                'Please give your consent before submitting the application.',

            'website.prohibited' =>
                'The application could not be accepted. Please reload the form.',
        ];
    }
}