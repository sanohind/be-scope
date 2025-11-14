<?php
/**
 * Test script for Invoice Status Distribution API
 * This script tests the endpoint and shows expected URL formats
 */

echo "=== Testing Invoice Status Distribution API ===\n\n";

// Show the correct endpoint URLs
echo "Correct API Endpoints:\n";
echo "1. If using php artisan serve:\n";
echo "   http://localhost:8000/api/dashboard/sales/invoice-status-distribution\n\n";
echo "2. If using Apache/Nginx:\n";
echo "   http://localhost/api/dashboard/sales/invoice-status-distribution\n\n";
echo "3. If using custom port:\n";
echo "   http://localhost:{PORT}/api/dashboard/sales/invoice-status-distribution\n\n";

echo "Query Parameters:\n";
echo "- group_by: 'customer' | 'daily' | 'monthly' | 'yearly' (default: 'monthly')\n";
echo "- date_from: Start date (YYYY-MM-DD format)\n";
echo "- date_to: End date (YYYY-MM-DD format)\n\n";

echo "Example URLs:\n";
echo "1. Group by monthly (default):\n";
echo "   GET /api/dashboard/sales/invoice-status-distribution\n\n";
echo "2. Group by customer:\n";
echo "   GET /api/dashboard/sales/invoice-status-distribution?group_by=customer\n\n";
echo "3. Filter by date range:\n";
echo "   GET /api/dashboard/sales/invoice-status-distribution?date_from=2024-01-01&date_to=2024-12-31\n\n";
echo "4. Group by customer with date filter:\n";
echo "   GET /api/dashboard/sales/invoice-status-distribution?group_by=customer&date_from=2024-01-01&date_to=2024-12-31\n\n";

echo "Expected Response Format:\n";
echo "{\n";
echo "  \"data\": [\n";
echo "    {\n";
echo "      \"category\": \"2024-01\" or \"Customer Name\",\n";
echo "      \"invoice_status\": \"Paid\",\n";
echo "      \"count\": 150\n";
echo "    },\n";
echo "    {\n";
echo "      \"category\": \"2024-01\" or \"Customer Name\",\n";
echo "      \"invoice_status\": \"Outstanding\",\n";
echo "      \"count\": 45\n";
echo "    },\n";
echo "    ...\n";
echo "  ],\n";
echo "  \"filter_metadata\": {\n";
echo "    \"period\": \"monthly\",\n";
echo "    \"date_field\": \"invoice_date\",\n";
echo "    \"date_from\": null,\n";
echo "    \"date_to\": null\n";
echo "  }\n";
echo "}\n\n";

echo "=== Testing with cURL ===\n\n";

// Test if server is accessible
$baseUrls = [
    'http://localhost:8000/api/dashboard/sales/invoice-status-distribution',
    'http://localhost/api/dashboard/sales/invoice-status-distribution',
    'http://127.0.0.1:8000/api/dashboard/sales/invoice-status-distribution',
];

foreach ($baseUrls as $url) {
    echo "Testing URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ Connection failed: $error\n\n";
    } else if ($httpCode == 200) {
        echo "  ✅ Endpoint accessible (HTTP $httpCode)\n\n";
        // Now do a full GET request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo "  Response received:\n";
            $data = json_decode($response, true);
            if ($data) {
                echo "  " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
            } else {
                echo "  " . substr($response, 0, 500) . "\n\n";
            }
        }
        break; // Stop after first successful connection
    } else if ($httpCode == 404) {
        echo "  ❌ Endpoint returned 404 - Not Found\n";
        echo "  This usually means:\n";
        echo "  1. Route cache needs to be cleared: php artisan route:clear\n";
        echo "  2. Config cache needs to be cleared: php artisan config:clear\n";
        echo "  3. The route is not properly registered\n\n";
    } else {
        echo "  ⚠️  HTTP $httpCode received\n\n";
    }
}

echo "\n=== Troubleshooting Steps ===\n";
echo "If you're getting 404 error, try these steps:\n\n";
echo "1. Make sure the Laravel development server is running:\n";
echo "   php artisan serve\n\n";
echo "2. Clear all caches:\n";
echo "   php artisan route:clear\n";
echo "   php artisan config:clear\n";
echo "   php artisan cache:clear\n\n";
echo "3. Verify the route is registered:\n";
echo "   php artisan route:list --path=dashboard/sales\n\n";
echo "4. Check if .htaccess is configured correctly (for Apache)\n\n";
echo "5. Check if you're accessing the correct URL:\n";
echo "   - Include /api/ prefix\n";
echo "   - Use correct port number\n";
echo "   - Check if using http or https\n\n";
