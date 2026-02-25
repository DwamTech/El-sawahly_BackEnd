<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Mocking some internal Laravel environment for the test script if needed,
// but since we want to test the API, we'll use CURL.

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api';

function testEndpoint($url, $moduleName)
{
    echo "Testing $moduleName: ";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 403 && isset($data['message']) && str_contains($data['message'], 'عفواً')) {
        echo 'LOCKED SUCCESS - Message: '.$data['message']."\n";
    } elseif ($httpCode === 200) {
        echo "OPEN SUCCESS\n";
    } else {
        echo "UNEXPECTED - Code: $httpCode, Response: $response\n";
    }
}

echo "--- Initial State (Should be all open) ---\n";
testEndpoint("$baseUrl/articles", 'Articles');
testEndpoint("$baseUrl/library/books", 'Library');

echo "\n--- Manually locking via database (simulated) ---\n";
// We can't easily run Eloquent here without full bootstrap,
// but we can use the admin API we just updated!

function updateSetting($baseUrl, $key, $value)
{
    // Note: Admin routes need login, but for this check script we might skip or just use artisan
}

echo "Running verification via artisan command to toggle...\n";
// Since I'm the assistant, I'll use artisan tinker to toggle and then test cURL.
