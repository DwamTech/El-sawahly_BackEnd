<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api';
$adminEmail = 'admin@example.com';
$adminPassword = 'password';

function sendRequest($method, $url, $data = [], $token = null)
{
    global $baseUrl;
    $ch = curl_init();
    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (! empty($data)) {
            // Check if has file
            $hasFile = false;
            foreach ($data as $key => $value) {
                if ($value instanceof CURLFile) {
                    $hasFile = true;
                    break;
                }
            }
            if ($hasFile) {
                $headers[] = 'Content-Type: multipart/form-data';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
    } elseif ($method === 'GET') {
        if (! empty($data)) {
            $url .= '?'.http_build_query($data);
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    curl_setopt($ch, CURLOPT_URL, $baseUrl.$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "--- 1. Login as Admin ---\n";
$loginResponse = sendRequest('POST', '/login', ['email' => $adminEmail, 'password' => $adminPassword]);
$token = $loginResponse['body']['token'] ?? null;

$token = $loginResponse['body']['token'] ?? null;

if (! $token) {
    echo "Login failed. Attempting to register admin...\n";
    $registerResponse = sendRequest('POST', '/register/admin', [
        'name' => 'Admin User',
        'email' => $adminEmail,
        'password' => $adminPassword,
        'password_confirmation' => $adminPassword,
    ]);

    if (($registerResponse['code'] == 200 || $registerResponse['code'] == 201) && isset($registerResponse['body']['token'])) {
        echo "Admin registered successfully.\n";
        $token = $registerResponse['body']['token'];
    } else {
        // Maybe user exists but password wrong? or route doesn't exist?
        // Let's try to update password via tinker or just fail
        print_r($registerResponse['body']);
        exit("Login and Registration failed.\n");
    }
}
echo "Token acquired.\n\n";

// --- Individual Support ---
echo "--- 2. List Individual Requests (Admin) ---\n";
$listResponse = sendRequest('GET', '/admin/support/individual/requests', [], $token);
echo 'Count: '.count($listResponse['body']['data'] ?? [])."\n";

if (! empty($listResponse['body']['data'])) {
    $firstId = $listResponse['body']['data'][0]['id'];
    echo "Updating status for ID: $firstId\n";

    $updateResponse = sendRequest('POST', "/admin/support/individual/requests/$firstId/update", [
        'status' => 'accepted',
    ], $token);
    echo 'Update Code: '.$updateResponse['code']."\n";
    print_r($updateResponse['body']);
}
echo "\n";

// --- Feedback ---
echo "--- 3. Delete Feedback (Admin) ---\n";
// Create dummy feedback first
sendRequest('POST', '/feedback', [
    'name' => 'To Delete',
    'email' => 'del@test.com',
    'message' => 'Delete me',
    'type' => 'suggestion',
]);
// Get list to find ID
$feedbackList = sendRequest('GET', '/admin/feedback', [], $token);
$feedbacks = $feedbackList['body']['data'] ?? [];
$lastFeedback = end($feedbacks);

if ($lastFeedback) {
    $delId = $lastFeedback['id'];
    echo "Deleting Feedback ID: $delId\n";
    $delResponse = sendRequest('DELETE', "/admin/feedback/$delId", [], $token);
    echo 'Delete Code: '.$delResponse['code']."\n";
}
echo "\n";

// --- Book Series ---
echo "--- 4. Update Book Series (Admin) ---\n";
// Create dummy series
$seriesRes = sendRequest('POST', '/admin/library/series', ['name' => 'Update Me Series'], $token);
$seriesId = $seriesRes['body']['data']['id'] ?? null;

if ($seriesId) {
    echo "Updating Series ID: $seriesId\n";
    $upSeries = sendRequest('POST', "/admin/library/series/$seriesId", [
        'name' => 'Updated Series Name',
        '_method' => 'PUT', // Laravel resource route expectation
    ], $token);
    echo 'Update Code: '.$upSeries['code']."\n";

    // Cleanup
    sendRequest('DELETE', "/admin/library/series/$seriesId", [], $token);
    echo "Deleted Series ID: $seriesId\n";
}

echo "\nDone Checking Admin CRUDs.\n";
