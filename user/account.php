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

// Get user order stats
$stmt = $pdo->prepare("SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                        COALESCE(SUM(total_amount), 0) as total_spent
                    FROM orders 
                    WHERE user_id = ?");
$stmt->execute([$user_id]);
$orderStats = $stmt->fetch();

// Get recent orders
$stmt = $pdo->prepare("SELECT o.*, 
                        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                      FROM orders o 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT 5");
$stmt->execute([$user_id]);
$recentOrders = $stmt->fetchAll();

// Get user addresses
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ?");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Get saved/favorite products
$stmt = $pdo->prepare("SELECT p.* FROM products p 
                      JOIN wishlist w ON p.id = w.product_id 
                      WHERE w.user_id = ? 
                      LIMIT 4");
$stmt->execute([$user_id]);
$favoriteProducts = $stmt->fetchAll();

// Check if user has an active wishlist
$stmt = $pdo->prepare("SELECT COUNT(*) as wishlist_count FROM wishlist WHERE user_id = ?");
$stmt->execute([$user_id]);
$wishlistCount = (int)$stmt->fetchColumn();

// Add database wishlist items if user is logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    try {
        // Check if the table exists first
        $tableExists = $pdo->query("SHOW TABLES LIKE 'user_wishlist'")->rowCount() > 0;
        
        if ($tableExists) {
            // Get count from database
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_wishlist WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $db_wishlist_count = (int)$stmt->fetchColumn();
            
            // Only count unique items (some might be both in session and DB)
            if (isset($_SESSION['wishlist']) && count($_SESSION['wishlist']) > 0) {
                // Get the actual product IDs from database
                $stmt = $pdo->prepare("SELECT product_id FROM user_wishlist WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $db_product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Count unique IDs
                $all_wishlist_items = array_unique(array_merge($_SESSION['wishlist'], $db_product_ids));
                $wishlist_count = count($all_wishlist_items);
            } else {
                $wishlist_count = $db_wishlist_count;
            }
        }
    } catch (PDOException $e) {
        // If error, just use session count
        error_log("Wishlist count error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Axiom</title>
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
        
        .badge {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
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
                <a href="account.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                
                <a href="profile.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <h2 class="text-3xl font-bold heading-font">My Account</h2>
                
                <div class="flex items-center gap-6">
                    
                    
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center overflow-hidden">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Profile picture"  class="w-full h-full object-cover">
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
            
            <!-- Welcome Section -->
            <div class="glass-card p-6 rounded-xl mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold mb-2 heading-font">Welcome back, <?= htmlspecialchars($user['first_name'] ?? $user['username']) ?>!</h3>
                        <p class="opacity-70">Here's an overview of your account and recent activity</p>
                    </div>
                    <a href="profile.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 transition rounded-lg flex items-center gap-2">
                        <i class="fas fa-pen"></i>
                        <span>Edit Profile</span>
                    </a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shopping-bag text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Total Orders</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($orderStats['total_orders'] ?? 0) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-clipboard mr-1"></i> Orders placed
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Processing</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($orderStats['processing_orders'] ?? 0) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-clock mr-1"></i> Orders in progress
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Completed</div>
                    </div>
                    <div class="text-3xl font-bold mb-1"><?= number_format($orderStats['completed_orders'] ?? 0) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-check mr-1"></i> Delivered orders
                    </div>
                </div>
                
                <div class="glass-card p-6 rounded-xl stat-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="card-icon w-12 h-12 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-xl"></i>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-wider opacity-70">Total Spent</div>
                    </div>
                    <div class="text-3xl font-bold mb-1">$<?= number_format($orderStats['total_spent'] ?? 0, 2) ?></div>
                    <div class="text-sm opacity-70 flex items-center">
                        <i class="fas fa-chart-line mr-1"></i> All time purchases
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders Section -->
            <div class="glass-card p-6 rounded-xl mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold heading-font">Recent Orders</h3>
                    <a href="orders.php" class="text-sm opacity-70 hover:opacity-100 transition flex items-center gap-1">
                        View All Orders
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left border-b border-white/10">
                                <th class="pb-3 font-medium opacity-70">Order #</th>
                                <th class="pb-3 font-medium opacity-70">Date</th>
                                <th class="pb-3 font-medium opacity-70">Items</th>
                                <th class="pb-3 font-medium opacity-70">Total</th>
                                <th class="pb-3 font-medium opacity-70">Status</th>
                                <th class="pb-3 font-medium opacity-70">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" class="py-4 text-center opacity-70">No orders placed yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr class="border-b border-white/5">
                                        <td class="py-4 opacity-80">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                        <td class="py-4 opacity-80"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                        <td class="py-4 opacity-80"><?= $order['item_count'] ?> item(s)</td>
                                        <td class="py-4 opacity-80">$<?= number_format($order['total_amount'], 2) ?></td>
                                        <td class="py-4">
                                            <?php
                                                $statusClass = 'bg-gray-400/20';
                                                if ($order['status'] == 'processing') {
                                                    $statusClass = 'bg-blue-400/20 text-blue-300';
                                                } elseif ($order['status'] == 'shipped') {
                                                    $statusClass = 'bg-amber-400/20 text-amber-300';
                                                } elseif ($order['status'] == 'completed') {
                                                    $statusClass = 'bg-green-400/20 text-green-300';
                                                } elseif ($order['status'] == 'cancelled') {
                                                    $statusClass = 'bg-red-400/20 text-red-300';
                                                }
                                            ?>
                                            <span class="px-3 py-1 rounded-full <?= $statusClass ?> text-xs uppercase font-medium">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4">
                                            <a href="order_details.php?id=<?= $order['id'] ?>" class="text-sm underline">
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
            
            <!-- Address Book and Favorites Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Address Book -->
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold heading-font">My Addresses</h3>
                        <a href="addresses.php" class="text-sm opacity-70 hover:opacity-100 transition flex items-center gap-1">
                            Manage Addresses
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($addresses)): ?>
                        <div class="text-center py-8 opacity-70">
                            <i class="fas fa-map-marker-alt text-3xl mb-3"></i>
                            <p>No addresses saved yet</p>
                            <a href="addresses.php?add=1" class="mt-3 px-4 py-2 bg-white/10 hover:bg-white/20 inline-block rounded-lg transition">
                                Add an Address
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach (array_slice($addresses, 0, 2) as $address): ?>
                                <div class="bg-white/10 p-4 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-semibold"><?= htmlspecialchars($address['full_name']) ?></span>
                                        <?php if ($address['is_default']): ?>
                                            <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm opacity-80">
                                        <?= htmlspecialchars($address['street_address']) ?><br>
                                        <?php if (!empty($address['street_address_2'])): ?>
                                            <?= htmlspecialchars($address['street_address_2']) ?><br>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['zip_code']) ?><br>
                                        <?= htmlspecialchars($address['country']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($addresses) > 2): ?>
                                <div class="text-center text-sm opacity-70 pt-2">
                                    +<?= count($addresses) - 2 ?> more address(es)
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                
                <!-- Favorites/Wishlist -->
                <div class="glass-card p-6 rounded-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold heading-font">My Favorites</h3>
                        <a href="wishlist.php" class="text-sm opacity-70 hover:opacity-100 transition flex items-center gap-1">
                            View Wishlist
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($favoriteProducts)): ?>
                        <div class="text-center py-8 opacity-70">
                            <i class="fas fa-heart text-3xl mb-3"></i>
                            <p>No favorite products yet</p>
                            <a href="../shop.php" class="mt-3 px-4 py-2 bg-white/10 hover:bg-white/20 inline-block rounded-lg transition">
                                Browse Products
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach ($favoriteProducts as $product): ?>
                                <div class="bg-white/10 rounded-lg overflow-hidden transition-all hover:bg-white/20">
                                    <div class="h-36 overflow-hidden relative">
                                        <?php 
                                        $imageUrl = !empty($product['image']) ? '../' . $product['image'] : 
                                                (!empty($product['image']) ? $product['image'] : '');
                                        ?>
                                        <?php if (!empty($imageUrl)): ?>
                                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-white/5">
                                                <i class="fas fa-image text-3xl opacity-50"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute top-2 right-2 bg-black/75 rounded-full p-1">
                                            <span class="badge px-2 py-1 rounded text-xs">
                                                $<?= number_format($product['price'], 2) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <h4 class="font-medium text-sm"><?= htmlspecialchars($product['name']) ?></h4>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($wishlistCount > 4): ?>
                            <div class="text-center text-sm opacity-70 pt-4">
                                +<?= $wishlistCount - 4 ?> more item(s)
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            
            </div>
        </div>
    </div>
</body>
</html>