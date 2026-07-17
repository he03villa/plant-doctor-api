<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@plantdoctor.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Expert User',
                'email' => 'expert@plantdoctor.com',
                'password' => Hash::make('password'),
                'role' => 'expert',
            ],
            [
                'name' => 'Test User',
                'email' => 'user@plantdoctor.com',
                'password' => Hash::make('password'),
                'role' => 'user',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'email_verified_at' => now(),
                ])
            );

            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
