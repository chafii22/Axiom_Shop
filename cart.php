<?php
session_start();
require_once 'config/connect_db.php';
require_once 'api/models/Cart.php';

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
$cartModel = new Cart($pdo);
$cartItems = $cartModel->getCartItems($userId);

$total_price = 0;
$products = [];

foreach ($cartItems as $item) {
    $products[$item['product_id']] = $item;
    $total_price += $item['price'] * $item['quantity'];
}

// Set page variables
$current_page = 'cart';
$base_url = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/shopstyle.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .cart-empty {
            text-align: center;
            padding: 50px 0;
        }
        .cart-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            overflow: hidden;
        }
        .cart-table th {
            background: #f9fafb;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
        }
        .cart-table td {
            background: #ffffff;
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .cart-table tr:last-child {
            border-bottom: none;
        }
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
        }
        .cart-item-quantity {
            display: flex;
            align-items: center;
        }
        .cart-item-quantity button {
            background: #f3f4f6;
            border: none;
            width: 30px;
            height: 30px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-item-quantity input {
            width: 40px;
            height: 30px;
            text-align: center;
            border: 1px solid #e5e7eb;
            margin: 0 8px;
        }
        .cart-item-remove {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .cart-item-remove:hover {
            background: #fee2e2;
        }
        .cart-summary {
            margin-top: 30px;
            text-align: right;
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
        }
        .checkout-btn {
            background: #0f172a;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 16px;
            transition: all 0.3s;
        }
        .checkout-btn:hover {
            background: #1e293b;
        }
        .continue-shopping {
            display: inline-block;
            margin-top: 16px;
            color: #0f172a;
            text-decoration: none;
            margin-right: 16px;
            text-decoration: underline;
        }

        .cart-table tr {
            border-bottom: 1px solid #f3f4f6;
        }

        /* Ensure the table fills the wrapper completely */
        .cart-table {
            margin-bottom: 0;
            border-collapse: collapse;
        }
        
        /* Make sure cart summary connects smoothly with table */
        .cart-summary {
            border-top: 1px solid #f3f4f6;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="cart-container">
        <h1 class="text-3xl font-bold mb-6">Your Shopping Cart</h1>
        
        <div class="cart-table-wrapper bg-white shadow-md rounded-lg overflow-hidden">
            <table class="cart-table w-full">
                <thead>
                    <tr>
                        <th class="w-1/6">Image</th>
                        <th class="w-1/3 text-left">Product</th>
                        <th class="w-1/6">Price</th>
                        <th class="w-1/6">Quantity</th>
                        <th class="w-1/6">Total</th>
                        <th class="w-1/12"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cartItems)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                
                                <p class="text-xl font-medium text-gray-500 mb-6">Your cart is empty</p>
                                <a href="shop.php" class="checkout-btn bg-[#1d2a48] hover:bg-[#0f172a] text-white py-3 px-6 rounded-md font-medium transition-colors flex items-center justify-center">
                                    <i class="fas fa-shopping-bag mr-2"></i>
                                    Continue Shopping
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($cartItems as $item): ?>
                        <!-- Existing cart items code here -->
                            <tr class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                                <td>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                        alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                        class="cart-item-image">
                                </td>
                                <td>
                                    <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($item['name']); ?></h3>
                                </td>
                                <td>
                                    $<?php echo number_format($item['price'], 2); ?>
                                </td>
                                <td>
                                    <div class="cart-item-quantity">
                                        <button class="quantity-decrease">-</button>
                                        <input type="number" min="1" max="99" value="<?php echo $item['quantity']; ?>" class="quantity-input" readonly>
                                        <button class="quantity-increase">+</button>
                                    </div>
                                </td>
                                <td class="font-semibold">
                                    $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </td>
                                <td>
                                    <button class="cart-item-remove" title="Remove from cart">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($cartItems)): ?>
            <div class="cart-summary">
                <div class="text-2xl mb-4"><strong>Total:</strong> $<?php echo number_format($total_price, 2); ?></div>
                <div class="flex justify-between">
                    <a href="shop.php" class="continue-shopping flex items-center text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-2"></i> Continue Shopping
                    </a>
                    <button id="checkout-btn" class="checkout-btn">
                        Proceed to Checkout <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'sticky_nav.php'; ?>
    <script src="js/shop.js"></script>
    <script src="js/utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle quantity changes
            document.querySelectorAll('.quantity-increase').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.closest('.cart-item').dataset.productId;
                    updateCartQuantity(productId, 1);
                });
            });
            
            document.querySelectorAll('.quantity-decrease').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.closest('.cart-item').dataset.productId;
                    updateCartQuantity(productId, -1);
                });
            });
            
            // Handle item removal
            document.querySelectorAll('.cart-item-remove').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.closest('.cart-item').dataset.productId;
                    removeFromCart(productId);
                });
            });
            
            // Checkout button
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    window.location.href = 'checkout.php';
                });
            }
            
            // Functions to interact with the cart_api.php API
            function updateCartQuantity(productId, change) {
                CartService.updateQuantity(productId, change)
                    .then(data => {
                        if (data.status === 'success') {
                            // Update quantity without page refresh
                            updateCartItemUI(productId);
                            // Update total price
                            updateCartTotals();
                            // Update cart badge in nav
                            if (data.data && data.data.cart_count) {
                                updateCartCountBadge(data.data.cart_count);
                            }
                        } else {
                            alert(data.message || 'Failed to update cart');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
            
            function removeFromCart(productId) {
                CartService.removeFromCart(productId)
                    .then(data => {
                        if (data.status === 'success') {
                            // Remove item row without page refresh
                            const item = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                            if (item) {
                                item.remove();
                            }
                            
                            // Update total price
                            updateCartTotals();
                            
                            // Update cart badge in nav
                            if (data.data && data.data.cart_count) {
                                updateCartCountBadge(data.data.cart_count);
                            }
                            
                            // If cart is now empty, show empty message
                            if (document.querySelectorAll('.cart-item').length === 0) {
                                location.reload(); // Only reload if cart is empty
                            }
                        } else {
                            alert(data.message || 'Failed to remove item');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }

            // Helper to update a single cart item's UI
            function updateCartItemUI(productId) {
                // Get updated cart data
                CartService.getCart()
                    .then(data => {
                        if (data.status === 'success' && data.data && data.data.items) {
                            // Find the product in the returned items
                            const product = data.data.items.find(item => item.product_id == productId);
                            
                            if (product) {
                                // Update the UI for this specific product
                                const row = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                                if (row) {
                                    // Update quantity input
                                    const qtyInput = row.querySelector('.quantity-input');
                                    if (qtyInput) qtyInput.value = product.quantity;
                                    
                                    // Update item total price
                                    const itemTotal = row.querySelector('td:nth-child(5)');
                                    if (itemTotal) {
                                        const total = product.price * product.quantity;
                                        itemTotal.innerHTML = `$${total.toFixed(2)}`;
                                    }
                                }
                            }
                        }
                    });
            }

            // Helper to update cart totals
            function updateCartTotals() {
                CartService.getCart()
                    .then(data => {
                        if (data.status === 'success' && data.data) {
                            // Update the summary total
                            const totalElement = document.querySelector('.cart-summary .text-2xl');
                            if (totalElement && data.data.total_price !== undefined) {
                                totalElement.innerHTML = `<strong>Total:</strong> $${parseFloat(data.data.total_price).toFixed(2)}`;
                            }
                        }
                    });
            }
        });
    </script>
</body>
</html>

<?php foreach ($cartItems as $item): ?>
                        <?php //if (isset($products[$product_id])): ?>
                            <?php //$product = $products[$product_id]; ?>
                            
                        <?php //endif; ?>
                    <?php endforeach; ?>