@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold">{{ $title }}</h1>
</div>

<form method="POST"
      action="{{ $record->exists
          ? route($routePrefix.'.update', $record)
          : route($routePrefix.'.store') }}"
      class="mx-auto max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow">
    @csrf

    @if($record->exists)
        @method('PUT')
    @endif

    <div>
        <label for="catalog-name" class="block font-medium">
            Name
        </label>
        <input id="catalog-name"
               name="name"
               required
               maxlength="255"
               value="{{ old('name', $record->name) }}"
               class="mt-1 w-full rounded-lg border px-3 py-2">
    </div>

    <div>
        <label for="catalog-fee" class="block font-medium">
            {{ $isLevel ? 'Membership fee' : 'Category fee' }} (₱)
        </label>
        <input id="catalog-fee"
               name="fee"
               type="number"
               required
               min="0"
               max="99999999.99"
               step="0.01"
               value="{{ old('fee', $record->fee ?? '0.00') }}"
               class="mt-1 w-full rounded-lg border px-3 py-2">
    </div>

    @if($isLevel)
        <div>
            <label for="validity-days" class="block font-medium">
                Validity in days
            </label>
            <input id="validity-days"
                   name="validity_days"
                   type="number"
                   required
                   min="1"
                   max="36500"
                   step="1"
                   value="{{ old('validity_days', $record->validity_days ?? 365) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </div>

        <div>
            <label for="benefits" class="block font-medium">
                Benefits
            </label>
            <textarea id="benefits"
                      name="benefits"
                      rows="5"
                      maxlength="2000"
                      class="mt-1 w-full rounded-lg border px-3 py-2">{{ old('benefits', $record->benefits) }}</textarea>
        </div>

        <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            Changes apply to future assignments. Existing members retain
            their recorded fee, validity, and benefits.
        </p>
    @else
        <div>
            <label for="category-label" class="block font-medium">
                Category label (optional)
            </label>
            <input id="category-label"
                   name="level"
                   maxlength="255"
                   value="{{ old('level', $record->level) }}"
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </div>
    @endif

    <input type="hidden" name="is_active" value="0">

    <label class="flex items-center gap-3">
        <input type="checkbox"
               name="is_active"
               value="1"
               @checked(old('is_active', $record->exists ? $record->is_active : true))
               class="h-4 w-4">
        <span>Available for new assignments</span>
    </label>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
                class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white">
            {{ $record->exists ? 'Save Changes' : 'Create' }}
        </button>

        <a href="{{ route($routePrefix.'.index') }}"
           class="rounded-lg border px-5 py-2">
            Cancel
        </a>
    </div>
</form>
@endsection