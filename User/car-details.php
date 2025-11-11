<?php

header('Cache-Control: public, max-age=3600');
include 'header.php';
include 'connect.php';
// Get car name from URL
if (!isset($_GET['name'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit();
}
// Add view count
if (isset($car['product_id'])) {
    $view_query = "UPDATE products SET views = views + 1 WHERE product_id = ?";
    $stmt = mysqli_prepare($connect, $view_query);
    mysqli_stmt_bind_param($stmt, "i", $car['product_id']);
    mysqli_stmt_execute($stmt);
}

// Get similar cars
$similar_query = "SELECT * FROM products 
                 WHERE brand_id = ? 
                 AND product_id != ? 
                 AND status IN ('selling', 'discounting')
                 LIMIT 4";
$stmt = mysqli_prepare($connect, $similar_query);
mysqli_stmt_bind_param($stmt, "ii", $car['brand_id'], $car['product_id']);
mysqli_stmt_execute($stmt);
$similar_cars = mysqli_stmt_get_result($stmt);

// First, modify the PHP section to fetch additional images
$car_name = mysqli_real_escape_string($connect, $_GET['name']);

// Get product details with car type using car_name
$query = "SELECT p.*, c.type_name 
          FROM products p 
          LEFT JOIN car_types c ON p.brand_id = c.type_id 
          WHERE p.car_name = '$car_name'";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<script>window.location.href='index.php';</script>";
    exit();
}

$car = mysqli_fetch_assoc($result);
$product_id = $car['product_id'];
$additional_images_query = "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC";
$stmt = mysqli_prepare($connect, $additional_images_query);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$additional_images_result = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $car['car_name']; ?> - Chi Tiết</title>
    <script src="https://kit.fontawesome.com/8341c679e5.js" crossorigin="anonymous"></script>
    <link rel="icon" href="dp56vcf7.png" type="image/png">
</head>

<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
    }

    main {
        padding: 40px 0;
        background-color: #f4f4f4;
    }

    .container {
        width: 90%;
        max-width: 1200px;
        margin: 20px auto;
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .car-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 40px;
    }

    .car-image {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 600px;
        height: 400px;
        overflow: hidden;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .car-image img {
        width: 100%;
        height: auto;
        transition: transform 0.3s ease;
    }

    .car-image:hover img {
        transform: scale(1.05);
    }

    .car-info {
        background: linear-gradient(to bottom right, #ffffff, #f1f3f5);
        border-radius: 10px;
        padding: 10px;
        max-width: 100%;
        width: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        font-family: 'Segoe UI', sans-serif;
        line-height: 1.4;
        transition: all 0.3s ease-in-out;
    }

    .car-info:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
    }

    .car-title {
        font-size: 30px;
        font-weight: 700;
        color: #212529;
        margin-bottom: 0;
        margin-top: 0;
        text-align: left;
    }

    .car-info h2 {
        font-size: 25px;
        color:#28A745;
        margin-bottom: 0;
        /* margin-top: 0; */
        font-weight: bold;
        text-align: left;
    }

    .car-features {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px 24px;
        margin-top: 12px;
    }

    .car-features-left,
    .car-features-right {
        display: flex;
        flex-direction: column;
        gap: 14px;
        justify-content: space-between;
    }

    .car-feature-item {
        font-size: 13px;
        color: #495057;
        display: flex;
        align-items: center;
        /* gap: 10px; */
        margin: 0;
        line-height: 1.5;
        padding: 5px 0;
    }

    .car-feature-item strong {
        color: #2c3e50;
        font-weight: 600;
        min-width: 130px;
        flex-shrink: 0;
    }

    .car-info i {
        color: #0d6efd;
        font-size: 18px;
        min-width: 18px;
        text-align: center;
        flex-shrink: 0;
    }

    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        margin-left: 4px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .status-selling {
        background-color: #28a745;
        color: #fff;
    }

    .status-discounting {
        background-color: #dc3545;
        color: #fff;
    }

    .status-hidden {
        background-color: #6c757d;
        color: #fff;
    }

    .status-soldout {
        background-color: #343a40;
        color: #fff;
    }

    @media (max-width: 768px) {
        .car-features {
            grid-template-columns: 1fr;
        }

        .car-title,
        .car-info h2 {
            text-align: center;
        }

        .car-feature-item strong {
            min-width: 110px;
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 20px;
        }

        .car-details {
            grid-template-columns: 1fr;
        }

        .car-info h2 {
            font-size: 1.5rem;
        }

        .car-info p {
            font-size: 1rem;
        }
    }


    .actions {
        display: flex;
        gap: 20px;
        margin-top: 30px;
        padding: 20px 0;
        border-top: 1px solid #eee;
    }

    .btn {
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
    }

    .btn.primary {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    }

    .btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
    }

    .btn.primary:active {
        transform: translateY(0);
    }

    .btn.secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
    }

    .btn.secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
    }

    .btn.secondary:active {
        transform: translateY(0);
    }

    /* Add icons to buttons */
    .btn.primary::before {
        content: '\f07a';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
    }

    .btn.secondary::before {
        content: '\f060';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .actions {
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            width: 100%;
            justify-content: center;
            padding: 15px;
        }
    }

    .car-info i {
        padding-right: 10px;
    }

    .car-title {
        color: rgb(150, 150, 150);
        text-transform: uppercase;
    }

    .car-info:hover {
        background-color: #f0f0f0;
        transition: background-color 0.3s ease-in-out;
    }

    /* add hover effect for the car-features and car-safety divs */
    .car-features:hover,
    .car-safety:hover {
        background-color: #f9f9f9;
        transition: background-color 0.3s ease-in-out;
    }

    .active {
        border: 4px solid #007bff;
        /* Blue border for active thumbnail */
        opacity: 1;
        /* Full opacity for active thumbnail */
    }

    .thumbnail-container {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 20px;
    }

    .thumbnail {
        width: 80px;
        height: 60px;
        margin: 0 5px;
        cursor: pointer;
        opacity: 0.7;
        /* Reduced opacity for inactive thumbnails */
        transition: opacity 0.3s ease-in-out;
        /* Smooth transition for opacity change */
    }

    .thumbnail:hover {
        opacity: 1;
        /* Full opacity on hover */
    }

    /* .thumbnail-container {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 20px;
    gap: 20px; Added gap for better spacing between thumbnails */
    /* } */
    /* animation while change the image */
    .animation {
        transition: opacity 0.5s ease-in-out;
        /* Smooth transition for image change */
    }

    /* Add a hover effect to the buttons */
    .btn:hover {
        background-color: #0056b3;
        /* Darker blue on hover */
        color: white;
        /* White text on hover */
        transform: scale(1.05);
        /* Slightly enlarge the button on hover */
        transition: all 0.3s ease-in-out;
        /* Smooth transition for all properties */
    }

    /* ...existing code... */

    /* Add these new styles at the end of your CSS file */
    .fade {
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
    }

    .fade-in {
        opacity: 1;
    }

    .car-image img {
        width: 600px;
        height: 350px;
        max-width: 600px;
        border: 2px solid #f4f4f4;
        border-radius: 10px;
        object-fit: contain;
        opacity: 1;
        transition: opacity 0.5s ease-in-out;
    }

    /* ...existing code... */

    .active {
        border: 4px solid #007bff;
        opacity: 1;
        transform: scale(1.1);
        transition: all 0.3s ease-in-out;
        box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        z-index: 1;
    }

    .thumbnail {
        width: 80px;
        height: 60px;
        margin: 0 5px;
        cursor: pointer;
        opacity: 0.7;
        transition: all 0.3s ease-in-out;
        position: relative;
        border: 4px solid transparent;
    }

    .thumbnail:hover {
        opacity: 1;
        transform: scale(1.05);
        box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
    }
</style>
<style>
    thumbnail-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
        padding: 10px;
        background: rgba(0, 0, 0, 0.05);
        border-radius: 10px;
    }

    .thumbnail {
        width: 80px;
        height: 60px;
        object-fit: cover;
        border: 2px solid transparent;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
        opacity: 0.7;
    }

    .thumbnail:hover {
        opacity: 1;
        transform: scale(1.05);
    }

    .thumbnail.active {
        border-color: #007bff;
        opacity: 1;
        transform: scale(1.1);
        box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
    }

    .arrow {
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        padding: 10px 15px;
        cursor: pointer;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .arrow:hover {
        background: rgba(0, 0, 0, 0.8);
        transform: scale(1.1);
    }

    .car-image img {
        width: 100%;
        height: auto;
        border-radius: 10px;
        transition: opacity 0.5s ease;
    }
</style>
<style>
/* Image Gallery Enhancements */
.car-image {
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    transform: translateY(0);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.car-image:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.2);
}

.car-image::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 50%;
    height: 100%;
    background: linear-gradient(
        to right,
        rgba(255,255,255,0) 0%,
        rgba(255,255,255,0.3) 100%
    );
    transform: skewX(-25deg);
    transition: all 0.75s;
}

.car-image:hover::after {
    left: 150%;
}

/* Thumbnail Container Enhancement */
.thumbnail-container {
    background: linear-gradient(145deg, #f6f6f6, #ffffff);
    box-shadow: inset 5px 5px 10px #d1d1d1,
                inset -5px -5px 10px #ffffff;
    padding: 15px;
    border-radius: 15px;
    position: relative;
}

/* Enhanced Thumbnail Animations */
.thumbnail {
    transform: scale(1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    filter: grayscale(30%);
}

.thumbnail:hover {
    transform: scale(1.15);
    filter: grayscale(0%);
    z-index: 2;
}

.thumbnail.active {
    animation: pulse 2s infinite;
    filter: grayscale(0%);
}

/* Arrow Button Enhancement */
.arrow {
    background: linear-gradient(145deg, #007bff, #0056b3);
    color: white;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: scale(1);
    transition: all 0.3s ease;
}

.arrow:hover {
    transform: scale(1.1);
    box-shadow: 0 0 15px rgba(0,123,255,0.5);
}

/* Car Info Enhancement */
.car-info {
    position: relative;
    overflow: hidden;
}

.car-feature-item {
    transform: translateX(0);
    opacity: 1;
    transition: all 0.3s ease;
}

.car-feature-item:hover {
    transform: translateX(10px);
    background: linear-gradient(90deg, rgba(0,123,255,0.1), transparent);
    border-radius: 5px;
}

/* Status Badge Enhancement */
.status-badge {
    position: relative;
    overflow: hidden;
}

.status-badge::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: rgba(255,255,255,0.2);
    transform: rotate(45deg);
    animation: shimmer 2s infinite;
}

/* Animations */
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0,123,255,0.4); }
    70% { box-shadow: 0 0 0 10px rgba(0,123,255,0); }
    100% { box-shadow: 0 0 0 0 rgba(0,123,255,0); }
}

@keyframes shimmer {
    from { transform: rotate(45deg) translateX(-100%); }
    to { transform: rotate(45deg) translateX(100%); }
}

/* Loading State */
.loading {
    position: relative;
    opacity: 0.7;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
  <!-- Add these styles to your existing CSS -->
        <style>
        .car-description {
            margin-top: 40px;
            padding: 30px;
            background: linear-gradient(145deg, #ffffff, #f6f6f6);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .car-description:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        
        .description-header {
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(0,123,255,0.1);
            padding-bottom: 15px;
        }
        
        .description-header h3 {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #2c3e50;
            font-size: 1.5rem;
            margin: 0;
            margin-top: 40px;
        }
        
        .description-header i {
            color: #007bff;
            font-size: 1.8rem;
            animation: wrench 3s infinite;
        }
        
        .description-content {
            padding: 20px;
            background: rgba(255,255,255,0.5);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .description-paragraph {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            margin: 10px 0;
            background: white;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .description-paragraph:hover {
            transform: translateX(10px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .description-paragraph i {
            color: #28a745;
            font-size: 1.2rem;
            margin-top: 3px;
            flex-shrink: 0;
        }
        
        @keyframes wrench {
            0% { transform: rotate(0deg); }
            20%, 30% { transform: rotate(-30deg); }
            50%, 60% { transform: rotate(30deg); }
            80% { transform: rotate(0deg); }
            100% { transform: rotate(0deg); }
        }
        
        @media (max-width: 768px) {
            .car-description {
                padding: 20px;
                margin-top: 30px;
            }
        
            .description-paragraph {
                padding: 12px;
                font-size: 0.9rem;
            }
        
            .description-header h3 {
                font-size: 1.3rem;
            }
        }
        
        /* Add smooth reveal animation */
        .description-paragraph {
            opacity: 0;
            transform: translateY(20px);
            animation: revealParagraph 0.5s forwards;
        }
        
        @keyframes revealParagraph {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Stagger animation delay for each paragraph */
        .description-paragraph:nth-child(1) { animation-delay: 0.1s; }
        .description-paragraph:nth-child(2) { animation-delay: 0.2s; }
        .description-paragraph:nth-child(3) { animation-delay: 0.3s; }
        .description-paragraph:nth-child(4) { animation-delay: 0.4s; }
        .description-paragraph:nth-child(5) { animation-delay: 0.5s; }
</style>
<style>
        /* Car Details Dark Theme */
    body.dark-theme .container {
        background-color: #2c3e50;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    
    /* Main Content */
    
    body.dark-theme main {
        background-color: #33475C;
    }
    
    /* Car Info Section */
    body.dark-theme .car-info {
        background: linear-gradient(to bottom right, #34495e, #2c3e50);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    
    body.dark-theme .car-title {
        color: #bdc3c7;
    }
    
    body.dark-theme .car-info h2 {
        color: #2ecc71;
    }
    
    /* Car Features */
    body.dark-theme .car-feature-item {
        color: #ecf0f1;
    }
    body.dark-theme .car-feature-item strong {
        color: #3498db;
    }
    
    body.dark-theme .car-feature-item i {
        color: #3498db;
    }
    
    /* Status Badges */
    body.dark-theme .status-badge {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    
    body.dark-theme .status-selling {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
    }
    
    body.dark-theme .status-discounting {
        background: linear-gradient(135deg, #c0392b, #e74c3c);
    }
    
    body.dark-theme .status-hidden {
        background: linear-gradient(135deg, #7f8c8d, #95a5a6);
    }
    
    body.dark-theme .status-soldout {
        background: linear-gradient(135deg, #2c3e50, #34495e);
    }
    
    /* Image Gallery */
    body.dark-theme .car-image {
        background-color: #2c3e50;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    body.dark-theme .thumbnail-container {
        background: linear-gradient(145deg, #2c3e50, #34495e);
        box-shadow: inset 5px 5px 10px #233140,
                    inset -5px -5px 10px #3c526e;
    }
    
    body.dark-theme .thumbnail {
        border: 2px solid #445566;
        background-color: #34495e;
    }
    
    body.dark-theme .thumbnail.active {
        border-color: #3498db;
        box-shadow: 0 0 15px rgba(52, 152, 219, 0.5);
    }
    
    /* Arrow Buttons */
    body.dark-theme .arrow {
        background: linear-gradient(145deg, #3498db, #2980b9);
        color: #ecf0f1;
    }
    
    body.dark-theme .arrow:hover {
        background: linear-gradient(145deg, #2980b9, #236a9c);
        box-shadow: 0 0 15px rgba(52, 152, 219, 0.5);
    }
    
    /* Car Description */
    body.dark-theme .car-description {
        background: linear-gradient(145deg, #2c3e50, #34495e);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    body.dark-theme .description-header h3 {
        color: #ecf0f1;
    }
    
    body.dark-theme .description-header i {
        color: #3498db;
    }
    
    body.dark-theme .description-content {
        background: rgba(52, 73, 94, 0.5);
    }
    
    body.dark-theme .description-paragraph {
        background-color: #34495e;
        color: #ecf0f1;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    body.dark-theme .description-paragraph i {
        color: #2ecc71;
    }
    
    /* Action Buttons */
    body.dark-theme .btn.primary {
        background: linear-gradient(135deg, #3498db, #2980b9);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    body.dark-theme .btn.secondary {
        background: linear-gradient(135deg, #34495e, #2c3e50);
        box-shadow: 0 4px 15px rgba(52, 73, 94, 0.3);
    }
    
    body.dark-theme .btn.primary:hover {
        background: linear-gradient(135deg, #2980b9, #236a9c);
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
    }
    
    body.dark-theme .btn.secondary:hover {
        background: linear-gradient(135deg, #2c3e50, #233140);
        box-shadow: 0 6px 20px rgba(52, 73, 94, 0.4);
    }
    
    /* Loading States */
    body.dark-theme .loading::after {
        background: linear-gradient(90deg, transparent, rgba(52, 152, 219, 0.2), transparent);
    }
    
    /* Hover Effects */
    body.dark-theme .car-feature-item:hover {
        background: linear-gradient(90deg, rgba(52, 152, 219, 0.1), transparent);
    }
    
    body.dark-theme .description-paragraph:hover {
        background-color: #2c3e50;
        transform: translateX(10px);
    }
    
    /* Animations */
    @keyframes darkShimmer {
        from { transform: translateX(-100%); }
        to { transform: translateX(100%); }
    }
    
    body.dark-theme .status-badge::before {
        background: rgba(255, 255, 255, 0.1);
        animation: darkShimmer 2s infinite;
    }
        
    body.dark-theme .car-features:hover,
    body.dark-theme .car-safety:hover {
        background-color: #33475C!important;
    }
</style>
<body>
    <main>
        <div class="container">
            <div class="car-details">
                <div class="car-image">
                    <img id="mainImage" src="../User/<?php echo $car['image_link']; ?>"
                        alt="<?php echo $car['car_name']; ?>">
                </div>

                <div class="car-info">
                    <h1 class="car-title"><?php echo $car['car_name']; ?></h1>
                    <h2><i class="fas fa-tag"></i> Giá: <?php echo number_format($car['price'], 0, ',', '.'); ?> VND
                    </h2>

                    <div class="car-features">
                        <div class="car-features-left">
                            <p class="car-feature-item"><i class="fas fa-car"></i><strong>Thương Hiệu:</strong>
                                <span style="text-transform: uppercase;">

                                    <?php echo $car['type_name']; ?>
                                </span>
                            </p>
                            <p class="car-feature-item"><i class="fas fa-calendar-alt"></i><strong>Năm Sản
                                    Xuất:</strong> <?php echo $car['year_manufacture']; ?></p>
                            <p class="car-feature-item"><i class="fas fa-gears"></i><strong>Động Cơ:</strong>
                                <?php echo $car['engine_name']; ?></p>
                            <p class="car-feature-item"><i class="fas fa-gear"></i><strong>Mã Lực:</strong>
                                <?php echo $car['engine_power']; ?> HP</p>
                            <p class="car-feature-item"><i class="fas fa-gas-pump"></i><strong>Loại Nhiên Liệu:</strong>
                                <?php echo $car['fuel_name']; ?></p>
                        </div>

                        <div class="car-features-right">
                            <p class="car-feature-item"><i class="fas fa-oil-can"></i><strong>Sức Chứa Nhiên
                                    Liệu:</strong> <?php echo $car['fuel_capacity']; ?></p>
                            <p class="car-feature-item"><i class="fas fa-palette"></i><strong>Màu:</strong>
                                <?php echo $car['color']; ?></p>
                            <p class="car-feature-item"><i class="fas fa-users"></i><strong>Số Chỗ Ngồi:</strong>
                                <?php echo $car['seat_number']; ?> chỗ</p>
                            <p class="car-feature-item"><i class="fas fa-tachometer-alt"></i><strong>Vận Tốc Tối
                                    Đa:</strong> <?php echo $car['max_speed']; ?> km/h</p>
                            <p class="car-feature-item">
                                <i class="fas fa-info-circle"></i><strong>Tình Trạng Xe:</strong>
                                <span class="status-badge status-<?php echo $car['status']; ?>">
                                    <?php
                                    switch ($car['status']) {
                                        case 'selling':
                                            echo 'Đang bán';
                                            break;
                                        case 'discounting':
                                            echo 'Đang giảm giá';
                                            break;
                                        case 'hidden':
                                            echo 'Tạm thời ẩn';
                                            break;
                                        case 'soldout':
                                            echo 'Hết hàng';
                                            break;
                                    }
                                    ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

            </div>
            <div class="thumbnail-container">
                <button class="arrow" onclick="prevImage()">&#10094;</button>

                <!-- Main product image thumbnail -->
                <img src="../User/<?php echo $car['image_link']; ?>" class="thumbnail active"
                    alt="<?php echo $car['car_name']; ?>"
                    onclick="changeImage('../User/<?php echo $car['image_link']; ?>', this)">

                <!-- Additional images thumbnails -->
                <?php while ($image = mysqli_fetch_assoc($additional_images_result)): ?>
                    <img src="../User/<?php echo $image['image_url']; ?>" class="thumbnail"
                        alt="<?php echo $car['car_name']; ?>"
                        onclick="changeImage('../User/<?php echo $image['image_url']; ?>', this)">
                <?php endwhile; ?>

                <button class="arrow" onclick="nextImage()">&#10095;</button>
            </div>
        
        <!-- Replace the existing description section with this enhanced version -->
        <?php if (!empty($car['car_description'])): ?>
            <div class="car-description">
                <div class="description-header">
                    <h3>
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        Chi tiết về <?php echo htmlspecialchars($car['car_name']); ?>
                    </h3>
                </div>
                <div class="description-content">
                    <?php 
                    $description = nl2br(htmlspecialchars($car['car_description']));
                    // Split description into paragraphs
                    $paragraphs = explode("<br />", $description);
                    foreach($paragraphs as $paragraph): 
                        if(trim($paragraph)): // Only show non-empty paragraphs
                    ?>
                        <p class="description-paragraph">
                            <i class="fas fa-check-circle"></i>
                            <?php echo trim($paragraph); ?>
                        </p>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
      

        <div class="actions">
            <button class="btn secondary" onclick="history.back()">Trở về</button>
            <?php if ($car['status'] != 'soldout' && $car['status'] != 'hidden'): ?>
                <button class="btn primary" onclick="addToCart(<?php echo $car['product_id']; ?>)">
                    Thêm vào giỏ hàng
                </button>
            <?php endif; ?>
        </div>
        <?php if ($car['remain_quantity'] > 0): ?>
            <p style="color:#28a745;margin-top:10px;">Còn lại: <?php echo $car['remain_quantity']; ?> xe</p>
        <?php else: ?>
            <p style="color:red;margin-top:10px;">Sản phẩm tạm hết hàng</p>
        <?php endif; ?>
        </div>
    </main>
    <script>
        function addToCart(productId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
            })
                .then(response => {
                    // try to parse JSON; if parse fails, show a generic error
                    return response.json().catch(() => ({ success: false, message: 'Lỗi phản hồi từ server' }));
                })
                .then(data => {
                    const msg = data.message || (data.success ? 'Thêm vào giỏ hàng thành công!' : 'Có lỗi xảy ra khi thêm vào giỏ hàng');
                    const type = data.success ? 'success' : 'error';
                    showNotification(msg, type);
                    if (data.success) {
                        setTimeout(() => { window.location.href = 'cart.php'; }, 1200);
                    }
                })
                .catch(error => {
                    showNotification('Có lỗi xảy ra khi thêm vào giỏ hàng', 'error');
                });
        }
    </script>
    <script>
        //    function changeImage(imageSrc) {
        //         const mainImage = document.getElementById("mainImage");
        //         mainImage.src = imageSrc;
        //         // imageSrc.classList.add("active");
        //         const thumbnails = document.querySelectorAll('.thumbnail');
        //         thumbnails.forEach(thumbnail => {
        //             thumbnail.classList.remove("active");
        //         });
        //         const activeThumbnail = Array.from(thumbnails).find(thumbnail => thumbnail.src.includes(imageSrc));
        //         if (activeThumbnail) {
        //             activeThumbnail.classList.add("active");
        //         }
        //     }
        function nextImage() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            const activeThumb = document.querySelector('.thumbnail.active');
            let nextThumb = activeThumb.nextElementSibling;

            if (!nextThumb || !nextThumb.classList.contains('thumbnail')) {
                nextThumb = thumbnails[0];
            }

            changeImage(nextThumb.src, nextThumb);
        }

        function prevImage() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            const activeThumb = document.querySelector('.thumbnail.active');
            let prevThumb = activeThumb.previousElementSibling;

            if (!prevThumb || !prevThumb.classList.contains('thumbnail')) {
                prevThumb = thumbnails[thumbnails.length - 1];
            }

            changeImage(prevThumb.src, prevThumb);
        }
        // Replace the existing changeImage function with this updated version:
        function changeImage(src, thumbnail) {
            const mainImage = document.getElementById('mainImage');
            const thumbnails = document.querySelectorAll('.thumbnail');

            // Fade out effect
            mainImage.style.opacity = '0';

            setTimeout(() => {
                mainImage.src = src;
                // Fade in effect
                mainImage.style.opacity = '1';

                // Update active thumbnail
                thumbnails.forEach(thumb => thumb.classList.remove('active'));
                thumbnail.classList.add('active');
            }, 500);
        }

        setInterval(nextImage, 5000);
        // Update the autoChangeImage function to use a longer interval
        function autoChangeImage() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            let currentIndex = Array.from(thumbnails).findIndex(thumbnail => thumbnail.src.includes(document.getElementById('mainImage').src));
            const nextIndex = (currentIndex + 1) % thumbnails.length;
            changeImage(thumbnails[nextIndex].src);
        }
        // setInterval(autoChangeImage, 3000);
        // Changed to 5 seconds to allow for animation


    </script>
        <script>
    // Lazy loading for images
    // document.addEventListener('DOMContentLoaded', function() {
    //     const images = document.querySelectorAll('.thumbnail');
    //     const imageOptions = {
    //         threshold: 0.5,
    //         rootMargin: '0px 0px 50px 0px'
    //     };
    
    //     const imageObserver = new IntersectionObserver((entries, observer) => {
    //         entries.forEach(entry => {
    //             if (entry.isIntersecting) {
    //                 const img = entry.target;
    //                 img.src = img.dataset.src;
    //                 img.classList.add('fade-in');
    //                 observer.unobserve(img);
    //             }
    //         });
    //     }, imageOptions);
    
    //     images.forEach(img => imageObserver.observe(img));
    // });
    
    // Enhanced image change function
    function changeImage(src, thumbnail) {
        const mainImage = document.getElementById('mainImage');
        const thumbnails = document.querySelectorAll('.thumbnail');
        
        mainImage.classList.add('loading');
        mainImage.style.opacity = '0';
    
        // Load new image
        const newImage = new Image();
        newImage.src = src;
        newImage.onload = () => {
            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
                mainImage.classList.remove('loading');
                
                thumbnails.forEach(thumb => {
                    thumb.classList.remove('active');
                    thumb.style.transform = 'scale(1)';
                });
                
                thumbnail.classList.add('active');
                thumbnail.style.transform = 'scale(1.15)';
            }, 300);
        };
    }
    
    // Add smooth scroll to description
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    
    // Add hover effect for features
    document.querySelectorAll('.car-feature-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(10px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            prevImage();
        } else if (e.key === 'ArrowRight') {
            nextImage();
        }
    });
    </script>
</body>

</html>

<?php include 'footer.php'; ?>