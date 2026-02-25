<?php

/**
 * Document Management System Test Script
 * Tests all endpoints for the Document Management System
 */
echo "=== Document Management System Test ===\n\n";

require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->safeLoad();

$baseUrl = ($_ENV['APP_URL'] ?? 'http://localhost:8000') . '/api';
$adminEmail = 'admin@dwam.com';
$adminPassword = 'password';

// Helper function to make HTTP requests
function makeRequest($method, $url, $data = null, $token = null, $files = [])
{
    $ch = curl_init();

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (! empty($files)) {
            // Multipart form data
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

// Create temporary test files
function createTestFiles()
{
    // Create test PDF file
    $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n>>\nendobj\n4 0 obj\n<<\n/Length 44\n>>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n(Test Document) Tj\nET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000115 00000 n\n0000000214 00000 n\ntrailer\n<<\n/Size 5\n/Root 1 0 R\n>>\nstartxref\n308\n%%EOF";

    $pdfPath = sys_get_temp_dir().'/test_document.pdf';
    file_put_contents($pdfPath, $pdfContent);

    // Create test cover image (1x1 PNG)
    $imagePath = sys_get_temp_dir().'/test_cover.png';
    $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    file_put_contents($imagePath, $imageData);

    return ['pdf' => $pdfPath, 'image' => $imagePath];
}

// Clean up test files
function cleanupTestFiles($files)
{
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

try {
    // Step 1: Admin Login
    echo "1. Admin Login...\n";
    $loginResponse = makeRequest('POST', "$baseUrl/login", [
        'email' => $adminEmail,
        'password' => $adminPassword,
    ]);

    if ($loginResponse['code'] !== 200) {
        throw new Exception('Login failed: '.$loginResponse['raw']);
    }

    $token = $loginResponse['body']['token'];
    echo "   ✓ Login successful. Token obtained.\n\n";

    // Create test files
    echo "2. Creating test files...\n";
    $testFiles = createTestFiles();
    echo "   ✓ Test files created.\n\n";

    // Step 2: Create Document (with file upload)
    echo "3. Creating new document...\n";
    $postData = [
        'title' => 'لائحة اختبار النظام',
        'description' => 'هذه لائحة تجريبية لاختبار نظام إدارة الملفات',
        'source_type' => 'file',
        'cover_type' => 'upload',
        'keywords' => json_encode(['اختبار', 'لوائح', 'نظام']),
        'file_path' => new CURLFile($testFiles['pdf'], 'application/pdf', 'test_document.pdf'),
        'cover_path' => new CURLFile($testFiles['image'], 'image/png', 'test_cover.png'),
    ];

    $createResponse = makeRequest('POST', "$baseUrl/admin/documents", $postData, $token, ['file_path', 'cover_path']);

    if ($createResponse['code'] !== 201) {
        throw new Exception('Document creation failed: '.$createResponse['raw']);
    }

    $documentId = $createResponse['body']['data']['id'];
    echo "   ✓ Document created successfully. ID: $documentId\n\n";

    // Step 3: List Documents (Public)
    echo "4. Fetching documents list...\n";
    $listResponse = makeRequest('GET', "$baseUrl/documents");

    if ($listResponse['code'] !== 200) {
        throw new Exception('Failed to fetch documents: '.$listResponse['raw']);
    }

    $documentsCount = count($listResponse['body']['data']);
    echo "   ✓ Documents list fetched. Total: $documentsCount documents\n\n";

    // Step 4: Get Document Details (Public)
    echo "5. Fetching document details...\n";
    $detailsResponse = makeRequest('GET', "$baseUrl/documents/$documentId");

    if ($detailsResponse['code'] !== 200) {
        throw new Exception('Failed to fetch document details: '.$detailsResponse['raw']);
    }

    $viewsBefore = $detailsResponse['body']['views_count'];
    echo "   ✓ Document details fetched. Views: $viewsBefore\n\n";

    // Step 5: Search Documents
    echo "6. Testing search functionality...\n";
    $searchTerm = urlencode('اختبار');
    $searchResponse = makeRequest('GET', "$baseUrl/documents?search=$searchTerm");

    if ($searchResponse['code'] !== 200) {
        throw new Exception('Search failed: '.$searchResponse['raw']);
    }

    echo "   ✓ Search completed successfully.\n\n";

    // Step 6: Download Document (increment downloads)
    echo "7. Testing download endpoint...\n";
    $downloadResponse = makeRequest('POST', "$baseUrl/documents/$documentId/download");

    if ($downloadResponse['code'] !== 200) {
        throw new Exception('Download endpoint failed: '.$downloadResponse['raw']);
    }

    echo '   ✓ Download registered. URL: '.($downloadResponse['body']['download_url'] ?? 'N/A')."\n\n";

    // Step 7: Update Document (Admin)
    echo "8. Updating document...\n";
    $updateResponse = makeRequest('PUT', "$baseUrl/admin/documents/$documentId", [
        'title' => 'لائحة اختبار النظام - محدثة',
        'description' => 'تم تحديث الوصف',
    ], $token);

    if ($updateResponse['code'] !== 200) {
        throw new Exception('Document update failed: '.$updateResponse['raw']);
    }

    echo "   ✓ Document updated successfully.\n\n";

    // Step 8: Verify Update
    echo "9. Verifying update...\n";
    $verifyResponse = makeRequest('GET', "$baseUrl/documents/$documentId");

    if ($verifyResponse['code'] !== 200 || $verifyResponse['body']['title'] !== 'لائحة اختبار النظام - محدثة') {
        throw new Exception('Update verification failed');
    }

    echo "   ✓ Update verified successfully.\n\n";

    // Step 9: Delete Document (Admin)
    echo "10. Deleting test document...\n";
    $deleteResponse = makeRequest('DELETE', "$baseUrl/admin/documents/$documentId", null, $token);

    if ($deleteResponse['code'] !== 200) {
        throw new Exception('Document deletion failed: '.$deleteResponse['raw']);
    }

    echo "   ✓ Document deleted successfully.\n\n";

    // Step 10: Verify Deletion
    echo "11. Verifying deletion...\n";
    $verifyDeleteResponse = makeRequest('GET', "$baseUrl/documents/$documentId");

    if ($verifyDeleteResponse['code'] !== 404) {
        throw new Exception('Document still exists after deletion');
    }

    echo "   ✓ Deletion verified successfully.\n\n";

    // Cleanup test files
    echo "12. Cleaning up test files...\n";
    cleanupTestFiles(array_values($testFiles));
    echo "   ✓ Test files cleaned up.\n\n";

    echo "=== ✅ ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "\n❌ TEST FAILED: ".$e->getMessage()."\n";

    // Attempt cleanup
    if (isset($testFiles)) {
        cleanupTestFiles(array_values($testFiles));
    }

    // Attempt to delete test document if it exists
    if (isset($documentId) && isset($token)) {
        makeRequest('DELETE', "$baseUrl/admin/documents/$documentId", null, $token);
    }

    exit(1);
}
