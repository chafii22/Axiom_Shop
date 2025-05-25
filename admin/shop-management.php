<?php
// Start session
session_start();

// Check for admin authorization
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../config/connect_db.php';

// Fetch admin user data
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? AND is_admin = 1");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    // If somehow the user is not an admin anymore
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Handle product and category actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : 'products';
$success_message = '';
$error_message = '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Items per page
$offset = ($page - 1) * $per_page;

// Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add-product') {
    $name = trim($_POST['name']);
    $price = (float) $_POST['price'];
    $stock = (int) $_POST['stock'];
    $category_id = (int) $_POST['category_id'];
    
    try {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_dir = '../uploads/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/products/' . $filename;
            }
        }
        
        // Insert product into database
        $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, category_id, image) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $stock, $category_id, $image_path]);
        
        $success_message = "Product added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding product: " . $e->getMessage();
    }

    $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$admin_id, 'add_product', "Added new product: $name ($product_id)"]);
}

// Delete Product
else if ($action === 'delete-product' && isset($_GET['id'])) {
    $product_id = (int) $_GET['id'];
    
    try {
        // Get product image to delete
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        // Delete image file if exists
        if ($product && !empty($product['image']) && file_exists('../' . $product['image'])) {
            unlink('../' . $product['image']);
        }
        
        $success_message = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting product: " . $e->getMessage();
    }

    $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$admin_id, 'delete_product', "Deleted product ID: $product_id"]);
}

// Add Category
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add-category') {
    $name = trim($_POST['name']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?, ?)");
        $stmt->execute([$name]);
        
        $success_message = "Category added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding category: " . $e->getMessage();
    }
}

// Edit Category
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit-category') {
    $category_id = (int) $_POST['category_id'];
    $name = trim($_POST['name']);
    
    try {
        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $category_id]);
        
        $success_message = "Category updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating category: " . $e->getMessage();
    }
}

// Delete Category
else if ($action === 'delete-category' && isset($_GET['id'])) {
    $category_id = (int) $_GET['id'];
    
    try {
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $product_count = $stmt->fetchColumn();
        
        if ($product_count > 0) {
            $error_message = "Cannot delete category: it contains $product_count products.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $success_message = "Category deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting category: " . $e->getMessage();
    }
}

// Update Order Status
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update-order') {
    $order_id = (int) $_POST['order_id'];
    $status = trim($_POST['status']);
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        /*$stmt = $pdo->prepare ("INSERT INTO order_status_history (order_id, status, created_at) 
                              VALUES (?, ?, NOW())");
        $stmt->execute([$order_id, $status, $user_id]);*/

        $success_message = "Order status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating order: " . $e->getMessage();
    }
}



// Fetch data based on current section with pagination
$products = [];
$categories = [];
$orders = [];
$product = null;
$category = null;
$order = null;
$total_pages = 1;

// Edit Product, Delete Product, Add Category, Edit Category, Delete Category, Update Order
switch($section) {
    case 'products':
        // Get total count for pagination
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        $total_products = $stmt->fetchColumn();
        $total_pages = ceil($total_products / $per_page);
        
        // Get products with pagination
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              ORDER BY p.id DESC
                              LIMIT ? OFFSET ?");
        $stmt->bindParam(1, $per_page, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll();
        break;
    
    // Other sections remain the same
    case 'edit-product':
        if (isset($_GET['id'])) {
            $product_id = (int) $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            // Fetch categories for dropdown
            $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
            $categories = $stmt->fetchAll();
        }
        break;
    
    case 'add-product':
        // Fetch categories for dropdown
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
        $categories = $stmt->fetchAll();
        break;
    
    case 'categories':
        $stmt = $pdo->query("SELECT c.*, COUNT(p.id) as product_count 
                           FROM categories c 
                           LEFT JOIN products p ON c.id = p.category_id 
                           GROUP BY c.id 
                           ORDER BY c.name");
        $categories = $stmt->fetchAll();
        break;
    
    case 'edit-category':
        if (isset($_GET['id'])) {
            $category_id = (int) $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch();
        }
        break;
    
    case 'orders':
        // Get total count for pagination
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        $status_condition = $status_filter ? 'WHERE o.status = :status' : '';
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $status_condition");
        if ($status_filter) {
            $stmt->bindParam(':status', $status_filter);
        }
        $stmt->execute();
        $total_orders = $stmt->fetchColumn();
        $total_pages = ceil($total_orders / $per_page);
        
        // Get orders with pagination
        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            $status_condition
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $paramIndex = 1;
        if ($status_filter) {
            $stmt->bindParam(':status', $status_filter);
            $paramIndex = 1;
        }
        $stmt->bindParam($paramIndex++, $per_page, PDO::PARAM_INT);
        $stmt->bindParam($paramIndex++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();
        break;
    
    case 'order-details':
        if (isset($_GET['id'])) {
            $order_id = (int) $_GET['id'];
            
            // Get order details
            $stmt = $pdo->prepare("SELECT o.*, u.username, u.email, u.phone
                                 FROM orders o 
                                 LEFT JOIN users u ON o.user_id = u.id 
                                 WHERE o.id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            // Get order items
            if ($order) {
                $stmt = $pdo->prepare("SELECT oi.*, p.name, p.image 
                                     FROM order_items oi 
                                     JOIN products p ON oi.product_id = p.id 
                                     WHERE oi.order_id = ?");
                $stmt->execute([$order_id]);
                $order['items'] = $stmt->fetchAll();
            }
        }
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Management | Axiom Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @font-face {
            font-family: 'Space Grotesk';
            src: url(../assets/fonts/Incrediible-BF6814d5097d803.ttf) format('truetype');
        }
        body {
            font-family: 'Noto Sans', sans-serif;
            background-color: #0f172a;
            position: relative;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.2;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                linear-gradient(to right, rgba(255, 255, 255, 0.05) 2px, transparent 2px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 2px, transparent 2px);
            background-size: 
                20px 30px,
                30px 20px,
                100px 100px,
                100px 100px;
            background-position:
                0 0,
                0 0,
                -1px -1px,
                -3px -3px;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.05;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.3) 0, rgba(255, 255, 255, 0.3) 1px, transparent 1px, transparent 40px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.3) 0, rgba(255, 255, 255, 0.3) 1px, transparent 1px, transparent 80px);
            pointer-events: none;
        }
    
        .heading-font {
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .glass-card {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .glass-sidebar {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .menu-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid rgba(255, 255, 255, 0.5);
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid white;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }
        
        .status-processing {
            background-color: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }
        
        .status-shipped {
            background-color: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }
        
        .status-delivered {
            background-color: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }
        
        .status-completed {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-cancelled {
            background-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .table-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
        }
        
        .table-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .search-box input {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .search-box input:focus {
            background: rgba(255, 255, 255, 0.15);
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .notification {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 0.5rem;
            margin: 0 2px;
            transition: all 0.2s;
        }
        
        .pagination-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .pagination-item.active {
            background: rgba(59, 130, 246, 0.7);
        }

        select option {
            background-color: #1e293b; /* Dark background that matches your theme */
            color: white;              /* White text */
            padding: 8px;              /* Add padding for better readability */
        }
        
        /* Fix for form control styling */
        .form-control {
            background: rgba(30, 41, 59, 0.8) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Ensure the select dropdown has proper contrast */
        select {
            background-color: rgba(30, 41, 59, 0.8) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="text-white min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="glass-sidebar w-64 p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <i class="fas fa-gem text-white"></i>
                </div>
                <h1 class="text-xl font-bold heading-font tracking-wide">Axiom</h1>
            </div>
            
            <div class="flex-1">
                <a href="dashboard.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-th-large w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="shop-management.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-bag w-5 text-center"></i>
                    <span>Shop Management</span>
                </a>
                
                <a href="customers.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span>Customers</span>
                </a>
                
                <a href="settings.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span>Settings</span>
                </a>
            </div>

            <a href="../home.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                <i class="fas fa-home w-5 text-center"></i>
                <span>Return to home</span>
            </a>
            
            <a href="../auth/logout.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mt-auto hover:bg-red-500/20">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Top Bar -->
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold heading-font">Shop Management</h2>
                
                <div class="flex items-center gap-6">
                    <div class="search-box relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-white/70"></i>
                        <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 rounded-lg w-64">
                    </div>
                    
                    <div class="notification relative w-10 h-10 rounded-lg flex items-center justify-center cursor-pointer hover:bg-white/20 transition">
                        <i class="fas fa-bell"></i>
                        <div class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($admin['username']) ?></div>
                            <div class="text-xs text-white/70">Administrator</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section Tabs -->
            <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
                <a href="?section=products" class="px-4 py-2 rounded-lg <?= $section === 'products' ? 'bg-blue-600' : 'glass-card hover:bg-gray-800' ?> transition-colors">
                    Products
                </a>
                <a href="?section=add-product" class="px-4 py-2 rounded-lg <?= $section === 'add-product' ? 'bg-blue-600' : 'glass-card hover:bg-gray-800' ?> transition-colors">
                    Add Product
                </a>
                <a href="?section=categories" class="px-4 py-2 rounded-lg <?= $section === 'categories' ? 'bg-blue-600' : 'glass-card hover:bg-gray-800' ?> transition-colors">
                    Categories
                </a>
                <a href="?section=add-category" class="px-4 py-2 rounded-lg <?= $section === 'add-category' ? 'bg-blue-600' : 'glass-card hover:bg-gray-800' ?> transition-colors">
                    Add Category
                </a>
                <a href="?section=orders" class="px-4 py-2 rounded-lg <?= $section === 'orders' ? 'bg-blue-600' : 'glass-card hover:bg-gray-800' ?> transition-colors">
                    Orders
                </a>
            </div>
            
            <!-- Alerts -->
            <?php if (!empty($success_message)): ?>
            <div class="bg-green-900/40 border border-green-500 text-green-200 px-4 py-3 rounded mb-6 flex justify-between items-center">
                <span><?= $success_message ?></span>
                <button type="button" class="close-alert"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="bg-red-900/40 border border-red-500 text-red-200 px-4 py-3 rounded mb-6 flex justify-between items-center">
                <span><?= $error_message ?></span>
                <button type="button" class="close-alert"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
            
            <!-- Content based on section -->
            <?php if ($section === 'products'): ?>
                <!-- Products List -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                        <h2 class="heading-font text-xl font-bold">All Products</h2>
                        <a href="?section=add-product" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Product</span>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                    <th class="px-6 py-3 font-medium">Product</th>
                                    <th class="px-6 py-3 font-medium">Category</th>
                                    <th class="px-6 py-3 font-medium">Price</th>
                                    <th class="px-6 py-3 font-medium">Stock</th>
                                    <th class="px-6 py-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr class="table-row">
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-400">No products found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $p): ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded bg-gray-800 flex-shrink-0 overflow-hidden">
                                                        <?php if (!empty($p['image']) && file_exists('../' . $p['image'])): ?>
                                                            <img src="../<?= $p['image'] ?>" alt="<?= $p['name'] ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center bg-gray-700">
                                                                <i class="fas fa-image text-gray-500"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h3 class="font-medium"><?= htmlspecialchars($p['name']) ?></h3>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="bg-gray-800 text-gray-300 text-xs px-2 py-1 rounded">
                                                    <?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">$<?= number_format($p['price'], 2) ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($p['stock'] > 10): ?>
                                                    <span class="text-green-500"><?= $p['stock'] ?></span>
                                                <?php elseif ($p['stock'] > 0): ?>
                                                    <span class="text-yellow-500"><?= $p['stock'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-red-500">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex gap-2">
                                                    <a href="?section=edit-product&id=<?= $p['id'] ?>" class="text-blue-400 hover:text-blue-300">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=delete-product&id=<?= $p['id'] ?>" class="text-red-400 hover:text-red-300" 
                                                       onclick="return confirm('Are you sure you want to delete this product?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="p-4 border-t border-gray-700 flex justify-center">
                        <div class="flex items-center space-x-1">
                            <!-- Previous page -->
                            <?php if ($page > 1): ?>
                            <a href="?section=products&page=<?= $page - 1 ?>" class="pagination-item">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php else: ?>
                            <span class="pagination-item opacity-50 cursor-not-allowed">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                            <?php endif; ?>
                            
                            <!-- Page numbers -->
                            <?php
                                // Determine the range of page numbers to display
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                // Always show first page
                                if ($start_page > 1) {
                                    echo '<a href="?section=products&page=1" class="pagination-item">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="pagination-item">...</span>';
                                    }
                                }
                                
                                // Display page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="pagination-item active">' . $i . '</span>';
                                    } else {
                                        echo '<a href="?section=products&page=' . $i . '" class="pagination-item">' . $i . '</a>';
                                    }
                                }
                                
                                // Always show last page
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="pagination-item">...</span>';
                                    }
                                    echo '<a href="?section=products&page=' . $total_pages . '" class="pagination-item">' . $total_pages . '</a>';
                                }
                            ?>
                            
                            <!-- Next page -->
                            <?php if ($page < $total_pages): ?>
                            <a href="?section=products&page=<?= $page + 1 ?>" class="pagination-item">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php else: ?>
                            <span class="pagination-item opacity-50 cursor-not-allowed">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>


                
            <?php elseif ($section === 'add-product'): ?>
                <!-- Add Product Form -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="heading-font text-xl font-bold mb-6">Add New Product</h2>
                    
                    <form action="?action=add-product" method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Product Name</label>
                                <input type="text" name="name" required 
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Category</label>
                                <select name="category_id" required 
                                        class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Price ($)</label>
                                <input type="number" name="price" step="0.01" min="0" required 
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Stock Quantity</label>
                                <input type="number" name="stock" min="0" required 
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                            
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-300 mb-1">Product Image</label>
                                <div class="file-input-wrapper">
                                    <div class="flex items-center gap-2 px-4 py-2 rounded-lg form-control cursor-pointer">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span id="filename-display">Choose file...</span>
                                    </div>
                                    <input type="file" name="image" accept="image/*" 
                                          onchange="document.getElementById('filename-display').innerText = this.files[0].name">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg">
                                Add Product
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($section === 'edit-product' && $product): ?>
                <!-- Edit Product Form -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="heading-font text-xl font-bold mb-6">Edit Product</h2>
                    
                    <form action="?action=edit-product" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Product Name</label>
                                <input type="text" name="name" required value="<?= htmlspecialchars($product['name']) ?>"
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Category</label>
                                <select name="category_id" required 
                                        class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Price ($)</label>
                                <input type="number" name="price" step="0.01" min="0" required value="<?= $product['price'] ?>"
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Stock Quantity</label>
                                <input type="number" name="stock" min="0" required value="<?= $product['stock'] ?>"
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                            
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-300 mb-1">Current Image</label>
                                <?php if (!empty($product['image']) && file_exists('../' . $product['image'])): ?>
                                    <div class="mb-3">
                                        <img src="../<?= $product['image'] ?>" alt="<?= $product['name'] ?>" class="w-32 h-32 object-cover rounded">
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-400 mb-3">No image available</p>
                                <?php endif; ?>
                                
                                <label class="block text-sm font-medium text-gray-300 mb-1">Change Image</label>
                                <div class="file-input-wrapper">
                                    <div class="flex items-center gap-2 px-4 py-2 rounded-lg form-control cursor-pointer">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span id="filename-edit-display">Choose file...</span>
                                    </div>
                                    <input type="file" name="image" accept="image/*" 
                                          onchange="document.getElementById('filename-edit-display').innerText = this.files[0].name">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="?section=products" class="bg-gray-700 hover:bg-gray-600 px-6 py-2 rounded-lg">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg">
                                Update Product
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($section === 'categories'): ?>
                <!-- Categories List -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                        <h2 class="heading-font text-xl font-bold">All Categories</h2>
                        <a href="?section=add-category" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Category</span>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                    <th class="px-6 py-3 font-medium">Category</th>
                                    <th class="px-6 py-3 font-medium">Products</th>
                                    <th class="px-6 py-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr class="table-row">
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-400">No categories found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4 font-medium"><?= htmlspecialchars($cat['name']) ?></td>
                                            <td class="px-6 py-4"><?= $cat['product_count'] ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex gap-2">
                                                    <a href="?section=edit-category&id=<?= $cat['id'] ?>" class="text-blue-400 hover:text-blue-300">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=delete-category&id=<?= $cat['id'] ?>" class="text-red-400 hover:text-red-300" 
                                                       onclick="return confirm('Are you sure you want to delete this category? This might affect products.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif ($section === 'add-category'): ?>
                <!-- Add Category Form -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="heading-font text-xl font-bold mb-6">Add New Category</h2>
                    
                    <form action="?action=add-category" method="POST">
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Category Name</label>
                                <input type="text" name="name" required 
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg">
                                Add Category
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($section === 'edit-category' && $category): ?>
                <!-- Edit Category Form -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="heading-font text-xl font-bold mb-6">Edit Category</h2>
                    
                    <form action="?action=edit-category" method="POST">
                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Category Name</label>
                                <input type="text" name="name" required value="<?= htmlspecialchars($category['name']) ?>" 
                                       class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                            </div>
                            
                        </div>
                        
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="?section=categories" class="bg-gray-700 hover:bg-gray-600 px-6 py-2 rounded-lg">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg">
                                Update Category
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($section === 'orders'): ?>
                <!-- Orders List -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                        <h2 class="heading-font text-xl font-bold">All Orders</h2>
                        <div>
                            <select name="status-filter" class="bg-gray-800 border border-gray-700 text-white px-4 py-2 rounded-lg">
                                <option value="all" <?= !isset($_GET['status']) ? 'selected' : '' ?>>All Orders</option>
                                <option value="pending" <?= isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= isset($_GET['status']) && $_GET['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped" <?= isset($_GET['status']) && $_GET['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered" <?= isset($_GET['status']) && $_GET['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="completed" <?= isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                    <th class="px-6 py-3 font-medium">Order ID</th>
                                    <th class="px-6 py-3 font-medium">Customer</th>
                                    <th class="px-6 py-3 font-medium">Date</th>
                                    <th class="px-6 py-3 font-medium">Amount</th>
                                    <th class="px-6 py-3 font-medium">Status</th>
                                    <th class="px-6 py-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr class="table-row">
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-400">No orders found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $o): ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4 font-medium">#<?= $o['id'] ?></td>
                                            <td class="px-6 py-4">
                                                <?= htmlspecialchars($o['username'] ?? 'Guest') ?>
                                                <div class="text-xs text-gray-400"><?= htmlspecialchars($o['email'] ?? 'No email') ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?= date('M d, Y', strtotime($o['created_at'])) ?>
                                                <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($o['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4">$<?= number_format($o['total_amount'], 2) ?></td>
                                            <td class="px-6 py-4">
                                                <span class="status-badge status-<?= $o['status'] ?>">
                                                    <?= ucfirst($o['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="?section=order-details&id=<?= $o['id'] ?>" class="text-blue-400 hover:text-blue-300">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif ($section === 'order-details' && $order): ?>
                <!-- Order Details -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <div class="glass-card rounded-xl p-6 h-full">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="heading-font text-xl font-bold">Order #<?= $order['id'] ?></h2>
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                            
                            <h3 class="font-medium text-lg mb-4">Items</h3>
                            
                            <div class="space-y-4">
                                <?php if (empty($order['items'])): ?>
                                    <p class="text-gray-400">No items found for this order</p>
                                <?php else: ?>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div class="flex items-center gap-4 p-3 rounded-lg bg-gray-800/50">
                                            <div class="w-12 h-12 rounded bg-gray-700 flex-shrink-0 overflow-hidden">
                                                <?php if (!empty($item['image']) && file_exists('../' . $item['image'])): ?>
                                                    <img src="../<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center bg-gray-700">
                                                        <i class="fas fa-image text-gray-500"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-medium"><?= htmlspecialchars($item['name']) ?></h4>
                                                <div class="text-sm text-gray-400">
                                                    $<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-medium">$<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-6 pt-4 border-t border-gray-700">
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-400">Subtotal:</span>
                                    <span>$<?= number_format($order['total_amount'], 2) ?></span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-400">Shipping:</span>
                                    <span>$0.00</span>
                                </div>
                                <div class="flex justify-between font-bold text-lg pt-2 border-t border-gray-700">
                                    <span>Total:</span>
                                    <span>$<?= number_format($order['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="glass-card rounded-xl p-6 mb-6">
                            <h3 class="font-medium text-lg mb-4">Customer & Shipping Information</h3>
                            
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm text-gray-400">Name</p>
                                    <p><?= htmlspecialchars($order['shipping_name'] ?? ($order['username'] ?? 'Guest')) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Email</p>
                                    <p><?= htmlspecialchars($order['shipping_email'] ?? ($order['email'] ?? 'No email')) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Phone</p>
                                    <p><?= htmlspecialchars($order['shipping_phone'] ?? 'No phone') ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Shipping Address</p>
                                    <p><?= htmlspecialchars($order['shipping_address'] ?? 'No address') ?><br>
                                       <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, 
                                       <?= htmlspecialchars($order['shipping_state'] ?? '') ?> 
                                       <?= htmlspecialchars($order['shipping_zip'] ?? '') ?><br>
                                       <?= htmlspecialchars($order['shipping_country'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        

                        <div class="glass-card rounded-xl p-6">
                            <h3 class="font-medium text-lg mb-4">Order Management</h3>
                            
                            <form action="?action=update-order" method="POST">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Current Status: 
                                        <span class="status-badge status-<?= $order['status'] ?> ml-2">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </label>
                                    <select name="status" class="w-full px-4 py-2 rounded-lg form-control focus:outline-none">
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Notes (optional)</label>
                                    <textarea name="admin_notes" class="w-full px-4 py-2 rounded-lg form-control focus:outline-none" rows="3"><?= htmlspecialchars($order['admin_notes'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                                    Update Order Status
                                </button>
                            </form>
                            
                            <div class="mt-4 pt-4 border-t border-gray-700">
                                <div class="flex justify-between mb-2">
                                    <p class="text-sm text-gray-400">Order Number:</p>
                                    <p class="font-mono"><?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></p>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <p class="text-sm text-gray-400">Order Date:</p>
                                    <p><?= date('F d, Y h:i A', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div class="flex justify-between">
                                    <p class="text-sm text-gray-400">Payment Method:</p>
                                    <p><?= ucfirst(htmlspecialchars($order['payment_method'] ?? 'Unknown')) ?></p>
                                </div>
                            </div>
                        
                            <div class="mt-4 pt-4 border-t border-gray-700">
                                <a href="?section=orders" class="block text-center bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg">
                                    Back to Orders List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Close alerts
        document.addEventListener('DOMContentLoaded', function() {
            const closeButtons = document.querySelectorAll('.close-alert');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });
        });

                
            // Order status filter functionality
            document.addEventListener('DOMContentLoaded', function() {
                const statusFilter = document.querySelector('select[name="status-filter"]');
                if(statusFilter) {
                    statusFilter.addEventListener('change', function() {
                        const status = this.value;
                        const url = new URL(window.location.href);
                        
                        // Update or add the status parameter
                        if(status === 'all') {
                            url.searchParams.delete('status');
                        } else {
                            url.searchParams.set('status', status);
                        }
                        
                        // Keep the section parameter
                        url.searchParams.set('section', 'orders');
                        
                        // Reset to page 1 when filtering
                        url.searchParams.delete('page');
                        
                        window.location.href = url.toString();
                    });
                }
            });
        
    </script>
</body>
</html>