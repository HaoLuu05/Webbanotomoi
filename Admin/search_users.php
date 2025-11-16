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

    // Chỉ lấy user đang activated, tìm theo username / full_name / email / phone
    $sql = "
        SELECT id, username, full_name, phone_num, email
        FROM users_acc
        WHERE status = 'activated'
          AND (
                username   LIKE ?
             OR full_name  LIKE ?
             OR email      LIKE ?
             OR phone_num  LIKE ?
          )
        ORDER BY username
        LIMIT 10
    ";

    $stmt = $connect->prepare($sql);
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id'        => (int)$row['id'],
            'username'  => $row['username'],
            'full_name' => $row['full_name'],
            'phone_num' => $row['phone_num'],
            'email'     => $row['email'],
        ];
    }
    $stmt->close();

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    // Không trả lỗi chi tiết ra ngoài để tránh lộ info
    echo json_encode([], JSON_UNESCAPED_UNICODE);
<<<<<<< HEAD
}
=======
}
>>>>>>> 9e8891c346e764570cf2a26dd549167c036c9343
