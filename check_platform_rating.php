<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api';

function makeRequest($url, $method = 'GET', $data = [])
{
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $jsonData = json_encode($data);
        $options[CURLOPT_POSTFIELDS] = $jsonData;
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Accept: application/json'];
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "--- Test 1: Get Initial Stats (Should be 0) ---\n";
$res1 = makeRequest($baseUrl.'/platform-rating', 'GET');
print_r($res1);

echo "\n--- Test 2: Submit 5 Star Rating ---\n";
$res2 = makeRequest($baseUrl.'/platform-rating', 'POST', ['rating' => 5]);
print_r($res2);

echo "\n--- Test 3: Get Updated Stats (Should be 5.0, count 1) ---\n";
$res3 = makeRequest($baseUrl.'/platform-rating', 'GET');
print_r($res3);

echo "\n--- Test 4: Submit Spam Rating Immediately (Should Fail 429) ---\n";
$res4 = makeRequest($baseUrl.'/platform-rating', 'POST', ['rating' => 1]);
print_r($res4);

if (($res4['code'] ?? 0) == 429) {
    echo ">> Spam Protection Working: Request blocked as expected.\n";
} else {
    echo '>> Warning: Request was not blocked. Code: '.($res4['code'] ?? 'null')."\n";
}
