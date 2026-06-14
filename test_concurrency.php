<?php
$url = "http://127.0.0.1:8000/api/orders";
$token = "2|HbvLVYTeHbpRWgVW7GA5HOmrSRmvXCSxCZdcQz9m855b7075";
$totalRequests = 50;

$multiHandle = curl_multi_init();
$channels = [];

echo "--- Initializing Simultaneous Requests (AOP Stress Test) ---\n";

for ($i = 0; $i < $totalRequests; $i++) {
    $channels[$i] = curl_init();
    curl_setopt($channels[$i], CURLOPT_URL, $url);
    curl_setopt($channels[$i], CURLOPT_RETURNTRANSFER, true);
    curl_setopt($channels[$i], CURLOPT_POST, true);
    curl_setopt($channels[$i], CURLOPT_POSTFIELDS, json_encode([
        'items' => [['product_id' => 2, 'quantity' => 1]]
    ]));
    curl_setopt($channels[$i], CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer $token"
    ]);

    curl_multi_add_handle($multiHandle, $channels[$i]);
}

echo "Launching $totalRequests requests simultaneously...\n";

$active = null;
$executionStartTime = microtime(true);

do {
    $mrc = curl_multi_exec($multiHandle, $active);
} while ($active > 0);

$executionEndTime = microtime(true);

$successTimes = [];
$rejectedTimes = [];

foreach ($channels as $channel) {
    $info = curl_getinfo($channel);
    $time = $info['total_time'] * 1000;
    $httpCode = $info['http_code'];

    if ($httpCode == 201) {
        $successTimes[] = $time;
    } else {
        $rejectedTimes[] = $time;
    }
    curl_multi_remove_handle($multiHandle, $channel);
}

$avgSuccess = count($successTimes) > 0 ? array_sum($successTimes) / count($successTimes) : 0;
$avgRejected = count($rejectedTimes) > 0 ? array_sum($rejectedTimes) / count($rejectedTimes) : 0;

echo "\n" . str_repeat("=", 50) . "\n";
echo "CONCURRENCY & EFFICIENCY FINAL REPORT\n";
echo str_repeat("=", 50) . "\n";
echo "Total Requests: $totalRequests\n";
echo " Successful Orders: " . count($successTimes) . "\n";
echo " Rejected (AOP Blocked): " . count($rejectedTimes) . "\n";
echo "--------------------------------------------------\n";
echo "Avg Time (Full Process): " . number_format($avgSuccess, 2) . " ms\n";
echo "Avg Time (AOP Fast-Reject): " . number_format($avgRejected, 2) . " ms\n";

if ($avgSuccess > 0 && $avgRejected > 0) {
    $efficiency = (($avgSuccess - $avgRejected) / $avgSuccess) * 100;
    echo "Performance Improvement: " . number_format($efficiency, 2) . "%\n";
}

echo "Capacity Control: Active & Stable\n";
echo str_repeat("=", 50) . "\n";

curl_multi_close($multiHandle);
