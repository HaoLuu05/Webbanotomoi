<?php
include 'header.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Nếu header.php đã khởi tạo $connect thì không cần làm gì, nếu chưa hãy đảm bảo kết nối DB có biến $connect
// ví dụ: $connect = mysqli_connect('localhost','username','password','webbanoto');

// Xử lý cập nhật tồn kho (vẫn giữ phần này theo yêu cầu)
if (isset($_POST['update_stock']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['new_stock']);

    $stmt = mysqli_prepare($connect, "UPDATE products SET remain_quantity = ? WHERE product_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $new_stock, $product_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['notification'] = ['message' => '✅ Cập nhật tồn kho thành công!', 'type' => 'success'];
        } else {
            $_SESSION['notification'] = ['message' => '❌ Cập nhật thất bại!', 'type' => 'error'];
        }
        mysqli_stmt_close($stmt);
    } else {
        // fallback
        $q = "UPDATE products SET remain_quantity = " . $new_stock . " WHERE product_id = " . $product_id;
        if (mysqli_query($connect, $q)) {
            $_SESSION['notification'] = ['message' => '✅ Cập nhật tồn kho thành công!', 'type' => 'success'];
        } else {
            $_SESSION['notification'] = ['message' => '❌ Cập nhật thất bại!', 'type' => 'error'];
        }
    }

    echo "<script>window.location.href='manage-inventory.php';</script>";
    exit();
}

// Lọc và sắp xếp
$where = [];
$params = [];
$types = "";

// Lọc theo brand_id (category) - brand_id là số trong DB (tham số int)
if (!empty($_GET['category'])) {
    $cat = intval($_GET['category']);
    $where[] = "brand_id = ?";
    $params[] = $cat;
    $types .= "i";
}

// Lọc theo mức tồn kho (dùng remain_quantity trong DB)
if (!empty($_GET['stock_filter'])) {
    if ($_GET['stock_filter'] == 'low') $where[] = "remain_quantity < 10";
    if ($_GET['stock_filter'] == 'out') $where[] = "remain_quantity = 0";
}

// Câu query: alias remain_quantity thành stock để giữ giao diện cũ
$query = "SELECT *, remain_quantity AS stock FROM products";
if ($where) $query .= " WHERE " . implode(" AND ", $where);

// Sắp xếp
$sort = $_GET['sort'] ?? 'id_asc';
switch ($sort) {
    case 'price_desc': $query .= " ORDER BY price DESC"; break;
    case 'price_asc': $query .= " ORDER BY price ASC"; break;
    case 'stock_desc': $query .= " ORDER BY remain_quantity DESC"; break;
    case 'stock_asc': $query .= " ORDER BY remain_quantity ASC"; break;
    default: $query .= " ORDER BY product_id ASC";
}

// Prepare + execute (vẫn dùng prepare nếu có params)
if ($stmt = mysqli_prepare($connect, $query)) {
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    // nếu prepare lỗi, fallback dùng mysqli_query
    $result = mysqli_query($connect, $query);
    if ($result === false) {
        die("Lỗi query: " . mysqli_error($connect));
    }
}

// Hiển thị thông báo (notification)
if (isset($_SESSION['notification'])) {
    echo "<script>
        if (typeof showNotification === 'function') {
            showNotification('" . addslashes($_SESSION['notification']['message']) . "', '" . $_SESSION['notification']['type'] . "');
        } else {
            alert('" . addslashes($_SESSION['notification']['message']) . "');
        }
    </script>";
    unset($_SESSION['notification']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý hàng tồn kho</title>
    <link rel="icon" href="../User/dp56vcf7.png" type="image/png">
    <script src="https://kit.fontawesome.com/8341c679e5.js" crossorigin="anonymous"></script>
    <style>
        /* Giữ nguyên style của bạn (không đổi giao diện) */
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .admin-section {
            margin: 20px; padding: 20px; background: white; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; }
        .low-stock { color: #e67e22; font-weight: bold; }
        .out-stock { color: #c0392b; font-weight: bold; }
        .good-stock { color: #27ae60; font-weight: bold; }

        .filter-container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .filter-group label { font-weight: 600; color: #34495e; }
        .filter-group select {
            width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;
        }
        .filter-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; }
        .filter-btn { background: #1abc9c; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .reset-btn { background: #bdc3c7; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="admin-section filter-container">
        <h2><i class="fas fa-boxes"></i> Bộ lọc hàng tồn kho</h2>
        <form method="GET" action="manage-inventory.php">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Hãng xe (Brand ID):</label>
                    <select name="category">
                        <option value="">Tất cả</option>
                        <?php
                        // Lấy danh sách hãng từ car_types (cột type_id, type_name)
                        $cat_query = mysqli_query($connect, "SELECT type_id, type_name FROM car_types");
                        while ($cat = mysqli_fetch_assoc($cat_query)) {
                            $val = $cat['type_id'];
                            $label = htmlspecialchars($cat['type_name']);
                            $selected = (isset($_GET['category']) && intval($_GET['category']) == $val) ? 'selected' : '';
                            echo "<option value='{$val}' $selected>{$label} (ID {$val})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tình trạng tồn kho:</label>
                    <select name="stock_filter">
                        <option value="">Tất cả</option>
                        <option value="low" <?= (isset($_GET['stock_filter']) && $_GET['stock_filter'] === 'low') ? 'selected' : '' ?>>Dưới 10</option>
                        <option value="out" <?= (isset($_GET['stock_filter']) && $_GET['stock_filter'] === 'out') ? 'selected' : '' ?>>Hết hàng</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Sắp xếp:</label>
                    <select name="sort">
                        <option value="price_desc" <?= (isset($_GET['sort']) && $_GET['sort']=='price_desc')?'selected':'' ?>>Giá cao → thấp</option>
                        <option value="price_asc" <?= (isset($_GET['sort']) && $_GET['sort']=='price_asc')?'selected':'' ?>>Giá thấp → cao</option>
                        <option value="stock_desc" <?= (isset($_GET['sort']) && $_GET['sort']=='stock_desc')?'selected':'' ?>>Tồn kho nhiều → ít</option>
                        <option value="stock_asc" <?= (isset($_GET['sort']) && $_GET['sort']=='stock_asc')?'selected':'' ?>>Tồn kho ít → nhiều</option>
                    </select>
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Lọc</button>
                <a href="manage-inventory.php" class="reset-btn"><i class="fas fa-undo"></i> Đặt lại</a>
            </div>
        </form>
    </div>

    <div class="admin-section">
        <h2><i class="fas fa-warehouse"></i> Quản lý hàng tồn kho</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên xe</th>
                    <th>Hãng (Brand ID)</th>
                    <th>Giá (₫)</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($p = mysqli_fetch_assoc($result)):
                    $product_id = isset($p['product_id']) ? $p['product_id'] : '';
                    $car_name = isset($p['car_name']) ? htmlspecialchars($p['car_name']) : '';
                    $brand_id = isset($p['brand_id']) ? htmlspecialchars($p['brand_id']) : '';
                    $price = isset($p['price']) ? number_format($p['price'], 0, ',', '.') : '0';
                    // Vì đã alias remain_quantity AS stock trong SELECT, ta có $p['stock']
                    $stock = isset($p['stock']) ? intval($p['stock']) : 0;
                ?>
                    <tr>
                        <td><?= $product_id ?></td>
                        <td><?= $car_name ?></td>
                        <td><?= $brand_id ?></td>
                        <td><?= $price ?></td>
                        <td><?= $stock ?></td>
                        <td>
                            <?php
                                if ($stock === 0)
                                    echo "<span class='out-stock'>Hết hàng</span>";
                                elseif ($stock < 10)
                                    echo "<span class='low-stock'>Sắp hết</span>";
                                else
                                    echo "<span class='good-stock'>Còn hàng</span>";
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</body>
</html>

<?php include 'footer.php'; ?>
