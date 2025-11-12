<?php
// Admin/add_order.php
session_start();
require_once __DIR__ . '/../User/connect.php';
header('Content-Type: application/json');

try {
  // (Tuỳ dự án) kiểm quyền admin
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    throw new Exception('Unauthorized');
  }

  if (!isset($connect) || !($connect instanceof mysqli)) {
    throw new Exception('DB connection not initialized');
  }
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  /** @var mysqli $db */
  $db = $connect;

  // --- Lấy FormData
  $user_id = (int)($_POST['user_id'] ?? 0);
  $pm_id   = (int)($_POST['payment_method_id'] ?? 0);
  $pids    = $_POST['product_id'] ?? [];
  $qties   = $_POST['qty'] ?? [];
  $prices  = $_POST['price'] ?? [];

  // --- Gom items hợp lệ
  $items = [];
  for ($i = 0; $i < count($pids); $i++) {
    $pid   = (int)$pids[$i];
    $qty   = (int)($qties[$i] ?? 0);
    $price = (float)($prices[$i] ?? 0);
    if ($pid > 0 && $qty > 0) {
      $items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price];
    }
  }
  if ($user_id <= 0 || $pm_id <= 0 || empty($items)) {
    throw new Exception('Missing data');
  }

  // --- Tính tổng
  $total = 0.0;
  foreach ($items as $it) $total += $it['price'] * $it['qty'];

  // --- Transaction
  $db->begin_transaction();

  // 1) Tạo order và LẤY shipping_address từ users_acc theo user_id
  //    (map cột theo schema hiện tại của bạn)
  $sqlOrder = "
    INSERT INTO orders
      (user_id, order_date, total_amount, shipping_address, payment_method_id, order_status)
    SELECT
      ?,       NOW(),     ?,            u.address,        ?,                 'is pending'
    FROM users_acc u
    WHERE u.id = ?
  ";
  $stmt = $db->prepare($sqlOrder);
  // i d i i  => user_id(int), total(double), payment_method_id(int), user_id(int - cho SELECT)
  $stmt->bind_param('idii', $user_id, $total, $pm_id, $user_id);
  $stmt->execute();

  // Nếu user_id không tồn tại -> 0 row inserted
  if ($stmt->affected_rows !== 1) {
    throw new Exception('User not found to fetch shipping address');
  }

  $order_id = $db->insert_id;
  $stmt->close();

  // 2) order_details + cập nhật tồn kho an toàn
  $stmDet = $db->prepare(
    "INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?,?,?,?)"
  );
  $stmUpd = $db->prepare(
    "UPDATE products
       SET remain_quantity = remain_quantity - ?, 
           sold_quantity   = sold_quantity + ?
     WHERE product_id = ? AND remain_quantity >= ?"
  );

  foreach ($items as $it) {
    $pid = $it['pid']; 
    $qty = $it['qty']; 
    $price = $it['price'];

    $stmDet->bind_param('iiid', $order_id, $pid, $qty, $price);
    $stmDet->execute();

    $stmUpd->bind_param('iiii', $qty, $qty, $pid, $qty);
    $stmUpd->execute();

    if ($stmUpd->affected_rows === 0) {
      throw new Exception("Out of stock for product #$pid");
    }
  }
  $stmDet->close();
  $stmUpd->close();

  // 3) Commit
  $db->commit();
  echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Throwable $e) {
  // Rollback an toàn
  if (isset($db) && $db instanceof mysqli) {
    try { $db->rollback(); } catch (Throwable $ignore) {}
  }
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
