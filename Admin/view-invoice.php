<?php
include 'header.php';

if (!isset($_GET['id'])) {
    header('Location: statics.php');
    exit;
}

$order_id = intval($_GET['id']);

// Comprehensive order query
$query = "
    SELECT 
        o.*,
        u.username, u.full_name, u.email, u.phone_num, u.address,
        p.car_name, p.image_link, p.color, p.year_manufacture, 
        p.seat_number, p.fuel_name,
        ct.type_name,
        pm.method_name,
        od.quantity,
        od.price AS unit_price
    FROM orders o
    JOIN users_acc u ON o.user_id = u.id
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    JOIN car_types ct ON p.brand_id = ct.type_id
    JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    WHERE o.order_id = ?
";


$stmt = mysqli_prepare($connect, $query);
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo "<script>
        showNotification('Order not found', 'error');
        window.location.href = 'statics.php';
    </script>";
    exit;
}

// Fetch all order data
$orderData = mysqli_fetch_all($result, MYSQLI_ASSOC);
$order = $orderData[0]; // Basic order info
// ===== Amounts (use order values, fallback to computed) =====
$itemsSubtotal = 0.0;
foreach ($orderData as $it) {
    $itemsSubtotal += (float)$it['unit_price'] * (int)$it['quantity'];
}

$subtotal  = is_null($order['expected_total_amount']) ? $itemsSubtotal : (float)$order['expected_total_amount'];
$vat       = is_null($order['VAT'])                   ? round($subtotal * 0.10, 2) : (float)$order['VAT'];
$shipping  = is_null($order['shipping_fee'])          ? 0.00                       : (float)$order['shipping_fee'];
$total     = is_null($order['total_amount'])          ? ($subtotal + $vat + $shipping) : (float)$order['total_amount'];

function vnd($n){ return number_format((float)$n, 0, ',', '.') . ' VND'; }

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Details</title>
    <!-- <link rel="stylesheet" href="wi.css"> -->
    <link rel="icon" href="../User/dp56vcf7.png" type="image/png">

    <!-- <script src="invoice.js" defer></script> -->
</head>
<style>
    .invoice-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 30px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        color: #ecf0f1;
    }

    .invoice-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .invoice-header h1 {
        color: #1abc9c;
        font-size: 28px;
        margin-bottom: 10px;
    }

    .invoice-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .detail-item {
        padding: 10px;
        background: rgba(44, 62, 80, 0.3);
        border-radius: 6px;
        transition: transform 0.3s ease;
    }

    .detail-item:hover {
        transform: translateY(-2px);
    }

    .detail-item strong {
        color: #1abc9c;
        display: block;
        margin-bottom: 5px;
    }

    .product-list {
        list-style: none;
        padding: 0;
        margin: 20px 0;
    }

    .product-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(44, 62, 80, 0.3);
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .product-item:hover {
        transform: translateX(5px);
        background: rgba(44, 62, 80, 0.5);
    }

    .total-amount {
        text-align: right;
        font-size: 20px;
        margin: 20px 0;
        padding: 20px;
        background: rgba(26, 188, 156, 0.2);
        border-radius: 6px;
    }

    .back-btn {
        background: #2c3e50;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
    }

    .back-btn:hover {
        background: #34495e;
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .invoice-container {
            margin: 20px;
            padding: 20px;
        }

        .invoice-details {
            grid-template-columns: 1fr;
        }
    }
</style>
<style>
    .invoice-container {
        /* override dark theme */ 
        background: rgba(20, 30, 48, 0.7);
        border: 1px solid rgba(100, 181, 246, 0.2);
        color: #e0e0e0;
    }

    .invoice-header {
        /* override dark theme */
        border-bottom: 2px solid rgba(100, 181, 246, 0.2);
    }


    .invoice-header h1 {
        color: #64B5F6;
        text-shadow: 0 0 10px rgba(100, 181, 246, 0.3);
    }

    .detail-item {
        background: rgba(25, 35, 55, 0.6);
        border: 1px solid rgba(100, 181, 246, 0.1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    }

    .detail-item strong {
        color: #64B5F6;
    }

    .product-item {
        background: rgba(25, 35, 55, 0.6);
        border: 1px solid rgba(100, 181, 246, 0.1);
    }

    .product-item:hover {
        background: rgba(30, 40, 60, 0.8);
        border: 1px solid rgba(100, 181, 246, 0.3);
    }

    .total-amount {
        background: rgba(100, 181, 246, 0.1);
        border: 1px solid rgba(100, 181, 246, 0.2);
    }

    .back-btn {
        background: linear-gradient(135deg, #1976D2, #2196F3);
        border: 1px solid rgba(100, 181, 246, 0.3);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    }

    .back-btn:hover {
        background: linear-gradient(135deg, #1E88E5, #42A5F5);
        box-shadow: 0 6px 12px rgba(33, 150, 243, 0.3);
    }

    /* Add subtle glow effects */
    .detail-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(100, 181, 246, 0.2);
        border: 1px solid rgba(100, 181, 246, 0.3);
    }

    /* Add glass morphism effect */
    .invoice-container,
    .detail-item,
    .product-item {
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
</style>
<style>
    /* Base styles */
    .invoice-container {
        max-width: 1000px;
        margin: 40px auto;
        padding: 30px;
        background: #ffffff;
        border-radius: 15px;
        box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
        color: #333333;
    }

    .invoice-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
    }

    .invoice-header h1 {
        color: #2c3e50;
        font-size: 28px;
        margin-bottom: 10px;
    }

    .invoice-header h2 {
        color: #1abc9c;
    }

    /* Section styles */
    section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    section h3 {
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    section h3 i {
        color: #1abc9c;
    }

    /* Grid and items */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin: 15px 0;
    }

    .info-item {
        background: #ffffff;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .info-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .info-item strong {
        color: #2c3e50;
        display: block;
        margin-bottom: 5px;
    }

    /* Products table */
    .products-table table {
        width: 100%;
        border-collapse: collapse;
        background: #ffffff;
        border-radius: 8px;
        overflow: hidden;
    }

    .products-table th {
        background: #1abc9c;
        color: #ffffff;
        padding: 12px;
        text-align: left;
    }

    .products-table td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
    }

    .car-thumbnail {
        width: 120px;
        height: 80px;
        object-fit: cover;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Status badges */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 500;
    }

    .status-initiated {
        background: #e3f2fd;
        color: #1976d2;
    }

    .status-is-pending {
        background: #fff3e0;
        color: #f57c00;
    }

    .status-is-confirmed {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-is-delivering {
        background: #ede7f6;
        color: #5e35b1;
    }

    .status-completed {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-cancelled {
        background: #ffebee;
        color: #c62828;
    }

    /* Payment summary */
    .summary-grid {
        background: #ffffff;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .summary-item.total {
        border-top: 2px solid #1abc9c;
        border-bottom: none;
        padding-top: 20px;
        font-size: 1.2em;
        color: #1abc9c;
    }

    /* Back button */
    .back-btn {
        background: #1abc9c;
        color: #ffffff;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
    }

    .back-btn:hover {
        background: #16a085;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(26, 188, 156, 0.3);
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .invoice-container {
            margin: 20px;
            padding: 20px;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .products-table {
            overflow-x: auto;
        }
    }
</style>

<body>

    <div class="invoice-container">
        <div class="invoice-header">
            <h1>Invoice Details</h1>
            <h2>Invoice #<?php echo $order_id; ?></h2>
        </div>

        <!-- Customer Information -->
        <section class="customer-info">
            <h3><i class="fas fa-user-circle"></i> Customer Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Name:</strong>
                    <span><?php echo htmlspecialchars($order['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Phone:</strong>
                    <span><?php echo htmlspecialchars($order['phone_num']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Address:</strong>
                    <span><?php echo htmlspecialchars($order['address']); ?></span>
                </div>
            </div>
        </section>

        <!-- Order Details -->
        <section class="order-details">
            <h3><i class="fas fa-shopping-cart"></i> Order Details</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Order Date:</strong>
                    <span><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></span>
                </div>
                <div class="info-item">
                    <strong>Status:</strong>
                    <span class="status-badge status-<?= str_replace(' ', '-', strtolower($order['order_status'])) ?>">
                        <?php
                        switch ($order['order_status']) {
                            case 'initiated':      echo 'Initiated'; break;
                            case 'is pending':     echo 'Is pending'; break;
                            case 'is confirmed':   echo 'Is confirmed'; break;
                            case 'is delivering':  echo 'Is delivering'; break;
                            case 'delivered':      echo 'Delivered'; break;
                            case 'completed':      echo 'Completed'; break;
                            case 'cancelled':      echo 'Cancelled'; break;
                            default:               echo htmlspecialchars($order['order_status']);
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>Payment Method:</strong>
                    <span><?php echo htmlspecialchars($order['method_name']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Shipping Address:</strong>
                    <span><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Distance:</strong>
                    <span><?php echo number_format((float)$order['distance'], 2, ',', '.'); ?> km</span>
                </div>
            </div>
        </section>

        <!-- Products List -->
        <section class="products-section">
            <h3><i class="fas fa-box"></i> Products</h3>
            <div class="products-table">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Car Details</th>
                            <th>Specifications</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderData as $item): ?>
                            <tr>
                                <td>
                                    <img src="../User/<?php echo htmlspecialchars($item['image_link']); ?>"
                                        alt="<?php echo htmlspecialchars($item['car_name']); ?>" class="car-thumbnail">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['car_name']); ?></strong>
                                    <br>
                                    <small>Brand: <?php echo htmlspecialchars($item['type_name']); ?></small>
                                    <br>
                                    <small>Year: <?php echo htmlspecialchars($item['year_manufacture']); ?></small>
                                </td>
                                <td>
                                    <small>Color: <?php echo htmlspecialchars($item['color']); ?></small>
                                    <br>
                                    <small>Seats: <?php echo htmlspecialchars($item['seat_number']); ?></small>
                                    <br>
                                    <small>Fuel: <?php echo htmlspecialchars($item['fuel_name']); ?></small>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo number_format($item['unit_price'], 0, ',', '.'); ?> VND</td>
                                <td><?php echo number_format($item['unit_price'] * $item['quantity'], 0, ',', '.'); ?> VND</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Payment Summary -->
        <section class="payment-summary">
            <h3><i class="fas fa-file-invoice-dollar"></i> Payment Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <span>Subtotal:</span>
                    <span><?php echo vnd($subtotal); ?></span>
                </div>
                <div class="summary-item">
                    <span>VAT (10%):</span>
                    <span><?php echo vnd($vat); ?></span>
                </div>
                <div class="summary-item">
                    <span>Shipping Fee:</span>
                    <span><?php echo vnd($shipping); ?></span>
                </div>
                <div class="summary-item total">
                    <strong>Total Amount:</strong>
                    <strong><?php echo vnd($total); ?></strong>
                </div>
            </div>
        </section>


        <!-- Action bar -->
        <div class="invoice-actions">
        <!-- Trái: về Statistics -->
        <button class="back-btn" onclick="window.location.href='statics.php'">
            <i class="fas fa-arrow-left"></i> Back to Statistics
        </button>

        <!-- Giữa: In / Xuất hóa đơn -->
        <button class="back-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Export
        </button>

        <!-- Phải: sang Manage Orders (mũi tên phải) -->
        <button class="back-btn" onclick="window.location.href='manage-orders.php'">
            Manage Orders <i class="fas fa-arrow-right"></i>
        </button>
        </div>

    </div>

    <style>
        /* Add your existing styles here and these new ones: */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 15px 0;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .products-table table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .products-table th,
        .products-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .car-thumbnail {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .summary-grid {
            display: grid;
            gap: 10px;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 8px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-item.total {
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            border-bottom: none;
            padding-top: 20px;
            font-size: 1.2em;
        }

        section {
            margin-bottom: 30px;
        }

        section h3 {
            color: #64B5F6;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
        }

        /* Thanh nút 3 cột: trái – giữa – phải */
        .invoice-actions{
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 12px;
        margin-top: 24px;
        }
        .invoice-actions > :first-child{ justify-self: start; }
        .invoice-actions > :nth-child(2){ justify-self: center; }
        .invoice-actions > :last-child{ justify-self: end; }

        /* Ẩn thanh nút & khung site khi in */
        @media print{
        .invoice-actions,
        .navbar,
        header, footer { display: none !important; }
        body{ background:#fff; }
        .invoice-container{
            box-shadow: none !important;
            margin: 0 !important;
            max-width: 100% !important;
        }
        }

    </style>

</body>
<script>
    document.getElementById('back-btn').addEventListener('click', function () {
        window.location.href = 'statics.php';
    });

    // Add fade-in animation on load
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('.invoice-container');
        container.style.opacity = '0';
        container.style.transform = 'translateY(20px)';

        setTimeout(() => {
            container.style.transition = 'all 0.5s ease';
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, 100);
    });
</script>

</html>
<?php
include 'footer.php';
?>