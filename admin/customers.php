
<?php
session_start();
require_once '../config/connect_db.php'; // Remove the extra .php

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php"); // Correct redirect path
    exit();
}

// Handle customer reporting
if (isset($_POST['report_customer'])) {
    $customer_id = $_POST['customer_id'];
    $report_reason = $_POST['report_reason'];
    
    try {
        // Insert report into database using PDO
        $stmt = $pdo->prepare("INSERT INTO customer_reports (customer_id, report_reason, reported_by, report_date) 
                            VALUES (?, ?, ?, NOW())");
        $stmt->execute([$customer_id, $report_reason, $_SESSION['admin_id']]);
        
        $_SESSION['success_message'] = "Customer has been reported to supervisor";
        header("Location: customers.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error reporting customer: " . $e->getMessage();
    }
}

// Process search/filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on search/filter
$query = "SELECT u.*, 
         (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
         (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id) as total_spent
         FROM users u WHERE u.is_admin = 0";

// Change the query to only show customers with orders
if ($filter == 'all') {
    $query = "SELECT u.*, 
             (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
             (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id) as total_spent
             FROM users u 
             WHERE u.is_admin = 0 
             AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)";
} else {
    $query = "SELECT u.*, 
             (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
             (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id) as total_spent
             FROM users u WHERE u.is_admin = 0";
}

$params = [];

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

if ($filter == 'new') {
    $query .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} else if ($filter == 'reported') {
    $query .= " AND u.id IN (SELECT customer_id FROM customer_reports)";
}

$query .= " ORDER BY u.created_at DESC";

// Prepare and execute statement using PDO
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the admin user info
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? AND is_admin = 1");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
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
                
                <a href="shop-management.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-bag w-5 text-center"></i>
                    <span>Shop Management</span>
                </a>
                
                <a href="customers.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span>Customers</span>
                </a>
                
                <a href="messages.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-comment-alt w-5 text-center"></i>
                    <span>Messages</span>
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
                <h2 class="text-3xl font-bold heading-font">Customer Management</h2>
                
                <div class="flex items-center gap-6">
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
                            <option value="reported" <?= $filter == 'reported' ? 'selected' : '' ?>>Reported Customers</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Customers List -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h3 class="text-lg font-semibold">Customers List</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                <th class="px-6 py-3 font-medium">ID</th>
                                <th class="px-6 py-3 font-medium">Name</th>
                                <th class="px-6 py-3 font-medium">Email</th>
                                <th class="px-6 py-3 font-medium">Registration Date</th>
                                <th class="px-6 py-3 font-medium">Orders</th>
                                <th class="px-6 py-3 font-medium">Total Spent</th>
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
                                        <td class="px-6 py-4"><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 bg-blue-400/20 text-blue-300 rounded-full text-xs">
                                                <?= $customer['order_count'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">$<?= number_format($customer['total_spent'], 2) ?></td>
                                        <td class="px-6 py-4">
                                            <?php 
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
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <button class="view-details p-1 text-blue-400 hover:text-blue-300" data-id="<?= $customer['id'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="report-customer p-1 text-yellow-400 hover:text-yellow-300" 
                                                        data-id="<?= $customer['id'] ?>"
                                                        data-name="<?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>">
                                                    <i class="fas fa-flag"></i>
                                                </button>
                                                <a href="shop-management.php?section=orders&customer=<?= $customer['id'] ?>" class="p-1 text-green-400 hover:text-green-300">
                                                    <i class="fas fa-shopping-cart"></i>
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
        </div>
    </div>
    
    <!-- Customer Details Modal -->
    <div id="customerDetailsModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Customer Details</h3>
                <button id="closeDetailsModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="customerDetailsContent" class="mt-4">
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-white"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Customer Modal -->
    <div id="reportCustomerModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Report Customer</h3>
                <button id="closeReportModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="customer_id" id="report_customer_id">
                <p class="mb-4">You are about to report <strong id="report_customer_name"></strong> to a supervisor.</p>
                <div class="mb-4">
                    <label for="report_reason" class="block text-sm font-medium text-gray-300 mb-1">Reason for Report:</label>
                    <textarea class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                              name="report_reason" id="report_reason" rows="4" required></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelReport" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" name="report_customer" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg">
                        Submit Report
                    </button>
                </div>
            </form>
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
    </script>
</body>
</html>