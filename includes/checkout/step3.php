<?php
// Store payment info in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $_SESSION['checkout'] = $_SESSION['checkout'] ?? [];
    $_SESSION['checkout']['payment'] = [
        'method' => $_POST['payment_method']
    ];
    
    // Store credit card info (in a real app, you'd never store full card details)
    if ($_POST['payment_method'] === 'credit_card') {
        $_SESSION['checkout']['payment']['card_last4'] = substr($_POST['card_number'], -4);
        $_SESSION['checkout']['payment']['card_expiry'] = $_POST['expiry_date'];
        $_SESSION['checkout']['payment']['cardholder'] = $_POST['card_holder'];
    }
    
    // Store billing info if different from shipping
    if (!isset($_POST['billing_same']) || $_POST['billing_same'] !== 'on') {
        $_SESSION['checkout']['billing'] = [
            'first_name' => $_POST['billing_first_name'],
            'last_name' => $_POST['billing_last_name'],
            'address' => $_POST['billing_address1'],
            'address2' => $_POST['billing_address2'] ?? '',
            'city' => $_POST['billing_city'],
            'state' => $_POST['billing_state'],
            'zip' => $_POST['billing_zip'],
            'country' => $_POST['billing_country']
        ];
    }
}

// Validate that shipping and payment info exists
if (!isset($_SESSION['checkout']['shipping']) || !isset($_SESSION['checkout']['payment'])) {
    header('Location: checkout.php?step=1');
    exit;
}

// Calculate order totals
$subtotal = 0;
$items = [];

global $pdo;

if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
    foreach ($_SESSION['cart'] as $product_id => $cartItem) {
        // Get product details
        $product = get_product_by_id($pdo, $product_id);
        if ($product) {
            $quantity = $cartItem['quantity'];
            $item_total = $product['price'] * $quantity;
            $subtotal += $item_total;
            $items[] = [
                'id' => $product_id,
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'total' => $item_total
            ];
        }
    }
}

// Calculate final costs
$tax_rate = 0.02; 
$tax = $subtotal * $tax_rate;
$shipping_cost = 5.99;
$total = $subtotal + $tax + $shipping_cost;

// Store order totals in session
$_SESSION['checkout']['totals'] = [
    'subtotal' => $subtotal,
    'tax_rate' => $tax_rate,
    'tax' => $tax,
    'shipping' => $shipping_cost,
    'total' => $total
];

// Store items in session
$_SESSION['checkout']['items'] = $items;
?>

<div class="card mb-4">
    <div class="card-header">
        <h3>Review Your Order</h3>
    </div>
    <div class="card-body">
        <div class="row mb-5">
            <div class="col-md-6">
                <h4>Shipping Information</h4>
                <div class="card mb-3">
                    <div class="card-body">
                        <?php
                        $shipping = $_SESSION['checkout']['shipping'];
                        echo '<p class="mb-1"><strong>' . $shipping['first_name'] . ' ' . $shipping['last_name'] . '</strong></p>';
                        echo '<p class="mb-1">' . $shipping['address'];
                        if (!empty($shipping['address2'])) {
                            echo '<br>' . $shipping['address2'];
                        }
                        echo '</p>';
                        echo '<p class="mb-1">' . $shipping['city'] . ', ' . $shipping['state'] . ' ' . $shipping['zip'] . '</p>';
                        echo '<p class="mb-1">' . $shipping['country'] . '</p>';
                        echo '<p class="mb-1">Phone: ' . $shipping['phone'] . '</p>';
                        echo '<p class="mb-0">Email: ' . $shipping['email'] . '</p>';
                        ?>
                        <a href="checkout.php?step=1" class="btn btn-sm btn-outline-secondary mt-2">Edit</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <h4>Payment Method</h4>
                <div class="card mb-3">
                    <div class="card-body">
                        <?php
                        $payment = $_SESSION['checkout']['payment'];
                        
                        if ($payment['method'] === 'credit_card') {
                            echo '<p class="mb-1"><strong>Credit Card</strong></p>';
                            echo '<p class="mb-1">Card ending in ' . $payment['card_last4'] . '</p>';
                            echo '<p class="mb-1">Expires: ' . $payment['card_expiry'] . '</p>';
                            echo '<p class="mb-0">Cardholder: ' . $payment['cardholder'] . '</p>';
                        } else if ($payment['method'] === 'paypal') {
                            echo '<p class="mb-0"><strong>PayPal</strong></p>';
                            echo '<p class="mb-0">You will be redirected to PayPal to complete your payment.</p>';
                        }
                        ?>
                        <a href="checkout.php?step=2" class="btn btn-sm btn-outline-secondary mt-2">Edit</a>
                    </div>
                </div>
            </div>
            <div  class="col-md-6">    
                <h4>Billing Address</h4>
                <div class="card">
                    <div class="card-body">
                        <?php
                        // Use billing address if different, otherwise use shipping address
                        $billing = isset($_SESSION['checkout']['billing']) ? $_SESSION['checkout']['billing'] : $_SESSION['checkout']['shipping'];
                        
                        echo '<p class="mb-1"><strong>' . $billing['first_name'] . ' ' . $billing['last_name'] . '</strong></p>';
                        echo '<p class="mb-1">' . $billing['address'];
                        if (!empty($billing['address2'])) {
                            echo '<br>' . $billing['address2'];
                        }
                        echo '</p>';
                        echo '<p class="mb-1">' . $billing['city'] . ', ' . $billing['state'] . ' ' . $billing['zip'] . '</p>';
                        echo '<p class="mb-0">' . $billing['country'] . '</p>';
                        
                        if (!isset($_SESSION['checkout']['billing'])) {
                            echo '<p class="text-muted mt-2 mb-0"><small>Same as shipping address</small></p>';
                        } else {
                            echo '<a href="checkout.php?step=2" class="btn btn-sm btn-outline-secondary mt-2">Edit</a>';
                        }
                        ?>
                    </div>
                </div>          
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-12">
                <h4>Order Items</h4>
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
                                <td class="text-end">$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Tax (<?php echo $tax_rate * 100; ?>%):</th>
                                <td class="text-end">$<?php echo number_format($tax, 2); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Shipping:</th>
                                <td class="text-end">$<?php echo number_format($shipping_cost, 2); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <td class="text-end"><strong>$<?php echo number_format($total, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h4>Terms and Conditions</h4>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="terms_agree" required>
                <label class="form-check-label" for="terms_agree">
                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a>
                </label>
            </div>
        </div>
        
        <div class="d-flex justify-content-between mt-4">
            <a href="checkout.php?step=2" class="btn btn-outline-secondary">Back to Payment</a>
            <form action="checkout.php?step=4" method="post" id="place-order-form">
                <input type="hidden" name="place_order" value="1">
                <button type="submit" class="btn btn-primary btn-lg" id="place-order-btn" disabled>Place Order</button>
            </form>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>1. Acceptance of Terms</h5>
                <p>By placing an order, you agree to these terms and conditions.</p>
                
                <h5>2. Payment</h5>
                <p>All payments are processed securely. We do not store your full credit card details.</p>
                
                <h5>3. Shipping and Delivery</h5>
                <p>Orders are typically processed within 1-2 business days. Delivery times vary by location.</p>
                
                <h5>4. Returns and Refunds</h5>
                <p>You may return items within 30 days of receipt for a full refund.</p>
                
                <h5>5. Privacy Policy</h5>
                <p>We collect only necessary information to process your order and improve your shopping experience.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    
    /* Fix table text color for light theme */
    .table {
        color: #333 !important;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        width: 100%;
        background-color: white;
    }
    
    .table td, 
    .table th {
        text-align: left;
        vertical-align: middle;
    }

        /* Fix text alignment in price columns */
    .table td:nth-child(2),
    .table td:nth-child(4),
    .table th:nth-child(2),
    .table th:nth-child(4),
    .table tfoot td,
    .table tfoot th.text-end {
        text-align: right;
    }

    /* Improve spacing in table cells */
    .table td, .table th {
        padding: 0.75rem 1rem;
    }

    /* Fix responsive table on mobile */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1rem;
    }

    .table thead {
        background-color: rgba(87, 75, 144, 0.1);
    }
    
    .table thead th {
        border-bottom: none;
        font-weight: 600;
        color: #574b90;
        padding: 1rem;
    }
    
    .table tbody td, .table tfoot th, .table tfoot td {
        padding: 0.8rem 1rem;
        vertical-align: middle;
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    
    .table tfoot {
        background-color: rgba(0,0,0,0.02);
        font-weight: 500;
    }
    
    .table tfoot tr:last-child {
        font-weight: 700;
        color: #574b90;
    }
    
    /* Address card enhancements */
    .col-md-6 h4 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 1rem 0 0.75rem;
        color: #333;
    }
    
    /* Terms and conditions section */
    .form-check-label {
        font-size: 0.95rem;
    }
    
    .form-check-label a {
        color: #574b90;
        text-decoration: none;
        border-bottom: 1px dotted #574b90;
    }
    
    .form-check-label a:hover {
        border-bottom: 1px solid #574b90;
    }
    
    /* Enhance place order button */
    #place-order-btn {
        font-size: 1.1rem;
        padding: 0.75rem 2rem;
        background: linear-gradient(45deg, #574b90, #786fa6);
        border: none;
    }
    
    #place-order-btn:disabled {
        background: linear-gradient(45deg, #9c96ad, #b4b0c5);
        opacity: 0.7;
    }
    
    #place-order-btn:not(:disabled):hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(87, 75, 144, 0.4);
    }
    
    /* Modal enhancements */
    .modal-content {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .modal-header {
        background-color: #574b90;
        color: white;
        border-bottom: none;
    }
    
    .modal-header .modal-title {
        font-weight: 600;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    
    .modal-body h5 {
        color: #574b90;
        margin-top: 1.5rem;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .modal-body h5:first-child {
        margin-top: 0.5rem;
    }
    
    .modal-body p {
        color: #555;
        margin-bottom: 1.2rem;
    }
    
    /* Fix card info display */
    .card p.mb-0, .card p.mb-1 {
        line-height: 1.5;
        margin-bottom: 0.4rem !important;
    }
    
    .card p strong {
        color: #574b90;
    }
    
    /* Fix summary card height issue */
    .col-md-6 .card {
        height: calc(100% - 45px);
        margin-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
    }
    
    .col-md-6 .card .card-body {
        flex: 1;
    }
    
    /* Address section styling */
    .col-md-6 .card .btn-sm {
        margin-top: 1rem;
        padding: 0.25rem 0.75rem;
    }
    
    /* Order review card header */
    .card-header h3 {
        color: white !important;
    }

        /* Add these to your step3.php style section */
    .card-body .row + .row {
        margin-top: 3rem !important;
        border-top: 1px solid rgba(0,0,0,0.05);
        padding-top: 3rem !important;
    }
    
    .table-responsive {
        margin-top: 1rem;
        border-radius: 8px;
        overflow: hidden;
    }
    
    @media (max-width: 767px) {
        .col-md-6 + .col-md-6 {
            margin-top: 2rem;
        }
    }

        /* Add more space to information cards */
    .col-md-6 .card {
        margin-bottom: 2rem !important;
    }

    /* Add visual separation for the order items section */
    .row.mt-5 {
        position: relative;
        clear: both;
        padding-top: 1.5rem;
    }

    /* Add top margin to the h4 for Order Items */
    .row.mt-5 h4 {
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
    }


    /* Fix modal visibility issues */
    .modal {
        z-index: 1055 !important; /* Ensure modal is above other elements */
    }
    
    .modal-backdrop {
        z-index: 1050 !important;
        opacity: 0.5 !important;
        background-color: rgba(0, 0, 0, 0.5) !important;
    }
    
    .modal-dialog {
        z-index: 1056 !important;
        max-width: 600px;
        margin: 1.75rem auto;
    }
    
    .modal-content {
        background-color: white !important;
        color: #333 !important;
        border: none;
        border-radius: 12px !important;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5) !important;
        position: relative;
    }
    
    .modal-header {
        background-color: #574b90 !important;
        color: white !important;
        border-bottom: none !important;
    }
    
    .modal-title {
        color: white !important;
    }
    
    .modal-body {
        color: #333 !important;
        background-color: white !important;
    }
    
    .modal-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.1) !important;
        background-color: white !important;
    }
    
    /* Ensure close button is visible */
    .btn-close {
        opacity: 1 !important;
    }

        /* Make buttons larger on mobile */
    @media (max-width: 576px) {
      .btn, button, .nav-link, a.product-link {
        min-height: 44px;
        min-width: 44px;
        padding: 12px 16px;
      }
    }

</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable/disable place order button based on terms checkbox
    const termsCheckbox = document.getElementById('terms_agree');
    const placeOrderBtn = document.getElementById('place-order-btn');
    
    termsCheckbox.addEventListener('change', function() {
        placeOrderBtn.disabled = !this.checked;
    });
    
    // Validate form on submission
    const placeOrderForm = document.getElementById('place-order-form');
    
    placeOrderForm.addEventListener('submit', function(e) {
        if (!termsCheckbox.checked) {
            e.preventDefault();
            alert('Please agree to the terms and conditions to continue.');
        }
    });
});


document.addEventListener('DOMContentLoaded', function() {
    // Your other code...
    
    // Fix modal implementation
    const termsLinks = document.querySelectorAll('[data-bs-target="#termsModal"]');
    
    // First ensure bootstrap is available
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JS is not loaded properly');
        
        // Fallback to basic functionality if bootstrap is not available
        termsLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('termsModal').style.display = 'block';
            });
        });
        
        // Simple close buttons
        document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('termsModal').style.display = 'none';
            });
        });
    } else {
        // Use Bootstrap modal if available
        termsLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
                termsModal.show();
            });
        });
    }
});
</script>