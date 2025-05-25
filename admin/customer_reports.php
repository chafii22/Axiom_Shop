<?php
// Start session
session_start();

// Check for supervisor authorization only
if (!isset($_SESSION['is_supervisor']) || $_SESSION['is_supervisor'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../config/connect_db.php';

// Fetch user data
$username = "";
$email = "";
$user_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if ($user_data) {
    $username = $user_data['username'];
    $email = $user_data['email'];
}

// Handle report resolution
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resolve_report'])) {
    $report_id = $_POST['report_id'];
    $resolution = $_POST['resolution'];
    $action = $_POST['action'];
    
    try {
        // Update report status
        $stmt = $pdo->prepare("UPDATE customer_reports SET status = ?, resolution = ?, resolved_by = ?, resolved_date = NOW() WHERE id = ?");
        $stmt->execute([$action, $resolution, $user_id, $report_id]);
        
        // Log the action
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, "resolve_report", "Resolved report #$report_id with action: $action"]);
        
        // If action is 'block', update the customer status
        if ($action == 'block') {
            // Get the customer ID from the report
            $stmt = $pdo->prepare("SELECT customer_id FROM customer_reports WHERE id = ?");
            $stmt->execute([$report_id]);
            $customer_id = $stmt->fetchColumn();
            
            if ($customer_id) {
                $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ?");
                $stmt->execute([$customer_id]);
            }
        }
        
        $_SESSION['success_message'] = "Report has been $action successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error resolving report: " . $e->getMessage();
    }

    $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, "resolve_report", "Resolved report #$report_id with action: $action"]);
    
    // Redirect to refresh the page
    header("Location: customer_reports.php");
    exit();
}

// Process filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the base query
$query = "SELECT cr.*, 
           u_customer.username as customer_username, 
           u_customer.email as customer_email,
           u_reporter.username as reporter_username,
           u_resolver.username as resolver_username
          FROM customer_reports cr
          JOIN users u_customer ON cr.customer_id = u_customer.id
          JOIN users u_reporter ON cr.reported_by = u_reporter.id
          LEFT JOIN users u_resolver ON cr.resolved_by = u_resolver.id
          WHERE 1=1";

// Add status filter
if ($status_filter === 'pending') {
    $query .= " AND cr.status = 'pending'";
} elseif ($status_filter === 'resolved') {
    $query .= " AND cr.status = 'resolved'";
} elseif ($status_filter === 'dismissed') {
    $query .= " AND cr.status = 'dismissed'";
} elseif ($status_filter === 'blocked') {
    $query .= " AND cr.status = 'block'";
}

// Add search filter
$params = [];
if (!empty($search)) {
    $query .= " AND (u_customer.username LIKE ? OR u_customer.email LIKE ? OR cr.report_reason LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

// Add order by and limit
$query .= " ORDER BY cr.report_date DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total for pagination
$count_query = "SELECT COUNT(*) FROM customer_reports cr 
                JOIN users u_customer ON cr.customer_id = u_customer.id
                JOIN users u_reporter ON cr.reported_by = u_reporter.id
                WHERE 1=1";

if ($status_filter === 'pending') {
    $count_query .= " AND cr.status = 'pending'";
} elseif ($status_filter === 'resolved') {
    $count_query .= " AND cr.status = 'resolved'";
} elseif ($status_filter === 'dismissed') {
    $count_query .= " AND cr.status = 'dismissed'";
} elseif ($status_filter === 'blocked') {
    $count_query .= " AND cr.status = 'block'";
}

if (!empty($search)) {
    $count_query .= " AND (u_customer.username LIKE ? OR u_customer.email LIKE ? OR cr.report_reason LIKE ?)";
    $count_params = [$search_param, $search_param, $search_param];
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
} else {
    $stmt = $pdo->prepare($count_query);
    $stmt->execute();
}

$total_reports = $stmt->fetchColumn();
$total_pages = ceil($total_reports / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reports | Axiom Supervisor</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
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
            background-size: 20px 30px;
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
        
        .status-resolved {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-dismissed {
            background-color: rgba(107, 114, 128, 0.2);
            color: #9ca3af;
        }
        
        .status-block {
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
                
                <a href="customers.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span>Customers</span>
                </a>

                <a href="customer_reports.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <h2 class="text-3xl font-bold heading-font">Customer Reports</h2>
                
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($username) ?></div>
                            <div class="text-xs text-white/70">Supervisor</div>
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
                <h3 class="text-lg font-semibold mb-4">Search & Filter Reports</h3>
                <form method="GET" action="customer_reports.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <div class="relative">
                            <input type="text" class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                                   name="search" placeholder="Search by username, email or report reason" 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-white/60 hover:text-white">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <select name="status" class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                                onchange="this.form.submit()">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Reports</option>
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="resolved" <?= $status_filter == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="dismissed" <?= $status_filter == 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
                            <option value="blocked" <?= $status_filter == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Reports List -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h3 class="text-lg font-semibold">
                        Customer Reports 
                        <span class="text-sm font-normal text-gray-400">(<?= $total_reports ?> total)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                <th class="px-6 py-3 font-medium">ID</th>
                                <th class="px-6 py-3 font-medium">Customer</th>
                                <th class="px-6 py-3 font-medium">Reported By</th>
                                <th class="px-6 py-3 font-medium">Reason</th>
                                <th class="px-6 py-3 font-medium">Date</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr class="border-b border-gray-700">
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-400">No reports found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                    <tr class="border-b border-gray-700 hover:bg-white/5">
                                        <td class="px-6 py-4">#<?= $report['id'] ?></td>
                                        <td class="px-6 py-4">
                                            <div><?= htmlspecialchars($report['customer_username']) ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($report['customer_email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($report['reporter_username']) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars(substr($report['report_reason'], 0, 30)) ?>...</td>
                                        <td class="px-6 py-4"><?= date('M d, Y', strtotime($report['report_date'])) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="status-badge status-<?= $report['status'] ?>">
                                                <?= ucfirst($report['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <button class="view-details p-1 text-blue-400 hover:text-blue-300" 
                                                        data-id="<?= $report['id'] ?>"
                                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>"
                                                        data-email="<?= htmlspecialchars($report['customer_email']) ?>"
                                                        data-reporter="<?= htmlspecialchars($report['reporter_username']) ?>"
                                                        data-reason="<?= htmlspecialchars($report['report_reason']) ?>"
                                                        data-date="<?= date('F d, Y', strtotime($report['report_date'])) ?>"
                                                        data-status="<?= $report['status'] ?>"
                                                        data-resolution="<?= htmlspecialchars($report['resolution'] ?? '') ?>"
                                                        data-resolver="<?= htmlspecialchars($report['resolver_username'] ?? '') ?>"
                                                        data-resolved-date="<?= $report['resolved_date'] ? date('F d, Y', strtotime($report['resolved_date'])) : '' ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($report['status'] == 'pending'): ?>
                                                <button class="resolve-report p-1 text-green-400 hover:text-green-300"
                                                        data-id="<?= $report['id'] ?>"
                                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="dismiss-report p-1 text-gray-400 hover:text-gray-300"
                                                        data-id="<?= $report['id'] ?>"
                                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <button class="block-customer p-1 text-red-400 hover:text-red-300"
                                                        data-id="<?= $report['id'] ?>"
                                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                <?php endif; ?>
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
                        <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="pagination-item">
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
                                echo '<a href="?page=1&status=' . $status_filter . '&search=' . urlencode($search) . '" class="pagination-item">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="pagination-item">...</span>';
                                }
                            }
                            
                            // Display page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $page) {
                                    echo '<span class="pagination-item active">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '&status=' . $status_filter . '&search=' . urlencode($search) . '" class="pagination-item">' . $i . '</a>';
                                }
                            }
                            
                            // Always show last page
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="pagination-item">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&status=' . $status_filter . '&search=' . urlencode($search) . '" class="pagination-item">' . $total_pages . '</a>';
                            }
                        ?>
                        
                        <!-- Next page -->
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="pagination-item">
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
        </div>
    </div>
    
    <!-- View Report Details Modal -->
    <div id="viewReportModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold heading-font">Report Details</h3>
                <button id="closeViewModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-400 mb-1">Customer</h4>
                    <p class="mb-4" id="view-customer"></p>
                    
                    <h4 class="text-sm font-semibold text-gray-400 mb-1">Customer Email</h4>
                    <p class="mb-4" id="view-email"></p>
                    
                    <h4 class="text-sm font-semibold text-gray-400 mb-1">Report Status</h4>
                    <p class="mb-4" id="view-status"></p>
                </div>
                
                <div>
                    <h4 class="text-sm font-semibold text-gray-400 mb-1">Reported By</h4>
                    <p class="mb-4" id="view-reporter"></p>
                    
                    <h4 class="text-sm font-semibold text-gray-400 mb-1">Report Date</h4>
                    <p class="mb-4" id="view-date"></p>
                    
                    <div id="resolution-details" class="hidden">
                        <h4 class="text-sm font-semibold text-gray-400 mb-1">Resolved By</h4>
                        <p class="mb-4" id="view-resolver"></p>
                        
                        <h4 class="text-sm font-semibold text-gray-400 mb-1">Resolution Date</h4>
                        <p class="mb-4" id="view-resolved-date"></p>
                    </div>
                </div>
            </div>
            
            <div class="mt-2">
                <h4 class="text-sm font-semibold text-gray-400 mb-1">Report Reason</h4>
                <p class="mb-4 p-3 bg-white/5 rounded-lg" id="view-reason"></p>
                
                <div id="resolution-notes" class="hidden">
                    <h4 class="text-sm font-semibold text-gray-400 mb-1">Resolution Notes</h4>
                    <p class="mb-4 p-3 bg-white/5 rounded-lg" id="view-resolution"></p>
                </div>
            </div>
            
            <div class="flex justify-end mt-6">
                <button id="closeViewBtn" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Resolve Report Modal -->
    <div id="resolveReportModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Resolve Report</h3>
                <button id="closeResolveModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="customer_reports.php">
                <input type="hidden" name="report_id" id="resolve-report-id">
                <input type="hidden" name="action" value="resolved">
                <p class="mb-4">You are resolving the report for customer <strong id="resolve-customer-name"></strong>.</p>
                <div class="mb-4">
                    <label for="resolution" class="block text-sm font-medium text-gray-300 mb-1">Resolution Notes:</label>
                    <textarea class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                              name="resolution" id="resolution" rows="4" required
                              placeholder="Explain how this report was resolved..."></textarea>
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
    
    <!-- Dismiss Report Modal -->
    <div id="dismissReportModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Dismiss Report</h3>
                <button id="closeDismissModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="customer_reports.php">
                <input type="hidden" name="report_id" id="dismiss-report-id">
                <input type="hidden" name="action" value="dismissed">
                <p class="mb-4">You are dismissing the report for customer <strong id="dismiss-customer-name"></strong>.</p>
                <div class="mb-4">
                    <label for="dismiss-resolution" class="block text-sm font-medium text-gray-300 mb-1">Reason for Dismissal:</label>
                    <textarea class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                              name="resolution" id="dismiss-resolution" rows="4" required
                              placeholder="Explain why this report is being dismissed..."></textarea>
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
    
    <!-- Block Customer Modal -->
    <div id="blockCustomerModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Block Customer</h3>
                <button id="closeBlockModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="customer_reports.php">
                <input type="hidden" name="report_id" id="block-report-id">
                <input type="hidden" name="action" value="block">
                <p class="mb-4">You are blocking customer <strong id="block-customer-name"></strong>. This action will prevent the customer from logging in.</p>
                <div class="mb-4">
                    <label for="block-resolution" class="block text-sm font-medium text-gray-300 mb-1">Reason for Blocking:</label>
                    <textarea class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                              name="resolution" id="block-resolution" rows="4" required
                              placeholder="Explain why this customer is being blocked..."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelBlock" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" name="resolve_report" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg">
                        Block Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Close alerts
            const closeButtons = document.querySelectorAll('.close-alert');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });
            
            // View report details
            const viewButtons = document.querySelectorAll('.view-details');
            const viewModal = document.getElementById('viewReportModal');
            const closeViewModal = document.getElementById('closeViewModal');
            const closeViewBtn = document.getElementById('closeViewBtn');
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const customer = this.getAttribute('data-customer');
                    const email = this.getAttribute('data-email');
                    const reporter = this.getAttribute('data-reporter');
                    const reason = this.getAttribute('data-reason');
                    const date = this.getAttribute('data-date');
                    const status = this.getAttribute('data-status');
                    const resolution = this.getAttribute('data-resolution');
                    const resolver = this.getAttribute('data-resolver');
                    const resolvedDate = this.getAttribute('data-resolved-date');
                    
                    document.getElementById('view-customer').textContent = customer;
                    document.getElementById('view-email').textContent = email;
                    document.getElementById('view-reporter').textContent = reporter;
                    document.getElementById('view-reason').textContent = reason;
                    document.getElementById('view-date').textContent = date;
                    
                    // Set the status with badge
                    const statusElement = document.getElementById('view-status');
                    statusElement.innerHTML = `<span class="status-badge status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                    
                    // Show/hide resolution details
                    const resolutionDetails = document.getElementById('resolution-details');
                    const resolutionNotes = document.getElementById('resolution-notes');
                    
                    if (status !== 'pending' && resolution) {
                        resolutionDetails.classList.remove('hidden');
                        resolutionNotes.classList.remove('hidden');
                        document.getElementById('view-resolver').textContent = resolver;
                        document.getElementById('view-resolved-date').textContent = resolvedDate;
                        document.getElementById('view-resolution').textContent = resolution;
                    } else {
                        resolutionDetails.classList.add('hidden');
                        resolutionNotes.classList.add('hidden');
                    }
                    
                    viewModal.classList.remove('hidden');
                });
            });
            
            closeViewModal.addEventListener('click', function() {
                viewModal.classList.add('hidden');
            });
            
            closeViewBtn.addEventListener('click', function() {
                viewModal.classList.add('hidden');
            });
            
            // Resolve report modal
            const resolveButtons = document.querySelectorAll('.resolve-report');
            const resolveModal = document.getElementById('resolveReportModal');
            const closeResolveModal = document.getElementById('closeResolveModal');
            const cancelResolve = document.getElementById('cancelResolve');
            
            resolveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const customer = this.getAttribute('data-customer');
                    
                    document.getElementById('resolve-report-id').value = id;
                    document.getElementById('resolve-customer-name').textContent = customer;
                    
                    resolveModal.classList.remove('hidden');
                });
            });
            
            closeResolveModal.addEventListener('click', function() {
                resolveModal.classList.add('hidden');
            });
            
            cancelResolve.addEventListener('click', function() {
                resolveModal.classList.add('hidden');
            });
            
            // Dismiss report modal
            const dismissButtons = document.querySelectorAll('.dismiss-report');
            const dismissModal = document.getElementById('dismissReportModal');
            const closeDismissModal = document.getElementById('closeDismissModal');
            const cancelDismiss = document.getElementById('cancelDismiss');
            
            dismissButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const customer = this.getAttribute('data-customer');
                    
                    document.getElementById('dismiss-report-id').value = id;
                    document.getElementById('dismiss-customer-name').textContent = customer;
                    
                    dismissModal.classList.remove('hidden');
                });
            });
            
            closeDismissModal.addEventListener('click', function() {
                dismissModal.classList.add('hidden');
            });
            
            cancelDismiss.addEventListener('click', function() {
                dismissModal.classList.add('hidden');
            });
            
            // Block customer modal
            const blockButtons = document.querySelectorAll('.block-customer');
            const blockModal = document.getElementById('blockCustomerModal');
            const closeBlockModal = document.getElementById('closeBlockModal');
            const cancelBlock = document.getElementById('cancelBlock');
            
            blockButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const customer = this.getAttribute('data-customer');
                    
                    document.getElementById('block-report-id').value = id;
                    document.getElementById('block-customer-name').textContent = customer;
                    
                    blockModal.classList.remove('hidden');
                });
            });
            
            closeBlockModal.addEventListener('click', function() {
                blockModal.classList.add('hidden');
            });
            
            cancelBlock.addEventListener('click', function() {
                blockModal.classList.add('hidden');
            });
        });
    </script>
</body>
</html>