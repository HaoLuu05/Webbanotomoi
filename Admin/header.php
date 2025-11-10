<?php
session_start();
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
$password = isset($_SESSION['password']) ? htmlspecialchars($_SESSION['password']) : '';
$email = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
$status = isset($_SESSION['status']) ? htmlspecialchars($_SESSION['status']) : '';
$phone_num = isset($_SESSION['phone_num']) ? htmlspecialchars($_SESSION['phone_num']) : ''; // Removed $ from key
$register_date = isset($_SESSION['register_date']) ? htmlspecialchars($_SESSION['register_date']) : ''; // Removed $ from key
$full_name = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : '';
$address = isset($_SESSION['address']) ? htmlspecialchars($_SESSION['address']) : '';
$address = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : '';

if (isset($_POST["logout"])) {
    session_destroy();
    session_start();

    $_SESSION['logout_message'] = "Bạn đã đăng xuất thành công.";
    header("Location: login.php");
    exit();
}
// Add this new variable for first login check
$showLoginNotification = false;
if (isset($_SESSION['first_login']) && $_SESSION['first_login'] === true) {
    $showLoginNotification = true;
    $_SESSION['first_login'] = false; // Reset the flag
}
include '../User/connect.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>showNotification('You don't have permission to access this page!','warning'); window.location.href='login.php';</script>";
    exit();
}

$counts = array();

// Count users
$users_query = "SELECT COUNT(*) as count FROM users_acc";
$result = mysqli_query($connect, $users_query);
$counts['users'] = mysqli_fetch_assoc($result)['count'];

// Count products
$products_query = "SELECT COUNT(*) as count FROM products";
$result = mysqli_query($connect, $products_query);
$counts['products'] = mysqli_fetch_assoc($result)['count'];

// Count orders
$orders_query = "SELECT COUNT(*) as count FROM orders";
$result = mysqli_query($connect, $orders_query);
$counts['orders'] = mysqli_fetch_assoc($result)['count'];
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <link rel="icon" href="../User/dp56vcf7.png" type="image/png">
     <script src="https://kit.fontawesome.com/8341c679e5.js" crossorigin="anonymous"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <title>Document</title> -->
</head>

<style>
    body{
        margin:0;
    }
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #333;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transform: translateX(150%);
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    
        .notification.show {
            transform: translateX(0);
        }
    
        /* Notification types */
        .notification.success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
    
        .notification.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
    
        .notification.info {
            background-color: #cce5ff;
            color: #004085;
            border-left: 4px solid #007bff;
        }
    
        .notification.warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
    
        /* Responsive design */
        @media (max-width: 768px) {
            .notification {
                width: 90%;
                top: 10px;
                right: 50%;
                transform: translateX(50%) translateY(-100%);
            }
    
            .notification.show {
                transform: translateX(50%) translateY(0);
            }
        }
    
</style>
<style>
    .nav-user {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 20px;
        padding-right: 20px;
    }

    .user-greeting {
        color: #fff;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .user-greeting i {
        font-size: 16px;
    }

    .logout-form {
        margin: 0;
    }

    .logout-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .logout-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .nav-user {
            padding: 10px;
            flex-direction: column;
            gap: 10px;
        }
    }
</style>
<style>
    .nav-user {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 20px;
        padding-right: 20px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        max-width: 240px;
    }

    .user-greeting {
        color: #fff;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .user-greeting .greeting-text {
        display: inline-block;
        opacity: 0.9;
    }

    .user-greeting .username {
        font-weight: bold;
        color: #fff;
        position: relative;
    }

    .user-greeting .username::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: #fff;
        transition: width 0.3s ease;
    }

    .user-greeting:hover .username::after {
        width: 100%;
    }

    .logout-form {
        margin: 0;
    }

    .logout-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .logout-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .nav-user {
            padding: 10px;
            flex-direction: column;
            gap: 10px;
            margin-right: 10px;
        }
    }
</style>
<style>
    /* Admin Header */
.admin-header {
    background-color: #fff !important;
    color: white;
    padding: 20px;
    text-align: center;
}

/* Admin Main Sections */
.admin-section {
    margin: 20px ;
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

.admin-table th, .admin-table td {
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
    background-color: #2c3e50; /* Darker color for admin navbar */
    overflow: hidden;
    font-weight: bold;
    padding: 10px 0;
    padding-left: 0px !important;
}

.navbar a {
    color: #ecf0f1; /* Light text color */
    float: left;
    display: block;
    text-align: center;
    padding: 14px 20px;
    text-decoration: none;
    
    transition: background-color 0.3s, color 0.3s; /* Smooth transition */
}

/* Hover Effects for Links */
.navbar a:hover {
    background-color: #34495e; /* Slightly lighter background on hover */
    color: #1abc9c; /* Accent color for text on hover */
}

/* Active Link */
.navbar a.active {
    background-color: #1abc9c; /* Highlight color for active page */
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
    background-color: #1abc9c; /* Highlight for dropdown items on hover */
}

/* Show Dropdown on Hover */
.navbar .dropdown:hover .dropdown-content {
    display: block;
}

#logoheader{
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

Specific hover effect for Ban button
.admin-table button[style*="background-color: red;"] {
    background-color: #e74c3c;
    color: white;
    border: none;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.admin-table button[style*="background-color: red;"]:hover {
     background-color: #c0392b; /* Darker red on hover  */
    transform: scale(1.1); 
    /* Slight zoom effect  */
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2); 
    /* Add shadow  */
}
/* More Button Styling */
button.link {
    margin-top: 20px;
    display: inline-block;
    background-color: #1abc9c; /* Màu nền xanh ngọc */
    color: white; /* Màu chữ trắng */
    border: none;
    border-radius: 8px;
    padding: 10px 20px; /* Kích thước nút */
    font-size: 16px; /* Kích thước chữ */
    font-weight: bold; /* Đậm chữ */
    cursor: pointer; /* Hiển thị icon tay khi hover */
    transition: background-color 0.3s ease, box-shadow 0.3s ease; /* Hiệu ứng mượt */
    text-align: center; /* Canh giữa văn bản */
}

/* Hover Effect for More Button */
button.link:hover {
    background-color: #16a085; /* Màu nền đậm hơn khi hover */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Đổ bóng khi hover */
}

/* Active State for More Button */
button.link:active {
    background-color: #0e7766; /* Màu tối hơn khi bấm */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Giảm độ cao bóng */
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
/* Enhanced Admin Header */
.admin-header {
    background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
    padding: 20px;
    position: relative;
    overflow: hidden;
    animation: headerGradient 20s ease infinite;
}

.logo {
    position: relative;
    z-index: 2;
}

#logoheader {
    max-width: 10%;
    transition: transform 0.3s ease;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
}


/* Enhanced Navbar */
.navbar {
    background: rgba(44, 62, 80, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.navbar.scrolled {
    padding: 5px 0;
    background: rgba(44, 62, 80, 0.98);
}

.navbar a {
    position: relative;
    /* overflow: hidden; */
    transition: all 0.3s ease;
}

.navbar a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: #1abc9c;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.navbar a:hover::after {
    width: 80%;
}

/* Enhanced Nav User Section */
.nav-user {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transform: translateY(-100%);
    animation: slideDown 0.5s ease forwards;
}

.user-greeting {
    position: relative;
}

.user-greeting::before {
    /* content: '';
    position: absolute;
    top: 0;
    left: -50%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    ); */
    /* animation: shimmer 2s infinite; */
}

/* Animations */
@keyframes headerGradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

@keyframes slideDown {
    to {
        transform: translateY(0);
    }
}

@keyframes shimmer {
    100% {
        left: 200%;
    }
}

</style>
<style>
        /* Replace or update the admin-header and logo styles */
    .admin-header {
        background: linear-gradient(270deg,#40C1A8, #2C3E50,rgb(255, 255, 255));
        background-size: 400% 400%;
        padding: 20px;
        position: relative;
        overflow: hidden;
        /* min-height: 120px;
        display: flex;
        justify-content: center;
        align-items: center; */
        animation: gradientAnimation 30s ease-in-out infinite;
    }
    


    
    @keyframes gradientAnimation {
        0% {
            background-position: 0% 50%;
        }
        50% {
            background-position: 100% 50%;
        }
        100% {
            background-position: 0% 50%;
        }
    }
</style>
<style>
/* Add or update these styles in your header.php */
.nav-count {
    position: absolute;
    top: -8px;
    right: -8px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Different colors for different types */
a[href*="users"] .nav-count {
    background: #007bff;
    color: white;
}

a[href*="orders"] .nav-count {
    background: #28a745;
    color: white;
}

a[href*="products"] .nav-count {
    background: #dc3545;
    color: white;
}

/* Hover effect */
.navbar a:hover .nav-count {
    transform: scale(1.2) translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Animation for new items */
@keyframes countBounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.nav-count.new {
    animation: countBounce 1s ease infinite;
}

/* Position adjustment for the navbar links */
.navbar a {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
</style>

<body>
    <div id="notification" class="notification"></div>
    <header class="admin-header">
        <div class="logo">
            <a href="index.php">
                <img id="logoheader" src="../User/dp56vcf7.png" alt="Logo">
            </a>
        </div>
    </header>
    
    <div class="navbar">
     <a href="index.php" class="homelink">
        <i class="fa-solid fa-house-chimney"></i>
        <span>Home</span>
    </a>
    <a href="statics.php">
        <i class="fa-regular fa-clipboard"></i>
        <span>Statics</span>
    </a>
    <a href="manage-users.php">
        <i class="fa-solid fa-users-rectangle"></i>
        <span>Manage Users</span>
        <span class="nav-count"><?php echo $counts['users']; ?></span>
    </a>
    <a href="manage-orders.php">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>Manage Orders</span>
        <span class="nav-count"><?php echo $counts['orders']; ?></span>
    </a>
    <a href="manage-products.php">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>Manage Products</span>
        <span class="nav-count"><?php echo $counts['products']; ?></span>
    </a>
             <a href="best-seller.php">
        <i class="fa-solid fa-star"></i>
        <span>Best Sellers</span>
    <span class="nav-count"><?php echo $counts['products']; ?></span>
</a>
    <a href="manage-inventory.php">
        <i class="fa-solid fa-star"></i>
        <span>Inventory Statistics</span>
    <span class="nav-count"><?php echo $counts['products']; ?></span>
</a>
        <div class="nav-user">
        <span class="user-greeting">
            <i class="fa-regular fa-user"></i>
            <span class="greeting-text">Hi,</span>
            <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </span>
        <form method="POST" class="logout-form">
            <button type="submit" name="logout" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                Log out
            </button>
        </form>
    </div>
    </div> 
<script>
        function showNotification(message, type) {
        const notification = document.getElementById('notification');
        let icon = '';
        
        // Set icon based on notification type
        switch(type) {
            case 'success':
                icon = '<i class="fa-solid fa-circle-check"></i>';
                break;
            case 'error':
                icon = '<i class="fa-solid fa-circle-xmark"></i>';
                break;
            case 'warning':
                icon = '<i class="fa-solid fa-triangle-exclamation"></i>';
                break;
            case 'info':
                icon = '<i class="fa-solid fa-circle-info"></i>';
                break;
        }
        
        notification.innerHTML = `${icon} ${message}`;
        notification.className = `notification ${type}`;
        
        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Hide notification after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.textContent = '';
            }, 300);
        }, 5000);
    }
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        let lastScroll = 0;
    
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
    
            lastScroll = currentScroll;
        });
    
        // Add active state to current page link
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.navbar a');
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    
 
        // Enhanced logout button interaction
        const logoutBtn = document.querySelector('.logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('mouseenter', () => {
                logoutBtn.style.transform = 'translateX(5px)';
            });
    
            logoutBtn.addEventListener('mouseleave', () => {
                logoutBtn.style.transform = 'translateX(0)';
            });
        }
    
        // Enhance notification system
        const showEnhancedNotification = (message, type) => {
            const notification = document.getElementById('notification');
            let icon = '';
            
            switch(type) {
                case 'success':
                    icon = '<i class="fa-solid fa-circle-check"></i>';
                    break;
                case 'error':
                    icon = '<i class="fa-solid fa-circle-xmark"></i>';
                    break;
                case 'warning':
                    icon = '<i class="fa-solid fa-triangle-exclamation"></i>';
                    break;
                case 'info':
                    icon = '<i class="fa-solid fa-circle-info"></i>';
                    break;
            }
            
            notification.innerHTML = `${icon} ${message}`;
            notification.className = `notification ${type}`;
            
            requestAnimationFrame(() => {
                notification.classList.add('show');
            });
            
            setTimeout(() => {
                notification.classList.add('fade-out');
                setTimeout(() => {
                    notification.classList.remove('show', 'fade-out');
                    notification.textContent = '';
                }, 300);
            }, 5000);
        };
    
        // Replace existing showNotification function
        window.showNotification = showEnhancedNotification;
    });
    document.addEventListener('DOMContentLoaded', function() {
    // Store previous counts in localStorage
    const prevCounts = JSON.parse(localStorage.getItem('navCounts') || '{}');
    const currentCounts = {
        users: <?php echo $counts['users']; ?>,
                orders: <?php echo $counts['orders']; ?>,
                products: <?php echo $counts['products']; ?>
            };

            // Compare and animate new counts
            Object.keys(currentCounts).forEach(key => {
                if (prevCounts[key] && currentCounts[key] > prevCounts[key]) {
                    const countElement = document.querySelector(`a[href*="${key}"] .nav-count`);
                    if (countElement) {
                        countElement.classList.add('new');
                    }
                }
            });

            // Save current counts
            localStorage.setItem('navCounts', JSON.stringify(currentCounts));
        });
    </script>
</body>
</html>
