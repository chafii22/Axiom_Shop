
<?php
// Start session
session_start();

// Include database connection first
require_once '../config/connect_db.php';

// Now check for supervisor status
$is_supervisor = false;
if (isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] === true) {
    $is_supervisor = true;
} elseif (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT is_supervisor FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();
    $is_supervisor = ($user && $user['is_supervisor'] == 1);
    
    // Update the session if needed
    if ($is_supervisor) {
        $_SESSION['is_supervisor'] = true;
    }
}

// Check for admin authorization
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['is_supervisor'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch user data - handle both admin and supervisor cases
$username = "";
$email = "";
$user_role = "Supervisor"; // Default role

if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $username = $admin['username'];
        $email = $admin['email'];
        $user_role = "Administrator";
    }
} elseif (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        $username = $user['username'];
        $email = $user['email'];
    }
}

// Initialize supervisor statistics variables
$reportedCustomers = 0;
$adminActivity = 0;

// Only fetch these if supervisor is logged in
if ($is_supervisor) {
    // Get reported customers count
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as reported FROM customer_reports WHERE status = 'pending'");
        $reportedCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['reported'] ?? 0;
    } catch (PDOException $e) {
        // Table might not exist yet
        $reportedCustomers = 0;
    }
    
    // Get admin activity count
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as activity FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $adminActivity = $stmt->fetch(PDO::FETCH_ASSOC)['activity'] ?? 0;
    } catch (PDOException $e) {
        // Table might not exist yet
        $adminActivity = 0;
    }
}

// Get stats data from database
// Total sales
$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total_sales FROM orders WHERE status != 'cancelled'");
$totalSales = $stmt->fetch()['total_sales'];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
$totalOrders = $stmt->fetch()['total_orders'];

// Products sold
$stmt = $pdo->query("SELECT SUM(quantity) as products_sold FROM order_items");
$productsSold = $stmt->fetch()['products_sold'] ?: 0;

// New customers in the last 30 days
$stmt = $pdo->prepare("SELECT COUNT(*) as new_customers FROM users WHERE is_admin = 0 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute();
$newCustomers = $stmt->fetch()['new_customers'];

// Top products
$stmt = $pdo->query("SELECT p.id, p.name, SUM(oi.quantity) as sales 
                    FROM products p 
                    JOIN order_items oi ON p.id = oi.product_id 
                    JOIN orders o ON oi.order_id = o.id 
                    WHERE o.status != 'cancelled' 
                    GROUP BY p.id 
                    ORDER BY sales DESC 
                    LIMIT 4");
$topProducts = $stmt->fetchAll();

// Monthly revenue data for chart
$stmt = $pdo->query("SELECT 
                        MONTH(created_at) as month, 
                        YEAR(created_at) as year,
                        SUM(total_amount) as revenue 
                    FROM orders 
                    WHERE status != 'cancelled' 
                    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY YEAR(created_at), MONTH(created_at)
                    ORDER BY year, month");
$revenueData = $stmt->fetchAll();

// Process chart data
$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$chartLabels = [];
$chartData = [];

foreach ($revenueData as $data) {
    $chartLabels[] = $months[$data['month']-1];
    $chartData[] = $data['revenue'];
}

// Format chart data as JSON for JavaScript
$chartLabelsJson = json_encode($chartLabels ?: $months);
$chartDataJson = json_encode($chartData ?: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
        
        .stat-card {
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .card-icon {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
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
        
        .progress-bar {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .chart-container canvas {
            max-height: 250px;
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
                <a href="dashboard.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-th-large w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                
                <?php if (!$is_supervisor): ?>
                <!-- Admin-only menu items -->
                <a href="shop-management.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-bag w-5 text-center"></i>
                    <span>Shop Management</span>
                </a>
                
                <?php endif; ?>
                
                <!-- Common items -->
                <a href="customers.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span>Customers</span>
                </a>
        
                <!-- Supervisor-specific items -->
                <?php if ($is_supervisor): ?>
                <a href="customer_reports.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-flag w-5 text-center"></i>
                    <span>Customer Reports</span>
                </a>
                
                <a href="admin_management.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-user-cog w-5 text-center"></i>
                    <span>Admin Management</span>
                </a>
                
                <a href="admin_logs.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-history w-5 text-center"></i>
                    <span>Activity Logs</span>
                </a>
                
                
                <?php endif; ?>
                
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
                <h2 class="text-3xl font-bold heading-font">Dashboard</h2>
                
                <div class="flex items-center gap-6">
                    <div class="search-box relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-white/70"></i>
                        <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 rounded-lg w-64">
                    </div>
                    
                    <div class="notification relative w-10 h-10 rounded-lg flex items-center justify-center cursor-pointer hover:bg-white/20 transition">
                        <i class="fas fa-bell"></i>
                        <div class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></div>
                    </div>
                    
                    <!-- In the top bar user display section -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($username) ?></div>
                            <div class="text-xs text-white/70"><?= htmlspecialchars($user_role) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php if ($is_supervisor): ?>
                <!-- Supervisor Stats -->
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-flag text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Reports</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($reportedCustomers) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-exclamation-circle mr-1"></i> Pending review
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-shield text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Admin Activity</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($adminActivity) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-clock mr-1"></i> Past 7 days
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users-cog text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Admins</div>
                    </div>
                    <div class="text-3xl font-bold mb-1">
                        <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) as admin_count FROM users WHERE is_admin = 1");
                        echo number_format($stmt->fetch()['admin_count'] ?? 0); 
                        ?>
                    </div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-user-check mr-1"></i> Active administrators
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Total Users</div>
                    </div>
                    <div class="text-3xl font-bold mb-1">
                        <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
                        echo number_format($stmt->fetch()['user_count'] ?? 0); 
                        ?>
                    </div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-user-plus mr-1"></i> Registered accounts
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Admin Stats -->
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Total Sales</div>
                    </div>
                    <div class="text-3xl font-bold mb-1">$<?= number_format($totalSales, 0) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-chart-line mr-1"></i> Revenue from all orders
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Total Orders</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($totalOrders) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-clipboard mr-1"></i> All time processed orders
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Products Sold</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($productsSold) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-check-circle mr-1"></i> Total items purchased
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">New Customers</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($newCustomers) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-user-plus mr-1"></i> Last 30 days
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Supervisor Controls -->
            <?php if ($is_supervisor): ?>
            <div class="glass-card p-6 rounded-xl mb-8">
                <h3 class="text-xl font-bold heading-font mb-4">Supervisor Controls</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass-card bg-red-900/30 p-6 rounded-xl stat-card">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                                <i class="fas fa-flag text-xl"></i>
                            </div>
                            <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Customer Reports</div>
                        </div>
                        <div class="text-3xl font-bold mb-1"><?= number_format($reportedCustomers) ?></div>
                        <div class="text-sm opacity-70 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i> Pending review
                        </div>
                        <div class="mt-3">
                            <a href="customer_reports.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg inline-block">
                                View Reports
                            </a>
                        </div>
                    </div>
                    
                    <div class="glass-card bg-blue-900/30 p-6 rounded-xl stat-card">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-shield text-xl"></i>
                            </div>
                            <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Admin Activity</div>
                        </div>
                        <div class="text-3xl font-bold mb-1"><?= number_format($adminActivity) ?></div>
                        <div class="text-sm opacity-70 flex items-center">
                            <i class="fas fa-clock mr-1"></i> Past 7 days
                        </div>
                        <div class="mt-3">
                            <a href="admin_logs.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg inline-block">
                                View Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <?php if ($is_supervisor): ?>
                <!-- Supervisor charts -->
                <div class="glass-card p-6 rounded-xl lg:col-span-2">
                    <h3 class="text-lg font-semibold mb-4 heading-font">User Activity Overview</h3>
                    <div class="chart-container">
                        <canvas id="userActivityChart"></canvas>
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <h3 class="text-lg font-semibold mb-4 heading-font">User Distribution</h3>
                    <div class="chart-container">
                        <canvas id="userDistributionChart"></canvas>
                    </div>
                </div>
                <?php else: ?>
                <!-- Admin charts -->
                <div class="glass-card p-6 rounded-xl lg:col-span-2">
                    <h3 class="text-lg font-semibold mb-4 heading-font">Revenue Overview</h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <h3 class="text-lg font-semibold mb-4 heading-font">Sales Distribution</h3>
                    <div class="chart-container">
                        <canvas id="salesDistributionChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Top Products Section -->
            <div class="glass-card p-6 rounded-xl mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold heading-font">Top Products</h3>
                    <a href="products.php" class="text-sm opacity-70 hover:opacity-100 transition flex items-center gap-1">
                        View All
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left border-b border-white/10">
                                <th class="pb-3 font-medium opacity-70">#</th>
                                <th class="pb-3 font-medium opacity-70">Product</th>
                                <th class="pb-3 font-medium opacity-70">Popularity</th>
                                <th class="pb-3 font-medium opacity-70">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center opacity-70">No product data available yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $index => $product): ?>
                                    <?php 
                                        $maxSales = $topProducts[0]['sales'] > 0 ? $topProducts[0]['sales'] : 1;
                                        $percentage = ($product['sales'] / $maxSales) * 100;
                                        
                                        // Color classes for progress bars
                                        $colorClasses = [
                                            'bg-emerald-400',
                                            'bg-blue-400',
                                            'bg-purple-400',
                                            'bg-amber-400'
                                        ];
                                    ?>
                                    <tr class="border-b border-white/5">
                                        <td class="py-4 opacity-70"><?= $index + 1 ?></td>
                                        <td class="py-4"><?= htmlspecialchars($product['name']) ?></td>
                                        <td class="py-4 w-1/3">
                                            <div class="progress-bar rounded-full h-2 overflow-hidden">
                                                <div class="h-full <?= $colorClasses[$index % count($colorClasses)] ?>" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="py-4">
                                            <span class="px-3 py-1 rounded-full bg-white/10 text-sm font-medium">
                                                <?= number_format($product['sales']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            
        </div>
    </div>

    <script>
        <?php if ($is_supervisor): ?>
        // User Activity Chart
        const userActivityCtx = document.getElementById('userActivityChart').getContext('2d');
        const userActivityChart = new Chart(userActivityCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'User Logins',
                    data: [45, 39, 53, 47, 54, 36, 41],
                    borderColor: 'rgba(236, 72, 153, 0.8)',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(236, 72, 153, 1)',
                    pointRadius: 4
                },
                {
                    label: 'Admin Actions',
                    data: [25, 29, 23, 27, 24, 16, 21],
                    borderColor: 'rgba(59, 130, 246, 0.8)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });
        
        // User Distribution Chart
        const userDistributionCtx = document.getElementById('userDistributionChart').getContext('2d');
        const userDistributionChart = new Chart(userDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Regular Users', 'Admins', 'Supervisors', 'Inactive'],
                datasets: [{
                    data: [75, 15, 5, 5],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(236, 72, 153, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(251, 146, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(236, 72, 153, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(251, 146, 60, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            font: {
                                size: 12
                            },
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        <?php else: ?>
        // Regular admin charts (your existing chart code)
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= $chartLabelsJson ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?= $chartDataJson ?>,
                    borderColor: 'rgba(255, 255, 255, 0.8)',
                    backgroundColor: 'rgba(255, 255, 255, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: 'white',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Sales Distribution Chart
        const distributionCtx = document.getElementById('salesDistributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Apparel', 'Accessories', 'Home Decor', 'Other'],
                datasets: [{
                    data: [45, 25, 20, 10],
                    backgroundColor: [
                        'rgba(236, 72, 153, 0.7)',
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(251, 146, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgba(236, 72, 153, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(251, 146, 60, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            font: {
                                size: 12
                            },
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>