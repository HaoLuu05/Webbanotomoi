<?php
declare(strict_types=1);

/**
 * add_order.php — API JSON tạo đơn hàng
 * - KHÔNG include header/footer HTML
 * - Trả về JSON duy nhất (success|message|order_id)
 * - Tự tính subtotal, VAT(10%), total
 * - Lưu shipping_address, distance, shipping_fee
 * - Transaction + cập tồn kho (remain_quantity, sold_quantity)
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-error.log');

header('Content-Type: application/json; charset=utf-8');
// Xóa mọi buffer vô tình có
while (ob_get_level()) { ob_end_clean(); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // ===== KẾT NỐI DB: dùng file bạn đã có =====
    // Từ Admin/add_order.php đi lên 1 cấp tới User/connect.php
    require_once __DIR__ . '/../User/connect.php'; // phải tạo ra $connect (mysqli)

    // --- Normalize DB handle to $connect (không tạo file mới) ---
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== LẤY & VALIDATE INPUT =====
    $shipping_address  = trim((string)($_POST['shipping_address_hidden'] ?? $_POST['shipping_address'] ?? ''));
    $user_id           = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $payment_method_id = isset($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 0;
    $distance          = isset($_POST['distance']) ? (float)$_POST['distance'] : 0.0;
    $shipping_fee      = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;

    $product_ids = $_POST['product_id'] ?? [];
    $prices      = $_POST['price']      ?? [];
    $qtys        = $_POST['qty']        ?? [];

    if ($user_id <= 0)                 throw new RuntimeException('Missing user.');
    if ($payment_method_id <= 0)       throw new RuntimeException('Missing payment method.');
    if ($shipping_address === '')      throw new RuntimeException('Missing shipping address.');
    if (!is_array($product_ids) || !count($product_ids)) throw new RuntimeException('No products.');
    if (!is_array($prices) || !is_array($qtys))          throw new RuntimeException('Invalid items array.');

    if (count($product_ids) !== count($prices) || count($product_ids) !== count($qtys)) {
        throw new RuntimeException('Items array length mismatch.');
    }

    // ===== CHECK CUSTOMER TỒN TẠI TRONG users_acc =====
    // Chỉ cho phép tạo đơn với user đã có trong bảng users_acc và đang activated
    $chkUser = $connect->prepare("
        SELECT id, username 
        FROM users_acc 
        WHERE id = ? AND status = 'activated'
        LIMIT 1
    ");
    $chkUser->bind_param('i', $user_id);
    $chkUser->execute();
    $userRs = $chkUser->get_result();
    $chkUser->close();

    if (!$userRs->num_rows) {
        throw new RuntimeException('Customer not found or not activated.');
    }

    // ===== LÀM SẠCH ITEMS =====
    $items = [];
    for ($i = 0; $i < count($product_ids); $i++) {
        $pid = (int)$product_ids[$i];
        $qty = (int)$qtys[$i];
        $prc = (float)$prices[$i];
        if ($pid <= 0 || $qty <= 0 || $prc < 0) continue;
        $items[] = ['product_id' => $pid, 'qty' => $qty, 'price' => $prc];
    }
    if (!$items) throw new RuntimeException('No valid items.');

    // ===== TÍNH TIỀN PHÍA SERVER =====
    $items_subtotal = 0.0;
    foreach ($items as $it) {
        $items_subtotal += $it['qty'] * $it['price'];
    }
    $expected_total_amount = round($items_subtotal, 2);     // chưa VAT/ship
    $vat   = round($expected_total_amount * 0.10, 2);
    $ship  = round($shipping_fee, 2);
    $total_amount = round($expected_total_amount + $vat + $ship, 2);

    // ===== TRANSACTION =====
    $connect->begin_transaction();

    // (Optional) kiểm tra tồn kho + tồn tại product
    // => đảm bảo product_id đều có trong bảng products và không bị hidden
    $stockStmt = $connect->prepare("
        SELECT remain_quantity 
        FROM products 
        WHERE product_id = ? 
          AND (status IS NULL OR status <> 'hidden')
        LIMIT 1
    ");
    foreach ($items as $it) {
        $stockStmt->bind_param('i', $it['product_id']);
        $stockStmt->execute();
        $rs = $stockStmt->get_result();
        if (!$rs->num_rows) {
            throw new RuntimeException('Product not found or hidden: ID ' . $it['product_id']);
        }
        $remain = (int)$rs->fetch_assoc()['remain_quantity'];
        if ($remain > 0 && $it['qty'] > $remain) {
            throw new RuntimeException('Insufficient stock for product ID ' . $it['product_id']);
        }
    }
    $stockStmt->close();

    // ===== INSERT orders =====
    $status = 'initiated';
    $insOrder = $connect->prepare("
        INSERT INTO orders
        (user_id, order_status, payment_method_id, shipping_address, distance, shipping_fee,
         expected_total_amount, VAT, total_amount, order_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    // i = int, s = string, d = double/float
    // user_id(i), status(s), payment_method_id(i), shipping_address(s),
    // distance(d), shipping_fee(d), expected_total_amount(d), VAT(d), total_amount(d)
    $insOrder->bind_param(
        'isisddddd',
        $user_id,
        $status,
        $payment_method_id,
        $shipping_address,
        $distance,
        $ship,
        $expected_total_amount,
        $vat,
        $total_amount
    );
    $insOrder->execute();
    $order_id = (int)$connect->insert_id;
    $insOrder->close();

    // ===== INSERT order_details =====
    $insDetail = $connect->prepare("
        INSERT INTO order_details (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $it) {
        $insDetail->bind_param('iiid', $order_id, $it['product_id'], $it['qty'], $it['price']);
        $insDetail->execute();
    }
    $insDetail->close();

    // ===== CẬP TỒN KHO =====
    $updProd = $connect->prepare("
        UPDATE products
        SET remain_quantity = GREATEST(remain_quantity - ?, 0),
            sold_quantity   = COALESCE(sold_quantity, 0) + ?
        WHERE product_id = ?
    ");
    foreach ($items as $it) {
        $updProd->bind_param('iii', $it['qty'], $it['qty'], $it['product_id']);
        $updProd->execute();
    }
    $updProd->close();

    // Auto mark soldout
    $connect->query("
        UPDATE products 
        SET status='soldout' 
        WHERE remain_quantity <= 0 
          AND (status IS NULL OR status <> 'soldout')
    ");

    $connect->commit();

    echo json_encode(['success'=>true, 'order_id'=>$order_id, 'message'=>'Order created'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Rollback an toàn
    if (isset($connect) && $connect instanceof mysqli) {
        try { $connect->rollback(); } catch (\Throwable $ignored) {}
    }
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}