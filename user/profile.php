<?php
// Start session
session_start();

// Check for user authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../config/connect_db.php';

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    // If user doesn't exist or is an admin
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Handle profile update
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Check if email is already taken by another user
    if ($email !== $user['email']) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$email, $user_id]);
        if ($check_stmt->fetchColumn() > 0) {
            $_SESSION['error_message'] = "Email address is already in use.";
            header("Location: profile.php");
            exit();
        }
    }
    
    // Check if username is already taken by another user
    if ($username !== $user['username']) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$username, $user_id]);
        if ($check_stmt->fetchColumn() > 0) {
            $_SESSION['error_message'] = "Username is already in use.";
            header("Location: profile.php");
            exit();
        }
    }
    
    // Update profile
    $stmt = $pdo->prepare("UPDATE users SET 
                          first_name = ?, 
                          last_name = ?, 
                          username = ?, 
                          email = ?, 
                          phone = ? 
                          WHERE id = ?");
    
    if ($stmt->execute([$first_name, $last_name, $username, $email, $phone, $user_id])) {
        $_SESSION['success_message'] = "Profile updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update profile.";
    }
    
    header("Location: profile.php");
    exit();
}

// Handle password change
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error_message'] = "Current password is incorrect.";
        header("Location: profile.php");
        exit();
    }
    
    // Check if new passwords match
    if ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = "New passwords do not match.";
        header("Location: profile.php");
        exit();
    }
    
    // Validate password strength
    if (strlen($new_password) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters.";
        header("Location: profile.php");
        exit();
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    
    if ($stmt->execute([$hashed_password, $user_id])) {
        $_SESSION['success_message'] = "Password changed successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to change password.";
    }
    
    header("Location: profile.php");
    exit();
}

// Handle notification preferences
if (isset($_POST['action']) && $_POST['action'] === 'update_preferences') {
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    $order_updates = isset($_POST['order_updates']) ? 1 : 0;
    $promotional = isset($_POST['promotional']) ? 1 : 0;
    
    // First, check if preferences exist
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferences WHERE user_id = ?");
    $check_stmt->execute([$user_id]);
    
    if ($check_stmt->fetchColumn() > 0) {
        // Update existing preferences
        $stmt = $pdo->prepare("UPDATE user_preferences SET 
                              newsletter = ?, 
                              order_updates = ?, 
                              promotional = ? 
                              WHERE user_id = ?");
        $result = $stmt->execute([$newsletter, $order_updates, $promotional, $user_id]);
    } else {
        // Insert new preferences
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, newsletter, order_updates, promotional) 
                               VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$user_id, $newsletter, $order_updates, $promotional]);
    }
    
    if ($result) {
        $_SESSION['success_message'] = "Notification preferences updated!";
    } else {
        $_SESSION['error_message'] = "Failed to update preferences.";
    }
    
    header("Location: profile.php");
    exit();
}

// Fetch user notification preferences
$stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$preferences = $stmt->fetch();

if (!$preferences) {
    // Set default preferences if none exist
    $preferences = [
        'newsletter' => 1,
        'order_updates' => 1,
        'promotional' => 0
    ];
}

// Active tab management
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
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
        
        .tab-item {
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }
        
        .tab-item:hover {
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .tab-item.active {
            border-bottom: 2px solid white;
        }
        
        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-input:focus {
            border-color: rgba(255, 255, 255, 0.5);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.25);
        }
        
        .profile-progress {
            background: rgba(255, 255, 255, 0.1);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
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
                <a href="account.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-user w-5 text-center"></i>
                    <span>My Account</span>
                </a>
                
                <a href="orders.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-bag w-5 text-center"></i>
                    <span>My Orders</span>
                </a>
                
                <a href="wishlist.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-heart w-5 text-center"></i>
                    <span>Wishlist</span>
                </a>
                
                <a href="addresses.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-map-marker-alt w-5 text-center"></i>
                    <span>My Addresses</span>
                </a>
                
                <a href="profile.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span>Account Settings</span>
                </a>
            </div>
            
            <div class="mt-auto">
                <a href="../home.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-home w-5 text-center"></i>
                    <span>Return to home</span>
                </a>
                
                <a href="../auth/logout.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-red-500/20">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Top Bar -->
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold heading-font">Account Settings</h2>
                
                <div class="flex items-center gap-6">
                    
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center overflow-hidden">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Profile picture" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="text-xs text-white/70">Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status Message Display -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-500/20 border border-green-500/50 text-green-300 px-4 py-3 rounded mb-6 flex items-center justify-between">
                    <p><?= $_SESSION['success_message'] ?></p>
                    <button onclick="this.parentElement.style.display='none'" class="text-green-300 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded mb-6 flex items-center justify-between">
                    <p><?= $_SESSION['error_message'] ?></p>
                    <button onclick="this.parentElement.style.display='none'" class="text-red-300 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Profile Tabs -->
            <div class="glass-card p-5 rounded-xl mb-8">
                <div class="flex space-x-6 border-b border-white/20 pb-3">
                    <a href="?tab=profile" class="tab-item pb-2 px-2 <?= $activeTab === 'profile' ? 'active' : '' ?>">
                        <div class="font-medium">Profile</div>
                    </a>
                    <a href="?tab=password" class="tab-item pb-2 px-2 <?= $activeTab === 'password' ? 'active' : '' ?>">
                        <div class="font-medium">Password</div>
                    </a>
                    <a href="?tab=notifications" class="tab-item pb-2 px-2 <?= $activeTab === 'notifications' ? 'active' : '' ?>">
                        <div class="font-medium">Notifications</div>
                    </a>
                    <a href="?tab=privacy" class="tab-item pb-2 px-2 <?= $activeTab === 'privacy' ? 'active' : '' ?>">
                        <div class="font-medium">Privacy</div>
                    </a>
                </div>
            </div>
            
            <!-- Profile Completion Card -->
            <?php
            // Calculate profile completion
            $fields = [
                'first_name' => !empty($user['first_name']),
                'last_name' => !empty($user['last_name']),
                'email' => !empty($user['email']),
                'phone' => !empty($user['phone']),
                'has_address' => false, // Will be set below
                'has_order' => false,   // Will be set below
                'has_avatar' => !empty($user['avatar'])
            ];
            
            // Check if user has any address
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $fields['has_address'] = ($stmt->fetchColumn() > 0);
            
            // Check if user has any order
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $fields['has_order'] = ($stmt->fetchColumn() > 0);
            
            // Calculate percentage
            $completedFields = array_filter($fields);
            $completionPercentage = round(count($completedFields) / count($fields) * 100);
            ?>
            
            <div class="glass-card p-6 rounded-xl mb-8">
                <h3 class="text-lg font-semibold heading-font mb-4">Profile Completion</h3>
                <div class="flex items-center mb-1">
                    <div class="flex-1">
                        <div class="profile-progress">
                            <div class="progress-bar" style="width: <?= $completionPercentage ?>%"></div>
                        </div>
                    </div>
                    <span class="ml-4 font-medium"><?= $completionPercentage ?>%</span>
                </div>
                <p class="text-sm text-white/70">Complete your profile to get the most out of our services.</p>
                
                <!-- Missing fields suggestions -->
                <?php if ($completionPercentage < 100): ?>
                <div class="mt-4">
                    <p class="text-sm font-medium mb-2">Suggested actions to complete your profile:</p>
                    <ul class="text-sm">
                        <?php if (!$fields['first_name'] || !$fields['last_name']): ?>
                            <li class="flex items-center gap-2 text-white/70 mb-1">
                                <i class="fas fa-circle text-xs"></i> Add your full name
                            </li>
                        <?php endif; ?>
                        <?php if (!$fields['phone']): ?>
                            <li class="flex items-center gap-2 text-white/70 mb-1">
                                <i class="fas fa-circle text-xs"></i> Add your phone number
                            </li>
                        <?php endif; ?>
                        <?php if (!$fields['has_address']): ?>
                            <li class="flex items-center gap-2 text-white/70 mb-1">
                                <i class="fas fa-circle text-xs"></i> <a href="addresses.php?add=1" class="underline">Add a shipping address</a>
                            </li>
                        <?php endif; ?>
                        <?php if (!$fields['has_avatar']): ?>
                            <li class="flex items-center gap-2 text-white/70">
                                <i class="fas fa-circle text-xs"></i> Upload a profile picture
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Content -->
            <?php if ($activeTab === 'profile'): ?>
                <!-- Profile Tab Content -->
                <div class="glass-card p-6 rounded-xl mb-8">
                    <h3 class="text-xl font-semibold heading-font mb-6">Profile Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="md:col-span-2">
                            <form method="post" action="profile.php">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <label for="first_name" class="block text-sm mb-1 opacity-70">First Name</label>
                                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 rounded form-input">
                                    </div>
                                    <div>
                                        <label for="last_name" class="block text-sm mb-1 opacity-70">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                                               class="w-full px-3 py-2 rounded form-input">
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label for="username" class="block text-sm mb-1 opacity-70">Username</label>
                                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                                
                                <div class="mb-6">
                                    <label for="email" class="block text-sm mb-1 opacity-70">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                                
                                <div class="mb-6">
                                    <label for="phone" class="block text-sm mb-1 opacity-70">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                                
                                <div>
                                    <button type="submit" class="px-6 py-2 bg-white/20 hover:bg-white/30 transition rounded-lg">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div>
                            <div class="bg-white/10 p-5 rounded-lg mb-6">
                                <h4 class="font-medium mb-4">Profile Picture</h4>
                                
                                <div class="mb-4 flex justify-center">
                                    <div class="w-32 h-32 rounded-full bg-white/20 flex items-center justify-center overflow-hidden">
                                        <?php if (!empty($user['avatar'])): ?>
                                            <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Profile picture" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-4xl"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <form action="../scripts/upload_avatar.php" method="post" enctype="multipart/form-data" class="text-center">
                                    <div class="mb-3">
                                        <label for="avatar" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded transition inline-block cursor-pointer">
                                            <i class="fas fa-upload mr-2"></i> Choose file
                                        </label>
                                        <input type="file" id="avatar" name="avatar" class="hidden" accept="image/*" onchange="document.getElementById('file-name').textContent = this.files[0].name">
                                    </div>
                                    <p id="file-name" class="text-sm mb-3 opacity-70"></p>
                                    <button type="submit" class="px-4 py-1 bg-white/20 hover:bg-white/30 rounded text-sm transition">
                                        Upload
                                    </button>
                                </form>
                            </div>
                            
                            <div class="text-sm opacity-70">
                                <p class="mb-2">Account Created: <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                                <p>Last Updated: <?= date('F j, Y', strtotime($user['updated_at'] ?? $user['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($activeTab === 'password'): ?>
                <!-- Password Tab Content -->
                <div class="glass-card p-6 rounded-xl mb-8">
                    <h3 class="text-xl font-semibold heading-font mb-6">Change Password</h3>
                    
                    <div class="max-w-lg">
                        <form method="post" action="profile.php">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-6">
                                <label for="current_password" class="block text-sm mb-1 opacity-70">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required
                                       class="w-full px-3 py-2 rounded form-input">
                            </div>
                            
                            <div class="mb-6">
                                <label for="new_password" class="block text-sm mb-1 opacity-70">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="8"
                                       class="w-full px-3 py-2 rounded form-input">
                                <p class="text-xs opacity-70 mt-1">Password must be at least 8 characters</p>
                            </div>
                            
                            <div class="mb-6">
                                <label for="confirm_password" class="block text-sm mb-1 opacity-70">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                       class="w-full px-3 py-2 rounded form-input">
                            </div>
                            
                            <div>
                                <button type="submit" class="px-6 py-2 bg-white/20 hover:bg-white/30 transition rounded-lg">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="mt-8 border-t border-white/20 pt-6">
                        <h4 class="font-medium mb-3">Password Security Tips</h4>
                        <ul class="text-sm opacity-70 space-y-2">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-1"></i>
                                <span>Use a combination of letters, numbers, and special characters</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-1"></i>
                                <span>Don't reuse passwords from other websites</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-1"></i>
                                <span>Consider using a password manager for added security</span>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php elseif ($activeTab === 'notifications'): ?>
                <!-- Notifications Tab Content -->
                <div class="glass-card p-6 rounded-xl mb-8">
                    <h3 class="text-xl font-semibold heading-font mb-6">Notification Preferences</h3>
                    
                    <form method="post" action="profile.php">
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <div class="space-y-4 mb-8">
                            <div class="flex items-center justify-between p-4 bg-white/10 rounded-lg">
                                <div>
                                    <h4 class="font-medium">Newsletter</h4>
                                    <p class="text-sm opacity-70">Receive our weekly newsletter with deals and updates</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="newsletter" class="sr-only peer" <?= $preferences['newsletter'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500/50"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-white/10 rounded-lg">
                                <div>
                                    <h4 class="font-medium">Order Updates</h4>
                                    <p class="text-sm opacity-70">Receive notifications about your orders and shipping</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="order_updates" class="sr-only peer" <?= $preferences['order_updates'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500/50"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-white/10 rounded-lg">
                                <div>
                                    <h4 class="font-medium">Promotional Emails</h4>
                                    <p class="text-sm opacity-70">Receive special offers, discounts, and marketing emails</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="promotional" class="sr-only peer" <?= $preferences['promotional'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500/50"></div>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="px-6 py-2 bg-white/20 hover:bg-white/30 transition rounded-lg">
                            Save Preferences
                        </button>
                    </form>
                </div>
            <?php elseif ($activeTab === 'privacy'): ?>
                <!-- Privacy Tab Content -->
                <div class="glass-card p-6 rounded-xl mb-8">
                    <h3 class="text-xl font-semibold heading-font mb-6">Privacy Settings</h3>
                    
                    <div class="mb-8 p-5 bg-white/10 rounded-lg">
                        <h4 class="font-medium mb-3">Data Usage</h4>
                        <p class="text-sm opacity-70 mb-4">
                            We collect data to improve your shopping experience. Your data is never sold to third parties and is used 
                            in accordance with our <a href="../privacy-policy.php" class="underline">Privacy Policy</a>.
                        </p>
                        
                        <button type="button" class="px-4 py-2 bg-white/20 hover:bg-white/30 transition rounded text-sm">
                            Request My Data
                        </button>
                    </div>
                    
                    <div class="mb-8 p-5 bg-white/10 rounded-lg">
                        <h4 class="font-medium mb-3">Account Deletion</h4>
                        <p class="text-sm opacity-70 mb-4">
                            Deleting your account will remove all your personal information, orders, addresses, and preferences from our system.
                            This action cannot be undone.
                        </p>
                        
                        <button type="button" onclick="confirmDeletion()" class="px-4 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-300 transition rounded text-sm">
                            Delete My Account
                        </button>
                    </div>
                    
                    <div class="text-sm opacity-70">
                        <p class="mb-3">For questions regarding your privacy or data, please contact our support team:</p>
                        <p><a href="mailto:privacy@axiom.com" class="underline">privacy@axiom.com</a></p>
                    </div>
                </div>
                
                <!-- Confirmation modal (hidden by default) -->
                <div id="deletionModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
                    <div class="glass-card p-6 rounded-xl max-w-md w-full mx-4">
                        <h3 class="text-xl font-bold mb-4 heading-font">Confirm Account Deletion</h3>
                        <p class="mb-6 opacity-80">
                            Are you sure you want to delete your account? This will permanently remove all your data and cannot be undone.
                        </p>
                        
                        <div class="flex gap-4 justify-end">
                            <button onclick="closeModal()" class="px-4 py-2 bg-white/20 hover:bg-white/30 transition rounded">
                                Cancel
                            </button>
                            <form action="delete_account.php" method="post">
                                <button type="submit" class="px-4 py-2 bg-red-500/30 hover:bg-red-500/40 text-red-200 transition rounded">
                                    Delete Permanently
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // For file upload display
        document.getElementById('avatar').addEventListener('change', function() {
            // to show the selected filename here if desired
        });
        
        // Account deletion modal
        function confirmDeletion() {
            document.getElementById('deletionModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('deletionModal').classList.add('hidden');
        }
    </script>
</body>
</html>