<?php
// Admin/payment_methods_api.php
session_start();
require_once __DIR__ . '/../User/connect.php'; // đường dẫn từ Admin → User

header('Content-Type: application/json; charset=utf-8');

// Chỉ cho admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
  exit;
}

// Xác nhận & alias kết nối để IDE hiểu đúng kiểu
/** @var mysqli $connect */
if (!isset($connect) || !($connect instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['success'=>false, 'message'=>'DB connection not initialized']);
  exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
/** @var mysqli $db */
$db = $connect;

$action = $_GET['action'] ?? '';

try {
  if ($action === 'list') {
    $rs = $db->query(
        "SELECT payment_method_id, method_name, description, is_active
        FROM payment_methods
        ORDER BY method_name"
    );
    $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
    }


  if ($action === 'create') {
    $name = trim($_POST['method_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') throw new Exception('Method name is required.');

    $stmt = $db->prepare(
      "INSERT INTO payment_methods(method_name, description) VALUES(?, ?)"
    );
    $stmt->bind_param('ss', $name, $desc);
    $stmt->execute();
    $newId = $db->insert_id;
    $stmt->close();

    echo json_encode(['success'=>true, 'id'=>$newId]);
    exit;
  }

  if ($action === 'update') {
    $id   = (int)($_POST['payment_method_id'] ?? 0);
    $name = trim($_POST['method_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($id<=0 || $name==='') throw new Exception('Invalid data.');

    $stmt = $db->prepare(
      "UPDATE payment_methods SET method_name=?, description=? WHERE payment_method_id=?"
    );
    $stmt->bind_param('ssi', $name, $desc, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success'=>true]);
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['payment_method_id'] ?? 0);
    if ($id<=0) throw new Exception('Invalid id.');

    // Chặn xóa nếu đã dùng trong orders
    $stmt = $db->prepare(
      "SELECT COUNT(*) FROM orders WHERE payment_method_id=?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    if ($cnt > 0) {
      throw new Exception('Phương thức đã được sử dụng trong đơn hàng, không thể xóa.');
    }

    // Xóa
    $stmt = $db->prepare(
      "DELETE FROM payment_methods WHERE payment_method_id=?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success'=>true]);
    exit;
  }

  if (($_GET['action'] ?? '') === 'toggle') {
    $id = intval($_POST['payment_method_id'] ?? 0);
    $active = intval($_POST['is_active'] ?? 0) ? 1 : 0;
    $stmt = $connect->prepare("UPDATE payment_methods SET is_active=? WHERE payment_method_id=?");
    $stmt->bind_param('ii', $active, $id);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    exit;
}

  throw new Exception('Unknown action');
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
