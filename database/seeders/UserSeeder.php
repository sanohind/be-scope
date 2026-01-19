<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default system user for development
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@scope.com',
            'password' => Hash::make('password123'),
        ]);

        // Create additional dummy users if needed
        User::create([
            'name' => 'John Doe',
            'email' => 'john@scope.com',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@scope.com',
            'password' => Hash::make('password123'),
        ]);
    }
}
