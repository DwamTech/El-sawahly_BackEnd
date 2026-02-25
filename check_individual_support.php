<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$baseUrl = ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000') . '/api'; // Adjust if your local server is on a different port

function makeRequest($url, $method = 'GET', $data = [], $files = [])
{
    $ch = curl_init();

    // If we have files, we MUST use multipart/form-data
    if (! empty($files)) {
        foreach ($files as $key => $filePath) {
            if (file_exists($filePath)) {
                $data[$key] = new CURLFile($filePath);
            } else {
                echo "Warning: File not found: $filePath\n";
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
            $options[CURLOPT_POSTFIELDS] = $data; // cURL handles multipart boundary automatically when passing array with CURLFile
        } else {
            // For normal JSON requests (like checkStatus)
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

echo "--- Test 1: Submit Individual Support Request ---\n";

// Ensure dummy files exist for testing with VALID headers to pass MIME type validation
// Delete old files first to ensure we write new content
$dummyFiles = ['test_identity.jpg', 'test_qual.pdf', 'test_cv.pdf'];
foreach ($dummyFiles as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

// Create a valid minimal 1x1 JPEG
// Source: Minimal JPEG Hex
$jpegHeader = hex2bin('FFD8FFE000104A46494600010101004800480000FFDB004300FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFC00011080001000103012200021101031101FFC4001F0000010501010101010100000000000000000102030405060708090A0BFFDA000C03010002110311003F00BF');
file_put_contents('test_identity.jpg', $jpegHeader);

// Create valid minimal PDF
$pdfHeader = "%PDF-1.4\n%\n";
file_put_contents('test_qual.pdf', $pdfHeader);
file_put_contents('test_cv.pdf', $pdfHeader);

$requestData = [
    'full_name' => 'Ahmed Mohamed',
    'gender' => 'male',
    'nationality' => 'Saudi',
    'city' => 'Riyadh',
    'housing_type' => 'rent', // Assuming 'rent' matches, check validation if English or Arabic expected. Controller says "rent"? No, controller validation expects "required|string". Wait, 'housing_type' => 'required|string'. Let's use Arabic value if needed? Plan said Enum logic might be tricky, let's stick to what we sent or strings.
    // Controller validation: 'housing_type' => 'required|string|max:255'. It does NOT enforce enum in validation, just string.
    'identity_image_path' => 'dummy', // Will be overwritten by file
    'birth_date' => '1990-01-01',
    'identity_expiry_date' => '2030-01-01',
    'phone_number' => '0501234567',
    'whatsapp_number' => '0501234567',
    'email' => 'ahmed@example.com',
    'academic_qualification_path' => 'dummy',
    'scientific_activity' => 'Islamic Studies',
    'cv_path' => 'dummy',
    'workplace' => 'Schools',
    'support_scope' => 'full', // validation: in:full,partial
    'amount_requested' => 5000,
    'support_type' => 'Financial',
    'has_income' => 1, // true
    'income_source' => 'Part time job',
    'marital_status' => 'married', // validation: in:single,married
    'family_members_count' => 3,
    'bank_account_iban' => 'SA12345678901234567890',
    'bank_name' => 'Al Rajhi',
];

$filesData = [
    'identity_image_path' => __DIR__.'/test_identity.jpg',
    'academic_qualification_path' => __DIR__.'/test_qual.pdf',
    'cv_path' => __DIR__.'/test_cv.pdf',
];

// Note: To send files with additional data in PHP cURL, we pass the $requestData array MERGED with CURLFile objects to POSTFIELDS.
// But array keys must be flat.

$response1 = makeRequest(
    $baseUrl.'/support/individual/store',
    'POST',
    $requestData, // This function logic above needs slight tweak to merge properly if we want 'multipart/form-data'.
    // Let's rely on the function logic: "if !empty($files) -> $data[$key] = new CURLFile".
    // So we pass $requestData as 'data', and inside the function it appends CURLFiles to it, then sends the whole thing.
    $filesData
);

print_r($response1);

$requestNumber = $response1['body']['request_number'] ?? null;
$phoneNumber = $response1['body']['phone_number'] ?? '0501234567';

if (! $requestNumber) {
    echo "CRITICAL: Failed to get request number. Stopping tests.\n";
    exit;
}

echo "\n--- Test 2: Check Status (Pending) ---\n";
$response2 = makeRequest($baseUrl.'/support/individual/status', 'POST', [
    'request_number' => $requestNumber,
    'phone_number' => $phoneNumber,
]);
print_r($response2);

echo "\n--- Test 3: Admin Update Status (Simulation) ---\n";
// Connect to DB directly to update status, or use Artisan tinker?
// Since this is a PHP script in the root, we can boot valid laravel app instance or just use raw PDO/DB check if we want, OR just execute a command via shell_exec to tinker.
// Easiest is to boot Laravel app if possible, or just raw SQL manually since we know credentials from .env?
// Actually, let's try booting the app like 'test_user.php' did.

try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo "Updating request $requestNumber to 'rejected'...\n";
    $req = \App\Models\IndividualSupportRequest::where('request_number', $requestNumber)->first();
    if ($req) {
        $req->status = 'rejected';
        $req->rejection_reason = 'Files incomplete';
        $req->save();
        echo "Updated successfully in DB.\n";
    } else {
        echo "Could not find request in DB to update!\n";
    }
} catch (Exception $e) {
    echo 'Error updating DB: '.$e->getMessage()."\n";
}

echo "\n--- Test 4: Check Status (Rejected) ---\n";
$response3 = makeRequest($baseUrl.'/support/individual/status', 'POST', [
    'request_number' => $requestNumber,
    'phone_number' => $phoneNumber,
]);
print_r($response3);

// Cleanup dummy files
foreach ($dummyFiles as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}
