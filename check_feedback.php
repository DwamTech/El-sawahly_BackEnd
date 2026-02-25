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

echo "--- Test 1: Submit Suggestion ---\n";
$data1 = [
    'name' => 'Ahmed Visitor',
    'email' => 'ahmed@example.com',
    'message' => 'I suggest adding a dark mode.',
    'type' => 'suggestion',
];
$res1 = makeRequest($baseUrl.'/feedback', 'POST', $data1);
print_r($res1);

echo "\n--- Test 2: Submit Complaint ---\n";
$data2 = [
    'name' => 'Angry User',
    'email' => 'angry@example.com',
    'message' => 'The site is too slow!',
    'type' => 'complaint',
];
$res2 = makeRequest($baseUrl.'/feedback', 'POST', $data2);
print_r($res2);

echo "\n--- Test 3: List All Feedback (Admin) ---\n";
$res3 = makeRequest($baseUrl.'/admin/feedback', 'GET');
print_r($res3);

echo "\n--- Test 4: Filter Complaints Only ---\n";
$res4 = makeRequest($baseUrl.'/admin/feedback?type=complaint', 'GET');
$data = $res4['body']['data'] ?? [];
echo 'Count: '.count($data)."\n";
if (count($data) > 0) {
    echo 'Type of first item: '.($data[0]['type'] ?? 'unknown')."\n";
}
