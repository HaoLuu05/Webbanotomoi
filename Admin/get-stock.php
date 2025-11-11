<?php
include '../User/connect.php'; // điều chỉnh đường dẫn nếu cần

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Missing product ID']);
    exit;
}

$id = intval($_GET['id']);
$sql = "SELECT remain_quantity FROM products WHERE product_id = ?";
$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['stock' => (int)$row['remain_quantity']]);
} else {
    echo json_encode(['stock' => 0]);
}

mysqli_close($connect);
?>