<?php
// Place all PHP logic at the top before any HTML output
include 'header.php';

// Lấy danh sách PM đang active
$pms_rs = $connect->query("
  SELECT payment_method_id, method_name, is_active
  FROM payment_methods
  WHERE is_active = 1
  ORDER BY method_name
");
$PMS = $pms_rs ? $pms_rs->fetch_all(MYSQLI_ASSOC) : [];

// Map tên -> type đơn giản (nếu bạn chưa có cột type/enum trong DB)
function pm_type_from_name($name){
    $n = strtolower(trim($name));
    if (strpos($n, 'cash') !== false || strpos($n, 'tiền mặt') !== false) return 'cash';
    if (strpos($n, 'credit') !== false || strpos($n, 'tín dụng') !== false) return 'credit';
    if (strpos($n, 'atm') !== false) return 'atm';
    // mở rộng nếu sau này có Momo/ZaloPay/Chuyển khoản...
    if (strpos($n, 'momo') !== false) return 'momo';
    if (strpos($n, 'zalo') !== false) return 'zalopay';
    if (strpos($n, 'bank') !== false || strpos($n,'chuyển khoản')!==false) return 'bank';
    return 'other';
}

if (!isset($_SESSION['user_id'])) {
    echo "<script>
        showNotification('Vui lòng đăng nhập để tiếp tục.','warning');
        window.location.href='login.php';
    </script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $order_id = $_SESSION['current_order_id'];
        $payment_method = mysqli_real_escape_string($connect, $_POST['payment_method']);
        // Kiểm tra PM có tồn tại & còn active
        $check_stmt = mysqli_prepare($connect, "
        SELECT payment_method_id, method_name, is_active
        FROM payment_methods
        WHERE payment_method_id = ? AND is_active = 1
        ");
        mysqli_stmt_bind_param($check_stmt, "i", $payment_method);
        mysqli_stmt_execute($check_stmt);
        $pm_rs = mysqli_stmt_get_result($check_stmt);
        $pm_row = mysqli_fetch_assoc($pm_rs);
        if (!$pm_row) {
            throw new Exception("Phương thức thanh toán không hợp lệ hoặc đã bị ẩn.");
        }

        // Xác định loại để validate fields
        $pm_type = pm_type_from_name($pm_row['method_name']);

        // Validate theo loại
        if ($pm_type === 'credit') {
            if (empty($_POST['credit_number']) || empty($_POST['credit_name']) ||
                empty($_POST['credit_expiry']) || empty($_POST['credit_cvv'])) {
                throw new Exception("Vui lòng điền đầy đủ thông tin thẻ tín dụng.");
            }
            $payment_details = [
                'card_holder' => $_POST['credit_name'],
                'card_number' => substr(preg_replace('/\D/','', $_POST['credit_number']), -4)
            ];
        } elseif ($pm_type === 'atm' || $pm_type === 'bank') {
            if (empty($_POST['atm_number']) || empty($_POST['atm_bank']) || empty($_POST['atm_name'])) {
                throw new Exception("Vui lòng điền đầy đủ thông tin thẻ/ATM.");
            }
            $payment_details = [
                'bank_name' => $_POST['atm_bank'],
                'account_name' => $_POST['atm_name'],
                'account_number' => substr(preg_replace('/\D/','', $_POST['atm_number']), -4)
            ];
        }
        // các loại khác (cash/momo/zalopay/other) không yêu cầu thêm gì

        $distance = floatval($_POST['distance']);
        $shipping_fee = floatval($_POST['shipping_fee']);

        // Validate payment method specific fields
        if ($payment_method == 2) { // Credit Card
            if (empty($_POST['credit_number']) || empty($_POST['credit_name']) || 
                empty($_POST['credit_expiry']) || empty($_POST['credit_cvv'])) {
                throw new Exception("Vui lòng điền đầy đủ thông tin thẻ tín dụng");
            }
            // Store payment details securely
            $payment_details = [
                'card_holder' => $_POST['credit_name'],
                'card_number' => substr($_POST['credit_number'], -4) // Only store last 4 digits
            ];
        } elseif ($payment_method == 3) { // ATM
            if (empty($_POST['atm_number']) || empty($_POST['atm_bank']) || 
                empty($_POST['atm_name'])) {
                throw new Exception("Vui lòng điền đầy đủ thông tin thẻ ATM");
            }
            // Store payment details securely
            $payment_details = [
                'bank_name' => $_POST['atm_bank'],
                'account_name' => $_POST['atm_name'],
                'account_number' => substr($_POST['atm_number'], -4) // Only store last 4 digits
            ];
        }

        mysqli_begin_transaction($connect);

        // Update order with payment information
        $update_order = "UPDATE orders SET 
            payment_method_id = ?,
            distance = ?,
            shipping_fee = ?,
            VAT = expected_total_amount * 0.1,
            total_amount = expected_total_amount + ? + (expected_total_amount * 0.1)
            WHERE order_id = ?";

        $stmt = mysqli_prepare($connect, $update_order);
        mysqli_stmt_bind_param($stmt, "idddi", 
            $payment_method,
            $distance,  
            $shipping_fee,
            $shipping_fee,
            $order_id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Không thể cập nhật thông tin thanh toán");
        }

        mysqli_commit($connect);

        // Redirect to review page
        echo "<script>
            showNotification('Cập nhật thông tin thanh toán thành công', 'success');
            setTimeout(() => { window.location.href = 'review.php'; }, 1500);
        </script>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($connect);
        echo "<script>
            showNotification('" . addslashes($e->getMessage()) . "', 'error');
        </script>";
    }
}

// Get user information for the map
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users_acc WHERE id = ?";
$stmt = mysqli_prepare($connect, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_info = mysqli_fetch_assoc($user_result);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán</title>
    <!-- <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="payment.css"> -->
    <script src="payment.js"></script>
    <link rel="icon" href="dp56vcf7.png" type="image/png">
    <script src="https://kit.fontawesome.com/8341c679e5.js" crossorigin="anonymous"></script>
    <!-- Add this in the head section -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

</head>
<style>
    /* Payment Page Container */
    .payment {
        padding: 40px 0;
        background-color: #efefef;
    }

    /* Progress Bar Section */
    .payment-top-wrap {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .payment-top {
        height: 2px;
        width: 70%;
        background-color: #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 30px auto;
        max-width: 840px;
        position: relative;
    }

    .payment-top-item {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #fff;
        position: relative;
        transition: all 0.3s ease;
        z-index: 2;
    }

    .payment-top-item i {
        color: #666;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }

    .payment-top-item.active {
        border-color: #007bff;
        background-color: #007bff;
    }

    .payment-top-item.active i {
        color: #fff;
    }

    /* Payment Content Layout */
    .payment-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        /* Split into two equal columns */
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Left Column - Payment Methods */
    .payment-methods-container {
        background: #fff;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .payment-method-item {
        margin-bottom: 20px;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .payment-method-item:hover {
        border-color: #007bff;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
    }

    .payment-method-item input[type="radio"] {
        margin-right: 10px;
    }

    .payment-method-item label {
        font-weight: 600;
        color: #2c3e50;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .payment-details {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px dashed #ddd;
    }

    .input-text {
        width: 100%;
        padding: 12px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        transition: border-color 0.3s ease;
    }

    .input-text:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }

    /* Right Column - Map Section */
    .map-section {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        height: fit-content;
    }

    #map {
        width: 100%;
        height: 400px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .route-info {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 14px;
    }

    .distance-info,
    .shipping-fee-info,.price-info {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 8px 0;
        color: #2c3e50;
    }

    /* Bottom Navigation */
    .links-container {
        grid-column: 1 / -1;
        /* Span full width */
        display: flex;
        justify-content: space-between;
        padding: 20px 0;
        align-items:flex-end;
        margin-top: 185px;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .payment-content {
            grid-template-columns: 1fr;
        }
    }

    /* Preserve Header Styles */
    .header-container {
        --header-bg: #f8f9fa;
        --header-text: #495057;
    }

    /* Add this to ensure proper spacing */
    main {
        padding: 0;
        margin: 0;
    }
</style>
<!-- <style>
.eight {
    height: 100px;
    background-color: #efefef;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}
    /* Title Container */
    .eight {
        height: 100px;
        background-color: #efefef;
        /* border-radius: 10px; */
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 30px;
        width:1200px;
        margin-left:345px;
    }
    
    /* Title Styling */
    .eight h1 {
        padding-top: 30px;
        text-align: center;
        text-transform: uppercase;
        font-size: 26px;
        letter-spacing: 1px;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        grid-template-rows: 16px 0;
        grid-gap: 22px;
        color:rgb(172, 172, 172);
        font-family: Arial, Helvetica, sans-serif;
        background-color: #efefef;
        
        color: #2c3e50;
    }
    
    /* Title Lines */
    .eight h1:after,
    .eight h1:before {
        content: " ";
        display: block;
        border-bottom: 2px solid #ccc;
        background-color: #efefef;
    } -->
</style>

<style>
    /* Add these styles to your existing CSS */
    .links-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .payment-content-right-button {
        display: flex;
        gap: 20px;
        margin-top: 30px;
        width: 100%;
    }

    .return-btn {
        padding: 12px 24px;
        background-color: #6c757d;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pay-btn {
        padding: 12px 24px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .return-btn:hover,
    .pay-btn:hover {
        transform: translateY(-2px);
    }

    .return-btn:hover {
        background-color: #5a6268;
    }

    .pay-btn:hover {
        background-color: #0056b3;
    }
</style>
<style>
    /* Title Container */
    .eight {
        height: 100px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin: 20px auto;
        max-width: 1200px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Title Styling */
    .eight h1 {
        position: relative;
        padding: 0;
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 600;
        color: #2c3e50;
        font-size: 26px;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
    }

    /* Add underline accent */
    .eight h1::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background-color: #007bff;
        border-radius: 2px;
    }

    /* Remove old title lines */
    .eight h1:before,
    .eight h1:after {
        content: none;
    }
</style>
<style>
    /* Title Container */
    .eight {
        height: 100px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin: 20px auto;
        max-width: 1200px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Title Styling */
    .eight h1 {
        position: relative;
        padding: 0;
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 600;
        color: #2c3e50;
        font-size: 26px;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        display: inline-block;
        /* Add this */
    }

    /* Add underline accent */
    .eight h1::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 0;
        /* Change this */
        width: 100%;
        /* Change this */
        height: 3px;
        background-color: #007bff;
        border-radius: 2px;
        transform: none;
        /* Remove transform */
    }

    /* Remove any conflicting styles */
    .eight h1:before {
        display: none;
    }

    /* Update Title Container and Styling */
    .eight {
        height: 100px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin: 20px auto;
        max-width: 1200px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .eight h1 {
        position: relative;
        padding: 0;
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 600;
        color: #2c3e50;
        font-size: 26px;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
    }

    /* Fix underline accent to match cart.php */
    .eight h1::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background-color: #007bff;
        border-radius: 2px;
    }

    /* Remove any conflicting styles */
    .eight h1:before {
        display: none;
    }
</style>
<style>
    /* Add these styles */
    .map-section {
        /* margin: 20px 0; */
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    #map {
        width: 100%;
        height: 400px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .route-info {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 14px;
    }

    .distance-info,
    .shipping-fee-info,.price-info {
        margin: 5px 0;
        color: #2c3e50;
    }

    .map-section {
        margin-top: 0;
    }

    /* Add these styles */
    .input-text.error {
        border-color: #dc3545;
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
    }

    .payment-details {
        display: none;
    }

    /* Style for required field indicator */
    .input-text[data-required]::after {
        content: '*';
        color: #dc3545;
        margin-left: 4px;
    }

    /* Active payment method styling */
    .payment-method-item input[type="radio"]:checked+label {
        color: #007bff;
    }

    .payment-method-item input[type="radio"]:checked~.payment-details {
        display: block;
    }
</style>
<style>
    /* Payment Form Styling */
    .security-note {
        color: #6c757d;
        font-size: 0.9em;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group {
        position: relative;
        margin-bottom: 15px;
    }

    .card-extra {
        display: flex;
        gap: 15px;
    }

    .form-group.half {
        width: 50%;
    }

    .error-message {
        color: #dc3545;
        font-size: 0.8em;
        position: absolute;
        bottom: -20px;
        left: 0;
    }

    .input-text.error {
        border-color: #dc3545;
    }

    .input-text.valid {
        border-color: #28a745;
    }
</style>
<style>
    /* Payment Method Visibility */
    .payment-details {
        display: none;
        /* Hidden by default */
        margin-top: 15px;
        padding: 15px;
        border-top: 1px dashed #ddd;
    }

    /* Show payment details when radio is checked */
    .payment-method-item input[type="radio"]:checked~.payment-details {
        display: block !important;
        /* Force display when checked */
    }

    /* Input field styling */
    .input-text {
        width: 100%;
        padding: 12px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    /* Form group spacing */
    .form-group {
        margin-bottom: 25px;
        /* Increased to accommodate error message */
    }
</style>
<style>
/* Progress Bar Section */
.payment-top-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 40px;
}

.payment-top {
    height: 2px;
    width: 70%;
    background-color: #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 30px auto;
    max-width: 840px;
    position: relative;
}

/* Add progress line color */
.payment-top::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    transform: translateY(-50%);
    height: 2px;
    width: 100%; /* Full width since it's the last step */
    background-color: #4CAF50;
    z-index: 1;
}

.payment-top-item {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #fff;
    position: relative;
    transition: all 0.3s ease;
    z-index: 2;
}

.payment-top-item i {
    color: #666;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

/* Active state */
.payment-top-item.active {
    border-color: #4CAF50;
    background-color: #4CAF50;
}

.payment-top-item.active i {
    color: #fff;
}

/* Links in progress bar */
.payment-top-item a {
    color: inherit;
    text-decoration: none;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* Replace the existing button styles with these */
.payment-content-right-button {
    display: flex;
    gap: 20px;
    margin-top: 30px;
    padding: 20px 0;
    border-top: 1px solid #eee;
        justify-content: space-between;
    margin-top: 180px;
}

.return-btn, .pay-btn {
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.return-btn {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
}

.pay-btn {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.return-btn:hover, .pay-btn:hover {
    transform: translateY(-2px);
}

.return-btn:hover {
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
}

.pay-btn:hover {
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
}

.return-btn:active, .pay-btn:active {
    transform: translateY(0);
}

/* Add icons to buttons */
.return-btn i, .pay-btn i {
    font-size: 1.1rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .payment-content-right-button {
        flex-direction: column;
        gap: 15px;
    }

    .return-btn, .pay-btn {
        width: 100%;
        justify-content: center;
        padding: 15px;
    }
}
.eight {
    height: auto;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin: 20px auto;
    padding: 20px 40px;
    width: fit-content;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.eight h1 {
    font-family: Arial, Helvetica, sans-serif;
    font-weight: 600;
    font-size: 26px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 0;
    padding: 0;
    /* background: linear-gradient(90deg,rgb(255, 255, 255),#6D6E71, #0056B3); */
    background-size: 200% auto;
    color: #2c3e50; /* Fallback color */
    /* animation: gradientText 3s linear infinite; */
    /* height:32px; */
}

.eight h1 span {
    display: inline-block;
    opacity: 0;
    transform: translateY(20px);
    animation: revealLetters 0.5s ease forwards;
}

/* Gradient Animation */
@keyframes gradientText {
    0% { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
}

/* Letter Animation */
@keyframes revealLetters {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
/* Highlight Effect */
.eight::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(90, 90, 90, 0.8),
        transparent
    );
    animation: highlightSweep 3s ease-in-out infinite;
}

@keyframes highlightSweep {
    100% { left: 200%; }
}
/* Payment Page Dark Theme Styles */
body.dark-theme .payment {
    background-color: #34495E;
}

/* Title Container */
body.dark-theme .eight {
    background-color: #2c3e50;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

body.dark-theme .eight h1 {
    color: #ecf0f1;
}

body.dark-theme .eight h1 span {
    color: #ecf0f1;
}

body.dark-theme .eight:hover h1 span {
    color: #3498db;
}

/* Progress Bar */
body.dark-theme .payment-top {
    background-color: #445566;
}

body.dark-theme .payment-top::before {
    background-color: #2ecc71;
}

body.dark-theme .payment-top-item {
    background-color: #2c3e50;
    border-color: #445566;
}

body.dark-theme .payment-top-item i {
    color: #bdc3c7;
}

body.dark-theme .payment-top-item.active {
    background-color: #2ecc71;
    border-color: #27ae60;
}

body.dark-theme .payment-top-item.active i {
    color: #fff;
}

/* Payment Methods Container */
body.dark-theme .payment-methods-container {
    background-color: #2c3e50;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

body.dark-theme .payment-methods-container h3 {
    color: #ecf0f1;
}

/* Payment Method Items */
body.dark-theme .payment-method-item {
    border-color: #445566;
    background-color: #34495e;
}

body.dark-theme .payment-method-item:hover {
    border-color: #3498db;
    background-color: #2c3e50;
}

body.dark-theme .payment-method-item label {
    color: #ecf0f1;
}

body.dark-theme .payment-method-item i {
    color: #3498db;
}

/* Payment Details */
body.dark-theme .payment-details {
    border-top-color: #445566;
    background-color: #2c3e50;
}

/* Form Inputs */
body.dark-theme .input-text {
    background-color: #34495e;
    border-color: #445566;
    color: #ecf0f1;
}

body.dark-theme .input-text:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
}

body.dark-theme .input-text::placeholder {
    color: #95a5a6;
}

/* Map Section */
body.dark-theme .map-section {
    background-color: #2c3e50;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

body.dark-theme .map-section h3 {
    color: #ecf0f1;
}

body.dark-theme .route-info {
    background-color: #34495e;
    color: #ecf0f1;
}

body.dark-theme .distance-info,
body.dark-theme .shipping-fee-info,
body.dark-theme .price-info {
    color: #bdc3c7;
}

body.dark-theme .route-info i {
    color: #3498db;
}

/* Action Buttons */
body.dark-theme .return-btn {
    background: linear-gradient(135deg, #34495e, #2c3e50);
    color: #ecf0f1;
    box-shadow: 0 4px 15px rgba(52, 73, 94, 0.3);
}

body.dark-theme .pay-btn {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: #ecf0f1;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

body.dark-theme .return-btn:hover {
    background: linear-gradient(135deg, #2c3e50, #233140);
    box-shadow: 0 6px 20px rgba(52, 73, 94, 0.4);
}

body.dark-theme .pay-btn:hover {
    background: linear-gradient(135deg, #2980b9, #2472a4);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
}

/* Form Validation */
body.dark-theme .input-text.error {
    border-color: #e74c3c;
    box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.25);
}

body.dark-theme .input-text.valid {
    border-color: #2ecc71;
    box-shadow: 0 0 0 2px rgba(46, 204, 113, 0.25);
}

body.dark-theme .error-message {
    color: #e74c3c;
}

/* Security Note */
body.dark-theme .security-note {
    color: #95a5a6;
}

/* Form Groups */
body.dark-theme .form-group label {
    color: #bdc3c7;
}

/* Leaflet Map Customization */
body.dark-theme .leaflet-container {
    background-color: #34495e;
}

body.dark-theme .leaflet-popup-content-wrapper {
    background-color: #2c3e50;
    color: #ecf0f1;
}

body.dark-theme .leaflet-popup-tip {
    background-color: #2c3e50;
}
/* Progress Bar Section */
/* Progress Bar Styles */
body.dark-theme .payment-top {
    height: 2px;
    width: 70%;
    background-color: #445566;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 30px auto;
    max-width: 840px;
    position: relative;
}

/* Update the progress line color from green to blue */
body.dark-theme .payment-top::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    transform: translateY(-50%);
    height: 2px;
    width: 100%;
    background-color: #3498db; /* Changed from #2ecc71 to #3498db */
    transition: width 0.3s ease;
    z-index: 1;
}

/* Update active state colors */
body.dark-theme .payment-top-item.active {
    border-color: #2980b9; /* Changed from #27ae60 */
    background-color: #3498db; /* Changed from #2ecc71 */
}

/* Update hover effect color */
body.dark-theme .payment-top-item:hover {
    transform: scale(1.1);
    box-shadow: 0 0 15px rgba(52, 152, 219, 0.3); /* Changed from rgba(46, 204, 113, 0.3) */
}

/* Update inactive item colors */
body.dark-theme .payment-top-item {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #445566;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #2c3e50;
    position: relative;
    transition: all 0.3s ease;
    z-index: 2;
}
</style>
<body>




    <!------------payment----------------->
    <section class="payment">
        <div class="container">
                        <div class="payment-top-wrap">
                <div class="payment-top">
                    <div class="payment-top-cart payment-top-item active">
                        <a href="cart.php">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </a>
                    </div>
            
                    <div class="payment-top-address payment-top-item active">
                        <a href="delivery.php">
                            <i class="fa-solid fa-location-dot"></i>
                        </a>
                    </div>
            
                    <div class="payment-top-payment payment-top-item active">
                        <a href="payment.php">
                            <i class="fa-solid fa-money-check"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="eight">
                    <h1>Thông Tin Thanh Toán</h1>
                </div>
                <div class="payment-content">
                    <!-- Left Column - Payment Methods -->
                    <div class="payment-methods-container">
                    <form id="payment-form" method="POST" action="payment.php">
                        <h3>Chọn phương thức thanh toán</h3>

                        <?php if (empty($PMS)): ?>
                        <div class="payment-method-item" style="border-color:#ffc107">
                            Hiện chưa có phương thức thanh toán khả dụng. Vui lòng quay lại sau.
                        </div>
                        <?php else: ?>
                        <?php foreach($PMS as $idx => $pm):
                            $pmId   = (int)$pm['payment_method_id'];
                            $pmName = htmlspecialchars($pm['method_name']);
                            $pmType = pm_type_from_name($pm['method_name']); // credit | atm/bank | cash | ...
                            $checked = $idx === 0 ? 'checked' : '';
                        ?>
                        <div class="payment-method-item">
                            <input
                            type="radio"
                            name="payment_method"
                            id="pm-<?= $pmId ?>"
                            value="<?= $pmId ?>"
                            data-type="<?= $pmType ?>"
                            <?= $checked ?>
                            >
                            <label for="pm-<?= $pmId ?>">
                            <i class="fas fa-money-check"></i> <?= $pmName ?>
                            </label>

                            <!-- Chi tiết của phương thức -->
                            <div class="payment-details pm-details" data-for-type="<?= $pmType ?>">
                            <?php if ($pmType === 'credit'): ?>
                                <div class="form-group">
                                <input type="text" class="input-text" name="credit_number" placeholder="Số thẻ tín dụng" pattern="[0-9]{16}" maxlength="16">
                                <span class="error-message"></span>
                                </div>
                                <div class="form-group">
                                <input type="text" class="input-text" name="credit_name" placeholder="Họ tên trên thẻ">
                                <span class="error-message"></span>
                                </div>
                                <div class="form-group">
                                <input type="text" class="input-text" name="credit_expiry" placeholder="Ngày hết hạn (MM/YY)" pattern="(0[1-9]|1[0-2])\/([0-9]{2})">
                                <span class="error-message"></span>
                                </div>
                                <div class="form-group">
                                <input type="text" class="input-text" name="credit_cvv" placeholder="CVV" pattern="[0-9]{3}" maxlength="3">
                                <span class="error-message"></span>
                                </div>
                            <?php elseif ($pmType === 'atm' || $pmType === 'bank'): ?>
                                <div class="form-group">
                                <input type="text" class="input-text" name="atm_number" placeholder="Số thẻ / Số tài khoản">
                                <span class="error-message"></span>
                                </div>
                                <div class="form-group">
                                <input type="text" class="input-text" name="atm_bank" placeholder="Tên ngân hàng">
                                <span class="error-message"></span>
                                </div>
                                <div class="form-group">
                                <input type="text" class="input-text" name="atm_name" placeholder="Tên chủ tài khoản">
                                <span class="error-message"></span>
                                </div>
                            <?php else: ?>
                                <div class="security-note"><i class="fas fa-info-circle"></i> Không cần nhập thêm thông tin cho phương thức này.</div>
                            <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Hidden inputs -->
                        <input type="hidden" name="distance" id="distance-input" value="0">
                        <input type="hidden" name="shipping_fee" id="shipping-fee-input" value="0">

                        <!-- Chỉ 1 cụm nút ở cuối -->
                        <div class="payment-content-right-button">
                        <a href="delivery.php" class="return-btn">
                            <i class="fas fa-arrow-left"></i> Trở về
                        </a>
                        <button type="submit" class="pay-btn">
                            <i class="fas fa-money-check"></i> Thanh toán
                        </button>
                        </div>
                    </form>
                    </div>


                    <!-- Right Column - Map -->
                    <div class="map-section">
                        <h3>Chi tiết vận chuyển</h3>
                        <div id="map"></div>
                        <div class="route-info">
                            <div class="distance-info"></div>
                            <div class="price-info"></div>
                            <div class="shipping-fee-info"></div>
                        </div>
                    </div>




                </a>
            </div>
        </div>
        </div>
    </section>
    <!------------footer----------->
    <script>
        // Add this JavaScript after your existing scripts
        // Company address coordinates (05 Bà Huyện Thanh Quan, P. Võ Thị Sáu, Q.3, TP.HCM)
        const COMPANY_COORDS = [10.7797, 106.6915];
        let map, routeLayer;

        function initMap() {
            // Initialize map
            map = L.map('map').setView(COMPANY_COORDS, 13);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add company marker
            const companyMarker = L.marker(COMPANY_COORDS, {
                icon: L.divIcon({
                    html: '<i class="fas fa-building" style="color: #dc3545; font-size: 24px;"></i>',
                    className: 'company-marker',
                    iconSize: [24, 24],
                    iconAnchor: [12, 24]
                })
            }).addTo(map);
            companyMarker.bindPopup('Công ty - 05 Bà Huyện Thanh Quan');

            // Get user address from PHP
            const userAddress = '<?php echo addslashes($user_info["address"]); ?>';
            if (userAddress) {
                geocodeAddress(userAddress);
            }
        }

        function geocodeAddress(address) {
            // Add 'TP.HCM, Việt Nam' to improve geocoding accuracy
            const fullAddress = `${address}, TP.HCM, Việt Nam`;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fullAddress)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        const userCoords = [parseFloat(data[0].lat), parseFloat(data[0].lon)];

                        // Add user marker
                        const userMarker = L.marker(userCoords, {
                            icon: L.divIcon({
                                html: '<i class="fas fa-map-marker-alt" style="color: #007bff; font-size: 24px;"></i>',
                                className: 'user-marker',
                                iconSize: [24, 24],
                                iconAnchor: [12, 24]
                            })
                        }).addTo(map);
                        userMarker.bindPopup('Địa chỉ giao hàng');

                        // Get route
                        getRoute(userCoords);
                    }
                })
                .catch(error => console.error('Geocoding error:', error));
        }

        function getRoute(userCoords) {
            // OSRM expects coordinates in [longitude, latitude] format
            const start = `${COMPANY_COORDS[1]},${COMPANY_COORDS[0]}`;
            const end = `${userCoords[1]},${userCoords[0]}`;

            fetch(`https://router.project-osrm.org/route/v1/driving/${start};${end}?overview=full&geometries=geojson`)
                .then(response => response.json())
                .then(data => {
                    if (data.routes && data.routes.length > 0) {
                        // Remove existing route if any
                        if (routeLayer) {
                            map.removeLayer(routeLayer);
                        }

                        // Add new route
                        routeLayer = L.geoJSON(data.routes[0].geometry, {
                            style: {
                                color: '#007bff',
                                weight: 4,
                                opacity: 0.8
                            }
                        }).addTo(map);

                        // Calculate distance and shipping fee
                        const distanceKm = (data.routes[0].distance / 1000).toFixed(2);
                        const shippingFee = Math.ceil(distanceKm * 100000); // 20,000 VND per km

                        // Update info display
                        document.querySelector('.distance-info').innerHTML =
                            `<i class="fas fa-road"></i> Khoảng cách quãng đường vận chuyển: <strong>${distanceKm} km</strong>`;
                        document.querySelector('.price-info').innerHTML =
                            `<i class="fas fa-money-bill"></i> Đơn giá vận chuyển: <strong>100.000 VND/km</strong>`;
                        document.querySelector('.shipping-fee-info').innerHTML =
                            `<i class="fas fa-truck"></i> Phí vận chuyển: <strong>${new Intl.NumberFormat('vi-VN').format(shippingFee)} ₫</strong>`;

                        // Fit map to show entire route
                        map.fitBounds(routeLayer.getBounds(), { padding: [50, 50] });

                        // Store values in hidden inputs for form submission
                        document.getElementById('distance-input').value = distanceKm;
                        document.getElementById('shipping-fee-input').value = shippingFee;
                    }
                })
                .catch(error => console.error('Routing error:', error));
        }

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
    </script>
    <script>
    // Hiển thị phần chi tiết theo radio được chọn (dựa vào data-type)
    function refreshPmDetails(){
    // Ẩn tất cả
    document.querySelectorAll('.pm-details').forEach(d => d.style.display = 'none');
    // Tìm radio đang chọn
    const checked = document.querySelector('input[name="payment_method"]:checked');
    if (!checked) return;
    const type = checked.dataset.type || 'other';
    // Hiện block có data-for-type tương ứng
    document.querySelectorAll(`.pm-details[data-for-type="${type}"]`)
        .forEach(d => d.style.display = 'block');
    }

    document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="payment_method"]').forEach(r => {
        r.addEventListener('change', refreshPmDetails);
    });
    // gọi lần đầu
    refreshPmDetails();
    });
    </script>

    <script>
        function onlyNumbers(event) {
            return /[0-9]/.test(event.key);
        }

        function formatExpiry(event) {
            if (!/[0-9]/.test(event.key)) {
                event.preventDefault();
                return;
            }

            const input = event.target;
            if (input.value.length === 2 && !input.value.includes('/')) {
                input.value += '/';
            }
        }

        function validateCreditCard(input) {
            const value = input.value.replace(/\s/g, '');
            const valid = value.length === 16 && /^[0-9]{16}$/.test(value);
            showValidation(input, valid, 'Số thẻ không hợp lệ');
        }

        function validateName(input) {
            const valid = input.value.trim().length >= 3;
            showValidation(input, valid, 'Vui lòng nhập họ tên trên thẻ');
        }

        function validateExpiry(input) {
            const value = input.value;
            const [month, year] = value.split('/');
            const now = new Date();
            const expiry = new Date(2000 + parseInt(year), parseInt(month) - 1);
            const valid = expiry > now;
            showValidation(input, valid, 'Thẻ đã hết hạn');
        }

        function validateCVV(input) {
            const valid = /^[0-9]{3}$/.test(input.value);
            showValidation(input, valid, 'CVV không hợp lệ');
        }

        function showValidation(input, isValid, errorMessage) {
            const errorElement = input.nextElementSibling;
            input.classList.remove('valid', 'error');
            input.classList.add(isValid ? 'valid' : 'error');
            errorElement.textContent = isValid ? '' : errorMessage;
        }

        // Add event listeners for payment method selection
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function () {
                document.querySelectorAll('.payment-details').forEach(details => {
                    details.style.display = 'none';
                });

                if (this.checked && this.value !== 'cash') {
                    const details = document.getElementById(`${this.value}-details`);
                    if (details) {
                        details.style.display = 'block';
                    }
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Payment method selection handler
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            paymentMethods.forEach(method => {
                method.addEventListener('change', function () {
                    // Hide all payment details first
                    document.querySelectorAll('.payment-details').forEach(detail => {
                        detail.style.display = 'none';
                    });

                    // Show selected method's details
                    if (this.checked) {
                        const detailsId = `${this.value}-details`;
                        const details = document.getElementById(detailsId);
                        if (details) {
                            details.style.display = 'block';
                        }
                    }
                });
            });
        });
                // Add this to your existing script
    // Update the title animation script
    document.addEventListener('DOMContentLoaded', function() {
        const title = document.querySelector('.eight h1');
        const text = title.textContent.trim();
        title.innerHTML = ''; // Clear the title
        
        // Create spans for each letter with proper delays
        text.split('').forEach((letter, index) => {
            const span = document.createElement('span');
            span.textContent = letter === ' ' ? '\u00A0' : letter; // Preserve spaces
            span.style.display = 'inline-block';
            span.style.opacity = '0';
            span.style.transform = 'translateY(20px)';
            span.style.transition = `all 0.5s ease ${index * 0.1}s`;
            title.appendChild(span);
            
            // Trigger animation after a small delay
            setTimeout(() => {
                span.style.opacity = '1';
                span.style.transform = 'translateY(0)';
            }, 100);
        });
    });
    let $color;
    // Update hover effect
    const titleContainer = document.querySelector('.eight');
    titleContainer.addEventListener('mouseenter', () => {
        const letters = titleContainer.querySelectorAll('h1 span');
        letters.forEach((letter, index) => {
            letter.style.transform = 'translateY(-5px)';
            $color=letter.style.color;
            letter.style.color = '#007bff';
            letter.style.transition = `all 0.3s ease ${index * 0.05}s`;
        });
    });
    
    titleContainer.addEventListener('mouseleave', () => {
        const letters = titleContainer.querySelectorAll('h1 span');
        letters.forEach((letter, index) => {
            letter.style.transform = 'translateY(0)';
            letter.style.color = $color;
            letter.style.transition = `all 0.3s ease ${index * 0.05}s`;
        });
    });

    </script>
</body>

</html>
<?php
include 'footer.php';
?>