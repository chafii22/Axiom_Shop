<?php
// Store shipping info in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $_SESSION['checkout'] = $_SESSION['checkout'] ?? [];
    $_SESSION['checkout']['shipping'] = [
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'address' => $_POST['address'],
        'address2' => $_POST['address2'] ?? '',
        'city' => $_POST['city'],
        'state' => $_POST['state'],
        'zip' => $_POST['zip'],
        'country' => $_POST['country']
    ];
}

// Validate that shipping info exists
if (!isset($_SESSION['checkout']['shipping'])) {
    header('Location: checkout.php?step=1');
    exit;
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Payment Method</h3>
            </div>
            <div class="card-body">
                <form action="checkout.php?step=3" method="post" id="payment-form">
                    <div class="mb-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_cc" value="credit_card" checked>
                            <label class="form-check-label fw-bold" for="payment_cc">
                                Credit Card
                            </label>
                        </div>
                        
                        <div class="card-details ms-4">
                            <div class="mb-3">
                                <label for="card_number" class="form-label">Card Number*</label>
                                <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="expiry_date" class="form-label">Expiry Date*</label>
                                    <input type="text" class="form-control" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="cvv" class="form-label">CVV*</label>
                                    <input type="text" class="form-control" id="cvv" name="cvv" placeholder="123" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="card_holder" class="form-label">Cardholder Name*</label>
                                <input type="text" class="form-control" id="card_holder" name="card_holder" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_paypal" value="paypal">
                            <label class="form-check-label fw-bold" for="payment_paypal">
                                PayPal
                            </label>
                        </div>
                        <div class="paypal-info ms-4 d-none">
                            <p class="text-muted">You will be redirected to PayPal to complete your payment after reviewing your order.</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="billing_same" name="billing_same" checked>
                            <label class="form-check-label" for="billing_same">
                                Billing address is the same as shipping address
                            </label>
                        </div>
                    </div>
                    
                    <div id="billing_address" class="d-none mb-4">
                        <h4 class="mb-3">Billing Address</h4>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="billing_first_name" class="form-label">First Name*</label>
                                <input type="text" class="form-control" id="billing_first_name" name="billing_first_name">
                            </div>
                            <div class="col-md-6">
                                <label for="billing_last_name" class="form-label">Last Name*</label>
                                <input type="text" class="form-control" id="billing_last_name" name="billing_last_name">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="billing_address1" class="form-label">Street Address*</label>
                            <input type="text" class="form-control mb-2" id="billing_address1" name="billing_address1">
                            <input type="text" class="form-control" id="billing_address2" name="billing_address2" placeholder="Apartment, suite, etc. (optional)">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="billing_city" class="form-label">City*</label>
                                <input type="text" class="form-control" id="billing_city" name="billing_city">
                            </div>
                            <div class="col-md-4">
                                <label for="billing_state" class="form-label">State/Province*</label>
                                <input type="text" class="form-control" id="billing_state" name="billing_state">
                            </div>
                            <div class="col-md-4">
                                <label for="billing_zip" class="form-label">Postal/ZIP Code*</label>
                                <input type="text" class="form-control" id="billing_zip" name="billing_zip">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="billing_country" class="form-label">Country*</label>
                            <select class="form-select" id="billing_country" name="billing_country">
                                <option value="">Select Country</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="UK">United Kingdom</option>
                                <!-- Add more countries as needed -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="checkout.php?step=1" class="btn btn-outline-secondary">Back to Shipping</a>
                        <button type="submit" class="btn btn-primary">Review Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Order Summary</h3>
            </div>
            <div class="card-body">
                <?php
                $subtotal = 0;
                if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                    echo '<ul class="list-group list-group-flush mb-3">';
                    
                    foreach ($_SESSION['cart'] as $product_id => $quantity) {
                        // Get product details
                        $product = get_product_by_id($pdo, $product_id);
                        if ($product) {
                            $item_total = $product['price'] * $quantity;
                            $subtotal += $item_total;
                            
                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                            echo '<div><span class="fw-bold">' . $product['name'] . '</span> <span class="badge bg-secondary">x' . $quantity . '</span></div>';
                            echo '<span>$' . number_format($item_total, 2) . '</span>';
                            echo '</li>';
                        }
                    }
                    
                    echo '</ul>';
                }
                
                // Calculate estimated tax and shipping
                $estimated_tax = $subtotal * 0.08; // 8% tax rate (example)
                $shipping = 5.99; // Fixed shipping cost (example)
                $total = $subtotal + $estimated_tax + $shipping;
                ?>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Est. Tax:</span>
                    <span>$<?php echo number_format($estimated_tax, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Shipping:</span>
                    <span>$<?php echo number_format($shipping, 2); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total:</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h3>Shipping Address</h3>
            </div>
            <div class="card-body">
                <?php
                $shipping = $_SESSION['checkout']['shipping'] ?? [];
                if (!empty($shipping)) {
                    echo '<address>';
                    echo $shipping['first_name'] . ' ' . $shipping['last_name'] . '<br>';
                    echo $shipping['address'] . '<br>';
                    if (!empty($shipping['address2'])) {
                        echo $shipping['address2'] . '<br>';
                    }
                    echo $shipping['city'] . ', ' . $shipping['state'] . ' ' . $shipping['zip'] . '<br>';
                    echo $shipping['country'] . '<br>';
                    echo 'Phone: ' . $shipping['phone'] . '<br>';
                    echo 'Email: ' . $shipping['email'];
                    echo '</address>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle payment method details
    const paymentCc = document.getElementById('payment_cc');
    const paymentPaypal = document.getElementById('payment_paypal');
    const cardDetails = document.querySelector('.card-details');
    const paypalInfo = document.querySelector('.paypal-info');
    
    paymentCc.addEventListener('change', function() {
        if (this.checked) {
            cardDetails.classList.remove('d-none');
            paypalInfo.classList.add('d-none');
        }
    });
    
    paymentPaypal.addEventListener('change', function() {
        if (this.checked) {
            cardDetails.classList.add('d-none');
            paypalInfo.classList.remove('d-none');
        }
    });
    
    // Toggle billing address form
    const billingSame = document.getElementById('billing_same');
    const billingAddress = document.getElementById('billing_address');
    
    billingSame.addEventListener('change', function() {
        if (this.checked) {
            billingAddress.classList.add('d-none');
            // Disable billing address form fields
            const fields = billingAddress.querySelectorAll('input, select');
            fields.forEach(field => field.removeAttribute('required'));
        } else {
            billingAddress.classList.remove('d-none');
            // Enable billing address form fields
            const fields = billingAddress.querySelectorAll('input, select');
            fields.forEach(field => {
                if (field.id !== 'billing_address2') {
                    field.setAttribute('required', 'required');
                }
            });
        }
    });
});
</script>