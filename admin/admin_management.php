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

// Process actions
$success_message = '';
$error_message = '';

// Create new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $new_password_confirm = $_POST['password_confirm'];
    
    // Validate input
    if (empty($new_username) || empty($new_email) || empty($new_password)) {
        $error_message = "All fields are required";
    } elseif ($new_password !== $new_password_confirm) {
        $error_message = "Passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$new_username, $new_email]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            $error_message = "Username or email already exists";
        } else {
            // Create the new admin
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, is_admin, created_at) 
                                      VALUES (?, ?, ?, 1, NOW())");
                $stmt->execute([$new_username, $new_email, $hashed_password]);
                
                $new_admin_id = $pdo->lastInsertId();
                
                // Log the action
                $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) 
                                      VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user_id, 'create_admin', "Created new admin account: $new_username ($new_admin_id)"]);
                
                $success_message = "Admin account created successfully";
            } catch (PDOException $e) {
                $error_message = "Error creating admin account: " . $e->getMessage();
            }
        }
    }
}

// Update admin status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_status'])) {
    $admin_id = $_POST['admin_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND is_admin = 1");
        $stmt->execute([$status, $admin_id]);
        
        // Log the action
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) 
                              VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, 'update_admin_status', "Updated admin ID $admin_id status to: $status"]);
        
        $success_message = "Admin status updated successfully";
    } catch (PDOException $e) {
        $error_message = "Error updating admin status: " . $e->getMessage();
    }
}

// Reset admin password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $admin_id = $_POST['admin_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND is_admin = 1");
            $stmt->execute([$hashed_password, $admin_id]);
            
            // Log the action
            $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) 
                                  VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, 'reset_admin_password', "Reset password for admin ID: $admin_id"]);
            
            $success_message = "Admin password reset successfully";
        } catch (PDOException $e) {
            $error_message = "Error resetting admin password: " . $e->getMessage();
        }
    }
}

// Delete admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = $_POST['admin_id'];
    $confirmation = $_POST['confirmation'];
    
    if ($confirmation !== 'DELETE') {
        $error_message = "Incorrect confirmation text";
    } else {
        try {
            // Get admin username for logging
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND is_admin = 1");
            $stmt->execute([$admin_id]);
            $admin_username = $stmt->fetchColumn();
            
            // Delete the admin
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 1");
            $stmt->execute([$admin_id]);
            
            // Log the action
            $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details, created_at) 
                                  VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, 'delete_admin', "Deleted admin account: $admin_username ($admin_id)"]);
            
            $success_message = "Admin account deleted successfully";
        } catch (PDOException $e) {
            $error_message = "Error deleting admin account: " . $e->getMessage();
        }
    }
}

// Process filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the base query
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM admin_logs WHERE user_id = u.id) as activity_count,
          (SELECT MAX(created_at) FROM admin_logs WHERE user_id = u.id) as last_activity
          FROM users u
          WHERE u.is_admin = 1";

// Add status filter
if ($status_filter === 'active') {
    $query .= " AND (u.status = 'active' OR u.status IS NULL)";
} elseif ($status_filter === 'suspended') {
    $query .= " AND u.status = 'suspended'";
} elseif ($status_filter === 'inactive') {
    $query .= " AND u.status = 'inactive'";
}

// Add search filter
$params = [];
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
}

// Count total for pagination
$count_query = "SELECT COUNT(*) FROM users u WHERE u.is_admin = 1";

// Add status filter
if ($status_filter === 'active') {
    $count_query .= " AND (u.status = 'active' OR u.status IS NULL)";
} elseif ($status_filter === 'suspended') {
    $count_query .= " AND u.status = 'suspended'";
} elseif ($status_filter === 'inactive') {
    $count_query .= " AND u.status = 'inactive'";
}

// Add search filter
if (!empty($search)) {
    $count_query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_admins = $stmt->fetchColumn();
$total_pages = ceil($total_admins / $per_page);

// Add order by and limit
$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get admin activity summary
$stmt = $pdo->query("SELECT COUNT(*) as total_admins FROM users WHERE is_admin = 1");
$total_all_admins = $stmt->fetch()['total_admins'];

$stmt = $pdo->query("SELECT COUNT(*) as active_count FROM users WHERE is_admin = 1 AND (status = 'active' OR status IS NULL)");
$active_admins = $stmt->fetch()['active_count'];

$stmt = $pdo->query("SELECT COUNT(*) as suspended_count FROM users WHERE is_admin = 1 AND status = 'suspended'");
$suspended_admins = $stmt->fetch()['suspended_count'];

$stmt = $pdo->query("SELECT COUNT(*) as inactive_count FROM users WHERE is_admin = 1 AND status = 'inactive'");
$inactive_admins = $stmt->fetch()['inactive_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | Axiom Supervisor</title>
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
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-suspended {
            background-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        
        .status-inactive {
            background-color: rgba(107, 114, 128, 0.2);
            color: #9ca3af;
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
                
                <a href="admin_management.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <h2 class="text-3xl font-bold heading-font">Admin Management</h2>
                
                <div class="flex items-center gap-6">
                    <button id="createAdminBtn" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Admin</span>
                    </button>
                    
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
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                            <i class="fas fa-users-cog text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Total Admins</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format($total_all_admins) ?></p>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-green-500/20 flex items-center justify-center">
                            <i class="fas fa-user-check text-green-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Active Admins</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format($active_admins) ?></p>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-yellow-500/20 flex items-center justify-center">
                            <i class="fas fa-user-clock text-yellow-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Suspended</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format($suspended_admins) ?></p>
                </div>
                
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-12 h-12 rounded-lg bg-gray-500/20 flex items-center justify-center">
                            <i class="fas fa-user-slash text-gray-400"></i>
                        </div>
                        <h3 class="text-sm uppercase tracking-wider opacity-70">Inactive</h3>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format($inactive_admins) ?></p>
                </div>
            </div>
            
            <!-- Search & Filter -->
            <div class="glass-card rounded-xl p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Search & Filter Admins</h3>
                <form method="GET" action="admin_management.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <div class="relative">
                            <input type="text" class="form-input pr-10" name="search" placeholder="Search by username or email" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <select name="status" class="form-input" onchange="this.form.submit()">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Admins</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= $status_filter == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Admins List -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h3 class="text-lg font-semibold">
                        Admin Accounts
                        <span class="text-sm font-normal text-gray-400">(<?= $total_admins ?> total)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-400 border-b border-gray-700">
                                <th class="px-6 py-3 font-medium">ID</th>
                                <th class="px-6 py-3 font-medium">Username</th>
                                <th class="px-6 py-3 font-medium">Email</th>
                                <th class="px-6 py-3 font-medium">Created</th>
                                <th class="px-6 py-3 font-medium">Last Activity</th>
                                <th class="px-6 py-3 font-medium">Actions</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr class="border-b border-gray-700">
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-400">No admin accounts found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-4">#<?= $admin['id'] ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($admin['username']) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($admin['email']) ?></td>
                                        <td class="px-6 py-4"><?= date('M d, Y', strtotime($admin['created_at'])) ?></td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($admin['last_activity'])): ?>
                                                <?= date('M d, Y', strtotime($admin['last_activity'])) ?>
                                                <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($admin['last_activity'])) ?></div>
                                            <?php else: ?>
                                                <span class="text-gray-400">No activity</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <button class="view-admin p-1 text-blue-400 hover:text-blue-300" 
                                                        data-id="<?= $admin['id'] ?>"
                                                        data-username="<?= htmlspecialchars($admin['username']) ?>"
                                                        data-email="<?= htmlspecialchars($admin['email']) ?>"
                                                        data-created="<?= date('F d, Y', strtotime($admin['created_at'])) ?>"
                                                        data-activity-count="<?= $admin['activity_count'] ?>"
                                                        data-last-activity="<?= !empty($admin['last_activity']) ? date('F d, Y h:i A', strtotime($admin['last_activity'])) : 'Never' ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <button class="reset-password p-1 text-yellow-400 hover:text-yellow-300"
                                                        data-id="<?= $admin['id'] ?>"
                                                        data-username="<?= htmlspecialchars($admin['username']) ?>">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                
                                                <button class="change-status p-1 text-purple-400 hover:text-purple-300"
                                                        data-id="<?= $admin['id'] ?>"
                                                        data-username="<?= htmlspecialchars($admin['username']) ?>"
                                                        data-status="<?= $admin['status'] ?? 'active' ?>">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                
                                                <button class="delete-admin p-1 text-red-400 hover:text-red-300"
                                                        data-id="<?= $admin['id'] ?>"
                                                        data-username="<?= htmlspecialchars($admin['username']) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                                $status = $admin['status'] ?? 'active';
                                                $statusClass = 'status-' . $status;
                                                $statusText = ucfirst($status);
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
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
    
    <!-- Create Admin Modal -->
    <div id="createAdminModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold heading-font">Create New Admin</h3>
                <button id="closeCreateModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="admin_management.php">
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                    <input type="text" name="username" id="username" class="form-input" required>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                    <input type="email" name="email" id="email" class="form-input" required>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                    <input type="password" name="password" id="password" class="form-input" required minlength="8">
                    <p class="text-xs text-gray-400 mt-1">Must be at least 8 characters long</p>
                </div>
                
                <div class="mb-6">
                    <label for="password_confirm" class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirm" id="password_confirm" class="form-input" required minlength="8">
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelCreate" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="create_admin" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg">
                        Create Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Admin Modal -->
    <div id="viewAdminModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold heading-font">Admin Details</h3>
                <button id="closeViewModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-400 mb-1">Username</h4>
                <p class="text-lg" id="view-username"></p>
                
                <h4 class="text-sm font-semibold text-gray-400 mb-1 mt-4">Email</h4>
                <p class="text-lg" id="view-email"></p>
                
                <h4 class="text-sm font-semibold text-gray-400 mb-1 mt-4">Created</h4>
                <p class="text-lg" id="view-created"></p>
                
                <h4 class="text-sm font-semibold text-gray-400 mb-1 mt-4">Activity Count</h4>
                <p class="text-lg" id="view-activity-count"></p>
                
                <h4 class="text-sm font-semibold text-gray-400 mb-1 mt-4">Last Activity</h4>
                <p class="text-lg" id="view-last-activity"></p>
            </div>
            
            <div class="flex justify-end">
                <button type="button" id="closeViewBtn" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold heading-font">Reset Admin Password</h3>
                <button id="closeResetModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="admin_management.php">
                <input type="hidden" name="admin_id" id="reset-admin-id">
                
                <p class="mb-4">You are resetting the password for <strong id="reset-admin-name"></strong>.</p>
                
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                    <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8">
                    <p class="text-xs text-gray-400 mt-1">Must be at least 8 characters long</p>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" required minlength="8">
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelReset" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="reset_password" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Status Modal -->
    <div id="changeStatusModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold heading-font">Change Admin Status</h3>
                <button id="closeStatusModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="admin_management.php">
                <input type="hidden" name="admin_id" id="status-admin-id">
                
                <p class="mb-4">You are changing the status for <strong id="status-admin-name"></strong>.</p>
                
                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium text-gray-300 mb-1">New Status</label>
                    <select name="status" id="status" class="form-input">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelStatus" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="update_admin_status" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Admin Modal -->
    <div id="deleteAdminModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold heading-font">Delete Admin Account</h3>
                <button id="closeDeleteModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="admin_management.php">
                <input type="hidden" name="admin_id" id="delete-admin-id">
                
                <div class="bg-red-900/40 border border-red-500 text-red-200 px-4 py-3 rounded mb-4">
                    <p>Warning: This action cannot be undone. The account will be permanently deleted.</p>
                </div>
                
                <p class="mb-4">You are about to delete the admin account for <strong id="delete-admin-name"></strong>.</p>
                
                <div class="mb-6">
                    <label for="confirmation" class="block text-sm font-medium text-gray-300 mb-1">Type DELETE to confirm</label>
                    <input type="text" name="confirmation" id="confirmation" class="form-input" required>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelDelete" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="delete_admin" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg">
                        Delete Account
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
            
            // Create Admin Modal
            const createAdminBtn = document.getElementById('createAdminBtn');
            const createAdminModal = document.getElementById('createAdminModal');
            const closeCreateModal = document.getElementById('closeCreateModal');
            const cancelCreate = document.getElementById('cancelCreate');
            
            createAdminBtn.addEventListener('click', function() {
                createAdminModal.classList.remove('hidden');
            });
            
            closeCreateModal.addEventListener('click', function() {
                createAdminModal.classList.add('hidden');
            });
            
            cancelCreate.addEventListener('click', function() {
                createAdminModal.classList.add('hidden');
            });
            
            // View Admin Modal
            const viewButtons = document.querySelectorAll('.view-admin');
            const viewAdminModal = document.getElementById('viewAdminModal');
            const closeViewModal = document.getElementById('closeViewModal');
            const closeViewBtn = document.getElementById('closeViewBtn');
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');
                    const email = this.getAttribute('data-email');
                    const created = this.getAttribute('data-created');
                    const activityCount = this.getAttribute('data-activity-count');
                    const lastActivity = this.getAttribute('data-last-activity');
                    
                    document.getElementById('view-username').textContent = username;
                    document.getElementById('view-email').textContent = email;
                    document.getElementById('view-created').textContent = created;
                    document.getElementById('view-activity-count').textContent = activityCount + ' actions';
                    document.getElementById('view-last-activity').textContent = lastActivity;
                    
                    viewAdminModal.classList.remove('hidden');
                });
            });
            
            closeViewModal.addEventListener('click', function() {
                viewAdminModal.classList.add('hidden');
            });
            
            closeViewBtn.addEventListener('click', function() {
                viewAdminModal.classList.add('hidden');
            });
            
            // Reset Password Modal
            const resetButtons = document.querySelectorAll('.reset-password');
            const resetPasswordModal = document.getElementById('resetPasswordModal');
            const closeResetModal = document.getElementById('closeResetModal');
            const cancelReset = document.getElementById('cancelReset');
            
            resetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    
                    document.getElementById('reset-admin-id').value = id;
                    document.getElementById('reset-admin-name').textContent = username;
                    
                    resetPasswordModal.classList.remove('hidden');
                });
            });
            
            closeResetModal.addEventListener('click', function() {
                resetPasswordModal.classList.add('hidden');
            });
            
            cancelReset.addEventListener('click', function() {
                resetPasswordModal.classList.add('hidden');
            });
            
            // Change Status Modal
            const statusButtons = document.querySelectorAll('.change-status');
            const changeStatusModal = document.getElementById('changeStatusModal');
            const closeStatusModal = document.getElementById('closeStatusModal');
            const cancelStatus = document.getElementById('cancelStatus');
            const statusSelect = document.getElementById('status');
            
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    const currentStatus = this.getAttribute('data-status');
                    
                    document.getElementById('status-admin-id').value = id;
                    document.getElementById('status-admin-name').textContent = username;
                    
                    // Set the current status in the dropdown
                    for (let i = 0; i < statusSelect.options.length; i++) {
                        if (statusSelect.options[i].value === currentStatus) {
                            statusSelect.selectedIndex = i;
                            break;
                        }
                    }
                    
                    changeStatusModal.classList.remove('hidden');
                });
            });
            
            closeStatusModal.addEventListener('click', function() {
                changeStatusModal.classList.add('hidden');
            });
            
            cancelStatus.addEventListener('click', function() {
                changeStatusModal.classList.add('hidden');
            });
            
            // Delete Admin Modal
            const deleteButtons = document.querySelectorAll('.delete-admin');
            const deleteAdminModal = document.getElementById('deleteAdminModal');
            const closeDeleteModal = document.getElementById('closeDeleteModal');
            const cancelDelete = document.getElementById('cancelDelete');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    
                    document.getElementById('delete-admin-id').value = id;
                    document.getElementById('delete-admin-name').textContent = username;
                    
                    deleteAdminModal.classList.remove('hidden');
                });
            });
            
            closeDeleteModal.addEventListener('click', function() {
                deleteAdminModal.classList.add('hidden');
            });
            
            cancelDelete.addEventListener('click', function() {
                deleteAdminModal.classList.add('hidden');
            });
        });
    </script>
</body>
</html>