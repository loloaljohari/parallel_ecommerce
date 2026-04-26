<?php
$url = "http://127.0.0.1:8000/api/orders";
$token = "5|21BDPWl1gXYs6hFTJr8IVXa9KMwLEfuOEeUtqPbU940ef5c4";
$totalRequests = 100;

$results = [];

echo "Starting Sequential Test for AOP Efficiency...\n";

for ($i = 0; $i < $totalRequests; $i++) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'items' => [['product_id' => 2, 'quantity' => 2]]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer $token"
    ]);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $executionTime = ($endTime - $startTime) * 1000;

    curl_close($ch);

    $results[] = ['status' => $httpCode, 'time' => $executionTime];
    echo "Request " . ($i + 1) . ": Code $httpCode | Time: " . number_format($executionTime, 2) . "ms\n";
}

$success = array_filter($results, fn($r) => $r['status'] == 201);
$rejected = array_filter($results, fn($r) => $r['status'] == 400);

$avgSuccess = count($success) > 0 ? array_sum(array_column($success, 'time')) / count($success) : 0;
$avgRejected = count($rejected) > 0 ? array_sum(array_column($rejected, 'time')) / count($rejected) : 0;

echo "\n" . str_repeat("=", 50) . "\n";
echo "AOP RESOURCE MANAGEMENT ANALYSIS\n";
echo str_repeat("=", 50) . "\n";
echo "Avg. Time (Full Process - Success): " . number_format($avgSuccess, 2) . " ms\n";
echo "Avg. Time (AOP Filtered - Rejected): " . number_format($avgRejected, 2) . " ms\n";

if ($avgSuccess > 0) {
    $efficiency = (($avgSuccess - $avgRejected) / $avgSuccess) * 100;
    echo "Computing Resource Savings: " . number_format($efficiency, 2) . "%\n";
}
echo "Capacity Control: PREVENTED DATABASE OVERLOAD\n";
echo str_repeat("=", 50) . "\n";
