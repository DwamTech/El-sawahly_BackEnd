<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api';

function makeRequest($url, $method = 'GET', $data = [], $files = [])
{
    $ch = curl_init();

    if (! empty($files)) {
        foreach ($files as $key => $filePath) {
            if (file_exists($filePath)) {
                $data[$key] = new CURLFile($filePath);
            }
        }
    }

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        if (! empty($files)) {
            $options[CURLOPT_POSTFIELDS] = $data;
        } else {
            $jsonData = json_encode($data);
            $options[CURLOPT_POSTFIELDS] = $jsonData;
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Accept: application/json'];
        }
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error];
    }

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// 0. Setup Mock Files
$dummyFiles = ['cert.pdf', 'letter.pdf', 'proj.pdf', 'plan.pdf', 'bank.jpg'];
$pdfHeader = "%PDF-1.4\n%\n";
$jpegHeader = hex2bin('FFD8FFE000104A46494600010101004800480000FFDB004300FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFC00011080001000103012200021101031101FFC4001F0000010501010101010100000000000000000102030405060708090A0BFFDA000C03010002110311003F00BF');

foreach ($dummyFiles as $file) {
    if (str_ends_with($file, '.jpg')) {
        file_put_contents($file, $jpegHeader);
    } else {
        file_put_contents($file, $pdfHeader);
    }
}

echo "--- Test 1.1: Check Initial Settings (Should be enabled) ---\n";
$settings = makeRequest($baseUrl.'/support/settings', 'GET');
print_r($settings);

echo "\n--- Test 1.2: Submit Institutional Request (Success Scenario) ---\n";
$reqData = [
    'institution_name' => 'Charity Org',
    'license_number' => 'ORG-12345',
    'email' => 'info@charity.org',
    'phone_number' => '0501111111',
    'ceo_name' => 'Dr. CEO',
    'ceo_mobile' => '0502222222',
    'whatsapp_number' => '0503333333',
    'city' => 'Makkah',
    'activity_type' => 'social',
    'project_name' => 'Community Center',
    'project_type' => 'construction',
    'project_manager_name' => 'Eng. Manager',
    'project_manager_mobile' => '0504444444',
    'goal_1' => 'Serve community',
    'beneficiaries' => 'public',
    'project_cost' => 100000,
    'project_outputs' => 'Building',
    'support_scope' => 'partial',
    'amount_requested' => 50000,
    'account_name' => 'Charity Account',
    'bank_account_iban' => 'SA998877665544332211',
    'bank_name' => 'Alinma',
];

$files = [
    'license_certificate_path' => __DIR__.'/cert.pdf',
    'support_letter_path' => __DIR__.'/letter.pdf',
    'project_file_path' => __DIR__.'/proj.pdf',
    'operational_plan_path' => __DIR__.'/plan.pdf',
    'bank_certificate_path' => __DIR__.'/bank.jpg',
];

$res = makeRequest($baseUrl.'/support/institutional/store', 'POST', $reqData, $files);
print_r($res);
$reqNum = $res['body']['request_number'] ?? null;
$phone = $res['body']['phone_number'] ?? '0501111111';

if ($reqNum) {
    echo "\n--- Test 1.3: Check Status ---\n";
    $statusRes = makeRequest($baseUrl.'/support/institutional/status', 'POST', [
        'request_number' => $reqNum,
        'phone_number' => $phone,
    ]);
    print_r($statusRes);
}

echo "\n--- Test 2.1: Admin Disable Institutional Support ---\n";
// Note: This endpoint is protected by auth:sanctum and admin middleware.
// For simplicity in this script, we will simulate DB update directly since we are on the same server,
// unless we want to implement login flow here (which is complex).
// Direct DB update is easier for testing the "Store" endpoint logic rejection.

try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    \App\Models\SupportSetting::where('key', 'institutional_support_enabled')->update(['value' => 'false']);
    echo "Settings updated via DB to FALSE.\n";
} catch (Exception $e) {
    echo 'DB Update failed: '.$e->getMessage()."\n";
}

echo "\n--- Test 2.2: Verify Public Settings showing False ---\n";
$settingsOff = makeRequest($baseUrl.'/support/settings', 'GET');
print_r($settingsOff);

echo "\n--- Test 2.3: Submit Request (Should Fail) ---\n";
$resFail = makeRequest($baseUrl.'/support/institutional/store', 'POST', $reqData, $files);
print_r($resFail);

// Reset Settings
\App\Models\SupportSetting::where('key', 'institutional_support_enabled')->update(['value' => 'true']);
echo "\n--- Settings Reset to TRUE ---\n";

foreach ($dummyFiles as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}
