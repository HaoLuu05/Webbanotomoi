<?php
include 'header.php';
include '../User/connect.php';

// Add this near the top of your file, after session and connection checks
if (isset($_POST['confirm_action']) && isset($_POST['product_id'])) {
    $product_id = mysqli_real_escape_string($connect, $_POST['product_id']);

    // Check if product exists in orders
    $check_query = "SELECT COUNT(*) as count FROM order_details WHERE product_id = ?";
    $stmt = mysqli_prepare($connect, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($result['count'] > 0) {
        // Product exists in orders - hide it
        $update_query = "UPDATE products SET status = 'hidden' WHERE product_id = ?";
        $stmt = mysqli_prepare($connect, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $product_id);

        if (mysqli_stmt_execute($stmt)) {
            echo "<script>showNotification('Product has been hidden as it exists in orders', 'info');</script>";
        } else {
            echo "<script>showNotification('Error hiding product', 'error');</script>";
        }
    } else {
        // Product can be safely deleted
        // First get the image path
        $image_query = "SELECT image_link FROM products WHERE product_id = ?";
        $stmt = mysqli_prepare($connect, $image_query);
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($product) {
            // Delete the image file
            $image_path = "../User/" . $product['image_link'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }

            // Delete from database
            $delete_query = "DELETE FROM products WHERE product_id = ?";
            $stmt = mysqli_prepare($connect, $delete_query);
            mysqli_stmt_bind_param($stmt, "i", $product_id);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>showNotification('Product deleted successfully', 'success');</script>";
            } else {
                echo "<script>showNotification('Error deleting product', 'error');</script>";
            }
        }
    }

    // Redirect to refresh the page
    echo "<script>setTimeout(function() { window.location.href = 'manage-products.php'; }, 1500);</script>";
    exit;
}
// Xử lý thêm sản phẩm
if (isset($_POST['add_product'])) {
    $car_name = mysqli_real_escape_string($connect, $_POST['car_name']);
    // Check if car name already exists
    $check_query = "SELECT product_id FROM products WHERE car_name = '$car_name'";
    $check_result = mysqli_query($connect, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>
            showNotification('A car with this name already exists!', 'warning');
            setTimeout(() => {
                document.getElementById('car_name').focus();
            }, 500);
        </script>";
        exit();
    }

    $brand_id = mysqli_real_escape_string($connect, $_POST['brand_id']);
    $year = mysqli_real_escape_string($connect, $_POST['year']);
    $price = mysqli_real_escape_string($connect, $_POST['price']);
    $max_speed = mysqli_real_escape_string($connect, $_POST['max_speed']);
    $engine_name = mysqli_real_escape_string($connect, $_POST['engine_name']);
    $fuel_name = mysqli_real_escape_string($connect, $_POST['fuel_name']);
    $color = mysqli_real_escape_string($connect, $_POST['color']);
    $seat_number = mysqli_real_escape_string($connect, $_POST['seat_number']);
    $engine_power = mysqli_real_escape_string($connect, $_POST['engine_power']);
    $status = mysqli_real_escape_string($connect, $_POST['status']);
    $fuel_capacity = mysqli_real_escape_string($connect, $_POST['fuel_capacity']);
    $car_description = mysqli_real_escape_string($connect, $_POST['car_description']);

    // Xử lý upload ảnh
    $image_link = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../User/uploads/';


        // Kiểm tra và tạo thư mục uploads nếu chưa tồn tại
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Đặt tên file với timestamp để tránh trùng lặp
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $image_name;

        // Di chuyển file upload vào thư mục đã chỉ định
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Lưu đường dẫn tương đối (sau ../User/)
            $image_link = 'uploads/' . $image_name;
        } else {
            echo "<script>showNotification('Couldn't upload the image!','error');</script>";
        }
    }

    // Thêm sản phẩm chính
    $query = "INSERT INTO products (car_name, brand_id, year_manufacture, price, max_speed, engine_name, 
              fuel_name, color, seat_number, engine_power, image_link, status, fuel_capacity, car_description) 
              VALUES ('$car_name', '$brand_id', '$year', '$price', '$max_speed', '$engine_name', 
              '$fuel_name', '$color', '$seat_number', '$engine_power', '$image_link', '$status', '$fuel_capacity', '$car_description')";

    if (mysqli_query($connect, $query)) {
        // Lấy ID sản phẩm vừa thêm
        $product_id = mysqli_insert_id($connect);

        // Xử lý các hình ảnh phụ
        if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
            $upload_dir = '../User/uploads/';

            // Kiểm tra và tạo thư mục nếu chưa tồn tại
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Lặp qua các hình ảnh phụ
            $additional_images_count = count($_FILES['additional_images']['name']);
            for ($i = 0; $i < $additional_images_count; $i++) {
                // Kiểm tra lỗi upload
                if ($_FILES['additional_images']['error'][$i] == 0) {
                    $tmp_name = $_FILES['additional_images']['tmp_name'][$i];
                    $original_name = $_FILES['additional_images']['name'][$i];

                    // Tạo tên file duy nhất
                    $image_name = time() . '_' . $product_id . '_' . $i . '_' . $original_name;
                    $target_file = $upload_dir . $image_name;

                    // Di chuyển file upload
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        // Lưu đường dẫn hình ảnh vào CSDL
                        $relative_path = 'uploads/' . $image_name;
                        $insert_image_query = "INSERT INTO product_images (product_id, image_url, sort_order) 
                                               VALUES ($product_id, '$relative_path', $i)";
                        mysqli_query($connect, $insert_image_query);
                    }
                }
            }
        }

        echo "<script>showNotification('Add product successfully!', 'success');</script>";
    } else {
        echo "<script>showNotification('Error: " . mysqli_error($connect) . "', 'error');</script>";
    }
}
// Đọc dữ liệu JSON từ request
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['image_id'])) {
    $image_id = mysqli_real_escape_string($connect, $data['image_id']);

    // Lấy đường dẫn hình ảnh trước khi xóa
    $query = "SELECT image_url FROM product_images WHERE image_id = $image_id";
    $result = mysqli_query($connect, $query);
    $image = mysqli_fetch_assoc($result);

    if ($image) {
        // Xóa file vật lý
        $file_path = '../User/' . $image['image_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Xóa khỏi CSDL
        $delete_query = "DELETE FROM product_images WHERE image_id = $image_id";
        if (mysqli_query($connect, $delete_query)) {
            // echo json_encode(['success' => true]);
        } else {
            // echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        // echo json_encode(['success' => false, 'message' => 'Image not found']);
    }
} else {
    // echo json_encode(['success' => false, 'message' => 'No image ID provided']);
}
// Xử lý cập nhật sản phẩm
if (isset($_POST['update_product'])) {
    $product_id = mysqli_real_escape_string($connect, $_POST['product_id']);
    $car_name = mysqli_real_escape_string($connect, $_POST['car_name']);
    $brand_id = mysqli_real_escape_string($connect, $_POST['brand_id']);
    $year = mysqli_real_escape_string($connect, $_POST['year']);
    $price = mysqli_real_escape_string($connect, $_POST['price']);
    $max_speed = mysqli_real_escape_string($connect, $_POST['max_speed']);
    $engine_name = mysqli_real_escape_string($connect, $_POST['engine_name']);
    $fuel_name = mysqli_real_escape_string($connect, $_POST['fuel_name']);
    $color = mysqli_real_escape_string($connect, $_POST['color']);
    $seat_number = mysqli_real_escape_string($connect, $_POST['seat_number']);
    $engine_power = mysqli_real_escape_string($connect, $_POST['engine_power']);
    $status = mysqli_real_escape_string($connect, $_POST['status']);
    $fuel_capacity = mysqli_real_escape_string($connect, $_POST['fuel_capacity']);
    $car_description = mysqli_real_escape_string($connect, $_POST['car_description']);

    // Xử lý upload ảnh mới nếu có
    $image_update = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../User/uploads/';

        // Kiểm tra và tạo thư mục nếu chưa tồn tại
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_update = ", image_link = 'uploads/" . $image_name . "'";
        } else {
            echo "<script>showNotification('Couldn't upload the image!','error');</script>";
        }
    }

    $query = "UPDATE products SET 
              car_name = '$car_name', 
              brand_id = '$brand_id', 
              year_manufacture = '$year', 
              price = '$price', 
              max_speed = '$max_speed', 
              engine_name = '$engine_name', 
              fuel_name = '$fuel_name', 
              color = '$color', 
              seat_number = '$seat_number', 
              engine_power = '$engine_power',
              fuel_capacity = '$fuel_capacity',
              car_description = '$car_description', 
              status = '$status'
              $image_update
              WHERE product_id = $product_id";

    if (mysqli_query($connect, $query)) {
        // Xử lý các hình ảnh phụ
        if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
            $upload_dir = '../User/uploads/';

            // Kiểm tra và tạo thư mục nếu chưa tồn tại
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Lặp qua các hình ảnh phụ
            $additional_images_count = count($_FILES['additional_images']['name']);
            for ($i = 0; $i < $additional_images_count; $i++) {
                // Kiểm tra lỗi upload
                if ($_FILES['additional_images']['error'][$i] == 0) {
                    $tmp_name = $_FILES['additional_images']['tmp_name'][$i];
                    $original_name = $_FILES['additional_images']['name'][$i];

                    // Tạo tên file duy nhất
                    $image_name = time() . '_' . $product_id . '_' . $i . '_' . $original_name;
                    $target_file = $upload_dir . $image_name;

                    // Di chuyển file upload
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        // Lưu đường dẫn hình ảnh vào CSDL
                        $relative_path = 'uploads/' . $image_name;
                        $insert_image_query = "INSERT INTO product_images (product_id, image_url, sort_order) 
                                               VALUES ($product_id, '$relative_path', $i)";
                        mysqli_query($connect, $insert_image_query);
                    }
                }
            }
        }

        echo "<script>showNotification('Update the product successfully!', 'success');</script>";
    } else {
        echo "<script>showNotification('Error: " . mysqli_error($connect) . "', 'error');</script>";
    }
}

function isProductInOrders($connect, $product_id)
{
    // Kiểm tra sản phẩm có trong các đơn hàng đã hoàn thành
    $query = "SELECT COUNT(*) as count 
              FROM order_details od
              JOIN orders o ON od.order_id = o.order_id
              WHERE od.product_id = ? AND o.order_status IN ('completed', 'processing', 'shipped')";
    $stmt = mysqli_prepare($connect, $query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $result['count'] > 0;
}

// Xử lý xóa/ẩn sản phẩm
// Xử lý xóa/ẩn sản phẩm
if (isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    $action_type = $_POST['action_type'] ?? '';

    // Lấy trạng thái hiện tại của sản phẩm
    $status_query = "SELECT status FROM products WHERE product_id = ?";
    $stmt = mysqli_prepare($connect, $status_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $status_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $current_status = $status_result['status'];

    // Kiểm tra xem sản phẩm có trong đơn hàng không
    $in_orders = isProductInOrders($connect, $product_id);

    // Quyết định hành động
    if ($in_orders || $current_status == 'hidden') {
        // Nếu sản phẩm đã từng được đặt hàng hoặc đã ở trạng thái ẩn
        $update_query = "UPDATE products SET status = 'hidden' WHERE product_id = ?";
        $stmt = mysqli_prepare($connect, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $product_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Product has been hidden',
                'type' => 'info'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error hiding product',
                'type' => 'error'
            ]);
        }
    } else {
        // Sản phẩm chưa từng được đặt hàng, có thể xóa hoàn toàn
        // Lấy đường dẫn ảnh
        $query = "SELECT image_link FROM products WHERE product_id = ?";
        $stmt = mysqli_prepare($connect, $query);
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);

        if ($product) {
            // Xóa file ảnh
            $image_path = "../User/" . $product['image_link'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }

            // Xóa các ảnh phụ
            $delete_additional_images_query = "DELETE FROM product_images WHERE product_id = ?";
            $stmt = mysqli_prepare($connect, $delete_additional_images_query);
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);

            // Xóa sản phẩm
            $delete_query = "DELETE FROM products WHERE product_id = ?";
            $stmt = mysqli_prepare($connect, $delete_query);
            mysqli_stmt_bind_param($stmt, "i", $product_id);

            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Product deleted successfully',
                    'type' => 'success'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error deleting product',
                    'type' => 'error'
                ]);
            }
        }
    }
    exit;
}


// Lấy danh sách hãng xe cho dropdown
$brands_query = "SELECT * FROM car_types ORDER BY type_name";
$brands_result = mysqli_query($connect, $brands_query);
$brands = [];
while ($brand = mysqli_fetch_assoc($brands_result)) {
    $brands[] = $brand;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products</title>
    <!-- <link rel="stylesheet" href="style.css"> -->
    <link rel="icon" href="../User/dp56vcf7.png" type="image/png">
    <!-- <script src="mp.js"></script>
    <link rel="stylesheet" href="mp.css"> -->
    <script src="https://kit.fontawesome.com/8341c679e5.js" crossorigin="anonymous"></script>
</head>
<style>
    /* Admin Header */
    .admin-header {
        background-color: #f3f3f3;
        color: white;
        padding: 20px;
        text-align: center;
    }

    /* Admin Main Sections */
    .admin-section {
        margin: 20px;
        padding: 20px;
        border: 1px solid #ddd;
        background-color: #f9f9f9;
        border-radius: 8px;

    }

    /* Quick Stats Boxes */
    .stats-container {
        display: flex;
        justify-content: space-around;
        margin-top: 20px;
    }

    .stat-box {
        background-color: #007BFF;
        color: white;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        width: 30%;
    }

    /* Admin Tables */
    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th,
    .admin-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .admin-table th {
        background-color: #f3f3f3;
        font-weight: bold;
    }


    /* Admin Buttons */
    .admin-table button {
        padding: 5px 10px;
        margin-right: 5px;
        background-color: #007BFF;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .admin-table button:hover {
        background-color: #0056b3;
    }


    /* Navbar Styling */
    .navbar {
        background-color: #2c3e50;
        /* Darker color for admin navbar */
        overflow: hidden;
        font-weight: bold;
        padding: 10px 0;
        padding-left: 100px;
    }

    .navbar a {
        color: #ecf0f1;
        /* Light text color */
        float: left;
        display: block;
        text-align: center;
        padding: 14px 20px;
        text-decoration: none;
        transition: background-color 0.3s, color 0.3s;
        /* Smooth transition */
    }

    /* Hover Effects for Links */
    .navbar a:hover {
        background-color: #34495e;
        /* Slightly lighter background on hover */
        color: #1abc9c;
        /* Accent color for text on hover */
    }

    /* Active Link */
    .navbar a.active {
        background-color: #1abc9c;
        /* Highlight color for active page */
        color: #ffffff;
    }

    /* Dropdown Menu for Navbar (optional for sub-navigation) */
    .navbar .dropdown {
        float: left;
        overflow: hidden;
    }

    .navbar .dropdown .dropbtn {
        font-size: 16px;
        border: none;
        outline: none;
        color: #ecf0f1;
        padding: 14px 20px;
        background-color: inherit;
        font-family: inherit;
        margin: 0;
    }

    /* Dropdown Content (Hidden by Default) */
    .navbar .dropdown-content {
        display: none;
        position: absolute;
        background-color: #34495e;
        min-width: 160px;
        z-index: 1;
        box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
    }

    /* Links inside Dropdown */
    .navbar .dropdown-content a {
        float: none;
        color: #ecf0f1;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        text-align: left;
    }

    .navbar .dropdown-content a:hover {
        background-color: #1abc9c;
        /* Highlight for dropdown items on hover */
    }

    /* Show Dropdown on Hover */
    .navbar .dropdown:hover .dropdown-content {
        display: block;
    }

    #logoheader {
        max-width: 10%;
    }


    /* Styling for Admin Info in Header */
    .admin-info {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-right: 20px;
        font-size: 16px;
        color: #000000;
        font-size: 1.5em;
        font-weight: bold;
    }

    #logout-btn {
        background-color: #e74c3c;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s ease;
    }

    #logout-btn:hover {
        background-color: #c0392b;
    }

    /* Specific hover effect for Ban */
    button .admin-table button[style*="background-color: red;"] {
        background-color: #e74c3c;
        color: white;
        border: none;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .admin-table button[style*="background-color: red;"]:hover {
        background-color: #c0392b;
        /* Darker red on hover  */
        transform: scale(1.1);
        /* Slight zoom effect  */
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        /* Add shadow  */
    }

    /* More Button Styling */
    button.link {
        margin-top: 20px;
        display: inline-block;
        background-color: #1abc9c;
        /* Màu nền xanh ngọc */
        color: white;
        /* Màu chữ trắng */
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        /* Kích thước nút */
        font-size: 16px;
        /* Kích thước chữ */
        font-weight: bold;
        /* Đậm chữ */
        cursor: pointer;
        /* Hiển thị icon tay khi hover */
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
        /* Hiệu ứng mượt */
        text-align: center;
        /* Canh giữa văn bản */
    }

    /* Hover Effect for More Button */
    button.link:hover {
        background-color: #16a085;
        /* Màu nền đậm hơn khi hover */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        /* Đổ bóng khi hover */
    }

    /* Active State for More Button */
    button.link:active {
        background-color: #0e7766;
        /* Màu tối hơn khi bấm */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        /* Giảm độ cao bóng */
    }

    /* Add this to style.css */
    #logout-btn {
        text-decoration: none;
        color: #fff;
        background-color: #dc3545;
        padding: 8px 16px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }

    #logout-btn:hover {
        background-color: #c82333;
    }

    /* Styling for Modals (Add Product and Edit Product) */
    #addProductModal,
    #editProductModal {
        display: none;
        /* Hidden by default */
        position: fixed;
        /* Position fixed to the viewport */
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        /* Ensure the modal is on top */
        width: 400px;
        max-width: 100%;
    }

    /* Styling for Form Inputs */
    #addProductForm input,
    #editProductForm input {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
    }

    #addProductForm input[type="file"],
    #editProductForm input[type="file"] {
        padding: 5px;
    }

    /* Styling for Buttons inside the Modals */
    #addProductForm button,
    #editProductForm button {
        /* width: 100%; */
        padding: 10px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
    }

    #addProductForm button {
        background-color: #1abc9c;
        color: white;
        margin-bottom: 10px;
    }

    #editProductForm button {
        background-color: #007bff;
        color: white;
    }

    #addProductForm button:hover,
    #editProductForm button:hover {
        background-color: #16a085;
        /* Darker green for Add button */
    }

    #addProductForm button[type="button"],
    #editProductForm button[type="button"] {
        background-color: #e74c3c;
        /* margin-top: 10px; */
    }

    #addProductForm button[type="button"]:hover,
    #editProductForm button[type="button"]:hover {
        background-color: #c0392b;
    }

    /* Close Button in Modals */
    button[type="button"] {
        /* width: 48%; */
        /* margin-right: 4%; */
        background-color: #e74c3c;
    }

    button[type="button"]:hover {
        background-color: #c0392b;
    }

    /* Background Overlay when Modals are Active */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
        /* Hidden by default */
    }

    /* Adding some spacing and styling to the modal header */
    #addProductModal h3,
    #editProductModal h3 {
        font-size: 20px;
        margin-bottom: 15px;
        font-weight: bold;
        text-align: center;
    }

    #editProductModal {
        width: 400px;
        padding: 20px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    #editProductModal input[type="text"],
    #editProductModal input[type="number"] {
        width: 100%;
        padding: 8px;
        margin: 5px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    #editProductModal button {
        padding: 10px;
        border: none;
        background-color: #007BFF;
        color: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    #editProductModal button:hover {
        background-color: #0056b3;
    }

    #currentProductImage img {
        max-width: 100%;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    /* Đặt kiểu cho overlay (màn che phía sau) */
    #modalOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        /* Màu nền mờ */
        display: none;
        /* Ẩn mặc định */
        z-index: 999;
        /* Đảm bảo nó hiển thị trên các phần tử khác */
    }

    /* Kiểu cho modal (form edit) */
    #editProductModal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        /* Dịch chuyển nó về giữa */
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.2);
        display: none;
        /* Ẩn mặc định */
        z-index: 1000;
        /* Đảm bảo modal hiển thị trên overlay */
        width: 400px;
        /* Chiều rộng modal */
        max-width: 90%;
        /* Chiều rộng tối đa */
    }

    /* Định dạng các trường trong form */
    #editProductModal input,
    #editProductModal select,
    #editProductModal textarea {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    /* Định dạng các nút trong form */
    #editProductModal button {
        /* padding: 10px 20px; */
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 10px;
    }

    #editProductModal button:hover {
        background-color: #45a049;
    }

    /* Đặt kiểu cho modal (form add product) */
    #addProductModal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        /* Căn giữa màn hình */
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.2);
        display: none;
        /* Ẩn modal khi không cần thiết */
        z-index: 1000;
        /* Đảm bảo modal hiển thị trên overlay */
        width: 400px;
        /* Chiều rộng modal */
        max-width: 90%;
        /* Chiều rộng tối đa */
        box-sizing: border-box;
        /* Đảm bảo padding không làm thay đổi kích thước tổng thể */
    }

    /* Định dạng cho các input trong form */
    #addProductModal input,
    #addProductModal select,
    #addProductModal textarea {
        width: 100%;
        /* Chiều rộng 100% để không bị tràn */
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        /* Đảm bảo padding không ảnh hưởng đến kích thước tổng thể */
    }

    /* Định dạng cho các nút trong form */
    #addProductModal button {
        /* padding: 10px 20px; */
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 10px;
    }

    #addProductModal button:hover {
        background-color: #45a049;
    }

    /* Đảm bảo modal không bị tràn khi mở */
    #modalOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: none;
        /* Ẩn modal overlay khi không cần thiết */
        z-index: 999;
    }
</style>
<style>
    /* Improved Modal Styling */
    #addProductModal,
    #editProductModal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        padding: 30px;
        width: 500px;
        max-width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    /* Modal Header */
    #addProductModal h3,
    #editProductModal h3 {
        font-size: 22px;
        font-weight: 600;
        color: #333;
        margin: 0 0 20px 0;
        text-align: center;
        border-bottom: 2px solid #1abc9c;
        padding-bottom: 10px;
    }

    /* Form Groups */
    #addProductForm div,
    #editProductForm div {
        margin-bottom: 16px;
    }

    /* Labels */
    #addProductForm label,
    #editProductForm label {
        display: block;
        font-weight: 500;
        margin-bottom: 6px;
        color: #444;
    }

    /* Form Controls */
    #addProductForm input,
    #addProductForm select,
    #editProductForm input,
    #editProductForm select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background-color: #f9f9f9;
        font-size: 15px;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    #addProductForm input:focus,
    #addProductForm select:focus,
    #editProductForm input:focus,
    #editProductForm select:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2);
        outline: none;
        background-color: #fff;
    }

    /* File Input */
    #addProductForm input[type="file"],
    #editProductForm input[type="file"] {
        padding: 8px;
        background-color: #fff;
        border: 1px dashed #ccc;
    }

    /* Current Image Preview */
    #currentProductImage {
        text-align: center;
        margin-bottom: 20px;
    }

    #currentImagePreview {
        max-width: 100%;
        height: auto;
        border-radius: 6px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Buttons Container */
    .form-buttons {
        display: flex;
        justify-content: space-between;
        margin-top: 25px;
    }

    /* Form Buttons */
    #addProductForm button,
    #editProductForm button {
        /* padding: 12px 24px; */
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
        flex: 1;
        margin: 0 5px;
    }

    /* Save Button */
    #addProductForm button[type="submit"],
    #editProductForm button[type="submit"] {
        background-color: #1abc9c;
        color: white;
    }

    #addProductForm button[type="submit"]:hover,
    #editProductForm button[type="submit"]:hover {
        background-color: #16a085;
        transform: translateY(-2px);
    }

    /* Cancel Button */
    #addProductForm button[type="button"],
    #editProductForm button[type="button"] {
        background-color: #e74c3c;
        color: white;
    }

    #addProductForm button[type="button"]:hover,
    #editProductForm button[type="button"]:hover {
        background-color: #c0392b;
        transform: translateY(-2px);
    }

    /* Modal Overlay */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        z-index: 999;
        display: none;
        backdrop-filter: blur(3px);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {

        #addProductModal,
        #editProductModal {
            width: 95%;
            padding: 20px;
        }

        .form-buttons {
            flex-direction: column;
        }

        #addProductForm button,
        #editProductForm button {
            margin: 5px 0;
        }
    }
    #addStockModal {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background-color: #ffffff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                z-index: 1001; /* higher than overlay (999) */
                width: 400px;
                max-width: 90%;
                box-sizing: border-box;
            }
    /* Form Section Separator */
    .form-section {
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    /* Required Field Indicator */
    .required:after {
        content: "*";
        color: #e74c3c;
        margin-left: 4px;
    }

    /* Status Options Styling */
    #status option,
    #edit_status option {
        padding: 8px;
    }

    /* Form Success Message */
    .form-success {
        background-color: #d4edda;
        color: #155724;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        display: none;
    }
</style>
<style>
    /* Delete confirmation popup styles */
    #deleteConfirmModal {
        display: none;
    }

    #deleteConfirmModal .popup-content {
        max-width: 400px;
        text-align: center;
    }

    #deleteConfirmModal h3 {
        color: #dc3545;
        margin-bottom: 20px;
    }

    #deleteConfirmModal h3 i {
        margin-right: 10px;
    }

    #deleteConfirmModal p {
        margin-bottom: 25px;
        color: #666;
    }

    #deleteConfirmModal .popup-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    #deleteConfirmModal .confirm-btn {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    #deleteConfirmModal .confirm-btn:hover {
        background-color: #c82333;
        transform: translateY(-2px);
    }

    #deleteConfirmModal .cancel-btn {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    #deleteConfirmModal .cancel-btn:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
    }
</style>
<style>
    /* Add these styles to your existing CSS */
    .popup {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(3px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .popup .popup-content {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        position: relative;
        width: 400px;
        max-width: 90%;
        animation: popupFadeIn 0.3s ease;
    }

    @keyframes popupFadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
<style>
    /* Add Product Button Styling */
    #add-user-btn {
        background: #1abc9c;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #add-user-btn i {
        font-size: 18px;
    }

    #add-user-btn:hover {
        background: #16a085;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    /* Form Input Icons */
    .form-section div {
        position: relative;
    }

    .form-section input,
    .form-section select {
        padding-left: 35px !important;
    }

    /* .form-section i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color:rgb(0, 0, 0);
    opacity: 0.7;
} */

    /* Table Header Icons */
    .admin-table th i {
        margin-right: 8px;
        color: rgb(0, 0, 0);
    }

    .delete-btn {
        background-color: red !important;
    }
</style>
<style>
    /* Add to your existing styles */


    .form-section i {
        /* position: absolute;
        left: 10px;
        top: 50%; */
        /* transform: translateY(-50%); */
        padding-right: 10px;
        /* color:rgb(0, 0, 0); */
        opacity: 0.7;
        z-index: 1;
    }

    .form-section input:focus+i,
    .form-section select:focus+i {
        /* color: #16a085; */
        opacity: 1;
        padding-right: 10px;
    }

    /* Style for file input icon */
    div:has(> input[type="file"]) {
        /* position: relative; */
    }

    div:has(> input[type="file"]) i {
        /* position: absolute;
        left: 10px;
        top: 50%; */
        /* transform: translateY(-50%); */
        /* color: #1abc9c; */
        opacity: 0.7;
        /* padding-right: 10px; */
    }

    /* Form buttons */
    .form-buttons button {
        /* display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px; */
    }

    .form-buttons button[type="submit"]::before {
        content: "\f0c7";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        padding-right: 10px;
    }

    .form-buttons button[type="button"]::before {
        content: "\f00d";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        padding-right: 10px;
    }
</style>
<style>
    /* Add to your existing styles */
    #addImagePreview,
    #currentProductImage {
        text-align: center;
        margin: 15px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    #addImagePreview img,
    #currentImagePreview {
        max-width: 200px;
        max-height: 150px;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    #addImagePreview img:hover,
    #currentImagePreview:hover {
        transform: scale(1.05);
    }
</style>
<style>
    /* Add to your existing styles */
    .form-section textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background-color: #f9f9f9;
        font-size: 15px;
        transition: border-color 0.3s, box-shadow 0.3s;
        resize: vertical;
        min-height: 100px;
    }

    .form-section textarea:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2);
        outline: none;
        background-color: #fff;
    }
</style>
<style>
    /* Add to your existing styles */
    #addImagePreview,
    #currentProductImage {
        text-align: center;
        margin: 15px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    #addImagePreview img,
    #currentImagePreview {
        max-width: 200px;
        max-height: 150px;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    #addImagePreview img:hover,
    #currentImagePreview:hover {
        transform: scale(1.05);
    }
</style>
<style>
    /* Add to your existing styles */
    .form-section textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background-color: #f9f9f9;
        font-size: 15px;
        transition: border-color 0.3s, box-shadow 0.3s;
        resize: vertical;
        min-height: 100px;
    }

    .form-section textarea:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2);
        outline: none;
        background-color: #fff;
    }

    /* Add to your existing styles */
    .images-preview-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }

    .image-preview-item {
        position: relative;
        aspect-ratio: 16/9;
        background: #f8f9fa;
        border-radius: 8px;
        overflow: hidden;
    }

    .image-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .image-preview-item:hover img {
        transform: scale(1.05);
    }

    .image-preview-item .remove-image {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        opacity: 0.5;
    }

    .image-preview-item .remove-image:hover {
        background: #dc3545;
        transform: scale(1.1);
    }

    /* Add to your existing styles */
    .no-images-message {
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 6px;
        color: #6c757d;
        text-align: center;
        width: 100%;
        margin: 10px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .error-message {
        padding: 15px;
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
        color: #856404;
        border-radius: 6px;
        margin: 10px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .error-message i,
    .no-images-message i {
        font-size: 1.1em;
    }

    /* Add to your existing styles */
    .images-preview-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px dashed #dee2e6;
    }

    .image-preview-item {
        position: relative;
        aspect-ratio: 16/9;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background: #fff;
    }

    .image-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .image-preview-item:hover img {
        transform: scale(1.05);
    }

    .remove-preview {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .remove-preview:hover {
        background: #dc3545;
        transform: scale(1.1);
    }

    .empty-preview-message {
        grid-column: 1 / -1;
        text-align: center;
        padding: 20px;
        color: #6c757d;
        font-style: italic;
    }

    .preview-count {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: rgba(0, 0, 0, 0.5);
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8em;
    }

    .fa-times {}

    .remove-preview {
        opacity: 0.7 !important;
    }

    .confirm-btn.hide-btn {
        background-color: #ffc107;
        color: black;
    }

    .confirm-btn.delete-btn {
        background-color: #dc3545;
        color: white;
    }
</style>
<style>
    .admin-table th:nth-child(1) {
        width: 50px;
    }

    .admin-table th:nth-child(2) {
        width: 80px;
    }

    .admin-table th:nth-child(5) {
        width: 60px;
    }

    .admin-table th:nth-child(6) {
        width: 150px;
    }

    .admin-table th:nth-child(7) {
        width: 60px;
    }

    .admin-table th:nth-child(8) {
        width: 130px;
    }

    .admin-table th:nth-child(9) {
        width: 125px;
    }

    .admin-table th:nth-child(13) {
        width: 115px;
    }

    .admin-table th:nth-child(12) {
        width: 70px;
    }
</style>

<body>
    <main>
        <section class="admin-section">
            <h2><i class="fa-solid fa-pen-to-square">&nbsp;&nbsp;</i>Product Management</h2>
            <button onclick="showAddProductForm()" id="add-user-btn">
                <i class="fa-solid fa-plus"></i>
                Add New Product
            </button>
            <form method="GET" class="filter-form" style="display:flex; gap:10px; align-items:center; margin-top:8px; margin-bottom:12px; flex-wrap:wrap;">
                <input type="text" name="keyword" placeholder="Tìm tên sản phẩm..." value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>" style="padding:6px 8px; min-width:200px;">

                <select name="car_type" style="padding:6px 8px;">
                    <option value="">Tất cả loại xe</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['type_id'] ?>" <?= (isset($_GET['car_type']) && $_GET['car_type']==$b['type_id'])?'selected':'' ?>><?= htmlspecialchars($b['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="price_filter" style="padding:6px 8px;">
                    <option value="">Tất cả giá</option>
                    <option value="1" <?= (isset($_GET['price_filter']) && $_GET['price_filter']=="1") ? 'selected' : '' ?>>Dưới 500 triệu</option>
                    <option value="2" <?= (isset($_GET['price_filter']) && $_GET['price_filter']=="2") ? 'selected' : '' ?>>500 - 1 tỷ</option>
                    <option value="3" <?= (isset($_GET['price_filter']) && $_GET['price_filter']=="3") ? 'selected' : '' ?>>Trên 1 tỷ</option>
                </select>

                <select name="status" style="padding:6px 8px;">
                    <option value="">Tình trạng</option>
                    <option value="selling" <?= (isset($_GET['status']) && $_GET['status']=='selling')?'selected':'' ?>>Còn hàng</option>
                    <option value="hidden" <?= (isset($_GET['status']) && $_GET['status']=='hidden')?'selected':'' ?>>Ẩn</option>
                    <option value="discounting" <?= (isset($_GET['status']) && $_GET['status']=='discounting')?'selected':'' ?>>Đang giảm giá</option>
                    <option value="soldout" <?= (isset($_GET['status']) && $_GET['status']=='soldout')?'selected':'' ?>>Hết hàng</option>
                </select>

                <button type="submit" style="padding:6px 10px;">Lọc</button>
                <a href="manage-products.php" style="padding:6px 10px; background:#f8f9fa; border:1px solid #ddd; text-decoration:none; color:#333;">Reset</a>
            </form>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-hashtag"></i> ID</th>
                        <th><i class="fa-solid fa-image"></i> Image</th>
                        <th><i class="fa-solid fa-car"></i> Car Name</th>
                        <th><i class="fa-solid fa-building"></i> Brand</th>
                        <th><i class="fa-solid fa-calendar"></i> Year</th>
                        <th><i class="fa-solid fa-tag"></i> Price</th>
                        <th><i class="fa-solid fa-gas-pump"></i> Fuel</th>
                        <th><i class="fa-solid fa-oil-can"></i> Fuel Capacity</th>

                        <th><i class="fa-solid fa-gear"></i> Engine Power</th>
                        <th><i class="fa-solid fa-gears"></i> Engine</th>
                        <th><i class="fa-solid fa-palette"></i> Color</th>
                        <th><i class="fa-solid fa-users"></i> Seats</th>
                        <th><i class="fa-solid fa-gauge"></i> Max Speed</th>
                        <th><i class="fa-solid fa-circle-info"></i> Status</th>
                        <th><i class="fa-solid fa-wrench"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="product-list">
                    <?php
                    // Lấy danh sách sản phẩm với tên hãng xe — hỗ trợ lọc từ form ở trên
                    $wheres = [];
                    if (!empty($_GET['keyword'])) {
                        $kw = mysqli_real_escape_string($connect, $_GET['keyword']);
                        $wheres[] = "p.car_name LIKE '%" . $kw . "%'";
                    }
                    if (!empty($_GET['car_type'])) {
                        $type = (int)$_GET['car_type'];
                        $wheres[] = "p.brand_id = " . $type;
                    }
                    if (!empty($_GET['price_filter'])) {
                        $pf = $_GET['price_filter'];
                        if ($pf == '1') $wheres[] = "p.price < 500000000";
                        elseif ($pf == '2') $wheres[] = "p.price BETWEEN 500000000 AND 1000000000";
                        elseif ($pf == '3') $wheres[] = "p.price > 1000000000";
                    }
                    if (!empty($_GET['status'])) {
                        $st = mysqli_real_escape_string($connect, $_GET['status']);
                        $wheres[] = "p.status = '" . $st . "'";
                    }

                    $sql = "SELECT p.*, c.type_name FROM products p LEFT JOIN car_types c ON p.brand_id = c.type_id";
                    if ($wheres) $sql .= ' WHERE ' . implode(' AND ', $wheres);
                    $sql .= ' ORDER BY p.product_id ASC';

                    $result = mysqli_query($connect, $sql);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo '<tr>';
                            echo '<td>' . $row['product_id'] . '</td>';
                            echo '<td><img src="../User/' . $row['image_link'] . '" alt="' . $row['car_name'] . '" style="width: 80px; height: 60px; object-fit: cover;"></td>';
                            echo '<td>' . $row['car_name'] . '</td>';
                            echo '<td>' . $row['type_name'] . '</td>';
                            echo '<td>' . $row['year_manufacture'] . '</td>';
                            echo '<td>' . number_format($row['price'], 0, ',', '.') . ' VND</td>';
                            echo '<td>' . $row['fuel_name'] . '</td>';
                            echo '<td>' . $row['fuel_capacity'] . ' </td>';
                            echo '<td>' . $row['engine_power'] . ' hp</td>';
                            echo '<td>' . $row['engine_name'] . '</td>';
                            echo '<td>' . $row['color'] . '</td>';
                            echo '<td>' . $row['seat_number'] . '</td>';

                            echo '<td>' . $row['max_speed'] . '</td>';
                            echo '<td>' . getStatusLabel($row['status']) . '</td>';
                            echo '<td>
    <button onclick="showEditProductForm(' . $row['product_id'] . ')" class="edit-btn">
        <i class="fa-solid fa-edit"></i> Edit
    </button>
    <button onclick="confirmDeleteProduct(' . $row['product_id'] . ')" class="delete-btn">
        <i class="fa-solid fa-trash"></i> Delete
    </button>
    <button onclick="openAddStockForm(' . $row['product_id'] . ')" class="stock-btn">
    <i class="fa-solid fa-boxes-stacked"></i> Add Stock
</button>

</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="9">No products available</td></tr>';
                    }

                    function getStatusLabel($status)
                    {
                        switch ($status) {
                            case 'selling':
                                return '<span style="color: green;">selling</span>';
                            case 'hidden':
                                return '<span style="color: gray;">hidden</span>';
                            case 'discounting':
                                return '<span style="color: blue;">discounting</span>';
                            case 'soldout':
                                return '<span style="color: red;">sold out</span>';
                            default:
                                return $status;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </section>
    </main>


    <!-- Add Product Form -->
    <div id="addProductModal" style="display: none;">
        <h3>Add New Product</h3>
        <form id="addProductForm" method="POST" enctype="multipart/form-data">
            <!-- Update the Add Product Form fields -->
            <div class="form-section">
                <div>
                    <label for="car_name" class="required"><span>

                            <i class="fa-solid fa-car"></i>
                        </span>Car Name:</label>

                    <input type="text" id="car_name" name="car_name" required>
                </div>

                <div>
                    <label for="brand_id" class="required">
                        <span>

                            <i class="fa-solid fa-building"></i>
                        </span>
                        Brand:</label>

                    <select id="brand_id" name="brand_id" required>
                        <option value="">Select brand</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['type_id'] ?>"><?= $brand['type_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="year" class="required"><span><i class="fa-solid fa-calendar"></i></span>Year of
                        Manufacture:</label>

                    <input type="number" id="year" name="year" min="1900" max="<?= date('Y') + 1 ?>" required>
                </div>
            </div>

            <div class="form-section">
                <div>
                    <label for="price" class="required"><span><i class="fa-solid fa-tag"></i></span>Price (VND):</label>

                    <input type="number" id="price" name="price" min="0" required>
                </div>

                <div>
                    <label for="max_speed" class="required"><span><i class="fa-solid fa-gauge-high"></i></span>Maximum
                        Speed:</label>

                    <input type="text" id="max_speed" name="max_speed" required>
                </div>
            </div>

            <div class="form-section">
                <div>
                    <label for="engine_name" class="required"><span><i
                                class="fa-solid fa-gears"></i></span>Engine:</label>

                    <input type="text" id="engine_name" name="engine_name" required>
                </div>

                <div>
                    <label for="fuel_name" class="required"><span><i class="fa-solid fa-gas-pump"></i></span>Fuel
                        Type:</label>

                    <input type="text" id="fuel_name" name="fuel_name" required>
                </div>


                <div>
                    <label for="color" class="required"><span><i class="fa-solid fa-palette"></i></span>Color:</label>

                    <input type="text" id="color" name="color" required>
                </div>
            </div>

            <div class="form-section">
                <div>
                    <label for="seat_number" class="required"><span><i class="fa-solid fa-users"></i></span>Number of
                        Seats:</label>

                    <input type="number" id="seat_number" name="seat_number" min="1" max="20" required>
                </div>

                <div>
                    <label for="engine_power" class="required"><span><i class="fa-solid fa-gear"></i></span>Engine
                        Power:</label>
                    <input type="number" id="engine_power" name="engine_power" min="0" max="2000" required>
                </div>
            </div>
            <div class="form-section">
                <div>
                    <label for="fuel_capacity" class="required">
                        <span><i class="fa-solid fa-oil-can"></i></span>Fuel Capacity:
                    </label>
                    <input type="text" id="fuel_capacity" name="fuel_capacity" placeholder="e.g., 65L, 100kWh, 5kg"
                        required>
                </div>

                <div>
                    <label for="car_description" class="required">
                        <span><i class="fa-solid fa-align-left"></i></span>Description:
                    </label>
                    <textarea id="car_description" name="car_description" rows="4" required></textarea>
                </div>
            </div>
            <div>
                <label for="image" class="required"><span><i class="fa-solid fa-image"></i></span>Image:</label>

                <input type="file" id="image" name="image" accept="image/*" required>
            </div>
            <div>
                <label for="additional_images"><span><i class="fa-solid fa-images"></i></span>Additional Images:</label>
                <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                <small style="display: block; margin-top: 5px; color: #666;">You can select multiple images at
                    once</small>
            </div>

            <div id="addImagesPreview" class="images-preview-container">
                <!-- Preview images will appear here -->
            </div>
            <div>
                <label for="status" class="required"><span><i class="fa-solid fa-circle-info"
                            style="padding-right: 10px; opacity:0.7;"></i></span>Status:</label>

                <select id="status" name="status" required>
                    <option value="selling">Available</option>
                    <option value="hidden">Hidden</option>
                    <option value="discounting">On Sale</option>
                    <option value="soldout">Sold Out</option>
                </select>
            </div>
            <div class="form-buttons">
                <button type="submit" name="add_product">Add Product</button>
                <button type="button" onclick="closeAddProductForm()">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Edit Product Form -->
    <div id="editProductModal" style="display: none;">
        <h3>Edit Product Information</h3>
        <form id="editProductForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="edit_product_id" name="product_id">

            <div id="currentProductImage">
                <img id="currentImagePreview" src="" alt="Product Image">
            </div>

            <div class="form-section">
                <div>
                    <label for="edit_car_name" class="required">Car Name:</label>
                    <input type="text" id="edit_car_name" name="car_name" required>
                </div>

                <div>
                    <label for="edit_brand_id" class="required"> <span>

                            <i class="fa-solid fa-building"></i>
                        </span>Brand:</label>
                    <select id="edit_brand_id" name="brand_id" required>
                        <option value="">Select brand</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['type_id'] ?>"><?= $brand['type_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="edit_year" class="required"><span><i class="fa-solid fa-calendar"></i></span>Year of
                        Manufacture:</label>
                    <input type="number" id="edit_year" name="year" min="1900" max="<?= date('Y') + 1 ?>" required>
                </div>
            </div>

            <div class="form-section">
                <div>
                    <label for="edit_price" class="required"><span><i class="fa-solid fa-tag"></i></span>Price
                        (VND):</label>
                    <input type="number" id="edit_price" name="price" min="0" required>
                </div>

                <div>
                    <label for="edit_max_speed" class="required"><span><i
                                class="fa-solid fa-gauge-high"></i></span>Maximum Speed:</label>
                    <input type="text" id="edit_max_speed" name="max_speed" required>
                </div>
            </div>

            <div class="form-section">
                <div>
                    <label for="edit_engine_name" class="required"><span><i
                                class="fa-solid fa-gears"></i></span>Engine:</label>
                    <input type="text" id="edit_engine_name" name="engine_name" required>
                </div>

                <div>
                    <label for="edit_fuel_name" class="required"><span><i class="fa-solid fa-gas-pump"></i></span>Fuel
                        Type:</label>
                    <input type="text" id="edit_fuel_name" name="fuel_name" required>
                </div>

                <div>
                    <label for="edit_color" class="required"><span><i
                                class="fa-solid fa-palette"></i></span>Color:</label>
                    <input type="text" id="edit_color" name="color" required>
                </div>
            </div>

            <div class="form-section">
                <div>
                    <label for="edit_seat_number" class="required"><span><i class="fa-solid fa-users"></i></span>Number
                        of Seats:</label>
                    <input type="number" id="edit_seat_number" name="seat_number" min="1" max="20" required>
                </div>

                <div>
                    <label for="edit_engine_power" class="required"><span><i class="fa-solid fa-gear"></i></span>Engine
                        Power:</label>
                    <input type="number" id="edit_engine_power" name="engine_power" min="0" max="2000" required>
                </div>
            </div>
            <div class="form-section">
                <div>
                    <label for="edit_fuel_capacity" class="required">
                        <span><i class="fa-solid fa-oil-can"></i></span>Fuel Capacity:
                    </label>
                    <input type="text" id="edit_fuel_capacity" name="fuel_capacity" placeholder="e.g., 65L, 100kWh, 5kg"
                        required>
                </div>

                <div>
                    <label for="edit_car_description" class="required">
                        <span><i class="fa-solid fa-align-left"></i></span>Description:
                    </label>
                    <textarea id="edit_car_description" name="car_description" rows="4" required></textarea>
                </div>
            </div>
            <div>
                <label for="edit_image"><span><i class="fa-solid fa-image"></i></span>New Image (leave empty if
                    unchanged):</label>
                <input type="file" id="edit_image" name="image" accept="image/*">
            </div>
            <div>
                <label for="edit_additional_images">Additional Images:</label>
                <input type="file" id="edit_additional_images" name="additional_images[]" accept="image/*" multiple>
                <small style="display: block; margin-top: 5px; color: #666;">You can select multiple images at
                    once</small>
                <label for="">Product Additional Image(s):</label>
                <div id="currentAdditionalImages" class="images-preview-container">
                    <!-- Các hình ảnh hiện tại sẽ được load động -->
                </div>
                <label for="">Product Additional Image(s):</label>
                <div id="editImagesPreview" class="images-preview-container">
                    <!-- Các hình ảnh mới được chọn sẽ hiển thị ở đây -->
                </div>
            </div>

            <div>
                <label for="edit_status" class="required"><span><i class="fa-solid fa-circle-info"
                            style="padding-right: 10px ;opacity:0.7;"></i></span>Status:Status:</label>
                <select id="edit_status" name="status" required>
                    <option value="selling">Available</option>
                    <option value="hidden">Hidden</option>
                    <option value="discounting">On Sale</option>
                    <option value="soldout">Sold Out</option>
                </select>
            </div>

            <div class="form-buttons">
                <button type="submit" name="update_product">Save Changes</button>
                <button type="button" onclick="closeEditProductForm()">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="popup">
        <div class="popup-content">
            <h3><i class="fa-solid fa-trash"></i> Delete Product</h3>
            <p id="deleteMessage"></p>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="product_id" id="delete_product_id">
                <input type="hidden" name="action_type" id="action_type">
                <div class="popup-buttons">
                    <button type="submit" name="confirm_action" class="confirm-btn">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                    <button type="button" class="cancel-btn" onclick="closeDeleteConfirm()">
                        <i class="fa-solid fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Add Stock Modal -->
<div id="addStockModal" style="display: none;">
    <h3>Add Stock</h3>
    <form id="addStockForm" method="POST">
        <input type="hidden" id="stock_product_id" name="product_id">

        <div class="form-section">
            <div>
                <label for="current_stock">Current Stock:</label>
                <input type="number" id="current_stock" name="current_stock" readonly>
            </div>

            <div>
                <label for="add_stock_quantity" class="required">Add Quantity:</label>
                <input type="number" id="add_stock_quantity" name="add_quantity" min="1" required>
            </div>
        </div>

        <div class="form-buttons">
            <button type="submit" name="save_stock">Update Stock</button>
            <button type="button" onclick="closeAddStockForm()">Cancel</button>
        </div>
    </form>
</div>


    <div class="modal-overlay" id="modalOverlay"></div>
    <script>
        // Hiển thị form thêm sản phẩm
        function showAddProductForm() {
            document.getElementById('addProductForm').reset();
            document.getElementById('addProductModal').style.display = 'block';
            document.getElementById('modalOverlay').style.display = 'block';
        }

        // Đóng form thêm sản phẩm
        function closeAddProductForm() {
            document.getElementById('addProductModal').style.display = 'none';
            document.getElementById('modalOverlay').style.display = 'none';
        }

        // Hiển thị form sửa sản phẩm
        function showEditProductForm(productId) {
            // Send AJAX request to get product details
            fetch(`get_product.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;

                        // Fill form with product data
                        document.getElementById('edit_product_id').value = product.product_id;
                        document.getElementById('edit_car_name').value = product.car_name;
                        document.getElementById('edit_brand_id').value = product.brand_id;
                        document.getElementById('edit_year').value = product.year_manufacture;
                        document.getElementById('edit_price').value = product.price;
                        document.getElementById('edit_max_speed').value = product.max_speed;
                        document.getElementById('edit_engine_name').value = product.engine_name;
                        document.getElementById('edit_fuel_name').value = product.fuel_name;
                        document.getElementById('edit_color').value = product.color;
                        document.getElementById('edit_seat_number').value = product.seat_number;
                        document.getElementById('edit_engine_power').value = product.engine_power;
                        document.getElementById('edit_status').value = product.status;
                        // Add this to the showEditProductForm function
                        document.getElementById('edit_fuel_capacity').value = product.fuel_capacity;
                        document.getElementById('edit_car_description').value = product.car_description;
                        // Show current image
                        document.getElementById('currentImagePreview').src = '../User/' + product.image_link;

                        // Show modal
                        document.getElementById('editProductModal').style.display = 'block';
                        document.getElementById('modalOverlay').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading product information', 'error');
                });
            loadExistingImages(productId);
        }
        function loadExistingImages(productId) {
            fetch(`get_product_images.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('currentAdditionalImages');
                    container.innerHTML = '';

                    if (data.images.length > 0) {
                        data.images.forEach(image => {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'image-preview-item';
                            previewItem.innerHTML = `
                        <img src="../User/${image.image_url}" alt="Product Image">
                        <button type="button" class="remove-image" 
                                onclick="deleteProductImage(${image.image_id}, this)">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    `;
                            container.appendChild(previewItem);
                        });
                    } else {
                        container.innerHTML = '<div class="empty-preview-message">No additional images</div>';
                    }
                });
        }
        // Replace the existing confirmDeleteProduct function
        // Update your JavaScript functions
        //         function confirmDeleteProduct(productId) {
        //     fetch(`check_product_orders.php?id=${productId}`)
        //         .then(response => response.json())
        //         .then(data => {
        //             const modal = document.getElementById('deleteConfirmModal');
        //             const overlay = document.getElementById('modalOverlay');
        //             document.getElementById('delete_product_id').value = productId;

        //             if (data.inOrders) {
        //                 // Sản phẩm đã từng được đặt hàng
        //                 document.querySelector('#deleteConfirmModal h3').innerHTML = 
        //                     '<i class="fa-solid fa-eye-slash"></i> Hide Product';
        //                 document.querySelector('#deleteMessage').innerHTML = 
        //                     'This product has been in previous orders and cannot be permanently deleted. It will be hidden from the website instead.';
        //                 document.querySelector('.confirm-btn').innerHTML = 
        //                     '<i class="fa-solid fa-eye-slash"></i> Hide Product';
        //                 document.getElementById('action_type').value = 'hide';
        //             } else {
        //                 // Sản phẩm chưa từng được đặt hàng
        //                 document.querySelector('#deleteConfirmModal h3').innerHTML = 
        //                     '<i class="fa-solid fa-trash"></i> Delete Product';
        //                 document.querySelector('#deleteMessage').innerHTML = 
        //                     'Are you sure you want to permanently delete this product? This action cannot be undone.';
        //                 document.querySelector('.confirm-btn').innerHTML = 
        //                     '<i class="fa-solid fa-trash"></i> Delete';
        //                 document.getElementById('action_type').value = 'delete';
        //             }

        //             modal.style.display = 'flex';
        //             overlay.style.display = 'block';
        //         });
        // }


        // function closeDeleteConfirm() {
        //     const modal = document.getElementById('deleteConfirmModal');
        //     const overlay = document.getElementById('modalOverlay');

        //     modal.style.display = 'none';
        //     overlay.style.display = 'none';
        // }
        function confirmDeleteProduct(productId) {
            fetch(`check_product_orders.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    const modal = document.getElementById('deleteConfirmModal');
                    const overlay = document.getElementById('modalOverlay');
                    document.getElementById('delete_product_id').value = productId;

                    if (data.inOrders) {
                        // Setup hide product modal
                        document.querySelector('#deleteConfirmModal h3').innerHTML =
                            '<i class="fa-solid fa-eye-slash"></i> Hide Product';
                        document.querySelector('#deleteMessage').innerHTML =
                            'This product exists in orders and cannot be deleted. It will be hidden from the website instead.';
                        document.querySelector('.confirm-btn').innerHTML =
                            '<i class="fa-solid fa-eye-slash"></i> Hide Product';
                        document.querySelector('.confirm-btn').classList.add('hide-btn');
                        document.querySelector('.confirm-btn').classList.remove('delete-btn');
                        document.getElementById('action_type').value = 'hide';
                    } else {
                        // Setup delete product modal
                        document.querySelector('#deleteConfirmModal h3').innerHTML =
                            '<i class="fa-solid fa-trash"></i> Delete Product';
                        document.querySelector('#deleteMessage').innerHTML =
                            'Are you sure you want to delete this product? This action cannot be undone.';
                        document.querySelector('.confirm-btn').innerHTML =
                            '<i class="fa-solid fa-trash"></i> Delete';
                        document.querySelector('.confirm-btn').classList.add('delete-btn');
                        document.querySelector('.confirm-btn').classList.remove('hide-btn');
                        document.getElementById('action_type').value = 'delete';
                    }

                    modal.style.display = 'flex';
                    overlay.style.display = 'block';
                });
        }

        function closeDeleteConfirm() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.getElementById('modalOverlay').style.display = 'none';
        }
        // Update the delete form handler
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('deleteForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                console.log('Delete form submitted');
                console.log('Form data:', Object.fromEntries(formData));

                fetch('manage-products.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.text();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        closeDeleteConfirm();
                        showNotification('Product deleted successfully', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Error deleting product', 'error');
                    });
            });
        });



        function closeEditProductForm() {
            document.getElementById('editProductModal').style.display = 'none';
            document.getElementById('modalOverlay').style.display = 'none';
        }
        // Mở form Add Stock
function openAddStockForm(productId) {
    fetch('get-stock.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('stock_product_id').value = productId;
            document.getElementById('current_stock').value = data.stock ?? 0;
            document.getElementById('add_stock_quantity').value = '';
            document.getElementById('addStockModal').style.display = 'block';
            const overlay = document.getElementById('modalOverlay');
            if (overlay) overlay.style.display = 'block';
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Cannot load stock data.');
        });
}

// Đóng form Add Stock
function closeAddStockForm() {
    document.getElementById('addStockModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
}
// Close modals when clicking the overlay
document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            // Hide common modals if they are open
            const modals = ['addStockModal', 'editProductModal', 'addProductModal', 'deleteConfirmModal'];
            modals.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            overlay.style.display = 'none';
        });
    }
});
// Gửi form cập nhật stock
document.getElementById('addStockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('update-stock.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        closeAddStockForm();
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Failed to update stock.');
    });
});



    </script>
    <script>
        // Image preview function for Add Product form
        document.getElementById('image').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();

                // Create preview container if it doesn't exist
                let previewContainer = document.getElementById('addImagePreview');
                if (!previewContainer) {
                    previewContainer = document.createElement('div');
                    previewContainer.id = 'addImagePreview';
                    previewContainer.style.marginTop = '10px';
                    this.parentElement.appendChild(previewContainer);
                }

                reader.onload = function (e) {
                    previewContainer.innerHTML = `
                    <img src="${e.target.result}" 
                         style="max-width: 200px; 
                                max-height: 150px; 
                                object-fit: cover; 
                                border-radius: 4px; 
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                `;
                }
                reader.readAsDataURL(file);
            }
        });

        // Image preview function for Edit Product form
        document.getElementById('edit_image').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                const currentPreview = document.getElementById('currentImagePreview');

                reader.onload = function (e) {
                    currentPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
    <script>
        // function previewAdditionalImages(input, previewContainerId) {
        //     const container = document.getElementById(previewContainerId);
        //     container.innerHTML = ''; // Clear existing previews

        //     if (input.files && input.files.length > 0) {
        //         Array.from(input.files).forEach((file, index) => {
        //             const reader = new FileReader();
        //             const previewItem = document.createElement('div');
        //             previewItem.className = 'image-preview-item';

        //             reader.onload = function (e) {
        //                 previewItem.innerHTML = `
        //                                             <img src="${e.target.result}" alt="Preview ${index + 1}">
        //                                             <button type="button" class="remove-preview" onclick="removePreview(this, '${containerId}', ${index})">
        //                                                 <i class="fa-solid fa-times" style="width: 0 !important;"></i>
        //                                             </button>
        //                                         `;
        //             }

        //             reader.readAsDataURL(file);
        //             container.appendChild(previewItem);
        //         });
        //     }
        // }

        function removePreview(button) {
            button.closest('.image-preview-item').remove();
        }

        // function loadExistingImages(productId) {
        //     fetch(`get_product_images.php?id=${productId}`)
        //         .then(response => response.json())
        //         .then(data => {
        //             const container = document.getElementById('currentAdditionalImages');
        //             container.innerHTML = '';

        //             if (data.images.length > 0) {
        //                 data.images.forEach((image, index) => {
        //                     const previewItem = document.createElement('div');
        //                     previewItem.className = 'image-preview-item';
        //                     previewItem.innerHTML = `
        //                 <img src="../User/${image.image_url}" alt="Product Image ${index + 1}">
        //                 <button type="button" class="remove-image" 
        //                         onclick="deleteProductImage(${image.image_id}, this)">
        //                     <i class="fa-solid fa-times"></i>
        //                 </button>
        //                 <span class="preview-count">${index + 1}/${data.images.length}</span>
        //             `;
        //                     container.appendChild(previewItem);
        //                 });
        //             } else {
        //                 container.innerHTML = '<div class="empty-preview-message">No additional images</div>';
        //             }
        //         });
        // }


        function deleteProductImage(imageId, button) {
            // Hiển thị xác nhận
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('delete_product_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ image_id: imageId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Xóa phần tử hình ảnh khỏi DOM
                            button.closest('.image-preview-item').remove();

                            // Hiển thị thông báo thành công
                            showNotification('Image deleted successfully', 'success');
                        } else {
                            // Hiển thị thông báo lỗi
                            showNotification(data.message || 'Error deleting image', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Network error occurred', 'error');
                    });
            }
        }

        function closeEditProductForm() {
            document.getElementById('editProductModal').style.display = 'none';
            document.getElementById('modalOverlay').style.display = 'none';
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Xử lý xem trước cho hình ảnh bổ sung trong form thêm sản phẩm
            const additionalImagesInput = document.getElementById('additional_images');
            if (additionalImagesInput) {
                additionalImagesInput.addEventListener('change', function () {
                    previewAdditionalImages(this, 'addImagesPreview');
                });
            }
            // For Edit Product Form
            document.getElementById('edit_additional_images').addEventListener('change', function (e) {
                previewAdditionalImages(this, 'editImagesPreview');
            });
        });



        function previewAdditionalImages(input, containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = ''; // Xóa xem trước hiện có

            if (input.files && input.files.length > 0) {
                const fragment = document.createDocumentFragment();

                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    const previewItem = document.createElement('div');
                    previewItem.className = 'image-preview-item';

                    reader.onload = function (e) {
                        previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <button type="button" class="remove-preview" onclick="removePreview(this, '${containerId}', ${index})">
                        <i class="fa-solid fa-times"></i>
                    </button>
                    <span class="preview-count">${index + 1}/${input.files.length}</span>
                `;
                    };

                    reader.readAsDataURL(file);
                    fragment.appendChild(previewItem);
                });

                container.appendChild(fragment);
            } else {
                container.innerHTML = '<div class="empty-preview-message"><i class="fa-solid fa-image"></i> No additional images selected</div>';
            }
        }

        function removePreview(button, containerId, index) {
            const container = document.getElementById(containerId);
            const fileInput = containerId === 'addImagesPreview' ?
                document.getElementById('additional_images') :
                document.getElementById('edit_additional_images');

            // Xóa phần tử xem trước
            button.closest('.image-preview-item').remove();

            // Tạo FileList mới không có file đã xóa
            const dt = new DataTransfer();
            Array.from(fileInput.files).forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });
            fileInput.files = dt.files;

            // Nếu không còn file nào, hiển thị thông báo
            if (dt.files.length === 0) {
                container.innerHTML = '<div class="empty-preview-message"><i class="fa-solid fa-image"></i> No additional images selected</div>';
            } else {
                // Cập nhật lại số thứ tự
                const previewItems = container.querySelectorAll('.image-preview-item');
                previewItems.forEach((item, i) => {
                    const countSpan = item.querySelector('.preview-count');
                    if (countSpan) {
                        countSpan.textContent = `${i + 1}/${dt.files.length}`;
                    }
                });
            }
        }

    </script>
</body>

</html>
<?php
include 'footer.php';