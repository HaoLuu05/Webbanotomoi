<?php
session_start();
include 'connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit();
}

$product_id = intval($_POST['product_id'] ?? 0);
$quantity = max(1, intval($_POST['quantity'] ?? 1));

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ']);
    exit();
}

// helper: fetch product row (optionally with FOR UPDATE by using the FOR_UPDATE flag)
function get_product_row($connect, $product_id, $for_update = false) {
    $sql = 'SELECT product_id, remain_quantity, status, car_name, price, image_link FROM products WHERE product_id = ?' . ($for_update ? ' FOR UPDATE' : '');
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

try {
    if (isset($_SESSION['user_id'])) {
        // Logged-in user: use transaction and lock product row
        $user_id = $_SESSION['user_id'];
        mysqli_begin_transaction($connect);

        // lock the product row
        $prod_res = get_product_row($connect, $product_id, true);
        $product = mysqli_fetch_assoc($prod_res);
        if (!$product) throw new Exception('Sản phẩm không tồn tại');
        if (!in_array($product['status'], ['selling', 'discounting'])) throw new Exception('Sản phẩm hiện không bán');

            // get or create cart
        $cart_query = "SELECT cart_id FROM cart WHERE user_id = ? AND cart_status = 'activated'";
        $stmt = mysqli_prepare($connect, $cart_query);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $cart_result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($cart_result)) {
            $cart_id = $row['cart_id'];
        } else {
            $create_cart = "INSERT INTO cart (user_id, cart_status) VALUES (?, 'activated')";
            $stmt = mysqli_prepare($connect, $create_cart);
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $cart_id = mysqli_insert_id($connect);
        }

           // existing qty in cart
        $check_query = "SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?";
        $stmt = mysqli_prepare($connect, $check_query);
        mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $product_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $existing_qty = 0;
        if ($r = mysqli_fetch_assoc($res)) $existing_qty = intval($r['quantity']);

        $final_qty = $existing_qty + $quantity;
        if (intval($product['remain_quantity']) < $final_qty) {
            throw new Exception('Không đủ tồn kho. Còn ' . intval($product['remain_quantity']) . ' sản phẩm');
        }
    
        if ($existing_qty > 0) {
            $update_query = "UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($connect, $update_query);
            mysqli_stmt_bind_param($stmt, 'iii', $final_qty, $cart_id, $product_id);
            mysqli_stmt_execute($stmt);
        } else {
            $insert_query = "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($connect, $insert_query);
            mysqli_stmt_bind_param($stmt, 'iii', $cart_id, $product_id, $quantity);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($connect);
        echo json_encode(['success' => true, 'message' => 'Đã thêm vào giỏ hàng']);
        exit();
    }

    // Guest users: no transaction; check remain before adding to session
    $prod_res = get_product_row($connect, $product_id, false);
    $product = mysqli_fetch_assoc($prod_res);
    if (!$product) { echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']); exit(); }
    if (!in_array($product['status'], ['selling', 'discounting'])) { echo json_encode(['success' => false, 'message' => 'Sản phẩm hiện không bán']); exit(); }

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $newQty = $item['quantity'] + $quantity;
            if (intval($product['remain_quantity']) < $newQty) {
                echo json_encode(['success' => false, 'message' => 'Không đủ tồn kho. Còn ' . intval($product['remain_quantity']) . ' sản phẩm']);
                exit();
            }
            $item['quantity'] = $newQty;
            $found = true;
            break;
        }
    }
    if (!$found) {
        if (intval($product['remain_quantity']) < $quantity) { echo json_encode(['success' => false, 'message' => 'Không đủ tồn kho. Còn ' . intval($product['remain_quantity']) . ' sản phẩm']); exit(); }
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'car_name' => $product['car_name'],
            'price' => $product['price'],
            'image_link' => $product['image_link'],
            'quantity' => $quantity,
            'status' => $product['status']
        ];
    }

    echo json_encode(['success' => true, 'message' => 'Đã thêm vào giỏ hàng']);
    } catch (Exception $e) {
    // rollback if transaction active
    if (mysqli_errno($connect)) @mysqli_rollback($connect);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
