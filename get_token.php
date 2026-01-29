<?php

/**
 * Script to get a valid JWT token from Sphere for testing SCOPE authentication
 * 
 * Usage:
 * 1. Run this script to get a token
 * 2. Use the token to test protected endpoints in SCOPE
 */

$beSphereUrl = 'http://127.0.0.1:8000';
$scopeUrl = 'http://127.0.0.1:8005';

// Login credentials
$credentials = [
    'email' => 'superadmin@besphere.com',
    'password' => 'password123'
];

echo "Getting JWT Token from Sphere...\n";
echo "================================\n\n";

// Step 1: Login to Sphere to get token
echo "1. Logging in to Sphere...\n";
$ch = curl_init($beSphereUrl . '/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($credentials));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
    echo "   ❌ Login failed with status: $status\n";
    echo "   Response: $response\n";
    exit(1);
}

$data = json_decode($response, true);
$token = $data['data']['token'] ?? null;

if (!$token) {
    echo "   ❌ No token in response\n";
    echo "   Response: $response\n";
    exit(1);
}

echo "   ✓ Login successful!\n";
echo "   Token (first 30 chars): " . substr($token, 0, 30) . "...\n\n";

// Step 2: Test token verification with Sphere
echo "2. Verifying token with Sphere...\n";
$ch = curl_init($beSphereUrl . '/api/auth/verify-token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status === 200) {
    echo "   ✓ Token verified successfully with Sphere!\n\n";
} else {
    echo "   ❌ Token verification failed with status: $status\n";
    echo "   Response: $response\n";
    exit(1);
}

// Step 3: Test SCOPE protected endpoint
echo "3. Testing SCOPE protected endpoint (/api/test-auth)...\n";
$ch = curl_init($scopeUrl . '/api/test-auth');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $status\n";

if ($status === 200) {
    $responseData = json_decode($response, true);
    echo "   ✓ Authentication successful!\n";
    echo "\n   User Data:\n";
    echo "   - Name: " . ($responseData['user']['name'] ?? 'N/A') . "\n";
    echo "   - Email: " . ($responseData['user']['email'] ?? 'N/A') . "\n";
    echo "   - Username: " . ($responseData['user']['username'] ?? 'N/A') . "\n";

    if (!empty($responseData['user']['role'])) {
        echo "   - Role: " . $responseData['user']['role']['name'] . " (" . $responseData['user']['role']['slug'] . ")\n";
    }

    if (!empty($responseData['user']['department'])) {
        echo "   - Department: " . $responseData['user']['department']['name'] . " (" . $responseData['user']['department']['code'] . ")\n";
    }
} else {
    echo "   ❌ Authentication failed!\n";
    echo "   Response: $response\n";
    exit(1);
}

echo "\n================================\n";
echo "All tests passed successfully! ✓\n\n";
echo "You can use this token to test other protected endpoints:\n";
echo "Token: $token\n\n";
echo "Example curl command:\n";
echo "curl -X GET \"$scopeUrl/api/test-auth\" \\\n";
echo "  -H \"Authorization: Bearer $token\" \\\n";
echo "  -H \"Accept: application/json\"\n";
