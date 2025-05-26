<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use a try-catch to handle potential errors with database connection
try {
    require_once (file_exists('config/connect_db.php') ? 
        'config/connect_db.php' : 
        (file_exists('../config/connect_db.php') ? 
            '../config/connect_db.php' : ''));
} catch (Exception $e) {
    // Silently fail
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);

// Check for admin and supervisor roles
$is_admin = isset($_SESSION['admin_id']);
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] === true;

// Calculate correct paths based on current location
$in_user = strpos($_SERVER['PHP_SELF'], '/user/') !== false;
$in_admin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$in_subfolder = $in_user || $in_admin;

// Root path to get back to main directory
$root_path = $in_subfolder ? '../' : '';

// User and admin directory paths
$user_path = $in_user ? '' : ($root_path . 'user/');  
$admin_path = $in_admin ? '' : ($root_path . 'admin/');

require_once (file_exists('api/models/Cart.php') ? 
    'api/models/Cart.php' : 
    (file_exists('../api/models/Cart.php') ? 
        '../api/models/Cart.php' : ''));

$cart_count = 0;
if (isset($pdo)) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
    $cartModel = new Cart($pdo);
    $cartItems = $cartModel->getCartItems($userId);
    
    foreach ($cartItems as $item) {
        $cart_count += $item['quantity'];
    }
}

// Get unread notification counts for supervisors
$unread_reports = 0;
if ($is_supervisor && isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM customer_reports WHERE status = 'pending'");
        $unread_reports = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>

<!-- Sticky navigation bar -->
<div class="fixed bottom-4 right-12 z-50 rounded-full px-4 py-4 bg-[#0f172a] text-white shadow-lg flex items-center transition-all duration-300 hover:shadow-xl">
    <?php if ($isLoggedIn): ?>
        <!-- Logged in state: show icons -->
        <div class="flex items-center gap-3">
            <?php if ($is_supervisor): ?>
                <!-- SUPERVISOR NAVIGATION -->
                <a href="<?php echo $admin_path; ?>dashboard.php" class="p-2 hover:text-indigo-300 transition-colors" title="Supervisor Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </a>
                
                <a href="<?php echo $admin_path; ?>customer_reports.php" class="p-2 hover:text-orange-300 transition-colors relative" title="Customer Reports">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <?php if ($unread_reports > 0): ?>
                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full"><?php echo $unread_reports; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="<?php echo $admin_path; ?>admin_management.php" class="p-2 hover:text-purple-300 transition-colors" title="Admin Management">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </a>
                
                <a href="<?php echo $admin_path; ?>admin_logs.php" class="p-2 hover:text-blue-300 transition-colors" title="Activity Logs">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </a>
            <?php elseif ($is_admin): ?>
                <!-- ADMIN NAVIGATION -->
                <a href="<?php echo $admin_path; ?>dashboard.php" class="p-2 hover:text-green-200 transition-colors" title="Admin Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </a>
                <a href="<?php echo $admin_path; ?>shop-management.php" class="p-2 hover:text-green-200 transition-colors" title="Shop Management">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </a>
                
                <a href="<?php echo $admin_path; ?>customers.php" class="p-2 hover:text-green-200 transition-colors" title="Customers">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </a>
            <?php else: ?>
                <!-- REGULAR USER NAVIGATION -->
                <a href="<?php echo $user_path; ?>account.php" class="p-2 hover:text-green-200 transition-colors" title="Account">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </a>
                
                <!-- Wishlist link -->
                <a href="<?php echo $user_path; ?>wishlist.php" class="p-2 hover:text-green-200 transition-colors" title="Wishlist">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </a>
                
                <!-- Cart is in root directory -->
                <a href="<?php echo $root_path; ?>cart.php" class="p-2 hover:text-green-200 transition-colors relative" title="Cart">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span id="cart-count-badge" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full <?php echo ($cart_count == 0) ? 'hidden' : ''; ?>"><?php echo $cart_count; ?></span>
                </a>
            <?php endif; ?>

            <!-- Chat is in root directory - available to all users -->
            <a href="<?php echo $root_path; ?>messages.php" class="p-2 hover:text-green-200 transition-colors" title="Chat">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </a>
            
            <!-- Logout is in auth directory - available to all users -->
            <a href="<?php echo $root_path; ?>auth/logout.php" class="p-2 hover:text-red-300 transition-colors" title="Logout">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </a>
        </div>
    <?php else: ?>
        <!-- Logged out state: show sign in link -->
        <a href="<?php echo $root_path; ?>auth/login.php" class="flex items-center gap-2 hover:text-green-200 transition-colors">
            <span>Sign In</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
        </a>
    <?php endif; ?>
</div>