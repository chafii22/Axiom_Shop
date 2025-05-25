<?php
session_start();
require_once '../config/connect_db.php';

// Update authorization check to include supervisors
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['is_supervisor'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Determine if user is supervisor
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] === true;

// Handle customer reporting (admin only)
if (isset($_POST['report_customer']) && !$is_supervisor) {
    $customer_id = $_POST['customer_id'];
    $report_reason = $_POST['report_reason'];
    
    try {
        // Insert report into database using PDO
        $stmt = $pdo->prepare("INSERT INTO customer_reports (customer_id, report_reason, reported_by, report_date) 
                            VALUES (?, ?, ?, NOW())");
        $stmt->execute([$customer_id, $report_reason, $_SESSION['admin_id']]);
        
        $_SESSION['success_message'] = "Customer has been reported to supervisor";
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error reporting customer: " . $e->getMessage();
    }
}

// Handle report resolution (supervisor only)
if (isset($_POST['resolve_report']) && $is_supervisor) {
    $report_id = $_POST['report_id'];
    $resolution = $_POST['resolution'];
    $action = $_POST['action'];
    
    try {
        // Update report status
        $stmt = $pdo->prepare("UPDATE customer_reports SET status = ?, resolution = ?, resolved_by = ?, resolved_date = NOW() WHERE id = ?");
        $stmt->execute([$action, $resolution, $_SESSION['user_id'], $report_id]);
        
        $_SESSION['success_message'] = "Report has been resolved";
        header("Location: customers.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error resolving report: " . $e->getMessage();
    }
}

// Process search/filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Fetch user data based on role
$username = "";
$email = "";
$user_role = $is_supervisor ? "Supervisor" : "Administrator";

if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        $username = $user_data['username'];
        $email = $user_data['email'];
    }
} elseif (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        $username = $user_data['username'];
        $email = $user_data['email'];
    }
}

// Adjust query for supervisor vs admin
if ($is_supervisor && $filter == 'reported') {
    // Supervisors see reported customers with report details
    $query = "SELECT u.*, r.id as report_id, r.report_reason, r.report_date, r.status as report_status,
             (SELECT username FROM users WHERE id = r.reported_by) as reported_by_name,
             (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
             (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id) as total_spent
             FROM users u 
             JOIN customer_reports r ON u.id = r.customer_id
             WHERE u.is_admin = 0";
             
    if ($filter == 'pending') {
        $query .= " AND r.status = 'pending'";
    }
} else {
    // Regular customer query
    $query = "SELECT u.*, 
             (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
             (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id) as total_spent
             FROM users u WHERE u.is_admin = 0";
             
    if ($filter == 'reported') {
        $query .= " AND u.id IN (SELECT customer_id FROM customer_reports)";
    }
}

// Add search condition
$params = [];
if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$query .= " ORDER BY u.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Keep your existing styles -->
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

                linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);

            background-size: 20px 30px, 30px 20px;

            pointer-events: none;

        }

        

        .glass-sidebar {

            background: rgba(0, 0, 0, 0.4);

            backdrop-filter: blur(10px);

            -webkit-backdrop-filter: blur(10px);

            border-right: 1px solid rgba(255, 255, 255, 0.1);

        }

        

        .glass-card {

            background: rgba(0, 0, 0, 0.2);

            backdrop-filter: blur(8px);

            -webkit-backdrop-filter: blur(8px);

            border: 1px solid rgba(255, 255, 255, 0.3);

            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);

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

        

        .heading-font {

            font-family: 'Space Grotesk', sans-serif;

        }

        

        .status-badge {

            padding: 0.25rem 0.75rem;

            border-radius: 9999px;

            font-size: 0.75rem;

            font-weight: 500;

            text-transform: uppercase;

        }

        

        .status-active {

            background-color: rgba(16, 185, 129, 0.2);

            color: #34d399;

        }

        

        .status-blocked {

            background-color: rgba(239, 68, 68, 0.2);

            color: #f87171;

        }

        

        .status-pending {

            background-color: rgba(245, 158, 11, 0.2);

            color: #fbbf24;

        }

    </style>

</head>
<body class="text-white min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar with role-based menu items -->
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
                
                <?php if (!$is_supervisor): ?>
                <!-- Admin-only menu items -->
                <a href="shop-management.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-bag w-5 text-center"></i>
                    <span>Shop Management</span>
                </a>
                
                <a href="products.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-box w-5 text-center"></i>
                    <span>Products</span>
                </a>
                
                <a href="inventory.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-boxes w-5 text-center"></i>
                    <span>Inventory</span>
                </a>
                
                <a href="orders.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-cart w-5 text-center"></i>
                    <span>Orders</span>
                </a>
                <?php endif; ?>
                
                <!-- Common items -->
                <a href="customers.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <h2 class="text-3xl font-bold heading-font">
                    <?= $is_supervisor ? "Customer Oversight" : "Customer Management" ?>
                </h2>
                
                <div class="flex items-center gap-6">
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
            
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-900/40 border border-green-500 text-green-200 px-4 py-3 rounded mb-6 flex justify-between items-center">
                <span><?= $_SESSION['success_message'] ?></span>
                <button type="button" class="close-alert"><i class="fas fa-times"></i></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-900/40 border border-red-500 text-red-200 px-4 py-3 rounded mb-6 flex justify-between items-center">
                <span><?= $_SESSION['error_message'] ?></span>
                <button type="button" class="close-alert"><i class="fas fa-times"></i></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Search & Filter -->
            <div class="glass-card rounded-xl p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Search & Filter</h3>
                <form method="GET" action="customers.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <div class="relative">
                            <input type="text" class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" name="search" 
                                placeholder="Search by name or email" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-white/60 hover:text-white">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <select name="filter" class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" onchange="this.form.submit()">
                            <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Customers</option>
                            <option value="new" <?= $filter == 'new' ? 'selected' : '' ?>>New Customers (30 days)</option>
                            <option value="reported" <?= $filter == 'reported' ? 'selected' : '' ?>>
                                <?= $is_supervisor ? "Reported Customers" : "Flagged Customers" ?>
                            </option>
                            <?php if ($is_supervisor): ?>
                            <option value="pending" <?= $filter == 'pending' ? 'selected' : '' ?>>Pending Reports</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Customers List -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h3 class="text-lg font-semibold">
                        <?= $is_supervisor ? "Customer Report Management" : "Customers List" ?>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                <th class="px-6 py-3 font-medium">ID</th>
                                <th class="px-6 py-3 font-medium">Name</th>
                                <th class="px-6 py-3 font-medium">Email</th>
                                <?php if ($is_supervisor && $filter == 'reported'): ?>
                                <th class="px-6 py-3 font-medium">Reported By</th>
                                <th class="px-6 py-3 font-medium">Reason</th>
                                <th class="px-6 py-3 font-medium">Date</th>
                                <?php else: ?>
                                <th class="px-6 py-3 font-medium">Registration Date</th>
                                <th class="px-6 py-3 font-medium">Orders</th>
                                <th class="px-6 py-3 font-medium">Total Spent</th>
                                <?php endif; ?>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr class="border-b border-gray-700">
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-400">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr class="border-b border-gray-700 hover:bg-white/5">
                                        <td class="px-6 py-4"><?= $customer['id'] ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($customer['email']) ?></td>
                                        
                                        <?php if ($is_supervisor && $filter == 'reported'): ?>
                                        <td class="px-6 py-4"><?= htmlspecialchars($customer['reported_by_name'] ?? 'Unknown') ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars(substr($customer['report_reason'] ?? '', 0, 30)) ?>...</td>
                                        <td class="px-6 py-4"><?= date('M d, Y', strtotime($customer['report_date'])) ?></td>
                                        <?php else: ?>
                                        <td class="px-6 py-4"><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 bg-blue-400/20 text-blue-300 rounded-full text-xs">
                                                <?= $customer['order_count'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">$<?= number_format($customer['total_spent'], 2) ?></td>
                                        <?php endif; ?>
                                        
                                        <td class="px-6 py-4">
                                            <?php 
                                                if ($is_supervisor && $filter == 'reported') {
                                                    $statusClass = 'status-pending';
                                                    $statusText = 'Pending';
                                                    
                                                    if (isset($customer['report_status'])) {
                                                        if ($customer['report_status'] == 'resolved') {
                                                            $statusClass = 'status-active';
                                                            $statusText = 'Resolved';
                                                        } elseif ($customer['report_status'] == 'dismissed') {
                                                            $statusClass = 'status-blocked';
                                                            $statusText = 'Dismissed';
                                                        }
                                                    }
                                                } else {
                                                    $statusClass = 'status-active';
                                                    $statusText = 'Active';
                                                    
                                                    if (isset($customer['status'])) {
                                                        if ($customer['status'] == 'blocked') {
                                                            $statusClass = 'status-blocked';
                                                            $statusText = 'Blocked';
                                                        } elseif ($customer['status'] == 'pending') {
                                                            $statusClass = 'status-pending';
                                                            $statusText = 'Pending';
                                                        }
                                                    }
                                                }
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <button class="view-details p-1 text-blue-400 hover:text-blue-300" data-id="<?= $customer['id'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($is_supervisor && $filter == 'reported'): ?>
                                                <button class="resolve-report p-1 text-green-400 hover:text-green-300"
                                                        data-id="<?= $customer['report_id'] ?>"
                                                        data-name="<?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="dismiss-report p-1 text-red-400 hover:text-red-300"
                                                        data-id="<?= $customer['report_id'] ?>"
                                                        data-name="<?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <?php elseif (!$is_supervisor): ?>
                                                <button class="report-customer p-1 text-yellow-400 hover:text-yellow-300" 
                                                        data-id="<?= $customer['id'] ?>"
                                                        data-name="<?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>">
                                                    <i class="fas fa-flag"></i>
                                                </button>
                                                <a href="shop-management.php?section=orders&customer=<?= $customer['id'] ?>" class="p-1 text-green-400 hover:text-green-300">
                                                    <i class="fas fa-shopping-cart"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
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
    
    <!-- Customer Details Modal -->
    <!-- Keep your existing modal code -->
    
    <?php if (!$is_supervisor): ?>
    <!-- Report Customer Modal (Admin only) -->
    <!-- Keep your existing report modal code -->
    <?php else: ?>
    <!-- Resolve Report Modal (Supervisor only) -->
    <div id="resolveReportModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Resolve Customer Report</h3>
                <button id="closeResolveModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="report_id" id="resolve_report_id">
                <input type="hidden" name="action" value="resolved">
                <p class="mb-4">You are resolving the report for <strong id="resolve_customer_name"></strong>.</p>
                <div class="mb-4">
                    <label for="resolution" class="block text-sm font-medium text-gray-300 mb-1">Resolution Notes:</label>
                    <textarea class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                              name="resolution" id="resolution" rows="4" required></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelResolve" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" name="resolve_report" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg">
                        Resolve Report
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Dismiss Report Modal (Supervisor only) -->
    <div id="dismissReportModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Dismiss Customer Report</h3>
                <button id="closeDismissModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="report_id" id="dismiss_report_id">
                <input type="hidden" name="action" value="dismissed">
                <p class="mb-4">You are dismissing the report for <strong id="dismiss_customer_name"></strong>.</p>
                <div class="mb-4">
                    <label for="dismiss_resolution" class="block text-sm font-medium text-gray-300 mb-1">Reason for Dismissal:</label>
                    <textarea class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                              name="resolution" id="dismiss_resolution" rows="4" required></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelDismiss" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" name="resolve_report" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg">
                        Dismiss Report
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Close alerts
        document.addEventListener('DOMContentLoaded', function() {
            const closeButtons = document.querySelectorAll('.close-alert');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });
            
            // View customer details
            const viewDetailsButtons = document.querySelectorAll('.view-details');
            const customerDetailsModal = document.getElementById('customerDetailsModal');
            const closeDetailsModal = document.getElementById('closeDetailsModal');
            const customerDetailsContent = document.getElementById('customerDetailsContent');
            
            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const customerId = this.getAttribute('data-id');
                    customerDetailsContent.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-10 w-10 border-t-2 border-white"></div></div>';
                    customerDetailsModal.classList.remove('hidden');
                    
                    // AJAX request to get customer details
                    fetch('ajax/get_customer_details.php?id=' + customerId)
                        .then(response => response.text())
                        .then(data => {
                            customerDetailsContent.innerHTML = data;
                        })
                        .catch(error => {
                            customerDetailsContent.innerHTML = '<div class="text-red-400">Error loading customer details</div>';
                        });
                });
            });
            
            closeDetailsModal.addEventListener('click', function() {
                customerDetailsModal.classList.add('hidden');
            });
            
            // Report customer
            const reportButtons = document.querySelectorAll('.report-customer');
            const reportCustomerModal = document.getElementById('reportCustomerModal');
            const closeReportModal = document.getElementById('closeReportModal');
            const cancelReport = document.getElementById('cancelReport');
            const reportCustomerId = document.getElementById('report_customer_id');
            const reportCustomerName = document.getElementById('report_customer_name');
            
            reportButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const customerId = this.getAttribute('data-id');
                    const customerName = this.getAttribute('data-name');
                    
                    reportCustomerId.value = customerId;
                    reportCustomerName.textContent = customerName;
                    reportCustomerModal.classList.remove('hidden');
                });
            });
            
            closeReportModal.addEventListener('click', function() {
                reportCustomerModal.classList.add('hidden');
            });
            
            cancelReport.addEventListener('click', function() {
                reportCustomerModal.classList.add('hidden');
            });
        });
        <?php if ($is_supervisor): ?>
        // Supervisor-specific scripts
        document.addEventListener('DOMContentLoaded', function() {
            // Resolve report modal
            const resolveButtons = document.querySelectorAll('.resolve-report');
            const resolveReportModal = document.getElementById('resolveReportModal');
            const closeResolveModal = document.getElementById('closeResolveModal');
            const cancelResolve = document.getElementById('cancelResolve');
            const resolveReportId = document.getElementById('resolve_report_id');
            const resolveCustomerName = document.getElementById('resolve_customer_name');
            
            resolveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-id');
                    const customerName = this.getAttribute('data-name');
                    
                    resolveReportId.value = reportId;
                    resolveCustomerName.textContent = customerName;
                    resolveReportModal.classList.remove('hidden');
                });
            });
            
            closeResolveModal.addEventListener('click', function() {
                resolveReportModal.classList.add('hidden');
            });
            
            cancelResolve.addEventListener('click', function() {
                resolveReportModal.classList.add('hidden');
            });
            
            // Dismiss report modal
            const dismissButtons = document.querySelectorAll('.dismiss-report');
            const dismissReportModal = document.getElementById('dismissReportModal');
            const closeDismissModal = document.getElementById('closeDismissModal');
            const cancelDismiss = document.getElementById('cancelDismiss');
            const dismissReportId = document.getElementById('dismiss_report_id');
            const dismissCustomerName = document.getElementById('dismiss_customer_name');
            
            dismissButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-id');
                    const customerName = this.getAttribute('data-name');
                    
                    dismissReportId.value = reportId;
                    dismissCustomerName.textContent = customerName;
                    dismissReportModal.classList.remove('hidden');
                });
            });
            
            closeDismissModal.addEventListener('click', function() {
                dismissReportModal.classList.add('hidden');
            });
            
            cancelDismiss.addEventListener('click', function() {
                dismissReportModal.classList.add('hidden');
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>