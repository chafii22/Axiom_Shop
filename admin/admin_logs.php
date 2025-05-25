
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

// Process filters
$admin_filter = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get all admins for filter dropdown
$stmt = $pdo->query("SELECT id, username FROM users WHERE is_admin = 1 ORDER BY username");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all action types for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT action FROM admin_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build the base query
$query = "SELECT al.*, u.username 
          FROM admin_logs al
          JOIN users u ON al.user_id = u.id
          WHERE 1=1";

// Add filters
$params = [];

// Admin filter
if ($admin_filter > 0) {
    $query .= " AND al.user_id = ?";
    $params[] = $admin_filter;
}

// Action filter
if ($action_filter !== 'all') {
    $query .= " AND al.action = ?";
    $params[] = $action_filter;
}

// Date range filter
if (!empty($date_from)) {
    $query .= " AND al.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $query .= " AND al.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

// Search filter
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR al.details LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Count total for pagination
$count_query = str_replace("SELECT al.*, u.username", "SELECT COUNT(*)", $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Add order by and limit
$query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_actions FROM admin_logs");
$total_actions = $stmt->fetch()['total_actions'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as active_admins FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$active_admins = $stmt->fetch()['active_admins'];

$stmt = $pdo->query("SELECT action, COUNT(*) as count FROM admin_logs GROUP BY action ORDER BY count DESC LIMIT 1");
$most_common_action = $stmt->fetch();

$stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM admin_logs GROUP BY DATE(created_at) ORDER BY count DESC LIMIT 1");
$most_active_day = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Activity Logs | Axiom Supervisor</title>
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
        
        .action-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .action-login {
            background-color: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }
        
        .action-logout {
            background-color: rgba(107, 114, 128, 0.2);
            color: #d1d5db;
        }
        
        .action-product {
            background-color: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }
        
        .action-order {
            background-color: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
        }
        
        .action-user {
            background-color: rgba(139, 92, 246, 0.2);
            color: #c4b5fd;
        }
        
        .action-report {
            background-color: rgba(236, 72, 153, 0.2);
            color: #f9a8d4;
        }
        
        .action-delete {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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
        
        .form-input {
            width: 100%;
            padding: 0.5rem 1rem;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: white;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            border-color: rgba(255, 255, 255, 0.5);
            outline: none;
            background-color: rgba(255, 255, 255, 0.15);
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

                <a href="customer_reports.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-flag w-5 text-center"></i>
                    <span>Customer Reports</span>
                </a>
                
                <a href="admin_management.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-user-cog w-5 text-center"></i>
                    <span>Admin Management</span>
                </a>
                
                <a href="admin_logs.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <h2 class="text-3xl font-bold heading-font">Admin Activity Logs</h2>
                
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
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-blue-500/20 flex items-center justify-center">
                            <i class="fas fa-chart-line text-blue-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Total Actions</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format($total_actions) ?></p>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-purple-500/20 flex items-center justify-center">
                            <i class="fas fa-users-cog text-purple-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Active Admins</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format($active_admins) ?></p>
                    <p class="text-xs text-gray-400 mt-1">In the last 7 days</p>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-green-500/20 flex items-center justify-center">
                            <i class="fas fa-tasks text-green-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Top Action</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= $most_common_action ? ucfirst(str_replace('_', ' ', $most_common_action['action'])) : 'None' ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= $most_common_action ? number_format($most_common_action['count']) . ' times' : '' ?></p>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-amber-500/20 flex items-center justify-center">
                            <i class="fas fa-calendar-day text-amber-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Busiest Day</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= $most_active_day ? date('M j', strtotime($most_active_day['date'])) : 'None' ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= $most_active_day ? number_format($most_active_day['count']) . ' actions' : '' ?></p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="glass-card rounded-xl p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Filter Activity Logs</h3>
                <form method="GET" action="admin_logs.php" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label for="admin_id" class="block text-sm font-medium text-gray-400 mb-1">Admin User</label>
                        <select name="admin_id" id="admin_id" class="form-input">
                            <option value="0">All Admins</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?= $admin['id'] ?>" <?= $admin_filter == $admin['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="action" class="block text-sm font-medium text-gray-400 mb-1">Action Type</label>
                        <select name="action" id="action" class="form-input">
                            <option value="all">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= $action ?>" <?= $action_filter == $action ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $action)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-400 mb-1">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-input" value="<?= $date_from ?>">
                    </div>
                    
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-400 mb-1">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-input" value="<?= $date_to ?>">
                    </div>
                    
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-400 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" name="search" id="search" class="form-input pr-10" 
                                   placeholder="Search details..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Activity Logs Table -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h3 class="text-lg font-semibold">
                        Activity Logs
                        <span class="text-sm font-normal text-gray-400">(<?= $total_logs ?> total)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                <th class="px-6 py-3 font-medium">ID</th>
                                <th class="px-6 py-3 font-medium">Admin</th>
                                <th class="px-6 py-3 font-medium">Action</th>
                                <th class="px-6 py-3 font-medium">Details</th>
                                <th class="px-6 py-3 font-medium">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr class="border-b border-gray-700">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-400">No activity logs found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-4">#<?= $log['id'] ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($log['username']) ?></td>
                                        <td class="px-6 py-4">
                                            <?php
                                                $action = $log['action'];
                                                $class = 'action-badge ';
                                                
                                                if (strpos($action, 'login') !== false) {
                                                    $class .= 'action-login';
                                                } elseif (strpos($action, 'logout') !== false) {
                                                    $class .= 'action-logout';
                                                } elseif (strpos($action, 'product') !== false) {
                                                    $class .= 'action-product';
                                                } elseif (strpos($action, 'order') !== false) {
                                                    $class .= 'action-order';
                                                } elseif (strpos($action, 'user') !== false || strpos($action, 'customer') !== false) {
                                                    $class .= 'action-user';
                                                } elseif (strpos($action, 'report') !== false) {
                                                    $class .= 'action-report';
                                                } elseif (strpos($action, 'delete') !== false) {
                                                    $class .= 'action-delete';
                                                } else {
                                                    $class .= 'action-logout'; // default
                                                }
                                            ?>
                                            <span class="<?= $class ?>">
                                                <?= ucfirst(str_replace('_', ' ', $action)) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($log['details']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                                            <div class="text-xs text-gray-400"><?= date('h:i:s A', strtotime($log['created_at'])) ?></div>
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
                        <a href="?page=<?= $page - 1 ?>&admin_id=<?= $admin_filter ?>&action=<?= $action_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" class="pagination-item">
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
                                echo '<a href="?page=1&admin_id=' . $admin_filter . '&action=' . $action_filter . '&date_from=' . $date_from . '&date_to=' . $date_to . '&search=' . urlencode($search) . '" class="pagination-item">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="pagination-item">...</span>';
                                }
                            }
                            
                            // Display page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $page) {
                                    echo '<span class="pagination-item active">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '&admin_id=' . $admin_filter . '&action=' . $action_filter . '&date_from=' . $date_from . '&date_to=' . $date_to . '&search=' . urlencode($search) . '" class="pagination-item">' . $i . '</a>';
                                }
                            }
                            
                            // Always show last page
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="pagination-item">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&admin_id=' . $admin_filter . '&action=' . $action_filter . '&date_from=' . $date_from . '&date_to=' . $date_to . '&search=' . urlencode($search) . '" class="pagination-item">' . $total_pages . '</a>';
                            }
                        ?>
                        
                        <!-- Next page -->
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&admin_id=<?= $admin_filter ?>&action=<?= $action_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>" class="pagination-item">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filters change
            const filterForm = document.querySelector('form');
            const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
</body>
</html>