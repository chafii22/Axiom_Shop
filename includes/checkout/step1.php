<?php
// Pre-fill form if user is logged in and has previous shipping info
$shipping_info = [];
if (isset($_SESSION['user_id'])) {
    global $pdo;
    $user_id = $_SESSION['user_id'];

    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $shipping_info['first_name'] = $user['first_name'];
        $shipping_info['last_name'] = $user['last_name'];
        $shipping_info['email'] = $user['email'];
        $shipping_info['phone'] = $user['phone'];
    }

    $address_stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1");
    $address_stmt->execute([$user_id]);
    $address = $address_stmt->fetch(PDO::FETCH_ASSOC);

    if ($address) {
        $shipping_info['address'] = $address['street_address'];
        $shipping_info['address2'] = $address['street_address_2'];
        $shipping_info['city'] = $address['city'];
        $shipping_info['state'] = $address['state'];
        $shipping_info['zip'] = $address['zip_code'];
        $shipping_info['country'] = $address['country'];
    

        if (!empty($address['phone'])) {
            $shipping_info['phone'] = $address['phone'];
        }
    }
}

// Pre-fill from session if available
if (isset($_SESSION['checkout']['shipping'])) {
    $shipping_info = $_SESSION['checkout']['shipping'];
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Shipping Information</h3>
            </div>
            <div class="card-body">
                <form action="checkout.php?step=2" method="post" id="shipping-form">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                value="<?php echo isset($shipping_info['first_name']) ? htmlspecialchars($shipping_info['first_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                value="<?php echo isset($shipping_info['last_name']) ? htmlspecialchars($shipping_info['last_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo isset($shipping_info['email']) ? htmlspecialchars($shipping_info['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                            value="<?php echo isset($shipping_info['phone']) ? htmlspecialchars($shipping_info['phone']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Street Address</label>
                        <input type="text" class="form-control mb-2" id="address" name="address"
                            value="<?php echo isset($shipping_info['address']) ? htmlspecialchars($shipping_info['address']) : ''; ?>" required>
                        <input type="text" class="form-control" id="address2" name="address2" 
                            value="<?php echo isset($shipping_info['address2']) ? htmlspecialchars($shipping_info['address2']) : ''; ?>"
                            placeholder="Apartment, suite, etc. (optional)">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city"
                                value="<?php echo isset($shipping_info['city']) ? htmlspecialchars($shipping_info['city']) : ''; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state" name="state"
                                value="<?php echo isset($shipping_info['state']) ? htmlspecialchars($shipping_info['state']) : ''; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="zip" class="form-label">Postal/ZIP Code</label>
                            <input type="text" class="form-control" id="zip" name="zip"
                                value="<?php echo isset($shipping_info['zip']) ? htmlspecialchars($shipping_info['zip']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="country" class="form-label">Country</label>
                        <select class="form-select text-black" id="country" name="country" required>
                            <option value="">Select Country</option>
                            <option value="MA" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'MA') ? 'selected' : ''; ?>>Morocco</option>
                            <option value="ES" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'ES') ? 'selected' : ''; ?>>Spain</option>
                            <option value="US" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'US') ? 'selected' : ''; ?>>United States</option>
                            <option value="CA" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'CA') ? 'selected' : ''; ?>>Canada</option>
                            <option value="UK" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'UK') ? 'selected' : ''; ?>>United Kingdom</option>
                            <option value="AU" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'AU') ? 'selected' : ''; ?>>Australia</option>
                            <option value="DE" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'DE') ? 'selected' : ''; ?>>Germany</option>
                            <option value="FR" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'FR') ? 'selected' : ''; ?>>France</option>
                            <option value="JP" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'JP') ? 'selected' : ''; ?>>Japan</option>
                            <option value="IN" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'IN') ? 'selected' : ''; ?>>India</option>
                            <option value="BR" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'BR') ? 'selected' : ''; ?>>Brazil</option>
                            <option value="CN" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'CN') ? 'selected' : ''; ?>>China</option>
                            <option value="MX" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'MX') ? 'selected' : ''; ?>>Mexico</option>
                            <option value="ZA" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'ZA') ? 'selected' : ''; ?>>South Africa</option>
                            <option value="RU" <?php echo (isset($shipping_info['country']) && $shipping_info['country'] == 'RU') ? 'selected' : ''; ?>>Russia</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="save_info" name="save_info">
                        <label class="form-check-label" for="save_info">Save this information for next time</label>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="cart.php" class="btn btn-outline-secondary">Back to Cart</a>
                        <button type="submit" class="btn btn-primary">Continue to Payment</button>
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
                global $pdo;
                
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
                            echo '<div><span class="fw-bold">' . htmlspecialchars($product['name']) . '</span> <span class="badge bg-secondary">x' . $quantity . '</span></div>';
                            echo '<span>$' . number_format($item_total, 2) . '</span>';
                            echo '</li>';
                        }
                    }
                    
                    echo '</ul>';
                }
                
                // Calculate estimated tax and shipping
                $estimated_tax = $subtotal * 0.08; 
                $shipping = 5.99; 
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
    </div>
</div>


<style>
    /* Enhanced form styling */
    #shipping-form label.form-label {
        font-weight: 600;
        color: #444;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }
    
    #shipping-form .form-control,
    #shipping-form .form-select {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        transition: all 0.2s ease;
        border: 1px solid #dedede;
        background-color: rgba(255,255,255,0.7);
    }
    
    #shipping-form .form-control:focus,
    #shipping-form .form-select:focus {
        box-shadow: 0 0 0 3px rgba(87, 75, 144, 0.25);
        border-color: #574b90;
        background-color: white;
    }
    
    /* Card styling */
    .card {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .card-header {
        
        color: white;
        font-weight: 600;
        padding: 1rem 1.5rem;
        border-bottom: none;
    }
    
    .card-header h3 {
        font-size: 1.25rem;
        margin: 0;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    /* Order summary styling */
    .list-group-item {
        background-color: transparent;
        border-color: rgba(0,0,0,0.08);
        padding: 0.75rem 0;
    }
    
    .badge.bg-secondary {
        background-color: #574b90 !important;
        font-weight: normal;
    }
    
    /* Button styling */
    .btn-primary {
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        border-radius: 8px;
        letter-spacing: 0.02em;
        box-shadow: 0 4px 10px rgba(87, 75, 144, 0.3);
    }
    
    .btn-primary:hover {
        box-shadow: 0 6px 15px rgba(87, 75, 144, 0.4);
        transform: translateY(-2px);
    }
    
    .btn-outline-secondary {
        border-color: #dedede;
        color: #555;
        font-weight: 500;
        border-radius: 8px;
    }
    
    .btn-outline-secondary:hover {
        background-color: #f5f5f5;
        color: #333;
        border-color: #ccc;
    }
    
    /* Form section spacing */
    .row.mb-3 {
        margin-bottom: 1.5rem !important;
    }

    .row h3, .card-header {
        background-color: rgb(15 23 42 / var(--tw-bg-opacity, 1));
    }
    
    /* Form check styling */
    .form-check-input {
        border-color: #574b90;
    }
    
    .form-check-input:checked {
        background-color: #574b90;
        border-color: #574b90;
    }
    
    /* Total price highlighting */
    .fw-bold {
        color: #333;
        font-size: 1.1rem;
    }
    
    /* Add a subtle border around the form */
    #shipping-form {
        position: relative;
        padding: 1rem;
        border-radius: 8px;
        background-color: rgba(255,255,255,0.5);
    }
    
    /* Add required field indicator */
    .form-label:after {
        content: "*";
        color: #574b90;
        margin-left: 3px;
    }
    
    /* Improve responsive spacing */
    @media (max-width: 768px) {
        .card {
            margin-bottom: 2rem;
        }
    }
</style>