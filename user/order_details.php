
<?php
session_start();
require_once '../config/connect_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=user/orders.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int) $_GET['id'];

// Get order details
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// If order doesn't exist or doesn't belong to user
if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Set page variables
$current_page = 'orders';
$base_url = '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order['order_number'] ?> - Axiom</title>
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
            background-size: 20px 20px;
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
                
                <a href="orders.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
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
                <div>
                    <a href="orders.php" class="text-white/70 hover:text-white flex items-center gap-2 mb-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Orders</span>
                    </a>
                    <h2 class="text-3xl font-bold heading-font">Order Details</h2>
                </div>
                
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
            
            <!-- Order Details Content -->
            <div class="glass-card rounded-xl p-6">
                <div class="flex flex-col md:flex-row justify-between mb-6 pb-6 border-b border-white/10">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-xl font-bold"><?= $order['order_number'] ?></h3>
                            <?php 
                                $statusClass = 'bg-gray-400/20';
                                if ($order['status'] == 'processing') {
                                    $statusClass = 'bg-blue-400/20 text-blue-300';
                                } elseif ($order['status'] == 'shipped') {
                                    $statusClass = 'bg-amber-400/20 text-amber-300';
                                } elseif ($order['status'] == 'completed') {
                                    $statusClass = 'bg-green-400/20 text-green-300';
                                } elseif ($order['status'] == 'pending') {
                                    $statusClass = 'bg-yellow-400/20 text-yellow-300';
                                } elseif ($order['status'] == 'cancelled') {
                                    $statusClass = 'bg-red-400/20 text-red-300';
                                }
                            ?>
                            <span class="px-3 py-1 rounded-full <?= $statusClass ?> text-xs uppercase font-medium">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>
                        <p class="text-white/60 mt-1">
                            Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                        </p>
                    </div>
                    <div class="mt-3 md:mt-0">
                        <div class="text-xl font-semibold">$<?= number_format($order['total_amount'], 2) ?></div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <h4 class="text-lg font-semibold mb-4">Items in your order</h4>
                <div class="space-y-4 mb-8">
                    <?php foreach ($order_items as $item): ?>
                    <div class="flex items-center gap-4 p-4 bg-white/5 rounded-lg">
                        <div class="w-16 h-16 bg-white/10 rounded-md overflow-hidden flex-shrink-0">
                            <?php if (!empty($item['image'])): ?>
                            <img src="../<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-box text-white/30"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <h5 class="font-medium"><?= htmlspecialchars($item['name']) ?></h5>
                            <p class="text-sm text-white/60">Qty: <?= $item['quantity'] ?></p>
                        </div>
                        <div class="text-right">
                            <div class="font-medium">$<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                            <p class="text-sm text-white/60">$<?= number_format($item['price'], 2) ?> each</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Order Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-lg font-semibold mb-4">Shipping Address</h4>
                        <div class="bg-white/5 p-4 rounded-lg">
                            <p class="mb-1"><?= htmlspecialchars($order['shipping_name']) ?></p>
                            <p class="mb-1"><?= htmlspecialchars($order['shipping_address']) ?></p>
                            <p class="mb-1"><?= 
                                htmlspecialchars($order['shipping_city'] . ', ' . 
                                $order['shipping_state'] . ' ' . 
                                $order['shipping_zip']) 
                            ?></p>
                            <p class="mb-1"><?= htmlspecialchars($order['shipping_country']) ?></p>
                            <p class="mb-1">Phone: <?= htmlspecialchars($order['shipping_phone']) ?></p>
                            <p>Email: <?= htmlspecialchars($order['shipping_email']) ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-lg font-semibold mb-4">Payment Information</h4>
                        <div class="bg-white/5 p-4 rounded-lg">
                            <p class="mb-1">Payment Method: <?= ucfirst(htmlspecialchars($order['payment_method'])) ?></p>
                            <p class="mb-1">Order Status: <?= ucfirst(htmlspecialchars($order['status'])) ?></p>
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <div class="flex justify-between mb-1">
                                    <span class="text-white/60">Subtotal:</span>
                                    <span>$<?= number_format($order['total_amount'] * 0.9, 2) ?></span>
                                </div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-white/60">Tax:</span>
                                    <span>$<?= number_format($order['total_amount'] * 0.02, 2) ?></span>
                                </div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-white/60">Shipping:</span>
                                    <span>$<?= number_format($order['total_amount'] * 0.08, 2) ?></span>
                                </div>
                                <div class="flex justify-between mt-2 pt-2 border-t border-white/10 font-semibold">
                                    <span>Total:</span>
                                    <span>$<?= number_format($order['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>