
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
                /* Dashboard-style wishlist styles */
        .dashboard-container {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: 1fr;
        }

                /* Make buttons larger on mobile */
        @media (max-width: 576px) {
          .btn, button, .nav-link, a.product-link {
            min-height: 44px;
            min-width: 44px;
            padding: 12px 16px;
          }
        }
        
        .dashboard-card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            overflow: hidden;
        }
        
        .dashboard-card-header {
            background-color: #f5f5f7;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .dashboard-card-header h2 {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .dashboard-card-header h2 i {
            margin-right: 0.5rem;
            color: #574b90;
        }
        
        .dashboard-card-body {
            padding: 1.5rem;
        }
        
        .dashboard-button {
            display: inline-block;
            background-color: #574b90;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 1rem;
            transition: background-color 0.2s;
        }
        
        .dashboard-button:hover {
            background-color: #483d8b;
        }
        
        .wishlist-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .wishlist-table th, 
        .wishlist-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .wishlist-table th {
            font-weight: 600;
            color: #4b5563;
        }
        
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            margin-right: 1rem;
            overflow: hidden;
            border-radius: 4px;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-name h3 {
            font-size: 1rem;
            font-weight: 500;
            margin: 0;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
            flex-direction: column;
        }
        
        .product-actions button {
            padding: 0.5rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        
        .add-to-cart-btn {
            background-color: #574b90;
            color: white;
        }
        
        .add-to-cart-btn:hover {
            background-color: #483d8b;
        }
        
        .wishlist-remove-btn {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .wishlist-remove-btn:hover {
            background-color: #e5e7eb;
        }
        
        .empty-wishlist {
            text-align: center;
            padding: 2rem;
        }
        
        .empty-wishlist .btn {
            display: inline-block;
            background-color: #574b90;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .wishlist-table thead {
                display: none;
            }
            
            .wishlist-table tbody tr {
                display: block;
                padding: 1rem 0;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .wishlist-table td {
                display: block;
                border: none;
                padding: 0.5rem 1rem;
            }
            
            .product-info {
                align-items: flex-start;
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
                    <p>You have <strong><?php echo $wishlist_count; ?></strong> items in your wishlist.</p>
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
                                        <td class="product-info">
                                            <div class="product-image">
                                                <img src="<?php echo '../'. htmlspecialchars($product['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            </div>
                                            <div class="product-name">
                                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                            </div>
                                        </td>
                                        <td class="product-price">
                                            $<?php echo htmlspecialchars(number_format($product['price'], 2)); ?>
                                        </td>
                                        <td class="product-rating">
                                            <div class="rating" title="<?php echo $rating['average']; ?> stars">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa<?php echo $i <= $rating['average'] ? 's' : 'r'; ?> fa-star"></i>
                                                <?php endfor; ?>
                                                <span class="rating-count">(<?php echo $rating['count']; ?>)</span>
                                            </div>
                                        </td>
                                        <td class="product-actions">
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
                            <p class="text-center text-lg">Your wishlist is empty</p>
                            <a href="../shop.php" class="btn mt-4">Shop Now</a>
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
                        // Remove item from page
                        const item = this.closest('.wishlist-item');
                        item.remove();
                        
                        // Update wishlist count
                        const wishlistCount = document.querySelectorAll('.wishlist-item').length;
                        const countElement = document.querySelector('.dashboard-card-body p strong');
                        if (countElement) {
                            countElement.textContent = wishlistCount;
                        }
                        
                        // Show empty message if no items left
                        if (wishlistCount === 0) {
                            const tableContainer = document.querySelector('.wishlist-items');
                            tableContainer.innerHTML = `
                                <div class="empty-wishlist">
                                    <p class="text-center text-lg">Your wishlist is empty</p>
                                    <a href="../shop.php" class="btn mt-4">Shop Now</a>
                                </div>
                            `;
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
        
        // Handle add to cart buttons
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');
                const productPrice = this.getAttribute('data-product-price');
                
                // Send add to cart request
                fetch('../api/cart_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add&product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success notification
                        showNotification(`${productName} added to cart!`, 'success');
                        window.alert(data.message);
                        
                        // Update cart count if needed
                        if (typeof updateCartCounter === 'function') {
                            updateCartCounter(data.cart_count);
                        }
                    } else {
                        showNotification('Failed to add item to cart', 'error');
                        console.log(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to add item to cart', 'error');
                });
            });
        });
        
        // Notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    });
    </script>
</body>
</html>