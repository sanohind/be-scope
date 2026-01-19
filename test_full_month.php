<?php

echo "=== Comparison: 3 Ways to Get Full Month Data ===\n\n";

// Option 1: /charts (default pagination)
$data1 = json_decode(file_get_contents('http://localhost:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01'), true);
echo "1. /charts (default)\n";
echo "   Items returned: " . count($data1['data']) . " / " . $data1['pagination']['total'] . "\n";
echo "   Pages: " . $data1['pagination']['last_page'] . " pages\n\n";

// Option 2: /charts/data (no pagination)
$data2 = json_decode(file_get_contents('http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01'), true);
echo "2. /charts/data (no pagination)\n";
echo "   Items returned: " . count($data2['data']) . " items\n";
echo "   All data in one response: YES\n\n";

// Option 3: /charts with per_page=31
$data3 = json_decode(file_get_contents('http://localhost:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&per_page=31'), true);
echo "3. /charts?per_page=31\n";
echo "   Items returned: " . count($data3['data']) . " / " . $data3['pagination']['total'] . "\n";
echo "   Pages: " . $data3['pagination']['last_page'] . " page(s)\n\n";

echo "=== Recommendation ===\n";
echo "For charts/graphs: Use option 2 (/charts/data)\n";
echo "For tables with pagination: Use option 1 or 3\n";
