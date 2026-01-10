&lt;?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\SalesAnalyticsController;

$controller = new SalesAnalyticsController();
$request = new Request();

try {
    $response = $controller->getDailyBarChartData($request);
    $data = json_decode($response->getContent(), true);
    
    echo "Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT);
    echo "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
