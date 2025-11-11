<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để tiếp tục']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

// Validate required fields
if (empty($data['full_name']) || empty($data['phone']) || empty($data['address'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
    exit();
}

// Validate phone number format
if (!preg_match('/^[0-9]{10}$/', $data['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ']);
    exit();
}

try {
    mysqli_begin_transaction($connect);

    // Update user information first
    $update_user = "UPDATE users_acc SET 
                    full_name = ?,
                    phone_num = ?,
                    address = ?
                    WHERE id = ?";

    $stmt = mysqli_prepare($connect, $update_user);
    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $data['full_name'],
        $data['phone'],
        $data['address'],
        $user_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Không thể cập nhật thông tin người dùng');
    }

    // Get cart information into an array (so we can lock product rows and re-use)
    $cart_query = "SELECT c.cart_id, ci.product_id, ci.quantity, p.price, p.car_name 
                   FROM cart c 
                   JOIN cart_items ci ON c.cart_id = ci.cart_id 
                   JOIN products p ON ci.product_id = p.product_id 
                   WHERE c.user_id = ? AND c.cart_status = 'activated'";

    $stmt = mysqli_prepare($connect, $cart_query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $cart_result = mysqli_stmt_get_result($stmt);

    $cart_items = [];
    while ($row = mysqli_fetch_assoc($cart_result)) {
        $cart_items[] = $row;
    }

    if (count($cart_items) === 0) {
        throw new Exception('Giỏ hàng của bạn đang trống');
    }

    // --- LOCK and verify stock for each product ---
    foreach ($cart_items as $ci) {
        $lock_q = "SELECT product_id, remain_quantity, status, car_name FROM products WHERE product_id = ? FOR UPDATE";
        $s = mysqli_prepare($connect, $lock_q);
        mysqli_stmt_bind_param($s, 'i', $ci['product_id']);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        $prod = mysqli_fetch_assoc($r);
        if (!$prod) {
            throw new Exception('Sản phẩm không tồn tại: ' . $ci['product_id']);
        }
        if (!in_array($prod['status'], ['selling', 'discounting'])) {
            throw new Exception('Sản phẩm "' . ($prod['car_name'] ?? $ci['product_id']) . '" hiện không bán');
        }
        if (intval($prod['remain_quantity']) < intval($ci['quantity'])) {
            throw new Exception('Không đủ tồn kho cho "' . ($prod['car_name'] ?? $ci['product_id']) . '". Còn ' . intval($prod['remain_quantity']) . ' chiếc');
        }
    }

    // Calculate total amount
    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }

    // Create new order
    $create_order = "INSERT INTO orders (user_id, order_date, shipping_address, expected_total_amount) 
                     VALUES (?, NOW(), ?, ?)";

    $stmt = mysqli_prepare($connect, $create_order);
    mysqli_stmt_bind_param($stmt, 'isd', $user_id, $data['address'], $total_amount);
    mysqli_stmt_execute($stmt);
    $order_id = mysqli_insert_id($connect);

    // Transfer items from cart to order_details + update inventory
    foreach ($cart_items as $item) {
        // Insert into order_details
        $insert_detail = "INSERT INTO order_details (order_id, product_id, quantity, price) 
                         VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($connect, $insert_detail);
        mysqli_stmt_bind_param($stmt, 'iiid', $order_id, $item['product_id'], $item['quantity'], $item['price']);
        mysqli_stmt_execute($stmt);

        // Deduct remain_quantity and increase sold_quantity
        $update_remain = "UPDATE products 
                          SET remain_quantity = GREATEST(remain_quantity - ?, 0)
                          WHERE product_id = ?";
        $stmt_remain = mysqli_prepare($connect, $update_remain);
        mysqli_stmt_bind_param($stmt_remain, 'ii', $item['quantity'], $item['product_id']);
        mysqli_stmt_execute($stmt_remain);

        $update_sold = "UPDATE products 
                        SET sold_quantity = sold_quantity + ?
                        WHERE product_id = ?";
        $stmt_sold = mysqli_prepare($connect, $update_sold);
        mysqli_stmt_bind_param($stmt_sold, 'ii', $item['quantity'], $item['product_id']);
        mysqli_stmt_execute($stmt_sold);

        // Set soldout status if remain == 0
        $check_stock = "SELECT remain_quantity FROM products WHERE product_id = ?";
        $stmt_check = mysqli_prepare($connect, $check_stock);
        mysqli_stmt_bind_param($stmt_check, 'i', $item['product_id']);
        mysqli_stmt_execute($stmt_check);
        $result_stock = mysqli_stmt_get_result($stmt_check);
        $stock = mysqli_fetch_assoc($result_stock);
        if ($stock && $stock['remain_quantity'] == 0) {
            $update_status = "UPDATE products SET status = 'soldout' WHERE product_id = ?";
            $stmt_status = mysqli_prepare($connect, $update_status);
            mysqli_stmt_bind_param($stmt_status, 'i', $item['product_id']);
            mysqli_stmt_execute($stmt_status);
        }
    }

    // After successful order creation, deactivate the cart
    $update_cart = "UPDATE cart SET cart_status = 'deactivated' 
                    WHERE user_id = ? AND cart_status = 'activated'";
    $stmt = mysqli_prepare($connect, $update_cart);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Không thể cập nhật trạng thái giỏ hàng');
    }

    mysqli_commit($connect);

    // Store order info in session for payment page
    $_SESSION['current_order_id'] = $order_id;
    $_SESSION['order_amount'] = $total_amount;

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Đã lưu thông tin và cập nhật kho hàng thành công'
    ]);

} catch (Exception $e) {
    mysqli_rollback($connect);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Có lỗi xảy ra, vui lòng thử lại'
    ]);
}

mysqli_close($connect);
?>