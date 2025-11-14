<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-error.log');

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Kết nối DB
    require_once __DIR__ . '/../User/connect.php';

    if (!isset($connect) || !($connect instanceof mysqli)) {
        if (isset($conn) && $conn instanceof mysqli) {
            $connect = $conn;
        } elseif (isset($mysqli) && $mysqli instanceof mysqli) {
            $connect = $mysqli;
        } elseif (isset($link) && $link instanceof mysqli) {
            $connect = $link;
        } else {
            throw new RuntimeException('User/connect.php không cung cấp biến kết nối mysqli.');
        }
    }
    if (method_exists($connect, 'set_charset')) {
        $connect->set_charset('utf8mb4');
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $like = '%' . $q . '%';

    // Lấy sản phẩm đang bán (không hidden), còn hàng
    $sql = "
        SELECT 
            product_id,
            car_name,
            price,
            remain_quantity
        FROM products
        WHERE (status IS NULL OR status <> 'hidden')
          AND remain_quantity > 0
          AND car_name LIKE ?
        ORDER BY car_name
        LIMIT 15
    ";

    $stmt = $connect->prepare($sql);
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'product_id'      => (int)$row['product_id'],
            'name'            => $row['car_name'],
            'price'           => (float)$row['price'],
            'remain_quantity' => (int)$row['remain_quantity'],
        ];
    }
    $stmt->close();

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}