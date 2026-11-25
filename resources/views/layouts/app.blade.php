<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Membership Management' }} | Eimerson E-Learning</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 text-slate-800">
<nav class="bg-indigo-700 text-white shadow">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4">
        <a href="{{ route('home') }}" class="text-xl font-bold">
            Eimerson E-Learning
        </a>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-sm">
            <a href="{{ route('membership.create') }}"
               class="hover:text-indigo-200">
                Apply
            </a>

            @can('access-admin')
                <a href="{{ route('dashboard') }}" class="hover:text-indigo-200">
                    Dashboard
                </a>

                <a href="{{ route('members.index') }}" class="hover:text-indigo-200">
                    Members
                </a>

                <a href="{{ route('categories.index') }}" class="hover:text-indigo-200">
                    Categories
                </a>

                <a href="{{ route('levels.index') }}" class="hover:text-indigo-200">
                    Levels
                </a>

                <a href="{{ route('applications.index') }}" class="hover:text-indigo-200">
                    Applications
                </a>

                <a href="{{ route('transactions.index') }}" class="hover:text-indigo-200">
                    Transactions
                </a>

                <a href="{{ route('audit-logs.index') }}" class="hover:text-indigo-200">
                    Audit history
                </a>
            @endcan

            @auth
                <span class="text-indigo-100">
                    {{ auth()->user()->name }}
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button class="rounded border border-indigo-300 px-3 py-1">
                        Log out
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hover:text-indigo-200">
                    Staff access
                </a>
            @endauth
        </div>
    </div>
</nav>

<main class="mx-auto max-w-7xl px-6 py-8">
    @if(session('success'))
        <div class="mb-6 rounded-lg bg-emerald-100 px-4 py-3 text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-lg bg-red-100 px-4 py-3 text-red-800">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>