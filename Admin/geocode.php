<?php
// geocode.php
// Proxy nhẹ nhàng tới Nominatim để tránh CORS từ browser

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '' || mb_strlen($q) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Query too short']);
    exit;
}

$base = 'https://nominatim.openstreetmap.org/search';
$params = http_build_query([
    'q'              => $q,
    'format'         => 'json',
    'limit'          => 1,
    'countrycodes'   => 'vn',
    'addressdetails' => 0,
]);

$ch = curl_init($base.'?'.$params);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,   // rút ngắn một chút
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_FOLLOWLOCATION => true,
    // Nhiều bản XAMPP/Windows không có CA bundle -> verify SSL fail
    // Dev local thì có thể tắt verify cho đỡ lỗi (production thì nên bật lại).
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER     => [
        // Nên dùng email thật của bạn ở đây
        'User-Agent: webbanoto-geocoder/1.0 (+your-email@example.com)',
        'Accept: application/json',
        'Accept-Language: vi,en;q=0.8',
    ],
]);

$resp  = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Nếu curl lỗi (timeout, SSL, DNS, ...), trả lỗi rõ ràng
if ($errno !== 0) {
    http_response_code(502);
    echo json_encode([
        'error' => 'curl error '.$errno.' - '.$error,
    ]);
    exit;
}

// Upstream trả mã lỗi HTTP
if ($http < 200 || $http >= 300) {
    http_response_code($http);
    echo json_encode([
        'error' => 'upstream http '.$http,
    ]);
    exit;
}

// Trả nguyên JSON array như Nominatim để JS dùng được
echo $resp;
