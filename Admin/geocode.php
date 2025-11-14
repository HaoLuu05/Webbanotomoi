<?php
// geocode.php
// Proxy nhẹ nhàng tới Nominatim để tránh CORS/rate-limit từ browser

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
  CURLOPT_CONNECTTIMEOUT => 6,
  CURLOPT_TIMEOUT        => 8,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTPHEADER     => [
    // NOTE: điền email dự án của bạn để “identify contact” (khuyến nghị của Nominatim)
    'User-Agent: webbanoto-geocoder/1.0 (+contact@example.com)',
    'Accept: application/json',
    'Accept-Language: vi,en;q=0.8',
  ],
]);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0) {
  http_response_code(504);
  echo json_encode(['error' => 'curl timeout']);
  exit;
}

if ($http < 200 || $http >= 300) {
  http_response_code($http);
  echo json_encode(['error' => 'upstream '.$http]);
  exit;
}

// Trả nguyên JSON array như Nominatim để JS cũ dùng được
echo $resp;
