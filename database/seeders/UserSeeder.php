<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create superadmin
        User::create([
            'username' => 'admin',
            'name' => 'Administrator',
            'email' => 'admin@scope.local',
            'password' => Hash::make('admin123'), // Change this in production!
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        // Create regular user
        User::create([
            'username' => 'user',
            'name' => 'Regular User',
            'email' => 'user@scope.local',
            'password' => Hash::make('user123'), // Change this in production!
            'role' => 'user',
            'is_active' => true,
        ]);
    }
}
