<?php
// ---------------- CẤU HÌNH KẾT NỐI ----------------
$host = "localhost";
$user = "root";
$password = "";
$dbname = "webbanoto";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// ---------------- XỬ LÝ KHOẢNG NGÀY ----------------
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// ---------------- TRUY VẤN THỐNG KÊ ----------------
$sql = "SELECT p.product_id, p.car_name, p.brand_id, p.price,
               SUM(od.quantity) AS sold_quantity,
               SUM(od.quantity * od.price) AS total_revenue
        FROM order_details od
        JOIN orders o ON od.order_id = o.order_id
        JOIN products p ON od.product_id = p.product_id
        WHERE o.order_date BETWEEN ? AND ?
        GROUP BY p.product_id, p.car_name, p.brand_id, p.price
        ORDER BY sold_quantity DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thống kê sản phẩm bán chạy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    background: #f9fafb;
    font-family: 'Segoe UI', sans-serif;
}
.container {
    margin-top: 50px;
}
.card {
    border-radius: 15px;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}
h2 {
    text-align: center;
    margin-bottom: 30px;
    font-weight: 700;
    color: #333;
}
table th {
    background-color: #007bff;
    color: white;
}
</style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h2>📊 Thống kê sản phẩm bán chạy</h2>

        <!-- Form chọn khoảng ngày -->
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Từ ngày</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Thống kê</button>
            </div>
        </form>

        <?php if (count($products) > 0): ?>
            <!-- Bảng thống kê -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle text-center">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên xe</th>
                            <th>Hãng (ID)</th>
                            <th>Giá</th>
                            <th>Số lượng đã bán</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= $p['product_id'] ?></td>
                            <td><?= htmlspecialchars($p['car_name']) ?></td>
                            <td><?= $p['brand_id'] ?></td>
                            <td><?= number_format($p['price'], 0, ',', '.') ?> VND</td>
                            <td><?= $p['sold_quantity'] ?></td>
                            <td><b><?= number_format($p['total_revenue'], 0, ',', '.') ?> VND</b></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Biểu đồ -->
            <canvas id="chart" height="120"></canvas>
            <script>
                const ctx = document.getElementById('chart');
                const chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($products, 'car_name')) ?>,
                        datasets: [{
                            label: 'Số lượng bán ra',
                            data: <?= json_encode(array_column($products, 'sold_quantity')) ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: '#007bff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Số lượng bán' }
                            },
                            x: {
                                title: { display: true, text: 'Tên sản phẩm' }
                            }
                        }
                    }
                });
            </script>
        <?php else: ?>
            <div class="alert alert-warning text-center">Không có dữ liệu trong khoảng thời gian đã chọn.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
