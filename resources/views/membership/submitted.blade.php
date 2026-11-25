@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
    <h1 class="text-2xl font-bold text-emerald-800">
        Application received
    </h1>

    <p class="mt-4 text-slate-700">
        Your application has been saved with pending status.
        Please keep this reference number:
    </p>

    <div class="mt-4 break-all rounded-lg bg-slate-100 p-4 font-mono text-lg">
        {{ $reference }}
    </div>

    <p class="mt-4 text-slate-600">
        An acknowledgement has been scheduled for email processing.
        Our staff will review your application.
    </p>

    <a href="{{ route('membership.create') }}"
       class="mt-6 inline-block rounded-lg bg-indigo-700 px-4 py-2 text-white">
        Submit another application
    </a>
</div>
@endsection