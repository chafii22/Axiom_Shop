<?php
// Start session
session_start();

// Check for admin or supervisor authorization
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['is_supervisor'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../config/connect_db.php';

// Determine if user is supervisor
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] === true;

// Fetch user data
$user_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['user_id'];
$user_role = $is_supervisor ? "Supervisor" : "Administrator";

$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    // If somehow the user doesn't exist anymore
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Initialize variables
$message = '';
$messageType = '';

// Process account deletion request
if (isset($_POST['delete_account']) && isset($_POST['confirm_delete'])) {
    $password = $_POST['password'] ?? '';
    
    // Verify password first
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete associated data (you may need to adjust this based on your database structure)
            // For example, delete orders, addresses, etc.
            
            // Finally delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Clear session
            session_destroy();
            
            // Redirect with success message
            header("Location: ../auth/login.php?deleted=1");
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $message = "Failed to delete account: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Incorrect password. Account was not deleted.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Axiom</title>
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
        
        /* Same background grid styling as your dashboard */
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
        
        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem;
            border-radius: 0.5rem;
            width: 100%;
        }
        
        .form-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .danger-zone {
            border: 1px solid rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.1);
        }
    </style>
</head>
<body class="text-white min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar with role-based menu items -->
        <div class="glass-sidebar w-64 p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-10 heading-font">
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <i class="fas fa-gem text-white"></i>
                </div>
                <h1 class="text-xl font-bold tracking-wide">Axiom</h1>
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
                
                <a href="settings.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <h2 class="text-3xl font-bold heading-font">Account Settings</h2>
                
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($user_data['username']) ?></div>
                            <div class="text-xs text-white/70"><?= htmlspecialchars($user_role) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="glass-card p-4 mb-6 rounded-lg <?= $messageType === 'error' ? 'border border-red-500 bg-red-500/10' : 'border border-green-500 bg-green-500/10' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Account Settings -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Account Info -->
                <div class="lg:col-span-2">
                    <div class="glass-card p-6 rounded-xl mb-6">
                        <h3 class="text-lg font-semibold mb-6 heading-font">Profile Information</h3>
                        
                        <form method="post" action="">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">Username</label>
                                    <input type="text" name="username" value="<?= htmlspecialchars($user_data['username']) ?>" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">Email Address</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" class="form-input">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition">
                                Update Profile
                            </button>
                        </form>
                    </div>
                    
                    <div class="glass-card p-6 rounded-xl mb-6">
                        <h3 class="text-lg font-semibold mb-6 heading-font">Change Password</h3>
                        
                        <form method="post" action="">
                            <div class="grid grid-cols-1 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">Current Password</label>
                                    <input type="password" name="current_password" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">New Password</label>
                                    <input type="password" name="new_password" class="form-input">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-input">
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition">
                                Change Password
                            </button>
                        </form>
                    </div>
                    
                    <?php if ($is_supervisor): ?>
                    <!-- Supervisor-specific settings -->
                    <div class="glass-card p-6 rounded-xl mb-6">
                        <h3 class="text-lg font-semibold mb-6 heading-font">Supervisor Settings</h3>
                        
                        <form method="post" action="">
                            <div class="grid grid-cols-1 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">Email Notifications</label>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="notify_reports" id="notify_reports" class="form-checkbox" checked>
                                        <label for="notify_reports">Receive email notifications for new customer reports</label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium mb-2 opacity-80">Admin Activity Monitoring</label>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="monitor_logins" id="monitor_logins" class="form-checkbox" checked>
                                        <label for="monitor_logins">Monitor admin login activities</label>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <input type="checkbox" name="monitor_actions" id="monitor_actions" class="form-checkbox" checked>
                                        <label for="monitor_actions">Monitor admin critical actions</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_supervisor_settings" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition">
                                Save Supervisor Settings
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Danger Zone -->
                <div class="lg:col-span-1">
                    <div class="glass-card danger-zone p-6 rounded-xl">
                        <h3 class="text-lg font-semibold mb-6 heading-font text-red-400">Danger Zone</h3>
                        
                        <p class="mb-6 text-sm opacity-80">
                            Deleting your account is permanent and cannot be undone. All your data will be permanently removed.
                        </p>
                        
                        <button id="showDeleteModal" class="w-full px-4 py-2 bg-red-500/20 hover:bg-red-500/40 border border-red-500/50 rounded-lg transition">
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center hidden">
        <div class="glass-card p-8 rounded-xl max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4 heading-font text-red-400">Delete Account</h3>
            
            <p class="mb-6">
                This action cannot be undone. All your data will be permanently deleted. Please enter your password to confirm.
            </p>
            
            <form method="post" action="">
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2 opacity-80">Password</label>
                    <input type="password" name="password" required class="form-input">
                </div>
                
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="confirm_delete" required class="mr-2">
                        <span>I understand this action is permanent</span>
                    </label>
                </div>
                
                <div class="flex gap-4">
                    <button type="button" id="cancelDelete" class="flex-1 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit" name="delete_account" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Delete My Account
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal controls
        const showDeleteModal = document.getElementById('showDeleteModal');
        const deleteModal = document.getElementById('deleteModal');
        const cancelDelete = document.getElementById('cancelDelete');
        
        showDeleteModal.addEventListener('click', () => {
            deleteModal.classList.remove('hidden');
        });
        
        cancelDelete.addEventListener('click', () => {
            deleteModal.classList.add('hidden');
        });
        
        // Close modal when clicking outside
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>