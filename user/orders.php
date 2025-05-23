<?php
session_start();
require_once '../config/connect_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=user/orders.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Your Orders - Axiom</title>
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
        
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
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
                <h2 class="text-3xl font-bold heading-font">Your Orders</h2>
                
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
                            <div class="font-semibold"><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                            <div class="text-xs text-white/70">
                                <?= !empty($user['created_at']) ? 'Member since ' . date('M Y', strtotime($user['created_at'])) : '' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Orders Content -->
            <div class="glass-card rounded-xl p-6">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-12">
                        <div class="text-white/40 mb-4"><i class="fas fa-shopping-bag text-5xl"></i></div>
                        <p class="text-xl mb-4">You haven't placed any orders yet.</p>
                        <a href="../shop.php" class="inline-block bg-white/10 hover:bg-white/20 transition text-white py-3 px-6 rounded-lg">
                            Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($orders as $order): ?>
                            <div class="glass-card card-hover rounded-lg overflow-hidden border border-white/10">
                                <div class="bg-black/20 p-4 md:p-5 flex flex-col md:flex-row justify-between items-start md:items-center">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-lg font-medium">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h3>
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
                                        <p class="text-sm text-white/60 mt-1">
                                            Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="mt-3 md:mt-0 text-right">
                                        <div class="text-lg font-semibold">$<?= number_format($order['total_amount'], 2) ?></div>
                                        <p class="text-sm text-white/60"><?= $order['item_count'] ?> item(s)</p>
                                    </div>
                                </div>
                                <div class="p-4 md:p-5 flex justify-between items-center">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                                            <i class="fas fa-box text-white/70"></i>
                                        </div>
                                        <span class="text-sm text-white/70">
                                            <?php if ($order['status'] == 'completed'): ?>
                                                Delivered
                                            <?php elseif ($order['status'] == 'cancelled'): ?>
                                                Order cancelled
                                            <?php elseif ($order['status'] == 'processing'): ?>
                                                Processing order
                                            <?php elseif ($order['status'] == 'pending'): ?>
                                                Awaiting processing
                                            <?php elseif ($order['status'] == 'shipped'): ?>
                                                Out for delivery
                                            <?php else: ?>
                                                Order status: <?= ucfirst($order['status']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <a href="order_details.php?id=<?= $order['id'] ?>" class="px-4 py-2 bg-white/10 hover:bg-white/20 transition rounded-lg flex items-center gap-2">
                                        View Details
                                        <i class="fas fa-arrow-right text-xs"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>