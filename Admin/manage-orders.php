<?php
include 'header.php';

// load options cho form popup
$users_rs = $connect->query("SELECT id, username FROM users_acc WHERE status='activated' ORDER BY username");
$USERS = $users_rs ? $users_rs->fetch_all(MYSQLI_ASSOC) : [];

$pms_rs = $connect->query("
  SELECT payment_method_id AS id, method_name AS name
  FROM payment_methods
  WHERE is_active = 1
  ORDER BY method_name
");
$PMS = $pms_rs ? $pms_rs->fetch_all(MYSQLI_ASSOC) : [];

$pros_rs = $connect->query("
  SELECT product_id, car_name AS name, price, remain_quantity
  FROM products
  WHERE (status IS NULL OR status <> 'hidden') AND remain_quantity > 0
  ORDER BY car_name
");
$PROS = $pros_rs ? $pros_rs->fetch_all(MYSQLI_ASSOC) : [];


// Add this PHP function at the top of the file
function buildFilterQuery($filters)
{
    // Base query (giữ shipping_address để còn sort theo Location)
    $base_query = "SELECT 
              o.order_id, o.order_date, o.order_status, o.total_amount, o.shipping_address,
              u.username, u.full_name, u.phone_num, u.email
            FROM orders o 
            JOIN users_acc u ON o.user_id = u.id";

    $where_clauses = [];
    $params = [];
    $types = "";

    // Date range filter
    if (!empty($filters['start_date'])) {
        $where_clauses[] = "o.order_date >= ?";
        $params[] = $filters['start_date'];
        $types .= "s";
    }
    if (!empty($filters['end_date'])) {
        $where_clauses[] = "o.order_date <= ?";
        $params[] = $filters['end_date'] . " 23:59:59";
        $types .= "s";
    }

    // Status filter
    if (!empty($filters['status'])) {
        $where_clauses[] = "o.order_status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }

    // Sort
    $order_by = (!empty($filters['sort']) && $filters['sort'] === 'location')
        ? "o.shipping_address ASC"
        : ((!empty($filters['sort']) && $filters['sort'] === 'date_asc')
            ? "o.order_date ASC"
            : "o.order_date DESC");

    // Combine
    $query = $base_query;
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $query .= " ORDER BY " . $order_by;

    return ['query' => $query, 'params' => $params, 'types' => $types];
}

// Process status update first
if (isset($_POST['update_status']) && isset($_POST['order_id'])) {
    try {
        $order_id = intval($_POST['order_id']);
        $status = mysqli_real_escape_string($connect, $_POST['order_status']);

        $update_query = "UPDATE orders SET order_status = ? WHERE order_id = ?";
        $stmt = mysqli_prepare($connect, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $status, $order_id);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['notification'] = [
                'message' => 'Status updated successfully',
                'type' => 'success'
            ];
        } else {
            throw new Exception("Failed to update status");
        }

    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'message' => $e->getMessage(),
            'type' => 'error'
        ];
    }
    // Use JavaScript for redirect instead of header()
    echo "<script>window.location.href = 'manage-orders.php';</script>";
    exit();
}

// Replace the existing orders query with this filtering logic
$where_clauses = [];
$params = [];
$types = "";

// Date range filter
if (!empty($_GET['start_date'])) {
    $where_clauses[] = "o.order_date >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if (!empty($_GET['end_date'])) {
    $where_clauses[] = "o.order_date <= ?";
    $params[] = $_GET['end_date'] . " 23:59:59";
    $types .= "s";
}

// Status filter
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $where_clauses[] = "o.order_status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Keyword search (full_name / username)
if (!empty($_GET['keyword'])) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.username LIKE ?)";
    $kw = '%' . $_GET['keyword'] . '%';
    $params[] = $kw;
    $params[] = $kw;
    $types .= "ss";
}

// Base query
$query = "SELECT o.*, u.username, u.full_name, u.phone_num, u.email 
          FROM orders o 
          JOIN users_acc u ON o.user_id = u.id";

// Add where clauses if any
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add sorting
$sort = $_GET['sort'] ?? 'date_desc';
switch ($sort) {
    case 'location':
        $query .= " ORDER BY o.shipping_address ASC";
        break;
    case 'date_asc':
        $query .= " ORDER BY o.order_date ASC";
        break;
    default:
        $query .= " ORDER BY o.order_date DESC";
}

// Prepare and execute query
$stmt = mysqli_prepare($connect, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);

// Show notification if exists
if (isset($_SESSION['notification'])) {
    echo "<script>
        showNotification('" . addslashes($_SESSION['notification']['message']) . "', 
                        '" . $_SESSION['notification']['type'] . "');
    </script>";
    unset($_SESSION['notification']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>

    <style>
        /* ===== Add New Order Modal (tự chứa, không đụng CSS cũ) ===== */
        .order-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;z-index:9998}
        .order-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:9999}
        .order-card{background:#fff;min-width:780px;max-width:90vw;max-height:85vh;overflow:auto;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2)}
        .order-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #eee}
        .order-body{padding:16px}
        .order-footer{display:flex;gap:8px;justify-content:flex-end;padding:12px 16px;border-top:1px solid #eee}
        .order-row{display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
        .order-row select,.order-row input[type=number]{padding:6px 8px;border:1px solid #ccc;border-radius:6px}
        .order-row select{min-width:260px}
        .order-table{width:100%;border-collapse:collapse;margin-top:8px}
        .order-table th,.order-table td{border:1px solid #e5e5e5;padding:8px;vertical-align:top}
        .order-table tfoot td{font-weight:600}
        .btn{padding:8px 12px;border:1px solid #cfcfcf;background:#f6f6f6;border-radius:8px;cursor:pointer}
        .btn-primary{background:#1abc9c;border-color:#18a085;color:#fff}
        .btn-danger{background:#dc3545;border-color:#c82333;color:#fff}
        .btn-outline{background:#fff}
        .hidden{display:none}
        @media (max-width:820px){.order-card{min-width:94vw}}

        .typeahead { position: relative; }
        .typeahead .suggest {
        position:absolute; left:0; right:0; top:100%;
        background:#fff; border:1px solid #e5e5e5; border-top:none;
        max-height:240px; overflow:auto; z-index:10000; display:none;
        box-shadow:0 8px 24px rgba(0,0,0,.08);
        }
        .typeahead .s-item { padding:8px 10px; cursor:pointer; display:flex; justify-content:space-between; gap:8px }
        .typeahead .s-item:hover { background:#f6f6f6 }
        .typeahead .s-name { font-weight:600 }
        .typeahead .s-meta { opacity:.7; font-size:.9em }

        .qtybox{ display:flex; align-items:center; gap:6px }
        .qtybox input{ width:90px; padding:6px 8px }
        .qtybtn{ padding:6px 10px; border:1px solid #cfcfcf; background:#fff; border-radius:8px; cursor:pointer }
    </style>

    <script src="mo.js"></script>
    <!-- <link rel="stylesheet" href="style.css"> -->
    <!-- <link rel="stylesheet" href="mo.css"> -->
    <link rel="icon" href="../User/dp56vcf7.png" type="image/png">
    <script src="https://kit.fontawesome.com/8341c679e5.js" crossorigin="anonymous"></script>

    <style>
    /* Style copy tương đồng với nút Add New Product */
    .btn-add-new{
    display:inline-flex; align-items:center; gap:8px;
    padding:10px 16px; border-radius:8px;
    background:#1abc9c; border:1px solid #18a085;
    color:#fff !important; text-decoration:none !important;
    font-weight:600; line-height:1; box-shadow:0 2px 0 #138f75;
    }
    .btn-add-new:hover{ background:#18a085; }
    .btn-add-new:active{ transform:translateY(1px); box-shadow:0 1px 0 #138f75; }
    </style>
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

    Specific hover effect for Ban button .admin-table button[style*="background-color: red;"] {
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

    /* Add this CSS to your existing styles */
    .filter-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .filter-container h2 {
        color: #2c3e50;
        font-size: 1.5rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .filter-group label {
        color: #34495e;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-group label i {
        color: #1abc9c;
        width: 16px;
    }
    
    .filter-group input,
    .filter-group select {
        padding: 10px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    .filter-group input:focus,
    .filter-group select:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.1);
        outline: none;
    }
    
    .filter-buttons {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .filter-btn, 
    .reset-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .filter-btn {
        background: linear-gradient(135deg, #1abc9c, #16a085);
        color: white;
    }
    
    .reset-btn {
        background: #f8f9fa;
        color: #666;
        border: 1px solid #ddd;
        text-decoration: none;
    }
    
    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(26, 188, 156, 0.3);
    }
    
    .reset-btn:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-buttons {
            flex-direction: column;
        }
        
        .filter-btn,
        .reset-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<style>
    .admin-section {
        margin: 20px;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .admin-table th,
    .admin-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .admin-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #2c3e50;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 14px;
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
        background: #e0f7fa;
        color: #00796b;
    }

    .status-cancelled {
        background: #ffebee;
        color: #c62828;
    }

    .edit-status-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        background: linear-gradient(135deg, #1abc9c, #16a085);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .edit-status-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(26, 188, 156, 0.3);
    }

    /* Status Modal Styles */
    .status-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }

    .modal-content {
        position: relative;
        background: white;
        width: 90%;
        max-width: 500px;
        margin: 50px auto;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .status-select {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .modal-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .modal-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .save-btn {
        background: #1abc9c;
        color: white;
    }

    .cancel-btn {
        background: #95a5a6;
        color: white;
    }
/* Add to your existing styles */
.admin-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.admin-table th i {
    margin-right: 8px;
    color: #1abc9c;
    width: 16px;
    text-align: center;
}

.admin-table th:hover i {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}
</style>

<style>
/* Bọc bảng để có thanh trượt ngang */
.table-scroll{
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

/* Không xuống dòng trong ô => bảng sẽ rộng ra và cho phép kéo ngang */
.admin-table.table-no-wrap th,
.admin-table.table-no-wrap td{
  white-space: nowrap;
}

/* ép bảng tối thiểu rộng hơn màn hình chút để bật scrollbar (tùy chỉnh) */
.admin-table.table-no-wrap{
  min-width: 1200px; /* hoặc 1400px nếu bạn có nhiều cột */
}
</style>

<style>
/* PM table: 5 cột (ID, Name, Status, Description, Action) */
#pmTable{ table-layout:fixed; width:100%; }
#pmTable th, #pmTable td{ vertical-align:top; }

/* Widths */
#pmTable th:nth-child(1){ width:80px; }    /* ID */
#pmTable th:nth-child(2){ width:22%; }     /* Name */
#pmTable th:nth-child(3){ width:120px; }   /* Status */
#pmTable th:nth-child(4){ width:auto; }    /* Description */
#pmTable th:nth-child(5){ width:220px; }   /* Action */

/* Chỉ cho Description được xuống dòng */
#pmTable td:nth-child(4), #pmTable th:nth-child(4){
  white-space:normal!important;
  word-break:break-word;
  overflow-wrap:anywhere;
}

/* Không cho Status bẻ chữ */
#pmTable td:nth-child(3), #pmTable th:nth-child(3){
  white-space:nowrap;
  word-break:normal;
  overflow-wrap:normal;
}

/* ---- Badge trạng thái ẩn/hiện ---- */
.badge{
  padding:4px 8px;
  border-radius:999px;
  font-size:12px;
  font-weight:600;
}
.badge--active{ background:#e8f5e9; color:#2e7d32; }
.badge--hidden{ background:#ffebee; color:#c62828; }
</style>

<body>


    <main>
        <!-- Filter & Sorting Section -->
        <!-- Replace the filter section HTML with this -->
        <div class="admin-section filter-container">
            <h2><i class="fas fa-filter"></i> Filter & Sort Orders</h2>
            <form method="GET" action="manage-orders.php" class="filters">
                <div class="filter-grid">
                    <!-- Date Range Filter -->
                    <div class="filter-group">
                        <label for="start-date"><i class="far fa-calendar-alt"></i> Start Date:</label>
                        <input type="date" id="start-date" name="start_date"
                            value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label for="end-date"><i class="far fa-calendar-alt"></i> End Date:</label>
                        <input type="date" id="end-date" name="end_date"
                            value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-tag"></i> Status:</label>
                        <select id="status" name="status">
                            <option value="all">All Statuses</option>
                        <?php
                        $statuses = [
                            'initiated' => 'Initiated',
                            'is pending' => 'Is pending',
                            'is confirmed' => 'Is confirmed',
                            'is delivering' => 'Is delivering',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled'
                        ];
                        foreach ($statuses as $value => $label) {
                            $selected = (isset($_GET['status']) && $_GET['status'] === $value) ? 'selected' : '';
                            echo "<option value='$value' $selected>$label</option>";
                        }
                        ?>
                        </select>
                    </div>

                    <!-- Sort Options -->
                    <div class="filter-group">
                        <label for="sort"><i class="fas fa-sort"></i> Sort by:</label>
                        <select id="sort" name="sort">
                            <option value="date_desc" <?= ($sort === 'date_desc') ? 'selected' : '' ?>>
                                <i class="fas fa-clock"></i> Newest First
                            </option>
                            <option value="date_asc" <?= ($sort === 'date_asc') ? 'selected' : '' ?>>
                                <i class="fas fa-clock"></i> Oldest First
                            </option>
                            <option value="location" <?= ($sort === 'location') ? 'selected' : '' ?>>
                                <i class="fas fa-map-marker-alt"></i> Location
                            </option>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="manage-orders.php" class="reset-btn">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>


        <div class="admin-section">
            <h2><i class="fas fa-list-check"></i>&nbsp;Manage Orders</h2>

        <div style="display:flex; gap:10px; align-items:center; margin-bottom:14px;">
          <button id="addOrderBtn" onclick="showOrderModal()">
            <i class="fa-solid fa-plus"></i> Add New Order
          </button>

          <!-- NEW: Manage Payment Methods -->
          <button id="pmBtn" type="button" class="btn" onclick="openPmModal()">
            <i class="fa-solid fa-credit-card"></i> Manage Payment Methods
          </button>
        </div>

        <!-- Search by Full Name / Username -->
        <form method="GET" action="manage-orders.php" style="margin-bottom:16px;">
          <div style="display:flex; gap:8px; align-items:center; max-width:480px;">
            <input
              type="text"
              name="keyword"
              placeholder="Search by full name or username..."
              value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>"
              style="flex:1; padding:8px 10px; border:1px solid #ddd; border-radius:6px;"
            >
            <button type="submit"
              style="
                padding:8px 14px;
                border-radius:6px;
                border:none;
                background:#1abc9c;
                color:#fff;
                display:inline-flex;
                align-items:center;
                gap:6px;
                cursor:pointer;
              ">
              <i class="fas fa-search"></i> Search
            </button>
            <a href="manage-orders.php"
              style="
                padding:8px 12px;
                border-radius:6px;
                border:1px solid #ddd;
                background:#f8f9fa;
                text-decoration:none;
                color:#666;
                white-space:nowrap;
              ">
              Reset
            </a>
          </div>
        </form>


      <style>
      #addOrderBtn {
        background-color: #007bff;
        color: #fff;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background-color 0.2s;
      }
      #addOrderBtn:hover {
        background-color: #0069d9;
      }
      #pmBtn{background:#1abc9c; color:#fff; border:none; padding:8px 14px; 
         border-radius:6px; font-weight:600; display:inline-flex; 
         align-items:center; gap:6px; cursor:pointer}
      #pmBtn:hover{background:#18a085}
      </style>

            <script>
                document.addEventListener('DOMContentLoaded',function(){
                const b=document.querySelector('.btn-add-new');
                if(!b) return;
                b.style.setProperty('color','#fff','important');
                b.style.setProperty('text-decoration','none','important');
                });
            </script>

          <div class="table-scroll">
            <table class="admin-table">
                <thead>
                  <tr>
                    <th><i class="fas fa-hashtag"></i> ID</th>
                    <th><i class="far fa-calendar-alt"></i> Order Date</th>
                    <th><i class="fas fa-user"></i> Full Name / Username</th>
                    <th><i class="fas fa-money-bill-wave"></i> Total Amount</th>
                    <th><i class="fas fa-info-circle"></i> Status</th>
                    <th><i class="fas fa-cogs"></i> Action</th>
                  </tr>
                </thead>
                                <!-- Fix the table rows closing tag -->
                <tbody>
                    <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                        <tr>
                          <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                          <td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td>
                          <td>
                            <strong><?= htmlspecialchars($order['full_name']) ?></strong><br>
                            <small><?= htmlspecialchars($order['username']) ?></small>
                          </td>
                          <td style="color:#008000;font-weight:bold;">
                            <?= number_format($order['total_amount']) ?> ₫
                          </td>
                          <td>
                            <span class="status-badge status-<?= str_replace(' ', '-', strtolower($order['order_status'])) ?>">
                              <?php
                                switch ($order['order_status']) {
                                  case 'initiated': echo 'Initiated'; break;
                                  case 'is pending': echo 'Is pending'; break;
                                  case 'is confirmed': echo 'Is confirmed'; break;
                                  case 'is delivering': echo 'Is delivering'; break;
                                  case 'completed': echo 'Completed'; break;
                                  case 'cancelled': echo 'Cancelled'; break;
                                  default: echo $order['order_status'];
                                }
                              ?>
                            </span>
                          </td>
                          <td class="td-actions">
                            <a href="manage-orders.php?edit=<?= $order['order_id'] ?>" class="edit-status-btn">
                              <i class="fas fa-edit"></i> Edit status
                            </a>
                            <a href="view-invoice.php?id=<?= $order['order_id'] ?>" target="_blank"
                              class="btn btn-outline" style="
                                background:#f8f9fa;border:1px solid #ccc;color:#333;
                                padding:6px 10px;border-radius:6px;display:inline-flex;
                                align-items:center;gap:6px;text-decoration:none;">
                              <i class="fas fa-file-invoice"></i> View / Print
                            </a>
                          </td>
                        </tr>

                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Status Edit Modal -->
        <!-- Replace the existing status modal div -->
        <div id="statusModal" class="status-modal"
            style="display: <?php echo isset($_GET['edit']) ? 'block' : 'none'; ?>">
            <div class="modal-content">
                <h3>Update order status</h3>
                <form action="manage-orders.php" method="POST">
                    <input type="hidden" name="order_id" value="<?php echo $_GET['edit'] ?? ''; ?>">
                    <select name="order_status" class="status-select">
                        <option value="initiated">Initiated</option>
                        <option value="is pending">Is pending</option>
                        <option value="is confirmed">Is confirmed</option>
                        <option value="is delivering">is delivering</option>
                        <option value="completed">Completed </option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <div class="modal-buttons">
                        <a href="manage-orders.php" class="modal-btn cancel-btn">Cancel</a>
                        <button type="submit" name="update_status" class="modal-btn save-btn">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- <hr> -->

        <!-- Payment Methods Modal -->
        <div id="pmModal" class="status-modal" style="display:none;">
          <div class="modal-content" style="max-width:680px;">
            <h3 style="margin-top:0;display:flex;justify-content:space-between;align-items:center">
              <span>Payment Methods</span>
              <button type="button" class="modal-btn cancel-btn" onclick="closePmModal()">Close</button>
            </h3>

            <!-- Add / Edit form -->
            <form id="pmForm" onsubmit="return savePm(event)">
              <input type="hidden" name="payment_method_id" id="pm_id">
              <div class="filter-grid" style="grid-template-columns:1fr;">
                <div class="filter-group">
                  <label><i class="fas fa-signature"></i> Method name</label>
                  <input type="text" name="method_name" id="pm_name" required>
                </div>
                <div class="filter-group">
                  <label><i class="fas fa-align-left"></i> Description (optional)</label>
                  <input type="text" name="description" id="pm_desc">
                </div>
              </div>
              <div class="filter-buttons">
                <button class="filter-btn" type="submit">
                  <i class="fas fa-save"></i> Update
                </button>
                <button class="reset-btn" type="button" onclick="resetPmForm()">Clear</button>
              </div>
            </form>

            <hr style="margin:18px 0">

            <!-- List -->
            <div class="table-scroll">
              <table class="admin-table" id="pmTable">
                <colgroup>
                  <col style="width:70px">    <!-- ID -->
                  <col style="width:24%">     <!-- Name -->
                  <col style="width:100px">   <!-- Status -->
                  <col>                       <!-- Description (auto) -->
                  <col style="width:200px">   <!-- Action (gọn lại) -->
                </colgroup>
                <thead>
                  <tr>
                    <th style="width:80px">ID</th>
                    <th>Name</th>
                    <th style="width:120px">Status</th>
                    <th>Description</th>
                    <th style="width:220px">Action</th>
                  </tr>
                </thead>
                <tbody><!-- filled by JS --></tbody>
              </table>
            </div>

            <div id="pmErr" style="margin-top:10px;color:#c62828;display:none"></div>
          </div>
        </div>

    </main>

    <script>
        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target.className === 'status-modal') {
                window.location.href = 'manage-orders.php';
            }
        }
    </script>

<!-- ===== Modal: Add New Order ===== -->
<div id="addOrderModal" style="display:none; position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center;">
  <div style="position:absolute; inset:0; background:rgba(0,0,0,.4)" onclick="hideOrderModal()"></div>
  <div style="position:relative; background:#fff; width:min(1200px,96vw); max-height:88vh; overflow:auto; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.2); padding:16px 18px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
      <h3 style="margin:0">Add New Order</h3>
      <button type="button" onclick="hideOrderModal()" class="btn">✕</button>
    </div>

    <form id="addOrderForm" method="post">
      <div class="order-top-row">
        <!-- Customer -->
        <div class="order-field order-field--customer">
          <label>Customer</label>
          <div class="typeahead cust-wrapper invalid">
            <input
              type="text"
              id="custSearch"
              placeholder="Search & choose customer..."
              autocomplete="off"
            >
            <input type="hidden" name="user_id" id="custId">
            <div class="suggest"></div>
            <div class="cust-helper">
              You must <b>choose a customer from the suggestions.</b>
            </div>
          </div>
        </div>

        <!-- Payment (nằm ngay kế bên) -->
        <div class="order-field order-field--payment">
          <label>Payment</label>
          <select id="ordPayment" name="payment_method_id" required>
            <option value="">-- Select method --</option>
            <?php foreach($PMS as $pm): ?>
              <option value="<?=$pm['id']?>"><?=htmlspecialchars($pm['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Address riêng 1 hàng bên dưới -->
      <div class="order-address-row">
        <label>Address</label>
        <div class="address-line">
          <input id="orderAddress" name="shipping_address"
                placeholder="Enter delivery address..." required>

          <button type="button" class="btn" id="btnCalcShip"
                  title="Geocode & route to get distance/ship">
            Calculate
          </button>

          <div id="shipCalcInfo">
            <small class="price-info">Price: <b>100.000 VND/km</b></small>
            <small>Distance: <b><span id="distanceInfo">0</span> km</b></small>
            <small>Shipping fee: <b><span id="shipFeeInfo">0</span> ₫</b></small>
          </div>
        </div>

        <!-- hidden inputs -->
        <input type="hidden" id="distance-input" name="distance" value="0">
        <input type="hidden" id="shipping-fee-input" name="shipping_fee" value="0">
        <input type="hidden" id="shipping-address-input" name="shipping_address_hidden" value="">
      </div>



      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; border:1px solid #eee; padding:8px; width:45%;">Product</th>
            <th style="border:1px solid #eee; padding:8px;">Price</th>
            <th style="border:1px solid #eee; padding:8px;">Stock</th>
            <th style="border:1px solid #eee; padding:8px;">Qty</th>
            <th style="border:1px solid #eee; padding:8px;">Subtotal</th>
            <th style="border:1px solid #eee; padding:8px;"></th>
          </tr>
        </thead>
        <tbody id="orderBody"></tbody>
        <tfoot>
          <tr>
            <td colspan="4" style="text-align:right; border:1px solid #eee; padding:8px;">Subtotal</td>
            <td style="border:1px solid #eee; padding:8px;"><span id="orderSubtotal">0</span></td>
            <td style="border:1px solid #eee; padding:8px;"></td>
          </tr>
          <tr>
            <td colspan="4" style="text-align:right; border:1px solid #eee; padding:8px;">VAT (10%)</td>
            <td style="border:1px solid #eee; padding:8px;"><span id="orderVAT">0</span></td>
            <td style="border:1px solid #eee; padding:8px;"></td>
          </tr>
          <!-- (ADD) show distance & ship fee -->
          <tr>
            <td colspan="4" style="text-align:right; border:1px solid #eee; padding:8px;">Distance</td>
            <td style="border:1px solid #eee; padding:8px;"><span id="orderDistance">0</span> km</td>
            <td style="border:1px solid #eee; padding:8px;"></td>
          </tr>
          <tr>
            <td colspan="4" style="text-align:right; border:1px solid #eee; padding:8px;">Shipping fee</td>
            <td style="border:1px solid #eee; padding:8px;"><span id="orderShipFee">0</span></td>
            <td style="border:1px solid #eee; padding:8px;"></td>
          </tr>
          <tr>
            <td colspan="4" style="text-align:right; border:1px solid #eee; padding:8px; font-weight:700;">Total</td>
            <td style="border:1px solid #eee; padding:8px; font-weight:700;"><span id="orderTotal">0</span></td>
            <td style="border:1px solid #eee; padding:8px;"></td>
          </tr>
        </tfoot>
      </table>

      <div style="margin-top:10px;">
        <button type="button" class="btn" id="btnAddOrderRow">+ Add product</button>
      </div>

      <div id="orderErr" style="color:#c53030; margin-top:8px; display:none;"></div>

      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
        <button type="button" class="btn" onclick="hideOrderModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Order</button>
      </div>
    </form>
  </div>
</div>

<style>
.typeahead{position:relative}
/* ==== Customer select state ==== */
.tag-required{
  display:inline-block;
  font-size:11px;
  padding:2px 6px;
  border-radius:999px;
  background:#fff3cd;
  color:#856404;
  border:1px solid #ffeeba;
}

.cust-wrapper{
  position:relative;
}

/* ==== Top row Add Order modal ==== */
.order-top-row{
  display:flex;
  flex-wrap:wrap;
  gap:16px;
  align-items:flex-start;
  margin-bottom:12px;
}

.order-field{
  display:flex;
  flex-direction:column;
  gap:4px;
}

.order-field label{
  font-weight:500;
}

/* Customer chiếm rộng, Payment hẹp hơn, Address chiếm cả dòng dưới */
.order-field--customer{ flex:1 1 320px; }
.order-field--payment{  flex:0 0 240px; }
.order-field--address{  flex:1 1 100%; }

/* Input/select trong 3 field nhìn đồng bộ */
.order-field .cust-wrapper input,
.order-field select,
.order-field--address input[type="text"],
.order-field--address input:not([type="hidden"]){
  min-width:0;
  padding:6px 8px;
}

/* Hàng Address: input + nút + info trên cùng một dòng */
.address-line{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
}

.address-line input{
  flex:1 1 260px;
}

#shipCalcInfo{
  display:flex;
  gap:12px;
  align-items:center;
}

.cust-wrapper input{
  transition:border-color .2s, box-shadow .2s, background-color .2s;
}

/* Chưa chọn customer -> đỏ nhạt */
.cust-wrapper.invalid input{
  border-color:#e55353 !important;
  background:#fff5f5;
}

/* Đã chọn từ list -> viền xanh */
.cust-wrapper.valid input{
  border-color:#28a745 !important;
  box-shadow:0 0 0 2px rgba(40,167,69,.15);
}

.cust-helper{
  font-size:12px;
  color:#6c757d;
  margin-top:4px;
}

.typeahead .suggest{
  position:absolute;left:0;right:0;top:100%;
  background:#fff;border:1px solid #e5e5e5;border-top:none;
  max-height:240px;overflow:auto;z-index:10000;display:none;
  box-shadow:0 8px 24px rgba(0,0,0,.08)
}
.typeahead .s-item{padding:8px 10px;cursor:pointer;display:flex;justify-content:space-between;gap:8px}
.typeahead .s-item:hover{background:#f6f6f6}
.typeahead .s-name{font-weight:600}
.typeahead .s-meta{opacity:.7;font-size:.9em}
.qtybox{display:flex;align-items:center;gap:6px}
.qtybox input{width:90px;padding:6px 8px}
.qtybtn{padding:6px 10px;border:1px solid #cfcfcf;background:#fff;border-radius:8px;cursor:pointer}

/* PM table: 5 cột (ID, Name, Status, Description, Action) */
#pmTable{ table-layout:fixed; width:100%; }
#pmTable th, #pmTable td{ vertical-align:top; }

/* Widths */
#pmTable th:nth-child(1){ width:80px; }    /* ID */
#pmTable th:nth-child(2){ width:22%; }     /* Name */
#pmTable th:nth-child(3){ width:120px; }   /* Status */
#pmTable th:nth-child(4){ width:auto; }    /* Description */
#pmTable th:nth-child(5){ width:220px; }   /* Action */

/* Chỉ cho Description được xuống dòng */
#pmTable td:nth-child(4), #pmTable th:nth-child(4){
  white-space:normal!important;
  word-break:break-word;
  overflow-wrap:anywhere;
}

/* Không cho Status bẻ chữ */
#pmTable td:nth-child(3), #pmTable th:nth-child(3){
  white-space:nowrap;
  word-break:normal;
  overflow-wrap:normal;
}


/* Badge trạng thái */
.badge{
  padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600;
}
.badge--active{ background:#e8f5e9;color:#2e7d32; }
.badge--hidden{ background:#ffebee;color:#c62828; }

/* PM table */
#pmTable{ table-layout:fixed; width:100%; }
#pmTable th, #pmTable td{ vertical-align:top; }

/* Chỉ Description được xuống dòng, nhưng đừng bẻ từng ký tự */
#pmTable th:nth-child(4),
#pmTable td:nth-child(4){
  white-space: normal !important;
  word-break: normal;           /* bỏ break-word */
  overflow-wrap: break-word;    /* đủ dùng cho từ dài */
  min-width: 180px;             /* chống bị co hẹp quá mức */
}

/* Status không bẻ dòng */
#pmTable th:nth-child(3),
#pmTable td:nth-child(3){
  white-space: nowrap;
}

/* ==== Payment Methods Table & Modal Fix ==== */

/* Giới hạn chiều cao modal + thêm thanh cuộn dọc */
#pmModal .modal-content {
  max-height: 85vh;            /* không vượt quá 85% chiều cao màn hình */
  overflow-y: auto;            /* bật thanh cuộn dọc nếu nội dung dài */
}

/* Giảm độ rộng cột Status, tăng cột Action */
#pmTable th:nth-child(3),
#pmTable td:nth-child(3) {
  width: 90px !important;      /* hẹp lại */
  text-align: center;
  white-space: nowrap;
}

#pmTable th:nth-child(5),
#pmTable td:nth-child(5) {
  width: 260px !important;     /* tăng thêm để đủ cho 3 nút */
}

/* Căn chỉnh 3 nút Action cùng hàng, không wrap */
#pmTable td:nth-child(5) {
  display: flex;
  flex-wrap: nowrap !important;  /* không xuống dòng */
  gap: 6px;
  align-items: center;
  justify-content: flex-start;
}

/* Giới hạn kích thước nút cho gọn gàng */
#pmTable td:nth-child(5) button {
  flex: 0 0 auto;
  white-space: nowrap;
  padding: 5px 8px;
  font-size: 13px;
  border-radius: 6px;
}

/* Badge thu nhỏ một chút */
.badge {
  padding: 3px 6px;
  font-size: 11px;
}

/* Name hẹp lại vừa đủ */
#pmTable th:nth-child(2),
#pmTable td:nth-child(2){
  width:18% !important;
}

/* Status nhỏ gọn */
#pmTable th:nth-child(3),
#pmTable td:nth-child(3){
  width:90px !important;
  white-space:nowrap;
  text-align:center;
}

/* Description rộng hơn tiêu đề, tránh header bị xuống dòng */
#pmTable th:nth-child(4){
  white-space:nowrap;          /* tiêu đề không xuống dòng */
}
#pmTable td:nth-child(4){
  white-space:normal !important;
  word-break:normal;
  overflow-wrap:break-word;
  min-width:240px;             /* rộng hơn chữ "Description" 1 chút */
}

/* Action đủ chỗ cho 3 nút trên 1 hàng */
#pmTable th:nth-child(5),
#pmTable td:nth-child(5){
  width:260px !important;
}
#pmTable td:nth-child(5){
  display:flex; gap:6px; align-items:center; flex-wrap:nowrap !important;
}
#pmTable td:nth-child(5) button{
  padding:5px 8px; font-size:13px; border-radius:6px; white-space:nowrap;
}

/* Modal cao tối đa + có cuộn dọc nếu danh sách dài */
#pmModal .modal-content{
  max-height:85vh; overflow-y:auto;
}

/* Badge gọn */
.badge{ padding:3px 6px; font-size:11px; }

.btn-outline:hover {
  background:#e9ecef;
  border-color:#999;
  transform:translateY(-1px);
}

/* Giữ action gọn 1 dòng, không bẻ chữ nhưng không làm cả bảng phải kéo ngang */
/* Cách nhau 10px, căn giữa theo hàng */
.td-actions{
  display: flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}

/* (tùy chọn) đồng bộ layout 2 nút */
.td-actions > a{
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.admin-table td, .admin-table th { vertical-align: middle; }

/* Nếu trước đó bạn có block ép min-width cho .table-no-wrap thì không còn hiệu lực
   vì ta đã bỏ class đó khỏi <table>. */

/* === PM modal tweaks === */
#pmModal .table-scroll{ overflow-x:hidden; } /* bỏ scrollbar ngang */
#pmModal .modal-content{ max-width: 720px; } /* modal gọn hơn chút */

/* Nút trong form PM: cùng chiều cao, padding, canh phải, bỏ đường viền trên */
#pmForm .filter-buttons{
  border-top: 0 !important;
  padding-top: 0 !important;
  justify-content: flex-end;
  gap: 12px;
}

/* Đồng bộ kích thước 2 nút */
#pmForm .filter-btn,
#pmForm .reset-btn{
  padding: 10px 18px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

/* Clear: kiểu outline cho cân mắt nhưng giữ kích thước như Update */
#pmForm .reset-btn{
  background: #fff;
  border: 1px solid #16a085;
  color: #16a085;
}
#pmForm .reset-btn:hover{ background:#e9f7f4; }

/* Khóa lại width cột theo thiết kế mới */
#pmTable th:nth-child(1), #pmTable td:nth-child(1){ width:70px; }
#pmTable th:nth-child(2), #pmTable td:nth-child(2){ width:24%; }
#pmTable th:nth-child(3), #pmTable td:nth-child(3){ width:100px; text-align:center; white-space:nowrap; }
#pmTable th:nth-child(5), #pmTable td:nth-child(5){ width:200px; }

#pmTable td:nth-child(5){
  display:flex; gap:8px; align-items:center; flex-wrap:nowrap !important;
}
#pmTable td:nth-child(5) button{
  padding:5px 8px; font-size:13px; border-radius:6px; white-space:nowrap;
}

#addOrderModal .modal-backdrop { z-index: 0; }
#addOrderModal .modal-panel    { z-index: 1; position: relative; }

/* ==== Top row Add Order modal ==== */
.order-top-row{
  display:flex;
  flex-wrap:wrap;
  gap:16px;
  align-items:flex-start;   /* TOP-align => label Customer & Payment thẳng hàng */
  margin-bottom:12px;
}

.order-field{
  display:flex;
  flex-direction:column;
  gap:4px;
}

.order-field label{
  font-weight:500;
}

/* Customer rộng, Payment hẹp hơn nhưng vẫn cùng 1 hàng */
.order-field--customer{ flex:0 0 430px; }
.order-field--payment{  flex:0 0 260px; }

/* Address chiếm cả dòng riêng */
.order-address-row{
  display:flex;
  flex-direction:column;
  gap:4px;
  margin-bottom:12px;
}

.address-line{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
}

/* Cho input Address rộng ra, ưu tiên chiếm chỗ */
.address-line input{
  flex:1 1 520px;
  padding:6px 8px;          /* giống Customer */
  height:auto;              /* để browser tự tính */
  line-height:normal;       /* đồng bộ */
}



/* Đưa phần Price / Distance / Shipping fee xuống dòng dưới,
   không ép chung hàng với ô Address để address đỡ bị hẹp */
#shipCalcInfo{
  display:flex;
  gap:12px;
  align-items:center;
  flex:1 0 100%;     /* luôn nằm trên 1 dòng riêng phía dưới */
}


/* Customer input dài hơn + dropdown gợi ý đúng bề ngang input */
.order-field--customer .cust-wrapper{
  display:inline-block;
  width:380px;          /* độ dài bạn muốn cho ô Customer */
}

.order-field--customer .cust-wrapper input{
  width:100%;
  padding:6px 8px;
}

.order-field--customer .cust-wrapper .suggest{
  left:0;
  right:auto;
  width:100%;           /* đúng bằng ô input */
}

</style>

<script>
(function(){
  const $ = s => document.querySelector(s);
  const modal = $('#addOrderModal');
  const body  = $('#orderBody');
  const total = $('#orderTotal');
  const err   = $('#orderErr');
  const addrInp = document.getElementById('orderAddress');
  const shipAddrHidden = document.getElementById('shipping-address-input');

    function resetOrderModal(){
      const form = document.getElementById('addOrderForm');
      if (form) form.reset();

      // reset customer
      const custWrap = document.querySelector('.cust-wrapper');
      const custId   = document.getElementById('custId');
      const custInp  = document.getElementById('custSearch');
      const custSug  = custWrap?.querySelector('.suggest');

      if (custId)  custId.value = '';
      if (custInp) custInp.value = '';
      if (custWrap){
        custWrap.classList.remove('valid');
        custWrap.classList.add('invalid');
      }
      if (custSug){
        custSug.innerHTML = '';
        custSug.style.display = 'none';
      }

      // reset shipping
      const idsZeroText = ['distanceInfo','orderDistance','shipFeeInfo','orderShipFee'];
      idsZeroText.forEach(id=>{
        const el = document.getElementById(id);
        if (el) el.textContent = '0';
      });

      const idsZeroVal = ['distance-input','shipping-fee-input'];
      idsZeroVal.forEach(id=>{
        const el = document.getElementById(id);
        if (el) el.value = 0;
      });

      const addr = document.getElementById('orderAddress');
      const addrHidden = document.getElementById('shipping-address-input');
      if (addr) addr.value = '';
      if (addrHidden) addrHidden.value = '';

      // xóa hết dòng sản phẩm, thêm lại 1 dòng trống
      body.innerHTML = '';
      addRow();
      recalcTotal();

      // ẩn lỗi
      if (err){
        err.textContent = '';
        err.style.display = 'none';
      }
    }

  if (addrInp && shipAddrHidden) {
    // khi gõ cũng sync sang input ẩn
    addrInp.addEventListener('input', () => {
      shipAddrHidden.value = addrInp.value;
    });

    // khi blur: chốt giá trị + tính ship (đã có hàm calcShippingFromAddress)
    addrInp.addEventListener('blur', async () => {
      const v = addrInp.value.trim();
      shipAddrHidden.value = v;
      if (!v) return;               // trống thì thôi, để validation HTML xử lý
      try { await calcShippingFromAddress(); } catch(_) {}
    });
  }


  const fmt = n => (Number(n)||0).toLocaleString('vi-VN');

  // === Mở / đóng modal ===
    function showOrderModal(){
      resetOrderModal();          // mỗi lần mở là trạng thái mới
      modal.style.display = 'flex';
    }
    window.showOrderModal = showOrderModal;

    function hideOrderModal(){
      modal.style.display = 'none';
      resetOrderModal();          // đóng bằng X hay click nền đều reset sạch
    }
    window.hideOrderModal = hideOrderModal;

  window.showOrderModal = showOrderModal;   // <= thêm dòng này
  function hideOrderModal(){ modal.style.display='none'; }
  window.hideOrderModal = hideOrderModal;
  $('#addOrderBtn')?.addEventListener('click', e => { e.preventDefault(); showOrderModal(); });
  $('#addOrderForm')?.addEventListener('submit', async function(e){
  e.preventDefault();
  err.style.display='none';
  err.textContent='';

  if(!body.querySelector('tr')){
    err.textContent='Please add at least one product.';
    err.style.display='block';
    return;
  }

  // Bắt buộc phải chọn customer từ danh sách (custId > 0)
  const custIdInput = document.getElementById('custId');
  const custInput   = document.getElementById('custSearch');
  const custWrap    = document.querySelector('.cust-wrapper');

  if (!custIdInput || !(+custIdInput.value > 0)) {
    err.textContent = 'Please choose a customer from the suggestions.';
    err.style.display = 'block';
    if (custWrap){
      custWrap.classList.remove('valid');
      custWrap.classList.add('invalid');
    }
    custInput?.focus();
    return;
  }

  // Bắt buộc có address hiển thị (name="shipping_address")
  const addr = (addrInp?.value || '').trim();
  if (!addr) {
    err.textContent = 'Please enter delivery address.';
    err.style.display = 'block';
    addrInp?.focus();
    return;
  }

  // Luôn sync vào hidden copy (phòng server đang đọc bản hidden)
  shipAddrHidden.value = addr;

  // Đảm bảo 3 hidden input tồn tại & có giá trị số
  const distEl = document.getElementById('distance-input');
  const feeEl  = document.getElementById('shipping-fee-input');

  // Nếu chưa có phí ship thì cố gắng tính
  let needCalc = !Number(feeEl?.value || 0) || !Number(distEl?.value || 0);
  if (needCalc) {
    try { await calcShippingFromAddress(); } catch(_) {}
  }

  // Fallback: nếu vẫn không tính được (API ngoài mạng bị chặn) thì cho = 0 nhưng KHÔNG để trống
  if (!distEl.value) distEl.value = 0;
  if (!feeEl.value)  feeEl.value  = 0;

  // Cập nhật lại các ô hiển thị để người dùng nhìn thấy giá trị đang gửi
  document.getElementById('orderDistance').textContent = (+distEl.value||0).toLocaleString('vi-VN');
  document.getElementById('orderShipFee').textContent  = (+feeEl.value||0).toLocaleString('vi-VN');
  recalcTotal();

  // Gửi
  const fd = new FormData(this);
  try{
    const res  = await fetch('add_order.php', { method:'POST', body: fd });
    const data = await res.json();
    if(!data.success) throw new Error(data.message || 'Cannot create order');
    hideOrderModal();
    location.href = 'manage-orders.php?created=' + data.order_id;
  }catch(ex){
    err.textContent = ex.message || 'Error';
    err.style.display = 'block';
  }
});


  // === Hàm thêm 1 dòng sản phẩm ===
  function addRow(){
    const tr = document.createElement('tr');

    // --- Cột Product (typeahead AJAX) ---
    const tdProd = document.createElement('td');
    tdProd.style.border='1px solid #eee'; tdProd.style.padding='8px';
    tdProd.innerHTML = `
      <div class="typeahead">
        <input type="text" class="prod-input" placeholder="Type product name..." style="min-width:320px; padding:6px 8px;">
        <input type="hidden" name="product_id[]">
        <div class="suggest"></div>
        <div class="suggest-hint" style="display:none;color:#c53030;font-size:.9em;margin-top:6px"></div>
      </div>
    `;
    const wrap = tdProd.querySelector('.typeahead');
    const inp  = wrap.querySelector('.prod-input');
    const hid  = wrap.querySelector('input[type=hidden]');
    const sug  = wrap.querySelector('.suggest');
    const hint = wrap.querySelector('.suggest-hint');
    bindTypeahead(tr, inp, hid, sug, hint);

        // ==== CUSTOMER TYPEAHEAD (AJAX search_users.php) ====
        (function(){
            const inp  = document.getElementById('custSearch');
            const hid  = document.getElementById('custId');
            const wrap = document.querySelector('.cust-wrapper');
            const sug  = wrap?.querySelector('.suggest');
            const helper = wrap?.querySelector('.cust-helper');
            if (!inp || !hid || !sug || !wrap) return;

            function setCustomerState(selected){
                wrap.classList.toggle('valid', !!selected);
                wrap.classList.toggle('invalid', !selected);
                if (!selected && helper){
                    helper.innerHTML = 'You must <b>choose a customer from the suggestions</b>.';
                } else if (helper){
                    helper.textContent = 'Customer selected from list.';
                }
            }

            let timer=null, lastQ='';

            async function fetchUsers(q){
                try{
                    const res = await fetch('search_users.php?q='+encodeURIComponent(q), {cache:'no-store'});
                    if (!res.ok) return [];
                    return await res.json();
                }catch(e){
                    console.error('User fetch error',e);
                    return [];
                }
            }

            function render(list){
                if (!list.length){
                    sug.style.display='none';
                    setCustomerState(false);
                    return;
                }
                sug.innerHTML = list.map(u=>`
                    <div class="s-item" data-id="${u.id}">
                        <span class="s-name">${u.username}</span>
                        <span class="s-meta">${u.full_name || ''}</span>
                    </div>`).join('');
                sug.style.display='block';
                sug.querySelectorAll('.s-item').forEach(it=>{
                    it.addEventListener('click',()=>{
                        hid.value = it.dataset.id;
                        inp.value = it.querySelector('.s-name').textContent;
                        sug.style.display='none';
                        setCustomerState(true);
                    });
                });
            }

            const doQuery = async (q)=>{
                q = q.trim();
                if (!q){
                    sug.style.display='none';
                    hid.value = '';
                    setCustomerState(false);
                    return;
                }
                lastQ = q;
                const list = await fetchUsers(q);
                if (lastQ !== q) return;
                render(list);
            };

            const debounced = (q)=>{ clearTimeout(timer); timer=setTimeout(()=>doQuery(q),180); };

            // Gõ lại -> coi như chưa chọn
            inp.addEventListener('input',()=>{
                hid.value = '';
                setCustomerState(false);
                debounced(inp.value);
            });

            inp.addEventListener('focus',()=>debounced(inp.value));

            document.addEventListener('click',(ev)=>{
                if(!sug.contains(ev.target) && ev.target!==inp){
                    sug.style.display='none';
                }
            });

            // Khởi tạo trạng thái ban đầu
            setCustomerState(false);
        })();

    // --- Cột Price ---
    const tdPrice=document.createElement('td');
    tdPrice.style.border='1px solid #eee'; tdPrice.style.padding='8px';
    tdPrice.innerHTML = `<input name="price[]" type="number" step="0.01" min="0" value="0" style="width:120px; padding:6px 8px;">`;
    const priceInp = tdPrice.querySelector('input[name="price[]"]');
    priceInp.addEventListener('input', ()=> recalcRow(tr));
    priceInp.addEventListener('change', ()=> recalcRow(tr));

    // --- Cột Stock ---
    const tdStock=document.createElement('td');
    tdStock.style.border='1px solid #eee'; tdStock.style.padding='8px';
    tdStock.innerHTML = `<span class="stock">0</span>`;

    // --- Cột Qty (+/-) ---
    const tdQty=document.createElement('td');
    tdQty.style.border='1px solid #eee'; tdQty.style.padding='8px';
    tdQty.innerHTML = `
      <div class="qtybox">
        <button type="button" class="qtybtn dec">−</button>
        <input name="qty[]" type="number" min="1" value="1">
        <button type="button" class="qtybtn inc">+</button>
      </div>
    `;
    const qtyInp = tdQty.querySelector('input[name="qty[]"]');
    const dec = tdQty.querySelector('.qtybtn.dec');
    const inc = tdQty.querySelector('.qtybtn.inc');
    qtyInp.addEventListener('input', ()=> limitQty(tr));
    dec.addEventListener('click', ()=>{ let v=Number(qtyInp.value||1)-1; if(v<1)v=1; qtyInp.value=v; limitQty(tr); });
    inc.addEventListener('click', ()=>{ let v=Number(qtyInp.value||1)+1; qtyInp.value=v; limitQty(tr); });

    // --- Cột Subtotal ---
    const tdSum=document.createElement('td');
    tdSum.style.border='1px solid #eee'; tdSum.style.padding='8px';
    tdSum.innerHTML = `<span class="sum">0</span>`;

    // --- Cột Delete ---
    const tdDel=document.createElement('td');
    tdDel.style.border='1px solid #eee'; tdDel.style.padding='8px';
    tdDel.innerHTML = `<button type="button" class="btn btn-danger">Delete</button>`;
    tdDel.querySelector('button').addEventListener('click', ()=>{ tr.remove(); recalcTotal(); });

    tr.append(tdProd, tdPrice, tdStock, tdQty, tdSum, tdDel);
    body.appendChild(tr);
  }

  // === Gắn sự kiện cho nút + Add product ===
  document.getElementById('btnAddOrderRow')?.addEventListener('click', () => {
    addRow();
    recalcTotal();
  });

  // === Giới hạn & tính lại từng hàng ===
  function limitQty(tr){
    const stock = Number(tr.querySelector('.stock').textContent || 0);
    const inp   = tr.querySelector('input[name="qty[]"]');
    let v = Number(inp.value || 1);
    if (stock > 0 && v > stock) v = stock;
    if (v < 1) v = 1;
    inp.value = v;
    recalcRow(tr);
  }

  function recalcRow(tr){
    const qty   = Number(tr.querySelector('input[name="qty[]"]').value || 0);
    const price = Number(tr.querySelector('input[name="price[]"]').value || 0);
    const sum   = qty * price;
    tr.querySelector('.sum').textContent = (Math.round(sum) || 0).toLocaleString('vi-VN');
    recalcTotal();
  }

  function recalcTotal(){
    let subtotal = 0;
    body.querySelectorAll('tr').forEach(tr=>{
      const qty   = Number(tr.querySelector('input[name="qty[]"]').value || 0);
      const price = Number(tr.querySelector('input[name="price[]"]').value || 0);
      subtotal += qty * price;
    });
    const vat  = subtotal * 0.10;
    const ship = Number(document.getElementById('shipping-fee-input')?.value || 0);

    document.getElementById('orderSubtotal').textContent = Math.round(subtotal).toLocaleString('vi-VN');
    document.getElementById('orderVAT').textContent      = Math.round(vat).toLocaleString('vi-VN');
    document.getElementById('orderTotal').textContent    = Math.round(subtotal + vat + ship).toLocaleString('vi-VN');
  }


  // === Gợi ý tìm sản phẩm (kèm debug & hint) ===
  let suggestTimer=null, lastQuery='';
  async function fetchSuggest(q){
    try{
      const url = 'search_products.php?q=' + encodeURIComponent(q);
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) {
        console.error('[typeahead] HTTP', res.status, '->', url);
        return { error: 'HTTP ' + res.status, list: [] };
      }
      const json = await res.json();
      if (Array.isArray(json)) return { list: json };
      console.error('[typeahead] Non-array JSON:', json);
      return { error: 'Invalid JSON', list: [] };
    }catch(err){
      console.error('[typeahead] fetch error:', err);
      return { error: err?.message || String(err), list: [] };
    }
  }

  function bindTypeahead(tr, inp, hid, sug, hint){
    const render=(list)=>{
      if(!list.length){sug.style.display='none';return;}
      sug.innerHTML=list.map(p=>`
        <div class="s-item" data-id="${p.product_id}" data-price="${p.price}" data-stock="${p.remain_quantity}">
          <span class="s-name">${p.name}</span>
          <span class="s-meta">₫${(p.price||0).toLocaleString('vi-VN')} • stock: ${p.remain_quantity}</span>
        </div>`).join('');
      sug.style.display='block';
      sug.querySelectorAll('.s-item').forEach(it=>{
        it.addEventListener('click',()=>{
          const id=it.dataset.id, price=Number(it.dataset.price||0), stock=Number(it.dataset.stock||0);
          hid.value=id; inp.value=it.querySelector('.s-name').textContent;
          tr.querySelector('input[name="price[]"]').value=price;
          tr.querySelector('.stock').textContent=stock;
          const qtyInp=tr.querySelector('input[name="qty[]"]');
          if(Number(qtyInp.value||1)>stock) qtyInp.value=stock||1;
          recalcRow(tr);
          sug.style.display='none';
          hint.style.display='none';
        });
      });
    };

    const doQuery=async(q)=>{
      q=q.trim();
      if(!q){sug.style.display='none'; hint.style.display='none'; return;}
      lastQuery=q;
      const {list, error} = await fetchSuggest(q);
      if (lastQuery!==q) return; // user đã gõ query mới
      if (error){
        hint.textContent = 'Không tải được gợi ý (' + error + '). Mở Console để xem chi tiết.';
        hint.style.display='block';
        sug.style.display='none';
        return;
      }
      if (!list.length){
        hint.textContent = 'Không tìm thấy sản phẩm khớp "'+q+'".';
        hint.style.display='block';
        sug.style.display='none';
        return;
      }
      hint.style.display='none';
      render(list);
    };

    const debounced=(q)=>{ clearTimeout(suggestTimer); suggestTimer=setTimeout(()=>doQuery(q), 200); };
    inp.addEventListener('input',()=>debounced(inp.value));
    inp.addEventListener('focus',()=>debounced(inp.value));
    document.addEventListener('click',(ev)=>{ if(!sug.contains(ev.target) && ev.target!==inp) sug.style.display='none'; });
  }

  // === Tính toán & giới hạn ===
  function limitQty(tr){
    const stock=Number(tr.querySelector('.stock').textContent||0);
    const inp=tr.querySelector('input[name="qty[]"]');
    let v=Number(inp.value||1);
    if(stock>0&&v>stock)v=stock;
    if(v<1)v=1;
    inp.value=v; recalcRow(tr);
  }
  
  // ===== Shipping calc (clean version, single definitions) =====

// 1) Config
const COMPANY_COORDS = [106.70098, 10.77689]; // [lon, lat]
const SHIP_RATE_PER_KM = 100000;              // 100.000 VND/km

// 2) Helpers
async function fetchWithTimeout(url, opts = {}, ms = 8000){
  const c = new AbortController();
  const t = setTimeout(()=>c.abort(), ms);
  try {
    const res = await fetch(url, { ...opts, signal: c.signal });
    clearTimeout(t);
    return res;
  } catch (e) {
    clearTimeout(t);
    throw e;
  }
}

function haversineKm([lon1,lat1],[lon2,lat2]){
  const toRad = d => d * Math.PI/180;
  const R = 6371;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
  return +(2 * R * Math.asin(Math.sqrt(a))).toFixed(2);
}


async function geocodeAddress(addr){
  // CHỖ NÀY: chỉnh cho đúng path thực tế của file
  const u = new URL('geocode.php', location.href); // hoặc '../geocode.php'
  u.searchParams.set('q', addr);

  console.log('[geocode] URL gọi tới:', u.toString());

  let res;
  try{
    res = await fetchWithTimeout(u.toString(), { headers:{ 'Accept':'application/json' } }, 12000);
  }catch(e){
    console.error('[geocode] fetch error:', e);
    throw new Error('Không gọi được geocode.php (bị chặn hoặc server không phản hồi).');
  }

  if(!res.ok){
    let msg = 'HTTP ' + res.status;
    try{
      const t = await res.text();
      console.log('[geocode] error body:', t);
      const j = JSON.parse(t);
      if (j && j.error) msg = j.error;
    }catch(_){}
    throw new Error('Geocode lỗi: ' + msg);
  }

  const j = await res.json();
  if(!Array.isArray(j) || !j.length) {
    throw new Error('Không tìm thấy địa chỉ phù hợp.');
  }

  const { lat, lon } = j[0];
  return [parseFloat(lon), parseFloat(lat)]; // [lon, lat]
}


// 4) Route distance (OSRM) – single definition + fallback
async function getRouteDistanceKm(start, end){
  const url = `https://router.project-osrm.org/route/v1/driving/${start[0]},${start[1]};${end[0]},${end[1]}?overview=false`;
  try{
    const res = await fetchWithTimeout(url, {}, 8000);
    if(!res.ok) throw new Error('OSRM HTTP '+res.status);
    const data = await res.json();
    const meters = data?.routes?.[0]?.distance ?? 0;
    if (!meters) throw new Error('No route');
    return +(meters / 1000).toFixed(2);
  }catch(e){
    // Fallback: ước lượng đường bộ ≈ 1.25 * chim bay
    return +(haversineKm(start, end) * 1.25).toFixed(2);
  }
}

// 5) Tính phí ship + cập nhật UI/hidden
async function calcShippingFromAddress(){
  const addrInp = document.getElementById('orderAddress');
  const errBox  = document.getElementById('orderErr');
  const addr = (addrInp?.value || '').trim();
  if(!addr){ throw new Error('Please enter address'); }

  // reset thông báo lỗi (nếu có)
  if (errBox){ errBox.style.display='none'; errBox.textContent=''; }

  const userLL = await geocodeAddress(addr);
  const km = await getRouteDistanceKm(COMPANY_COORDS, userLL);
  const fee = Math.ceil(km * SHIP_RATE_PER_KM);

  // UI
  const fmtVN = n => (Number(n)||0).toLocaleString('vi-VN');
  document.getElementById('distanceInfo').textContent = fmtVN(km);
  document.getElementById('shipFeeInfo').textContent  = fmtVN(fee);
  document.getElementById('orderDistance').textContent = fmtVN(km);
  document.getElementById('orderShipFee').textContent  = fmtVN(fee);

  // Hidden (giá trị thô)
  document.getElementById('distance-input').value = km;
  document.getElementById('shipping-fee-input').value = fee;
  document.getElementById('shipping-address-input').value = addr;

  recalcTotal();
}

// export để nút "Calculate" và blur gọi được
window.calcShippingFromAddress = calcShippingFromAddress;

// 6) Gắn sự kiện theo yêu cầu: chỉ blur & nút
const addrInputEl = document.getElementById('orderAddress');
if (addrInputEl){
  addrInputEl.addEventListener('blur', async ()=>{
    const v = addrInputEl.value.trim();
    if (!v) return;
    try { await calcShippingFromAddress(); }
    catch(ex){
      const errBox = document.getElementById('orderErr');
      if (errBox){ errBox.textContent = ex.message || 'Cannot calculate shipping'; errBox.style.display='block'; }
      console.error('[ship] blur calc failed:', ex);
    }
  });
}
const btnCalc = document.getElementById('btnCalcShip');
if (btnCalc){
  btnCalc.addEventListener('click', async ()=>{
    const errBox = document.getElementById('orderErr');
    if (errBox){ errBox.style.display='none'; errBox.textContent=''; }
    try { await calcShippingFromAddress(); }
    catch(ex){
      if (errBox){ errBox.textContent = ex.message || 'Cannot calculate shipping'; errBox.style.display='block'; }
      console.error('[ship] button calc failed:', ex);
    }
  });
}

// 7) Phím tắt kiểm tra nhanh API (Alt+G): sẽ thử geocode "Bến Thành, HCM"
document.addEventListener('keydown', async (e)=>{
  if (e.altKey && e.key.toLowerCase()==='g'){
    try{
      const test = await geocodeAddress('Chợ Bến Thành, Hồ Chí Minh');
      console.log('[ship] Geocode OK:', test);
    }catch(ex){
      console.error('[ship] Geocode test failed:', ex);
      const errBox = document.getElementById('orderErr');
      if (errBox){ errBox.textContent = 'Geocode bị chặn/timeout. Kiểm tra Network.'; errBox.style.display='block'; }
    }
  }
});


  // nếu muốn tự tính khi blur ô địa chỉ:
  document.getElementById('orderAddress')?.addEventListener('blur', async ()=>{
    if(!document.getElementById('orderAddress').value.trim()) return;
    try{ await calcShippingFromAddress(); }catch(_){}
  });
})();

</script>

<script>
const pmTableBody = document.querySelector('#pmTable tbody');
const pmErr = document.getElementById('pmErr');
const pm_id = document.getElementById('pm_id');
const pm_name = document.getElementById('pm_name');
const pm_desc = document.getElementById('pm_desc');

function openPmModal(){ document.getElementById('pmModal').style.display='block'; loadPm(); }
function closePmModal(){ document.getElementById('pmModal').style.display='none'; }
function resetPmForm(){ pm_id.value=''; pm_name.value=''; pm_desc.value=''; }

async function loadPm(){
  pmErr.style.display='none';
  try{
    const res = await fetch('payment_methods_api.php?action=list',{cache:'no-store'});
    const data = await res.json();
    pmTableBody.innerHTML = Array.isArray(data) ? data.map(row => {
      const active = Number(row.is_active) === 1;
      return `
        <tr>
          <td>#${row.payment_method_id}</td>
          <td>${escapeHtml(row.method_name||'')}</td>
          <td>
            <span class="badge ${active ? 'badge--active':'badge--hidden'}">
              ${active ? 'Active' : 'Hidden'}
            </span>
          </td>
          <td>${escapeHtml(row.description||'')}</td>
          <td style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
            <button class="edit-status-btn" type="button"
              onclick='editPm(${row.payment_method_id},
                              ${JSON.stringify(row.method_name).replace(/</g,"\\u003c")},
                              ${JSON.stringify(row.description||"").replace(/</g,"\\u003c")})'>
              <i class="fas fa-pen"></i> Edit
            </button>
            <button class="modal-btn ${active ? 'cancel-btn':'save-btn'}" type="button"
              onclick="togglePm(${row.payment_method_id}, ${active ? 0 : 1})">
              ${active ? '<i class="fas fa-eye-slash"></i> Hide' : '<i class="fas fa-eye"></i> Show'}
            </button>
          </td>
        </tr>
      `;
    }).join('') : '<tr><td colspan="5">No methods</td></tr>';
  }catch(e){
    pmErr.textContent = 'Không tải được danh sách phương thức: ' + (e.message||e);
    pmErr.style.display='block';
  }
}
</script>

<script>
// --- các hàm còn thiếu cho PM modal ---

function editPm(id, name, desc){
  pm_id.value   = id;
  pm_name.value = name;
  pm_desc.value = desc || '';
  pm_name.focus();
}

async function savePm(ev){
  ev.preventDefault();
  pmErr.style.display = 'none';
  pmErr.textContent   = '';

  if (!pm_id.value){
    pmErr.textContent = 'Hãy bấm “Edit” một phương thức trong danh sách trước khi cập nhật.';
    pmErr.style.display = 'block';
    return false;
  }

  const fd = new FormData(document.getElementById('pmForm'));
  try{
    const res  = await fetch('payment_methods_api.php?action=update', { method:'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Không thể cập nhật phương thức');

    resetPmForm();
    await loadPm();
    await refreshOrderPaymentSelect();
    return false;
  }catch(e){
    pmErr.textContent = e.message || 'Lỗi lưu dữ liệu';
    pmErr.style.display = 'block';
    return false;
  }
}

async function togglePm(id, active){
  pmErr.style.display = 'none';
  const fd = new FormData();
  fd.append('payment_method_id', id);
  fd.append('is_active', active);

  try{
    const res  = await fetch('payment_methods_api.php?action=toggle', { method:'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Không đổi được trạng thái');

    await loadPm();
    await refreshOrderPaymentSelect();
  }catch(e){
    pmErr.textContent = e.message || 'Lỗi đổi trạng thái';
    pmErr.style.display = 'block';
  }
}

async function refreshOrderPaymentSelect(){
  // Cần id="ordPayment" ở <select> thanh toán trong modal Add Order (bước B)
  const paySel = document.getElementById('ordPayment');
  if(!paySel) return;

  const res  = await fetch('payment_methods_api.php?action=list', { cache:'no-store' });
  const rows = await res.json();
  const actives = Array.isArray(rows) ? rows.filter(r => Number(r.is_active) === 1) : [];

  paySel.innerHTML = '<option value="">-- Select method --</option>' +
    actives.map(r => `<option value="${r.payment_method_id}">${escapeHtml(r.method_name||'')}</option>`).join('');
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>

</body>

</html>
<?php
include 'footer.php';
?>