<?php

// Quick script to check and setup users for local auth
// Run: php setup-local-auth.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

echo "=== SCOPE Local Auth Setup ===\n\n";

// Check if users table exists
if (!Schema::hasTable('users')) {
    echo "❌ Error: users table does not exist!\n";
    echo "Please run: php artisan migrate\n";
    exit(1);
}

// Check columns
$columns = Schema::getColumnListing('users');
echo "Current columns in users table:\n";
print_r($columns);
echo "\n";

$hasUsername = in_array('username', $columns);
$hasRole = in_array('role', $columns);

if (!$hasUsername || !$hasRole) {
    echo "⚠️  Missing columns:\n";
    if (!$hasUsername) echo "  - username\n";
    if (!$hasRole) echo "  - role\n";
    echo "\nAdding missing columns...\n";
    
    // Add columns
    Schema::table('users', function ($table) use ($hasUsername, $hasRole) {
        if (!$hasUsername) {
            $table->string('username')->unique()->nullable()->after('id');
        }
        if (!$hasRole) {
            $table->string('role')->default('user')->after('password');
        }
    });
    
    echo "✅ Columns added successfully!\n\n";
}

// Check if admin user exists
$adminExists = DB::table('users')->where('username', 'admin')->exists();
$userExists = DB::table('users')->where('username', 'user')->exists();

if ($adminExists) {
    echo "ℹ️  Admin user already exists\n";
} else {
    echo "Creating admin user...\n";
    DB::table('users')->insert([
        'username' => 'admin',
        'name' => 'Administrator',
        'email' => 'admin@scope.local',
        'password' => Hash::make('admin123'),
        'role' => 'superadmin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Admin user created!\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n\n";
}

if ($userExists) {
    echo "ℹ️  Regular user already exists\n";
} else {
    echo "Creating regular user...\n";
    DB::table('users')->insert([
        'username' => 'user',
        'name' => 'Regular User',
        'email' => 'user@scope.local',
        'password' => Hash::make('user123'),
        'role' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Regular user created!\n";
    echo "   Username: user\n";
    echo "   Password: user123\n\n";
}

echo "=== Setup Complete! ===\n";
echo "\nYou can now login with:\n";
echo "  - admin / admin123 (superadmin)\n";
echo "  - user / user123 (user)\n";
