<?php
include 'header.php';

// Xử lý cập nhật tồn kho
if (isset($_POST['update_stock']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['new_stock']);

    $stmt = mysqli_prepare($connect, "UPDATE products SET stock = ? WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $new_stock, $product_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['notification'] = ['message' => '✅ Cập nhật tồn kho thành công!', 'type' => 'success'];
    } else {
        $_SESSION['notification'] = ['message' => '❌ Cập nhật thất bại!', 'type' => 'error'];
    }
    echo "<script>window.location.href='manage-inventory.php';</script>";
    exit();
}

// Lọc và sắp xếp
$where = [];
$params = [];
$types = "";

// Lọc theo brand_id
if (!empty($_GET['category'])) {
    $where[] = "brand_id = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

// Lọc theo mức tồn kho
if (!empty($_GET['stock_filter'])) {
    if ($_GET['stock_filter'] == 'low') $where[] = "stock < 10";
    if ($_GET['stock_filter'] == 'out') $where[] = "stock = 0";
}

// Câu query cơ bản
$query = "SELECT * FROM products";
if ($where) $query .= " WHERE " . implode(" AND ", $where);

// Sắp xếp
$sort = $_GET['sort'] ?? 'id_asc';
switch ($sort) {
    case 'price_desc': $query .= " ORDER BY price DESC"; break;
    case 'price_asc': $query .= " ORDER BY price ASC"; break;
    case 'stock_desc': $query .= " ORDER BY stock DESC"; break;
    case 'stock_asc': $query .= " ORDER BY stock ASC"; break;
    default: $query .= " ORDER BY product_id ASC";
}

$stmt = mysqli_prepare($connect, $query);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Hiển thị thông báo
if (isset($_SESSION['notification'])) {
    echo "<script>
        showNotification('" . addslashes($_SESSION['notification']['message']) . "', '" . $_SESSION['notification']['type'] . "');
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

        .edit-btn {
            background: #1abc9c; color: white; border: none;
            padding: 8px 12px; border-radius: 4px; cursor: pointer;
        }
        .edit-btn:hover { background: #16a085; }

        .filter-container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .filter-group label { font-weight: 600; color: #34495e; }
        .filter-group select {
            width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;
        }
        .filter-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; }
        .filter-btn { background: #1abc9c; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .reset-btn { background: #bdc3c7; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }

        /* Modal */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%;
                 background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
        .modal-content { background:white; padding:20px; border-radius:8px; width:400px; text-align:center; }
        .modal input { width:100%; padding:8px; margin:10px 0; border-radius:4px; border:1px solid #ccc; }
        .modal button { margin-top:10px; padding:8px 12px; border:none; border-radius:4px; cursor:pointer; }
        .save-btn { background:#1abc9c; color:white; }
        .cancel-btn { background:#95a5a6; color:white; }
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
                        $cat_query = mysqli_query($connect, "SELECT DISTINCT brand_id FROM products");
                        while ($cat = mysqli_fetch_assoc($cat_query)) {
                            $selected = ($_GET['category'] ?? '') == $cat['brand_id'] ? 'selected' : '';
                            echo "<option value='{$cat['brand_id']}' $selected>Hãng #{$cat['brand_id']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tình trạng tồn kho:</label>
                    <select name="stock_filter">
                        <option value="">Tất cả</option>
                        <option value="low" <?= ($_GET['stock_filter'] ?? '') == 'low' ? 'selected' : '' ?>>Dưới 10</option>
                        <option value="out" <?= ($_GET['stock_filter'] ?? '') == 'out' ? 'selected' : '' ?>>Hết hàng</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Sắp xếp:</label>
                    <select name="sort">
                        <option value="price_desc">Giá cao → thấp</option>
                        <option value="price_asc">Giá thấp → cao</option>
                        <option value="stock_desc">Tồn kho nhiều → ít</option>
                        <option value="stock_asc">Tồn kho ít → nhiều</option>
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
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $p['product_id'] ?></td>
                        <td><?= htmlspecialchars($p['car_name']) ?></td>
                        <td><?= htmlspecialchars($p['brand_id']) ?></td>
                        <td><?= number_format($p['price'], 0, ',', '.') ?></td>
                        <td><?= $p['stock'] ?></td>
                        <td>
                            <?php
                                if ($p['stock'] == 0)
                                    echo "<span class='out-stock'>Hết hàng</span>";
                                elseif ($p['stock'] < 10)
                                    echo "<span class='low-stock'>Sắp hết</span>";
                                else
                                    echo "<span class='good-stock'>Còn hàng</span>";
                            ?>
                        </td>
                        <td>
                            <button class="edit-btn" onclick="openModal(<?= $p['product_id'] ?>, <?= $p['stock'] ?>)">Chỉnh sửa</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal chỉnh sửa tồn kho -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <h3>Cập nhật tồn kho</h3>
            <form method="POST" action="manage-inventory.php">
                <input type="hidden" name="product_id" id="modal_product_id">
                <input type="number" name="new_stock" id="modal_stock" min="0" required>
                <div>
                    <button type="submit" name="update_stock" class="save-btn">Lưu</button>
                    <button type="button" onclick="closeModal()" class="cancel-btn">Hủy</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, stock) {
            document.getElementById('modal_product_id').value = id;
            document.getElementById('modal_stock').value = stock;
            document.getElementById('stockModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('stockModal').style.display = 'none';
        }
        window.onclick = function(e) {
            if (e.target.id === 'stockModal') closeModal();
        }
    </script>
</body>
</html>

<?php include 'footer.php'; ?>
