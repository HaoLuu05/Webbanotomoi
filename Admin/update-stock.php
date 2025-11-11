<?php
include '../User/connect.php';

if (isset($_POST['product_id'], $_POST['add_quantity'])) {
    $id = intval($_POST['product_id']);
    $add = intval($_POST['add_quantity']);

    // Cập nhật số lượng tồn kho
    $query = "UPDATE products SET remain_quantity = remain_quantity + ? WHERE product_id = ?";
    $stmt = mysqli_prepare($connect, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $add, $id);
    $success = mysqli_stmt_execute($stmt);

    // Kiểm tra trạng thái hiện tại và số lượng mới
    $check_query = "SELECT remain_quantity, status FROM products WHERE product_id = ?";
    $check_stmt = mysqli_prepare($connect, $check_query);
    mysqli_stmt_bind_param($check_stmt, 'i', $id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($result);

    // Nếu trạng thái là 'soldout' và số lượng > 0 thì chuyển về 'selling'
    if ($row && $row['status'] === 'soldout' && $row['remain_quantity'] > 0) {
        $update_status_query = "UPDATE products SET status = 'selling' WHERE product_id = ?";
        $update_status_stmt = mysqli_prepare($connect, $update_status_query);
        mysqli_stmt_bind_param($update_status_stmt, 'i', $id);
        mysqli_stmt_execute($update_status_stmt);
    }

    if ($success) {
        echo json_encode(['message' => 'Cập nhật kho thành công! Nếu sản phẩm hết hàng, trạng thái đã chuyển về Đang bán.']);
    } else {
        echo json_encode(['message' => 'Lỗi khi cập nhật kho.']);
    }

    mysqli_close($connect);
}
?>