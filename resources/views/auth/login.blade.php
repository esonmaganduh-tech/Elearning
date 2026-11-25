@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Staff login</h1>
        <p class="mt-2 text-slate-500">
            Sign in with your administrator account.
        </p>
    </div>

    <form method="POST"
          action="{{ route('login.store') }}"
          class="space-y-5 rounded-xl bg-white p-6 shadow">
        @csrf

        <div>
            <label for="email" class="block font-medium">
                Email address
            </label>
            <input id="email"
                   name="email"
                   type="email"
                   value="{{ old('email') }}"
                   maxlength="255"
                   autocomplete="username"
                   required
                   autofocus
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </div>

        <div>
            <label for="password" class="block font-medium">
                Password
            </label>
            <input id="password"
                   name="password"
                   type="password"
                   maxlength="72"
                   autocomplete="current-password"
                   required
                   class="mt-1 w-full rounded-lg border px-3 py-2">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white hover:bg-indigo-700">
            Sign in
        </button>
    </form>
</div>
@endsection