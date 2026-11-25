@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
    <h1 class="text-2xl font-bold">Membership application</h1>

    <p class="mt-2 text-slate-600">
        Complete the form below. Our staff will review your application
        and confirm your membership level and payment requirements.
    </p>

    @if($categories->isEmpty())
        <div class="mt-6 rounded-lg bg-amber-100 p-4 text-amber-900">
            No categories are accepting applications right now.
            Please contact the membership office.
        </div>
    @else
        <form id="membership-application-form"
              method="POST"
              action="{{ route('membership.store') }}"
              class="mt-6 space-y-5">
            @csrf

            <input type="hidden"
                   name="submission_key"
                   value="{{ old('submission_key', $submissionKey) }}">

            <div>
                <label for="membership_category_id"
                       class="mb-1 block font-medium">
                    Membership category
                </label>

                <select id="membership_category_id"
                        name="membership_category_id"
                        required
                        class="w-full rounded-lg border border-slate-300 p-3">
                    <option value="">Select a category</option>

                    @foreach($categories as $category)
                        <option value="{{ $category->id }}"
                            @selected(
                                (string) old('membership_category_id')
                                === (string) $category->id
                            )>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="full_name" class="mb-1 block font-medium">
                    Full name
                </label>

                <input id="full_name"
                       name="full_name"
                       value="{{ old('full_name') }}"
                       maxlength="150"
                       autocomplete="name"
                       required
                       class="w-full rounded-lg border border-slate-300 p-3">
            </div>

            <div>
                <label for="email" class="mb-1 block font-medium">
                    Email address
                </label>

                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       maxlength="255"
                       autocomplete="email"
                       required
                       class="w-full rounded-lg border border-slate-300 p-3">
            </div>

            <div>
                <label for="phone" class="mb-1 block font-medium">
                    Phone number — optional
                </label>

                <input id="phone"
                       type="tel"
                       name="phone"
                       value="{{ old('phone') }}"
                       maxlength="30"
                       autocomplete="tel"
                       class="w-full rounded-lg border border-slate-300 p-3">
            </div>

            <div>
                <label for="address" class="mb-1 block font-medium">
                    Address — optional
                </label>

                <textarea id="address"
                          name="address"
                          rows="3"
                          maxlength="255"
                          autocomplete="street-address"
                          class="w-full rounded-lg border border-slate-300 p-3">{{ old('address') }}</textarea>
            </div>

            @include('membership._location')

            <div hidden aria-hidden="true">
                <label for="website">Leave this field empty</label>

                <input id="website"
                       name="website"
                       value=""
                       tabindex="-1"
                       autocomplete="off">
            </div>

            <label class="flex items-start gap-3 rounded-lg bg-slate-50 p-4">
                <input type="checkbox"
                       name="privacy_consent"
                       value="1"
                       required
                       @checked(old('privacy_consent'))
                       class="mt-1">

                <span class="text-sm text-slate-700">
                    I consent to the use of my contact details to process
                    this membership application. If I provide a selected
                    location, I also consent to storing its coordinates
                    for that purpose.
                </span>
            </label>

            <button type="submit"
                    class="rounded-lg bg-indigo-700 px-5 py-3 font-medium text-white hover:bg-indigo-800">
                Submit application
            </button>
        </form>
    @endif
</div>
@endsection