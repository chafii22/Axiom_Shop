<?php
// Start session
session_start();

// Check for proper authorization
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['is_supervisor'])) {
    http_response_code(403);
    echo "<p class='text-red-400'>Unauthorized access</p>";
    exit();
}

// Include database connection
require_once '../../config/connect_db.php';

// Get customer ID from URL parameter
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    http_response_code(400);
    echo "<p class='text-red-400'>Invalid customer ID</p>";
    exit();
}

// Determine if user is supervisor
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] === true;

try {
    // Get basic customer information
    $stmt = $pdo->prepare("
        SELECT u.*, 
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id) as total_spent,
        (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date
        FROM users u 
        WHERE u.id = ? AND u.is_admin = 0
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo "<p class='text-red-400'>Customer not found</p>";
        exit();
    }
    
    // Get customer address information
    $stmt = $pdo->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC
    ");
    $stmt->execute([$customer_id]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get report information if supervisor
    $reports = [];
    if ($is_supervisor) {
        $stmt = $pdo->prepare("
            SELECT r.*, 
            (SELECT username FROM users WHERE id = r.reported_by) as reported_by_name,
            (SELECT username FROM users WHERE id = r.resolved_by) as resolved_by_name
            FROM customer_reports r
            WHERE r.customer_id = ?
            ORDER BY r.report_date DESC
        ");
        $stmt->execute([$customer_id]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, 
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$customer_id]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the output as HTML
    ?>
    
    <div class="grid grid-cols-1 gap-4">
        <!-- Basic Info -->
        <div class="border-b border-white/20 pb-4">
            <h4 class="font-semibold mb-2">Basic Information</h4>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <p class="text-xs text-white/60">Full Name</p>
                    <p><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Username</p>
                    <p><?= htmlspecialchars($customer['username']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Email</p>
                    <p><?= htmlspecialchars($customer['email']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Status</p>
                    <p>
                        <?php 
                        $status = isset($customer['status']) ? $customer['status'] : 'active';
                        $statusColor = 'text-green-400';
                        if ($status == 'blocked') {
                            $statusColor = 'text-red-400';
                        } elseif ($status == 'pending') {
                            $statusColor = 'text-yellow-400';
                        }
                        ?>
                        <span class="<?= $statusColor ?>"><?= ucfirst($status) ?></span>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Registered</p>
                    <p><?= date('M d, Y', strtotime($customer['created_at'])) ?></p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Last Login</p>
                    <p><?= isset($customer['last_login']) && $customer['last_login'] ? date('M d, Y', strtotime($customer['last_login'])) : 'Never' ?></p>
                </div>
            </div>
        </div>
        
        <!-- Purchase History -->
        <div class="border-b border-white/20 pb-4">
            <h4 class="font-semibold mb-2">Purchase History</h4>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <p class="text-xs text-white/60">Total Orders</p>
                    <p class="text-lg font-semibold"><?= $customer['order_count'] ?></p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Total Spent</p>
                    <p class="text-lg font-semibold">$<?= number_format($customer['total_spent'], 2) ?></p>
                </div>
                <div>
                    <p class="text-xs text-white/60">Last Order</p>
                    <p><?= $customer['last_order_date'] ? date('M d, Y', strtotime($customer['last_order_date'])) : 'Never' ?></p>
                </div>
            </div>
            
            <?php if (!empty($recent_orders)): ?>
            <h5 class="font-semibold mt-3 mb-2 text-sm">Recent Orders</h5>
            <div class="space-y-2 max-h-40 overflow-y-auto">
                <?php foreach ($recent_orders as $order): ?>
                <div class="flex items-center justify-between bg-white/5 p-2 rounded">
                    <div>
                        <div class="text-sm">#<?= $order['id'] ?></div>
                        <div class="text-xs text-white/60"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm">$<?= number_format($order['total_amount'], 2) ?></div>
                        <div class="text-xs text-white/60"><?= $order['item_count'] ?> items</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-white/60 mt-2">No orders found</p>
            <?php endif; ?>
        </div>
        
        <!-- Address Information -->
        <div class="border-b border-white/20 pb-4">
            <h4 class="font-semibold mb-2">Address Information</h4>
            <?php if (!empty($addresses)): ?>
            <div class="space-y-3 max-h-40 overflow-y-auto">
                <?php foreach ($addresses as $address): ?>
                <div class="bg-white/5 p-3 rounded">
                    <?php if ($address['is_default']): ?>
                    <div class="text-xs text-blue-400 mb-1">Default Address</div>
                    <?php endif; ?>
                    <p class="text-sm">
                        <?= htmlspecialchars($address['address_line1']) ?>
                        <?= $address['address_line2'] ? ', ' . htmlspecialchars($address['address_line2']) : '' ?>
                    </p>
                    <p class="text-sm">
                        <?= htmlspecialchars($address['city']) ?>, 
                        <?= htmlspecialchars($address['state']) ?> 
                        <?= htmlspecialchars($address['postal_code']) ?>
                    </p>
                    <p class="text-sm">
                        <?= htmlspecialchars($address['country']) ?>
                    </p>
                    <p class="text-xs text-white/60 mt-1">
                        Phone: <?= htmlspecialchars($address['phone']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-white/60">No addresses found</p>
            <?php endif; ?>
        </div>
        
        <?php if ($is_supervisor && !empty($reports)): ?>
        <!-- Reports (Supervisor Only) -->
        <div>
            <h4 class="font-semibold mb-2">Customer Reports</h4>
            <div class="space-y-3 max-h-60 overflow-y-auto">
                <?php foreach ($reports as $report): ?>
                <div class="bg-white/5 p-3 rounded">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-sm font-medium">
                                Reported by: <?= htmlspecialchars($report['reported_by_name']) ?>
                            </div>
                            <div class="text-xs text-white/60">
                                <?= date('M d, Y', strtotime($report['report_date'])) ?>
                            </div>
                        </div>
                        <div>
                            <?php 
                            $statusClass = 'bg-yellow-400/20 text-yellow-300';
                            $statusText = 'Pending';
                            
                            if ($report['status'] == 'resolved') {
                                $statusClass = 'bg-green-400/20 text-green-300';
                                $statusText = 'Resolved';
                            } elseif ($report['status'] == 'dismissed') {
                                $statusClass = 'bg-red-400/20 text-red-300';
                                $statusText = 'Dismissed';
                            } elseif ($report['status'] == 'block') {
                                $statusClass = 'bg-purple-400/20 text-purple-300';
                                $statusText = 'Blocked';
                            }
                            ?>
                            <span class="text-xs px-2 py-1 rounded <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>
                    </div>
                    <div class="mt-2 text-sm">
                        <?= nl2br(htmlspecialchars($report['report_reason'])) ?>
                    </div>
                    
                    <?php if ($report['status'] != 'pending'): ?>
                    <div class="mt-3 pt-2 border-t border-white/10">
                        <div class="text-xs text-white/60">
                            Resolved by: <?= htmlspecialchars($report['resolved_by_name']) ?> on 
                            <?= date('M d, Y', strtotime($report['resolved_date'])) ?>
                        </div>
                        <div class="mt-1 text-sm">
                            <?= nl2br(htmlspecialchars($report['resolution'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php
} catch (PDOException $e) {
    // Log the error, but show a generic message to the user
    error_log("Error in get_customer_details.php: " . $e->getMessage());
    echo "<p class='text-red-400'>An error occurred while retrieving customer details.</p>";
}
?>