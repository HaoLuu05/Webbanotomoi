<?php
require_once '../connect.php';
session_start();

// Kiểm tra đăng nhập Admin
if (!isset($_SESSION['username']) || $_SESSION['status'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Truy vấn danh sách sản phẩm và hãng xe
$query = "
    SELECT p.product_id, p.product_name, b.brand_name, p.price, 
           p.sold_quantity, p.remain_quantity
    FROM products p
    JOIN brands b ON p.brand_id = b.brand_id
    ORDER BY p.product_id ASC
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý tồn kho sản phẩm</title>
    <link rel="icon" href="../User/dp56vcf7.png" type="image/png">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        h2 {
            text-align: center;
            margin: 25px 0;
            color: #2c3e50;
        }
        table {
            border-collapse: collapse;
            width: 90%;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: center;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #eaf3ff;
        }
        .out-stock {
            color: red;
            font-weight: bold;
        }
        .low-stock {
            color: orange;
            font-weight: bold;
        }
        .good-stock {
            color: green;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>📦 Quản lý tồn kho sản phẩm</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên sản phẩm</th>
                <th>Hãng</th>
                <th>Giá (VNĐ)</th>
                <th>Đã bán</th>
                <th>Còn lại</th>
                <th>Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($p['product_id']) ?></td>
                    <td><?= htmlspecialchars($p['product_name']) ?></td>
                    <td><?= htmlspecialchars($p['brand_name']) ?></td>
                    <td><?= number_format($p['price'], 0, ',', '.') ?> ₫</td>
                    <td><?= htmlspecialchars($p['sold_quantity']) ?></td>
                    <td><?= htmlspecialchars($p['remain_quantity']) ?></td>
                    <td>
                        <?php
                            if ($p['remain_quantity'] == 0)
                                echo "<span class='out-stock'>Hết hàng</span>";
                            elseif ($p['remain_quantity'] < 10)
                                echo "<span class='low-stock'>Sắp hết</span>";
                            else
                                echo "<span class='good-stock'>Còn hàng</span>";
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
