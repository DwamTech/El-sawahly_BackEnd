<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Mocking some internal Laravel environment for the test script if needed,
// but since we want to test the API, we'll use CURL.

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api';
$ch = curl_init("$baseUrl/links");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: $httpCode\n";
echo "Response: $response\n";
