<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->create([
            'name' => 'Demo User',
            'email' => config('seed.user.email'),
            'password' => config('seed.user.password'),
        ]);
    }
}
