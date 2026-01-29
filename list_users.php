<?php

// Script to check available users in Sphere database
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Available Users in Sphere Database\n";
echo "===================================\n\n";

$users = \App\Models\External\SphereUser::with(['role', 'department'])
    ->active()
    ->take(10)
    ->get();

foreach ($users as $user) {
    echo "User ID: {$user->id}\n";
    echo "  Name: {$user->name}\n";
    echo "  Email: {$user->email}\n";
    echo "  Username: {$user->username}\n";
    if ($user->role) {
        echo "  Role: {$user->role->name} ({$user->role->slug})\n";
    }
    if ($user->department) {
        echo "  Department: {$user->department->name} ({$user->department->code})\n";
    }
    echo "\n";
}

echo "\nNote: Use any of these usernames/emails with the correct password to test authentication.\n";
echo "You can get a token by logging in through the Sphere frontend at http://localhost:3000\n";
