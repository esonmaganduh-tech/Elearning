<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin', function (): int {
    $input = [
        'name' => trim((string) $this->ask('Administrator name')),
        'email' => Str::lower(
            trim((string) $this->ask('Administrator email'))
        ),
        'password' => $this->secret('Password'),
        'password_confirmation' => $this->secret('Confirm password'),
    ];

    $validator = Validator::make($input, [
        'name' => ['required', 'string', 'max:150'],
        'email' => [
            'required',
            'string',
            'email',
            'max:255',
            'unique:users,email',
        ],
        'password' => [
            'required',
            'string',
            'ascii',
            'min:12',
            'max:72',
            'confirmed',
        ],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $message) {
            $this->error($message);
        }

        return 1;
    }

    $data = $validator->validated();

    $user = new User();
    $user->name = $data['name'];
    $user->email = $data['email'];
    $user->password = $data['password'];
    $user->is_admin = true;
    $user->save();

    $this->info('Administrator created successfully.');

    return 0;
})->purpose('Create an administrator account');

Illuminate\Support\Facades\Schedule::command('memberships:expire')
    ->everyMinute()
    ->withoutOverlapping();