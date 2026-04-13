<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@dwam.com'],
            [
                'name'     => 'Admin',
                'email'    => 'admin@dwam.com',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        $this->command->info('Admin account ready → admin@dwam.com / password');
    }
}
