<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api';
$adminEmail = 'admin@example.com';
$adminPassword = 'password';

// Cookie File for session/visitor tracking
$cookieFile = tempnam(sys_get_temp_dir(), 'cookie');

function sendRequest($method, $url, $data = [], $token = null)
{
    global $baseUrl, $cookieFile;
    $ch = curl_init();
    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (! empty($data)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'GET') {
        if (! empty($data)) {
            $url .= '?'.http_build_query($data);
        }
    }

    curl_setopt($ch, CURLOPT_URL, $baseUrl.$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "--- 1. Trigger Visit (Public Route) ---\n";
// Call a public route to trigger TrackVisits middleware
$visitRes = sendRequest('GET', '/visuals');
echo 'Visited /visuals. Code: '.$visitRes['code']."\n";
// Visit again to check logic (should be same visitor)
$visitRes2 = sendRequest('GET', '/visuals');
echo 'Visited /visuals again. Code: '.$visitRes2['code']."\n\n";

echo "--- 2. Login as Admin ---\n";
// Try login, if fail register
$loginResponse = sendRequest('POST', '/login', ['email' => $adminEmail, 'password' => $adminPassword]);
$token = $loginResponse['body']['token'] ?? null;

if (! $token) {
    echo "Login failed. Registering admin...\n";
    $reg = sendRequest('POST', '/register/admin', [
        'name' => 'Admin Test',
        'email' => $adminEmail,
        'password' => $adminPassword,
        'password_confirmation' => $adminPassword,
    ]);
    $token = $reg['body']['token'] ?? null;
}

if (! $token) {
    exit("Failed to get token.\n");
}
echo "Token acquired.\n\n";

echo "--- 3. Dashboard Summary ---\n";
$summary = sendRequest('GET', '/admin/dashboard/summary', [], $token);
echo 'Status: '.($summary['body']['status'] ?? 'null')."\n";
print_r($summary['body']['data'] ?? $summary['body']);
echo "\n";

echo "--- 4. Recent Requests ---\n";
$recent = sendRequest('GET', '/admin/support-requests/recent', ['limit' => 3], $token);
echo 'Count: '.count($recent['body']['data'] ?? [])."\n";
// print_r($recent['body']['data']);
echo "\n";

echo "--- 5. Analytics (Visits) ---\n";
$analytics = sendRequest('GET', '/admin/dashboard/analytics', ['period' => '7d'], $token);
echo 'Status: '.($analytics['body']['status'] ?? 'null')."\n";
print_r($analytics['body']['data'] ?? []);
echo "\n";

echo "--- 6. Unread Notifications ---\n";
$notif = sendRequest('GET', '/admin/notifications/unread-count', [], $token);
print_r($notif['body']);
echo "\nDone.\n";
