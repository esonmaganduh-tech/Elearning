<?php

use App\Http\Controllers\ApplicationRegistrationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/members')->name('home');

Route::get('/membership/apply', [
    MembershipApplicationController::class,
    'create',
])->name('membership.create');

Route::post('/membership/apply', [
    MembershipApplicationController::class,
    'store',
])->middleware('throttle:10,1')
    ->name('membership.store');

Route::get('/membership/submitted', [
    MembershipApplicationController::class,
    'submitted',
])->name('membership.submitted');

Route::get('/weather', WeatherController::class)
    ->middleware('throttle:30,1')
    ->name('weather.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'can:access-admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/audit-logs', AuditLogController::class)
        ->name('audit-logs.index');

    Route::resource('members', MemberController::class);

    Route::patch(
        'members/{member}/deactivate',
        [MemberController::class, 'deactivate']
    )->name('members.deactivate');

    Route::resource('categories', CategoryController::class);

    Route::resource('levels', LevelController::class);

    Route::get(
        '/transactions',
        [TransactionController::class, 'index']
    )->name('transactions.index');

    Route::get(
        '/members/{member}/transactions/create',
        [TransactionController::class, 'create']
    )->name('transactions.create');

    Route::post(
        '/members/{member}/transactions',
        [TransactionController::class, 'store']
    )->name('transactions.store');

    Route::get(
        '/transactions/{transaction}',
        [TransactionController::class, 'show']
    )->name('transactions.show');

    Route::get(
        '/transactions/{transaction}/receipt',
        [TransactionController::class, 'receipt']
    )->name('transactions.receipt');

    Route::post(
        '/transactions/{transaction}/payments',
        [PaymentController::class, 'store']
    )->name('payments.store');

    Route::post(
        '/transactions/{transaction}/approve',
        [TransactionController::class, 'approve']
    )->name('transactions.approve');

    Route::post(
        '/transactions/{transaction}/cancel',
        [TransactionController::class, 'cancel']
    )->name('transactions.cancel');

    Route::get('/applications', [
        MembershipApplicationController::class,
        'index',
    ])->name('applications.index');

    Route::get('/applications/{application}', [
        MembershipApplicationController::class,
        'show',
    ])->name('applications.show');

    Route::post('/applications/{application}/register', [
        ApplicationRegistrationController::class,
        'store',
    ])->name('applications.register');

    Route::post('/applications/{application}/cancel', [
        ApplicationRegistrationController::class,
        'cancel',
    ])->name('applications.cancel');
});