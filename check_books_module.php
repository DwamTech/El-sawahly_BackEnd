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

// 0. Setup Mock PDF
$dummyPdf = 'book.pdf';
$pdfHeader = "%PDF-1.4\n%\n";
file_put_contents($dummyPdf, $pdfHeader);

echo "--- Test 1: Create Series 'Fiqh Series' ---\n";
$seriesData = [
    'name' => 'سلسلة الفقه الميسر',
    'description' => 'شرح مبسط للفقه',
];
$seriesRes = makeRequest($baseUrl.'/library/series', 'POST', $seriesData);
print_r($seriesRes);
$seriesId = $seriesRes['body']['data']['id'] ?? null;

if (! $seriesId) {
    echo "CRITICAL: Failed to create series. Exiting.\n";
    exit;
}

echo "\n--- Test 2: Add Book Part 1 (Taharah) ---\n";
$book1Data = [
    'title' => 'كتاب الطهارة',
    'description' => 'الجزء الأول من السلسلة',
    'source_type' => 'file',
    'cover_type' => 'auto',
    'author_name' => 'Sheikh Ahmed',
    'type' => 'part',
    'book_series_id' => $seriesId,
];
$book1Files = ['file_path' => __DIR__.'/'.$dummyPdf];

$book1Res = makeRequest($baseUrl.'/library/books', 'POST', $book1Data, $book1Files);
print_r($book1Res);
$book1Id = $book1Res['body']['data']['id'] ?? null;

echo "\n--- Test 3: Add Book Part 2 (Salah) ---\n";
$book2Data = [
    'title' => 'كتاب الصلاة',
    'description' => 'الجزء الثاني من السلسلة',
    'source_type' => 'link',
    'source_link' => 'https://example.com/salah',
    'cover_type' => 'auto',
    'author_name' => 'Sheikh Ahmed',
    'type' => 'part',
    'book_series_id' => $seriesId,
];
$book2Res = makeRequest($baseUrl.'/library/books', 'POST', $book2Data);
print_r($book2Res);
$book2Id = $book2Res['body']['data']['id'] ?? null;

if ($book1Id) {
    echo "\n--- Test 4: View Book 1 (Should show sibling Book 2) ---\n";
    $viewRes = makeRequest($baseUrl.'/library/books/'.$book1Id, 'GET');

    echo 'Book Title: '.$viewRes['body']['book']['title']."\n";
    echo 'Views Count: '.$viewRes['body']['book']['views_count']."\n";

    $siblings = $viewRes['body']['related_parts'] ?? [];
    echo 'Related Parts Count: '.count($siblings)."\n";
    if (count($siblings) > 0) {
        echo 'First Related: '.$siblings[0]['title']."\n";
    }

    echo "\n--- Test 5: Rate Book 1 ---\n";
    $rateRes = makeRequest($baseUrl.'/library/books/'.$book1Id.'/rate', 'POST', ['rating' => 5]);
    print_r($rateRes);

    // Rate again to check average
    $rateRes2 = makeRequest($baseUrl.'/library/books/'.$book1Id.'/rate', 'POST', ['rating' => 4]);
    print_r($rateRes2);
}

if (file_exists($dummyPdf)) {
    unlink($dummyPdf);
}
