<?php
include 'header.php';

// Fetch users from database (có hỗ trợ tìm kiếm)
$keyword = trim($_GET['keyword'] ?? '');

if ($keyword !== '') {
    $like = '%' . $keyword . '%';

    $sql = "
        SELECT *
        FROM users_acc
        WHERE username  LIKE ?
           OR full_name LIKE ?
           OR email     LIKE ?
           OR phone_num LIKE ?
        ORDER BY register_date ASC
    ";

    $stmt = $connect->prepare($sql);
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM users_acc ORDER BY register_date ASC";
    $result = mysqli_query($connect, $sql);
}


// Handle user actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $success = false;
        $message = '';
        $currentUser = $_SESSION['username'];

        // For all actions except 'add', we need a user ID.
        if ($action !== 'add') {
            if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
                $userId = intval($_POST['user_id']);
            } else {
                echo "<script>showNotification('User ID not provided!', 'error');</script>";
                exit;
            }

            // For actions other than edit, prevent modifying your own account.
            // For edit, you may want to allow self-editing. Adjust as needed.
            if ($action !== 'edit') {
                $checkSql = "SELECT username FROM users_acc WHERE id = $userId";
                $checkResult = mysqli_query($connect, $checkSql);
                if (!$checkResult) {
                    echo "<script>showNotification('Error fetching user data!', 'error');</script>";
                    exit;
                }
                $checkRow = mysqli_fetch_assoc($checkResult);
                if ($checkRow) {
                    $targetUser = $checkRow['username'];
                } else {
                    echo "<script>showNotification('User not found!', 'error');</script>";
                    exit;
                }
                if ($targetUser === $currentUser) {
                    echo "<script>showNotification('You cannot modify your own account!', 'error');</script>";
                    exit;
                }
            }
        }

        switch ($action) {
            case 'ban':
                $newStatus = ($_POST['current_status'] == 'banned') ? 'activated' : 'banned';
                $sql = "UPDATE users_acc SET status = '$newStatus' WHERE id = $userId";
                $success = mysqli_query($connect, $sql);
                $message = $success ? ($newStatus == 'banned' ? 'User banned successfully' : 'User unbanned successfully') : 'Error updating status';
                break;

            case 'disable':
                $newStatus = ($_POST['current_status'] == 'disabled') ? 'activated' : 'disabled';
                $sql = "UPDATE users_acc SET status = '$newStatus' WHERE id = $userId";
                $success = mysqli_query($connect, $sql);
                $message = $success ? ($newStatus == 'disabled' ? 'User disabled successfully' : 'User activated successfully') : 'Error updating status';
                break;

            case 'delete':
                $sql = "DELETE FROM users_acc WHERE id = $userId";
                $success = mysqli_query($connect, $sql);
                $message = $success ? 'User deleted successfully' : 'Error deleting user';
                break;

            case 'add':
                $username = mysqli_real_escape_string($connect, $_POST['username']);
                $email = mysqli_real_escape_string($connect, $_POST['email']);
                $password = mysqli_real_escape_string($connect, $_POST['password']);
                $role = mysqli_real_escape_string($connect, $_POST['role']);
                $phone = mysqli_real_escape_string($connect, $_POST['phone']);
                $address = mysqli_real_escape_string($connect, $_POST['address']);
                $fullName = mysqli_real_escape_string($connect, $_POST['full_name']);

                // Check if username already exists in the database
                $checkSql = "SELECT id FROM users_acc WHERE username = '$username'";
                $checkResult = mysqli_query($connect, $checkSql);
                if (mysqli_num_rows($checkResult) > 0) {
                    echo "<script>
                showNotification('Username already exists!', 'error');
                setTimeout(() => { window.location.href = 'manage-users.php'; }, 1000);
              </script>";
                    exit;
                }

                $sql = "INSERT INTO users_acc (username, email, password, role, phone_num, address, full_name, status, register_date) 
            VALUES ('$username', '$email', '$password', '$role', '$phone', '$address', '$fullName', 'activated', NOW())";
                $success = mysqli_query($connect, $sql);
                $message = $success ? 'User added successfully' : 'Error adding user';
                break;


            case 'edit':
                // Retrieve the edited data from the form.
                $username = mysqli_real_escape_string($connect, $_POST['username']);
                $email = mysqli_real_escape_string($connect, $_POST['email']);
                $fullName = mysqli_real_escape_string($connect, $_POST['full_name']);
                $phone = mysqli_real_escape_string($connect, $_POST['phone']);
                $address = mysqli_real_escape_string($connect, $_POST['address']);
                $role = mysqli_real_escape_string($connect, $_POST['role']);
                $password = mysqli_real_escape_string($connect, $_POST['password']);
                if(!empty($password)) {
                    $sql = "UPDATE users_acc 
                        SET username = '$username', email = '$email', full_name = '$fullName', phone_num = '$phone', address = '$address', role = '$role',password='$password'
                        WHERE id = $userId";
                } else {
                    $sql = "UPDATE users_acc 
                        SET username = '$username', email = '$email', full_name = '$fullName', phone_num = '$phone', address = '$address', role = '$role'
                        WHERE id = $userId";
                }

                
                $success = mysqli_query($connect, $sql);
                $message = $success ? 'User updated successfully' : 'Error updating user';
                break;
        }

        echo "<script>
            showNotification('$message', '" . ($success ? 'success' : 'error') . "');
            setTimeout(() => {
                window.location.href = 'manage-users.php';
            }, 1000);
        </script>";
        exit;
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <!-- <link rel="stylesheet" href="style.css"> -->
    <link rel="icon" href="../User/dp56vcf7.png" type="image/png">
    <!-- <script src="mu.js"></script> -->
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
</style>

<style>
    .popup {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .popup-content {
        background: white;
        padding: 20px;
        border-radius: 8px;
        width: 400px;
        max-width: 90%;
    }

    .popup-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input,
    .form-group select {
        width: 380px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .status-badge.active {
        background: #28a745;
        color: white;
    }

    .status-badge.banned {
        background: #dc3545;
        color: white;
    }

    .ban-btn {
        background: #dc3545 !important;
    }

    .unban-btn {
        background: #28a745 !important;
    }

    .delete-btn {
        background: #6c757d !important;
    }
</style>
<style>
    /* Add to your existing styles */
    .status-badge.disabled {
        background: #6c757d;
        color: white;
    }

    .disable-btn {
        background: #ffc107 !important;
        color: #000 !important;
    }

    .activate-btn {
        background: #28a745 !important;
    }

    .disable-btn:hover {
        background: #e0a800 !important;
    }

    .activate-btn:hover {
        background: #218838 !important;
    }
</style>
<style>
    .close-btn {
        position: absolute;
        right: 15px;
        top: 15px;
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #666;
        transition: color 0.3s ease;
    }

    .close-btn:hover {
        color: #dc3545;
    }

    .popup-content {
        position: relative;
        /* ...existing styles... */
    }

    #select {
        width: 400px;
    }
</style>
<style>
    /* Add this to your existing styles */
    .popup-buttons button {
        min-width: 120px;
        padding: 10px 20px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .confirm-btn {
        background-color: #28a745;
        color: white;
        border: none;
    }

    .cancel-btn {
        background-color: #6c757d;
        color: white;
        border: none;
    }

    .confirm-btn:hover {
        background-color: #218838;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .cancel-btn:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    #add-user-btn {
        background: #1abc9c;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    #add-user-btn:hover {
        background: #16a085;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    #add-user-btn i {
        font-size: 16px;
    }

    /* Product form icons */
    .form-group label i {
        width: 20px;
        /* color: #1abc9c; */
        margin-right: 8px;
    }


    .form-group input:focus,
    .form-group select:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 0 2px rgba(26, 188, 156, 0.2);
        outline: none;
    }
</style>

<body>

    <main>

        <!-- User Management -->
                <!-- User Management -->
                <section class="admin-section">
                    <h2><i class="fa-solid fa-users">&nbsp;&nbsp;</i>User Management</h2>

                    <!-- Hàng 1: chỉ có nút Add User -->
                    <div style="margin-bottom: 10px;">
                        <button onclick="addUser()" id="add-user-btn">
                            <i class="fa-solid fa-plus"></i>
                            Add User
                        </button>
                    </div>

                    <!-- Hàng 2: ô tìm kiếm, nằm hoàn toàn bên dưới nút Add User -->
                    <form method="get" action="manage-users.php" style="margin: 0 0 18px 0;">
                        <div style="display:flex; gap:8px; width:100%; max-width:480px;">

                            <input
                                type="text"
                                name="keyword"
                                placeholder="Search by username / full name / email / phone"
                                value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>"
                                style="padding:7px 10px; border:1px solid #ccc; border-radius:6px; flex:1;"
                            >

                            <!-- Search button -->
                            <button type="submit"
                                    style="padding:8px 14px; border-radius:6px; border:none;
                                        background:#1abc9c; color:#fff; display:inline-flex;
                                        align-items:center; gap:6px; cursor:pointer;">
                                <i class="fas fa-search"></i> Search
                            </button>

                            <!-- Reset button (luôn xuất hiện) -->
                            <a href="manage-users.php"
                            style="padding:8px 14px; border-radius:6px; border:1px solid #ccc;
                                    background:#f8f9fa; text-decoration:none; color:#333;
                                    display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-undo"></i> Reset
                            </a>

                        </div>
                    </form>


            </div>

            <table class="admin-table">

                <!-- ... existing code -->
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-id-badge"></i> ID</th>
                        <th><i class="fa-solid fa-user"></i> Username</th>
                        <th><i class="fa-solid fa-user"></i> Full Name</th>
                        <th><i class="fa-solid fa-envelope"></i> Email</th>
                        <th><i class="fa-solid fa-phone"></i> Phone</th>
                        <th><i class="fa-solid fa-user-tag"></i> Role</th>
                        <th><i class="fa-solid fa-toggle-on"></i> Status</th>
                        <th><i class="fa-solid fa-calendar-alt"></i> Register Date</th>
                        <th><i class="fa-solid fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <!-- ... existing code -->
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone_num']); ?></td>
                            <td><?php echo htmlspecialchars($row['role']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['register_date'])); ?></td>
                            <td>
                                <?php
                                $isCurrentUser = $row['username'] === $_SESSION['username'];
                                if (!$isCurrentUser):
                                    ?>
                                    <button
                                        onclick="showActionPopup('ban', <?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')"
                                        class="<?php echo $row['status'] == 'banned' ? 'unban-btn' : 'ban-btn'; ?>">
                                        <?php echo $row['status'] == 'banned' ? 'Unban' : 'Ban'; ?>
                                    </button>
                                    <button
                                        onclick="showActionPopup('disable', <?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')"
                                        class="<?php echo $row['status'] == 'disabled' ? 'activate-btn' : 'disable-btn'; ?>">
                                        <?php echo $row['status'] == 'disabled' ? 'Activate' : 'Disable'; ?>
                                    </button>
                                    <button
                                        onclick="showEditPopup(<?php
                                        echo $row['id']; ?>, '<?php echo addslashes($row['username']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['full_name']); ?>', '<?php echo addslashes($row['phone_num']); ?>', '<?php echo addslashes($row['address']); ?>', '<?php echo addslashes($row['role']); ?>')"
                                        class="edit-btn">Edit</button>
                                    <!-- <button onclick="showActionPopup('delete', <?php echo $row['id']; ?>)"
                                        class="delete-btn">Delete</button> -->
                                <?php else: ?>
                                    <span class="current-user-notice">You Can't Modify Your Own Account</span>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>
    <!-- Add this before </body> tag -->
    <div id="actionPopup" class="popup">
        <div class="popup-content">
            <button type="button" class="close-btn" onclick="hideActionPopup()">
                <i class="fa-solid fa-times"></i>
            </button>
            <h3>Confirm Action</h3>
            <p id="popupMessage"></p>
            <form method="POST" action="<?php htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="actionForm">
                <input type="hidden" name="user_id" id="popupUserId">
                <input type="hidden" name="action" id="popupAction">
                <input type="hidden" name="current_status" id="popupCurrentStatus">
                <div class="popup-buttons">
                    <button type="submit" class="confirm-btn">Confirm</button>
                    <button type="button" onclick="hideActionPopup()" class="cancel-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <div id="editUserPopup" class="popup">
        <div class="popup-content">
            <button type="button" class="close-btn" onclick="hideEditPopup()">
                <i class="fa-solid fa-times"></i>
            </button>
            <h3><i class="fa-solid fa-user-edit"></i> Edit User</h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-group">
                    <label><i class="fa fa-user"></i> Username:</label>
                    <input type="text" name="username" id="editUsername" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Password: (leave empty if not change)</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="editPassword" >
                        <i class="fa-solid fa-eye" id="togglePassword"
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fa fa-envelope"></i> Email:</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>
                <div class="form-group">
                    <label><i class="fa fa-id-badge"></i> Full Name:</label>
                    <input type="text" name="full_name" id="editFullName" required>
                </div>
                <div class="form-group">
                    <label><i class="fa fa-phone"></i> Phone:</label>
                    <input type="text" name="phone" id="editPhone" pattern="\d{8,20}" minlength="8" maxlength="20"
                        title="Please enter a phone number with 8 to 20 digits." required>
                </div>
                <div class="form-group">
                    <label><i class="fa fa-home"></i> Address:</label>
                    <input type="text" name="address" id="editAddress" required>
                </div>
                <div class="form-group">
                    <label><i class="fa fa-users"></i> Role:</label>
                    <select name="role" id="editRole" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="popup-buttons">
                    <button type="submit" class="confirm-btn">
                        <i class="fa-solid fa-check"></i>
                        Update User
                    </button>
                    <button type="button" onclick="hideEditPopup()" class="cancel-btn">
                        <i class="fa-solid fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>


    <div id="addUserPopup" class="popup">
        <div class="popup-content">
            <button type="button" class="close-btn" onclick="hideAddUserPopup()">
                <i class="fa-solid fa-times"></i>
            </button>
            <h3>Add New User</h3>
            <form method="POST" action="<?php htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="action" value="add">
                <!-- ... existing code -->
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Username:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Email:</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Password:</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Full Name:</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-phone"></i> Phone:</label>
                    <input type="tel" name="phone" required pattern="[0-9]\d{9,20}" minlength="9" maxlength="20"
                        title="Please enter a minimum of 9-digit phone number">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-map-marker-alt"></i> Address:</label>
                    <input type="text" name="address" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user-tag"></i> Role:</label>
                    <select name="role" id="select" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <!-- ... existing code -->
                <div class="popup-buttons">
                    <button type="submit" class="confirm-btn">
                        <i class="fa-solid fa-check"></i>
                        Add User
                    </button>
                    <button type="button" onclick="hideAddUserPopup()" class="cancel-btn">
                        <i class="fa-solid fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function showActionPopup(action, userId, currentStatus = '') {
            const popup = document.getElementById('actionPopup');
            const message = document.getElementById('popupMessage');
            const actionInput = document.getElementById('popupAction');
            const userIdInput = document.getElementById('popupUserId');
            const statusInput = document.getElementById('popupCurrentStatus');

            actionInput.value = action;
            userIdInput.value = userId;
            statusInput.value = currentStatus;

            switch (action) {
                case 'ban':
                    message.textContent = currentStatus === 'banned'
                        ? 'Are you sure you want to unban this user?'
                        : 'Are you sure you want to ban this user?';
                    break;
                case 'disable':
                    message.textContent = currentStatus === 'disabled'
                        ? 'Are you sure you want to activate this user?'
                        : 'Are you sure you want to disable this user?';
                    break;
                case 'delete':
                    message.textContent = 'Are you sure you want to delete this user?';
                    break;
            }

            popup.style.display = 'flex';
        }

        function hideActionPopup() {
            document.getElementById('actionPopup').style.display = 'none';
        }

        function showAddUserPopup() {
            document.getElementById('addUserPopup').style.display = 'flex';
        }

        function hideAddUserPopup() {
            document.getElementById('addUserPopup').style.display = 'none';
        }

        document.getElementById('add-user-btn').onclick = showAddUserPopup;
    </script>
    <script>
        function showEditPopup(userId, username, email, fullName, phone, address, role) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editPhone').value = phone;
            document.getElementById('editAddress').value = address;
            document.getElementById('editRole').value = role;
            document.getElementById('editUserPopup').style.display = 'flex';
        }

        function hideEditPopup() {
            document.getElementById('editUserPopup').style.display = 'none';
        }
    </script>
    <!-- Add this script at the end of your document before </body> -->
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('editPassword');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    </script>
</body>

</html>
<?php
include 'footer.php';
?>