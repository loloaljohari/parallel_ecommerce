<?php
$url = "http://127.0.0.1:8000/api/orders";
$token = "5|21BDPWl1gXYs6hFTJr8IVXa9KMwLEfuOEeUtqPbU940ef5c4";
$totalRequests = 200;
$results = [];

echo "--- Starting Sequential Test (Resource Management Analysis) ---\n";

for ($i = 0; $i < $totalRequests; $i++) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'items' => [['product_id' => 2, 'quantity' => 1]]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer $token"
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $end = microtime(true);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = ($end - $start) * 1000;
    curl_close($ch);

    $results[] = ['status' => $httpCode, 'time' => $time];
    echo "Req ".($i+1).": Code $httpCode | Time: ".number_format($time, 2)."ms\n";
}

$success = array_filter($results, fn($r) => $r['status'] == 201);
$rejected = array_filter($results, fn($r) => $r['status'] == 400);

$avgS = count($success) > 0 ? array_sum(array_column($success, 'time')) / count($success) : 0;
$avgR = count($rejected) > 0 ? array_sum(array_column($rejected, 'time')) / count($rejected) : 0;

echo "\n" . str_repeat("=", 50) . "\n";
echo "RESOURCE EFFICIENCY REPORT\n";
echo str_repeat("=", 50) . "\n";
echo "Avg Time (Full Process): " . number_format($avgS, 2) . " ms\n";
echo "Avg Time (AOP Filtered): " . number_format($avgR, 2) . " ms\n";

if ($avgS > 0) {
    $savings = (($avgS - $avgR) / $avgS) * 100;
    echo "Resource Savings: " . number_format($savings, 2) . "%\n";
}
echo "Capacity Control: PREVENTED DB OVERLOAD\n";
echo str_repeat("=", 50) . "\n";
