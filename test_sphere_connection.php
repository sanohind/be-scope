<?php

// Simple test script to verify Sphere database connection
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing Sphere Database Connection...\n";
    echo "=====================================\n\n";

    // Test 1: Database connection
    echo "1. Testing database connection...\n";
    $pdo = DB::connection('sphere')->getPdo();
    echo "   ✓ Database connection successful!\n\n";

    // Test 2: Count users
    echo "2. Counting users in Sphere database...\n";
    $userCount = \App\Models\External\SphereUser::count();
    echo "   ✓ Total users: $userCount\n\n";

    // Test 3: Get a sample user with relations
    echo "3. Loading sample user with role and department...\n";
    $sampleUser = \App\Models\External\SphereUser::with(['role', 'department'])
        ->active()
        ->first();

    if ($sampleUser) {
        echo "   ✓ Sample User:\n";
        echo "     - Name: {$sampleUser->name}\n";
        echo "     - Email: {$sampleUser->email}\n";
        echo "     - Username: {$sampleUser->username}\n";
        if ($sampleUser->role) {
            echo "     - Role: {$sampleUser->role->name} ({$sampleUser->role->slug})\n";
        }
        if ($sampleUser->department) {
            echo "     - Department: {$sampleUser->department->name} ({$sampleUser->department->code})\n";
        }
    } else {
        echo "   ! No active users found\n";
    }

    echo "\n=====================================\n";
    echo "All tests passed successfully! ✓\n";

} catch (\Exception $e) {
    echo "\n❌ Error occurred:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    exit(1);
}
