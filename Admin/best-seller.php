<?php
include 'header.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------- XỬ LÝ KHOẢNG NGÀY VÀ LOẠI BÁO CÁO -------------------
$report_type = $_GET['report_type'] ?? 'month'; // month hoặc year

if($report_type === 'year') {
    $year = $_GET['year'] ?? date('Y');
    $from_date = "$year-01-01";
    $to_date = "$year-12-31";
} else {
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
}

// ------------------- TRUY VẤN THỐNG KÊ BEST SELLER -------------------
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

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>📊 Thống kê sản phẩm bán chạy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: #f9fafb; font-family: 'Segoe UI', sans-serif; }
.container { margin-top: 50px; }
.card { border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
h2 { text-align: center; margin-bottom: 30px; font-weight: 700; color: #333; }
table th, table td { text-align: center; padding: 12px; border-bottom: 1px solid #eee; }
table th { background-color: #007bff; color: white; }
/* Chiều rộng cột chuẩn */
table th:nth-child(1), table td:nth-child(1) { min-width: 80px; }
table th:nth-child(2), table td:nth-child(2) { min-width: 200px; }
table th:nth-child(3), table td:nth-child(3) { min-width: 100px; }
table th:nth-child(4), table td:nth-child(4) { min-width: 120px; }
table th:nth-child(5), table td:nth-child(5) { min-width: 120px; }
table th:nth-child(6), table td:nth-child(6) { min-width: 140px; }
</style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h2>📊 Thống kê sản phẩm bán chạy</h2>

        <!-- Form chọn khoảng ngày & loại báo cáo -->
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Báo cáo theo</label>
                <select name="report_type" class="form-control" onchange="this.form.submit()">
                    <option value="month" <?= $report_type==='month'?'selected':'' ?>>Tháng</option>
                    <option value="year" <?= $report_type==='year'?'selected':'' ?>>Năm</option>
                </select>
            </div>
            <div class="col-md-3" id="month-picker" style="<?= $report_type==='year'?'display:none;':'' ?>">
                <label class="form-label">Từ ngày</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-3" id="month-picker-to" style="<?= $report_type==='year'?'display:none;':'' ?>">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-3" id="year-picker" style="<?= $report_type==='month'?'display:none;':'' ?>">
                <label class="form-label">Năm</label>
                <input type="number" name="year" min="2000" max="<?=date('Y')?>" class="form-control" value="<?= isset($year)?$year:date('Y') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
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
            <div style="overflow-x:auto; margin-top:25px;">
                <canvas id="chart" style="min-width:600px; height:180px;"></canvas>
            </div>

            <script>
                const ctx = document.getElementById('chart');
                const dataValues = <?= json_encode(array_column($products, 'sold_quantity')) ?>;
                const maxValue = Math.max(...dataValues);
                let stepSize = 1;
                if(maxValue > 10) stepSize = Math.ceil(maxValue / 10);

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($products, 'car_name')) ?>,
                        datasets: [{
                            label: 'Số lượng bán ra',
                            data: dataValues,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: '#007bff',
                            borderWidth: 1,
                            barThickness: 35,
                            maxBarThickness: 50
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                suggestedMax: maxValue,
                                ticks: { stepSize: stepSize, callback: v => Number(v) },
                                title: { display: true, text: 'Số lượng bán' }
                            },
                            x: { title: { display: true, text: 'Tên sản phẩm' } }
                        }
                    }
                });

                // Hiển thị/ẩn picker tháng/năm
                const reportSelect = document.querySelector('select[name="report_type"]');
                reportSelect.addEventListener('change', function(){
                    if(this.value==='year'){
                        document.getElementById('month-picker').style.display='none';
                        document.getElementById('month-picker-to').style.display='none';
                        document.getElementById('year-picker').style.display='block';
                    } else {
                        document.getElementById('month-picker').style.display='block';
                        document.getElementById('month-picker-to').style.display='block';
                        document.getElementById('year-picker').style.display='none';
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

<?php include 'footer.php'; ?>
