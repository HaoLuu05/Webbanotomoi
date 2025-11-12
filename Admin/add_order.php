<?php
// Admin/add_order.php
session_start();
require_once __DIR__ . '/../User/connect.php';
header('Content-Type: application/json');

try {
  // ===== Kiểm quyền admin =====
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    throw new Exception('Unauthorized');
  }

  if (!isset($connect) || !($connect instanceof mysqli)) {
    throw new Exception('DB connection not initialized');
  }

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $db = $connect;

  // ===== Lấy dữ liệu từ form =====
  $user_id = (int)($_POST['user_id'] ?? 0);
  $pm_id   = (int)($_POST['payment_method_id'] ?? 0);
  $pids    = $_POST['product_id'] ?? [];
  $qties   = $_POST['qty'] ?? [];

  // Phí ship mặc định = 0 (nếu sau này có thì truyền lên form)
  $shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;

  // Gom các item hợp lệ
  $items = [];
  $n = is_countable($pids) ? count($pids) : 0;
  for ($i = 0; $i < $n; $i++) {
    $pid = (int)($pids[$i] ?? 0);
    $qty = (int)($qties[$i] ?? 0);
    if ($pid > 0 && $qty > 0) {
      $items[] = ['pid' => $pid, 'qty' => $qty];
    }
  }
  if ($user_id <= 0 || $pm_id <= 0 || empty($items)) {
    throw new Exception('Missing or invalid input');
  }

  // ===== Kiểm tra payment method còn hoạt động =====
  $pm_check = $db->prepare("SELECT COUNT(*) FROM payment_methods WHERE payment_method_id=? AND is_active=1");
  $pm_check->bind_param('i', $pm_id);
  $pm_check->execute();
  $pm_check->bind_result($active);
  $pm_check->fetch();
  $pm_check->close();
  if ($active == 0) {
    throw new Exception('Selected payment method is inactive');
  }

  // ===== Transaction =====
  $db->begin_transaction();

  // ===== Lấy thông tin giá hiện tại và tính subtotal =====
  $subtotal = 0.0;
  $details = [];
  $qPrice = $db->prepare("SELECT price, remain_quantity FROM products WHERE product_id=? FOR UPDATE");

  foreach ($items as $it) {
    $pid = $it['pid'];
    $qty = $it['qty'];

    $qPrice->bind_param('i', $pid);
    $qPrice->execute();
    $qPrice->bind_result($price, $remain);
    if (!$qPrice->fetch()) {
      throw new Exception("Product #$pid not found");
    }
    $qPrice->free_result();

    if ($remain < $qty) {
      throw new Exception("Not enough stock for product #$pid (remain: $remain)");
    }

    $subtotal += $price * $qty;
    $details[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price];
  }
  $qPrice->close();

  // ===== Tính thuế và tổng =====
  $vat   = round($subtotal * 0.10);
  $total = $subtotal + $vat + $shipping_fee;

  // ===== Tạo đơn hàng =====
  $sqlOrder = "
    INSERT INTO orders
      (user_id, order_date,
       expected_total_amount, VAT, shipping_fee, total_amount,
       shipping_address, payment_method_id, order_status)
    SELECT
      ?, NOW(),
      ?, ?, ?, ?,
      u.address, ?, 'is pending'
    FROM users_acc u
    WHERE u.id = ?
  ";
  $stmt = $db->prepare($sqlOrder);
  $stmt->bind_param(
    'iddddii',
    $user_id, $subtotal, $vat, $shipping_fee, $total, $pm_id, $user_id
  );
  $stmt->execute();
  if ($stmt->affected_rows !== 1) {
    throw new Exception('Failed to insert order');
  }
  $order_id = $db->insert_id;
  $stmt->close();

  // ===== Chèn chi tiết đơn và cập nhật tồn kho =====
  $insDet = $db->prepare(
    "INSERT INTO order_details (order_id, product_id, quantity, price)
     VALUES (?,?,?,?)"
  );
  $updPro = $db->prepare(
    "UPDATE products
       SET remain_quantity = remain_quantity - ?,
           sold_quantity = sold_quantity + ?,
           status = CASE WHEN remain_quantity - ? <= 0 THEN 'soldout' ELSE status END
     WHERE product_id = ?"
  );

  foreach ($details as $d) {
    $insDet->bind_param('iiid', $order_id, $d['pid'], $d['qty'], $d['price']);
    $insDet->execute();

    $updPro->bind_param('iiii', $d['qty'], $d['qty'], $d['qty'], $d['pid']);
    $updPro->execute();
  }

  $insDet->close();
  $updPro->close();

  // ===== Commit =====
  $db->commit();

  echo json_encode([
    'success' => true,
    'order_id' => $order_id
  ]);

} catch (Throwable $e) {
  if (isset($db) && $db instanceof mysqli) {
    try { $db->rollback(); } catch (Throwable $ignore) {}
  }
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}
