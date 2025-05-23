<?php
// Check if order was placed
if (!isset($_POST['place_order']) || $_POST['place_order'] !== '1') {
    // If someone tries to access this page directly without placing an order, redirect to step 1
    header('Location: checkout.php?step=1');
    exit;
}



// In a real application, you would:
// 1. Process payment
// 2. Save order to database
// 3. Send confirmation emails
// 4. Clear cart

// Get order information from session
$shipping = $_SESSION['checkout']['shipping'] ?? [];
$payment = $_SESSION['checkout']['payment'] ?? [];
$items = $_SESSION['checkout']['items'] ?? [];
$totals = $_SESSION['checkout']['totals'] ?? [];


// Generate order number
$order_number = 'ORD-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
global $pdo;

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get user ID if logged in, otherwise null for guest checkout
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Insert order into database
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, status, 
            shipping_name, shipping_address, shipping_city, 
            shipping_state, shipping_zip, shipping_country, 
            shipping_phone, shipping_email, 
            payment_method, total_amount, created_at
        ) VALUES (
            ?, ?, 'pending',
            ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?, NOW()
        )
    ");

    $stmt->execute([
        $order_number,
        $user_id,
        $shipping['first_name'] . ' ' . $shipping['last_name'],
        $shipping['address'],
        $shipping['city'],
        $shipping['state'],
        $shipping['zip'],
        $shipping['country'],
        $shipping['phone'],
        $shipping['email'],
        $payment['method'],
        $totals['total']
    ]);

    // Get the last inserted order ID
    $order_id = $pdo->lastInsertId();

    // Insert order items into database
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $stmt->execute([
            $order_id,
            $item['id'],
            $item['quantity'],
            $item['price']
        ]);
    }
    // Commit transaction
    $pdo->commit();

    $_SESSION['last_order'] = [
        'order_number' => $order_number,
        'order_date' => date('Y-m-d H:i:s'),
        'shipping' => $shipping,
        'payment' => $payment,
        'items' => $items,
        'totals' => $totals
    ];

    // Clear the cart$
    $_SESSION['cart'] = [];
    unset($_SESSION['checkout']);

} catch (Exception $e) {
    // Rollback transaction in case of error
    $pdo->rollBack();

    // Log the error message
    error_log("Order placement error: " . $e->getMessage());
    // Redirect to an error page or show an error message
    $_SESSION['checkout_error'] = 'An error occurred while processing your order. Please try again.';
    header('Location: checkout.php?error=order_failed');
    exit;
}
?>

<div class="text-center mb-5">
    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
    <h2 class="mt-3">Thank You for Your Order!</h2>
    <p class="lead">Your order has been placed successfully.</p>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Order #<?php echo $order_number; ?></h3>
                    <span class="text-muted"><?php echo date('F j, Y, g:i a'); ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h4>Shipping Information</h4>
                        <address>
                            <strong><?php echo $shipping['first_name'] . ' ' . $shipping['last_name']; ?></strong><br>
                            <?php echo $shipping['address']; ?><br>
                            <?php if (!empty($shipping['address2'])): ?>
                                <?php echo $shipping['address2']; ?><br>
                            <?php endif; ?>
                            <?php echo $shipping['city'] . ', ' . $shipping['state'] . ' ' . $shipping['zip']; ?><br>
                            <?php echo $shipping['country']; ?><br>
                            Phone: <?php echo $shipping['phone']; ?><br>
                            Email: <?php echo $shipping['email']; ?>
                        </address>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <h4>Payment Method</h4>
                        <p>
                            <?php if ($payment['method'] === 'credit_card'): ?>
                                <strong>Credit Card</strong><br>
                                Card ending in <?php echo $payment['card_last4']; ?><br>
                                Expires: <?php echo $payment['card_expiry']; ?><br>
                                Cardholder: <?php echo $payment['cardholder']; ?>
                            <?php elseif ($payment['method'] === 'paypal'): ?>
                                <strong>PayPal</strong><br>
                                Payment processed through PayPal
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <h4>Order Summary</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td class="text-end">$<?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Subtotal:</th>
                                <td class="text-end">$<?php echo number_format($totals['subtotal'], 2); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Tax (<?php echo $totals['tax_rate'] * 100; ?>%):</th>
                                <td class="text-end">$<?php echo number_format($totals['tax'], 2); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Shipping:</th>
                                <td class="text-end">$<?php echo number_format($totals['shipping'], 2); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <td class="text-end"><strong>$<?php echo number_format($totals['total'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">What's Next?</h4>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li class="mb-2">You'll receive an order confirmation email at <strong><?php echo $shipping['email']; ?></strong></li>
                    <li class="mb-2">We'll process your order and send a shipping confirmation when your items are on the way</li>
                    <li class="mb-2">You can track your order status in your account dashboard</li>
                    <li>If you have any questions, please contact our customer service team</li>
                </ol>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="shop.php" class="btn btn-primary me-2">Continue Shopping</a>
            <a href="user/account.php?view=orders" class="btn btn-outline-secondary">View My Orders</a>
        </div>
    </div>
</div>

<script>
// Remove the beforeunload event warning since the checkout is complete
document.addEventListener('DOMContentLoaded', function() {
    window.onbeforeunload = null;
});
</script>