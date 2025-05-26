<?php
session_start();
require_once '../config/connect_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=user/wishlist.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Sync user's wishlist from database
try {
    // Check if the table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'wishlist'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Create wishlist table if it doesn't exist
        $pdo->exec("CREATE TABLE `wishlist` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `product_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_product_unique` (`user_id`, `product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Get user's wishlist from database
    $stmt = $pdo->prepare("SELECT p.* FROM products p 
                          JOIN wishlist w ON p.id = w.product_id 
                          WHERE w.user_id = ?");
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $_SESSION['wishlist'] = array_column($products, 'id');
    
    // Count wishlist items
    $wishlist_count = count($products);
    
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Wishlist DB error: " . $e->getMessage());
    $products = [];
    $wishlist_count = 0;
}

// Function to get average rating for a product
function getProductRating($pdo, $product_id) {
    try {
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM product_ratings WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'average' => $result['avg_rating'] ? round($result['avg_rating'], 1) : 0,
            'count' => (int)$result['count']
        ];
    } catch (PDOException $e) {
        return ['average' => 0, 'count' => 0];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/shopstyle.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body.user-area {
            font-family: 'Noto Sans', sans-serif;
            color: #333;
            background-color: #f5f5f7;
            min-height: 100vh;
        }
        
        /* Container styles */
        .container {
            max-width: 1200px;
            padding: 0 1rem;
        }
        
        /* Dashboard container */
        .dashboard-container {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: 1fr;
            margin-bottom: 2rem;
        }
        
        /* Card styling */
        .dashboard-card {
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .dashboard-card-header {
            display: flex;
            align-items: center;
            padding: 1.25rem 1.5rem;
            background-color: #f9f9fb;
            border-bottom: 1px solid #eaeaea;
        }
        
        .dashboard-card-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .dashboard-card-header h2 i {
            margin-right: 0.75rem;
            color: #574b90;
            font-size: 1.1rem;
        }
        
        .dashboard-card-body {
            padding: 1.5rem;
        }
        
        /* Dashboard buttons */
        .dashboard-button {
            display: inline-block;
            background-color: #574b90;
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }
        
        .dashboard-button:hover {
            background-color: #483d8b;
            transform: translateY(-1px);
        }
        
        /* Wishlist table */
        .wishlist-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .wishlist-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            color: #666;
            border-bottom: 1px solid #eaeaea;
            background-color: #f9f9fb;
            font-size: 0.9rem;
        }
        
        .wishlist-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #eaeaea;
        }
        
        .wishlist-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Column widths */
        .wishlist-table th:nth-child(1),
        .wishlist-table td:nth-child(1) {
            width: 45%;
        }
        
        .wishlist-table th:nth-child(2),
        .wishlist-table td:nth-child(2) {
            width: 15%;
        }
        
        .wishlist-table th:nth-child(3),
        .wishlist-table td:nth-child(3) {
            width: 20%;
        }
        
        .wishlist-table th:nth-child(4),
        .wishlist-table td:nth-child(4) {
            width: 20%;
        }
        
        /* Product info styling */
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            background-color: #f5f5f7;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-name {
            flex: 1;
            min-width: 0;
        }
        
        .product-name h3 {
            font-size: 1rem;
            font-weight: 500;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #333;
        }
        
        /* Price styling */
        .product-price {
            font-weight: 600;
            color: #333;
            font-size: 1.05rem;
        }
        
        /* Ratings */
        .rating {
            display: flex;
            align-items: center;
        }
        
        .rating i {
            color: #FFD700;
            font-size: 0.9rem;
            margin-right: 1px;
        }
        
        .rating-count {
            margin-left: 5px;
            color: #777;
            font-size: 0.85rem;
        }
        
        /* Buttons */
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .add-to-cart-btn, 
        .wishlist-remove-btn {
            border: none;
            border-radius: 6px;
            padding: 0.625rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-to-cart-btn {
            background-color: #574b90;
            color: white;
            min-width: 36px;
            height: 36px;
        }
        
        .add-to-cart-btn:hover {
            background-color: #483d8b;
        }
        
        .wishlist-remove-btn {
            background-color: #f3f4f6;
            color: #4b5563;
            display: flex;
            align-items: center;
            padding: 0.625rem 0.875rem;
        }
        
        .wishlist-remove-btn i {
            margin-right: 0.375rem;
        }
        
        .wishlist-remove-btn:hover {
            background-color: #e5e7eb;
        }
        
        /* Empty wishlist */
        .empty-wishlist {
            padding: 3rem 1rem;
            text-align: center;
        }
        
        .empty-wishlist p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        .empty-wishlist .btn {
            background-color: #574b90;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .empty-wishlist .btn:hover {
            background-color: #483d8b;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 4px;
            background-color: #333;
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s;
            font-size: 0.875rem;
            max-width: 300px;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .notification.success {
            background-color: rgba(52, 199, 89, 0.9);
        }
        
        .notification.error {
            background-color: rgba(255, 59, 48, 0.9);
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .dashboard-card-header {
                padding: 1rem;
            }
            
            .dashboard-card-body {
                padding: 1rem;
            }
            
            .wishlist-table thead {
                display: none;
            }
            
            .wishlist-table tbody tr {
                display: grid;
                grid-template-columns: 1fr;
                padding: 1rem 0;
                border-bottom: 1px solid #eaeaea;
            }
            
            .wishlist-table tbody tr:last-child {
                border-bottom: none;
            }
            
            .wishlist-table td {
                padding: 0.5rem 1rem;
                border-bottom: none;
            }
            
            .wishlist-table td:before {
                content: attr(data-label);
                font-weight: 600;
                display: block;
                margin-bottom: 0.25rem;
                color: #666;
                font-size: 0.85rem;
            }
            
            .product-info {
                margin-bottom: 0.5rem;
            }
            
            .product-actions {
                justify-content: flex-start;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body class="user-area">
    <?php include '../includes/header.php'; ?>
    
    <main class="container mx-auto px-4 py-8">
        <a href="account.php" class="text-gray-600 hover:text-gray-800 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-3xl font-bold mb-6">My Wishlist</h1>
        
        <div class="dashboard-container">
            <!-- Wishlist Summary -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2><i class="fas fa-heart"></i> Wishlist Summary</h2>
                </div>
                <div class="dashboard-card-body">
                    <p>You have <strong><?php echo $wishlist_count; ?></strong> <?php echo $wishlist_count == 1 ? 'item' : 'items'; ?> in your wishlist.</p>
                    <a href="../shop.php" class="dashboard-button">Continue Shopping</a>
                </div>
            </div>
            
            <!-- Wishlist Items -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2><i class="fas fa-list"></i> Your Saved Items</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if ($wishlist_count > 0): ?>
                        <div class="wishlist-items">
                            <table class="wishlist-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Rating</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): 
                                        $rating = getProductRating($pdo, $product['id']);
                                    ?>
                                    <tr class="wishlist-item" data-product-id="<?php echo $product['id']; ?>">
                                        <td class="product-info" data-label="Product">
                                            <div class="product-image">
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="<?php echo '../'. htmlspecialchars($product['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                <?php else: ?>
                                                    <div class="no-image">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-name">
                                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                            </div>
                                        </td>
                                        <td class="product-price" data-label="Price">
                                            $<?php echo number_format($product['price'], 2); ?>
                                        </td>
                                        <td class="product-rating" data-label="Rating">
                                            <div class="rating" title="<?php echo $rating['average']; ?> stars">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa<?php echo $i <= $rating['average'] ? 's' : 'r'; ?> fa-star"></i>
                                                <?php endfor; ?>
                                                <span class="rating-count">(<?php echo $rating['count']; ?>)</span>
                                            </div>
                                        </td>
                                        <td class="product-actions" data-label="Actions">
                                            <button class="add-to-cart-btn" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                    data-product-price="<?php echo $product['price']; ?>">
                                                <i class="fas fa-shopping-cart"></i>
                                            </button>
                                            <button class="wishlist-remove-btn"
                                                    data-product-id="<?php echo $product['id']; ?>">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-wishlist">
                            <p>Your wishlist is empty</p>
                            <a href="../shop.php" class="btn">Shop Now</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
    <?php include '../sticky_nav.php'; ?>
    
    <!-- Add wishlist-specific JavaScript -->
    <script>
        // Notification function
        function showNotification(message, type = 'success') {
            // Remove any existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notif => notif.remove());
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Show notification with small delay for animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Hide and remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    document.addEventListener('DOMContentLoaded', function() {
        // Handle remove from wishlist button clicks
        const removeButtons = document.querySelectorAll('.wishlist-remove-btn');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                
                // Send request to remove from wishlist
                fetch('../api/wishlist_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show notification
                        showNotification('Item removed from wishlist', 'success');
                        
                        // Reload page after a short delay
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showNotification('Error removing item', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error removing item', 'error');
                });
            });
        });
        
        // Handle add to cart buttons
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');
                
                // Show user feedback immediately
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.disabled = true;
                
                // Send add to cart request
                fetch('../api/cart_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add&product_id=${productId}&quantity=1`
                })
                .then(response => {
                    // Check if response is valid JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    }
                    throw new Error('Invalid response format');
                })
                .then(data => {
                    // Reset button
                    this.innerHTML = '<i class="fas fa-shopping-cart"></i>';
                    this.disabled = false;
                    
                    if (data.success) {
                        showNotification(`${productName} added to cart!`, 'success');
                        
                        // Update cart count if needed
                        if (typeof updateCartCounter === 'function') {
                            updateCartCounter(data.cart_count);
                        }
                    } else {
                        console.error('API Error:', data);
                        showNotification(data.message || 'Failed to add item to cart', 'error');
                    }
                })
                .catch(error => {
                    // Reset button
                    this.innerHTML = '<i class="fas fa-shopping-cart"></i>';
                    this.disabled = false;
                    
                    console.error('Error:', error);
                    
                    // Try alternative endpoint if the first one failed
                    fetch('../cart_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=add&product_id=${productId}&quantity=1`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(`${productName} added to cart!`, 'success');
                        } else {
                            showNotification(data.message || 'Failed to add item to cart', 'error');
                        }
                    })
                    .catch(e => {
                        showNotification('Network error adding item to cart', 'error');
                    });
                });
            });
        });
        
        
    });
    </script>
</body>
</html>