
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../config/connect_db.php';

    // Get params
    $category_id = isset($_GET['category_id']) ? $_GET['category_id'] : null;
    $page = 1;
    $products_per_page = 1000; // A large number to effectively show all products
    $offset = 0;

    // Prepare the base query
    $query = "SELECT p.* FROM products p ";
    $params = [];

    // Add category filter if category_id is set
    if ($category_id) {
        $query .= " WHERE p.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }

    // Add sorting, pagination
    $query .= " ORDER BY p.created_at DESC LIMIT :offset, :limit";
    $params[':offset'] = $offset;
    $params[':limit'] = $products_per_page;

    // Execute the query
    $stmt = $pdo->prepare($query);

    // PDO::PARAM_INT needed for LIMIT parameters
    foreach ($params as $key => $val) {
        if ($key == ':offset' || $key == ':limit') {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val);
        }
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // Function to get average rating for a product
    function getProductRating($pdo, $product_id) {
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM product_ratings WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'average' => $result['avg_rating'] ? round($result['avg_rating'], 1) : 0,
            'count' => (int)$result['count']
        ];
    }

    // Function to check if product is in user's wishlist
    function isInWishlist($product_id) {
        if (!isset($_SESSION['wishlist'])) {
            return false;
        }
        return in_array($product_id, $_SESSION['wishlist']);
    }

    
  
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());

    // Return a user-friendly error message
    echo '<p class="error">Database error: Unable to retrieve products.</p>';
    exit;
}?>
    
    <?php if (count($products) > 0): 
        foreach ($products as $product): 
            $rating = getProductRating($pdo, $product['id']);
            $wishlist_class = isInWishlist($product['id']) ? 'in-wishlist' : '';
            $estimate_days = isset($product['estimate_days']) ? $product['estimate_days'] : '3-5';
    ?>
        <div class="product-item glass-card" data-product-id="<?php echo $product['id']; ?>">
            <div class="product-image-container">
                <button class="wishlist-btn <?php echo $wishlist_class; ?>" data-product-id="<?php echo $product['id']; ?>">
                    <i class="fas fa-heart"></i>
                </button>
                
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                <?php else: ?>
                    <div class="no-image">
                        <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
                
                <div class="price-badge">
                    <span>$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></span>
                </div>
            </div>
            <div class="product-details">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                
                <div class="product-footer">
                    <div class="rating" title="<?php echo $rating['average']; ?> stars based on <?php echo $rating['count']; ?> reviews">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fa<?php echo $i <= $rating['average'] ? 's' : 'r'; ?> fa-star"></i>
                        <?php endfor; ?>
                        <span class="rating-count">(<?php echo $rating['count']; ?>)</span>
                    </div>
                    <button class="add-to-cart-btn" 
                        data-product-id="<?php echo $product['id']; ?>"
                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                        data-product-price="<?php echo $product['price']; ?>">
                        <i class="fas fa-shopping-cart"></i>
                    </button>
                </div>
                
                <!-- Hidden product data for modal -->
                <div class="product-data" style="display:none;">
                    <span data-type="admin"><?php echo isset($product['admin_name']) ? htmlspecialchars($product['admin_name']) : 'Admin'; ?></span>
                    <span data-type="estimate"><?php echo $estimate_days; ?> days</span>
                    <span data-type="image"><?php echo htmlspecialchars($product['image']); ?></span>
                </div>
            </div>
        </div>
    <?php 
        endforeach;
    else: 
    ?>
        <div class="no-products">
            <i class="fas fa-box-open text-3xl mb-3"></i>
            <p>No products available in this category.</p>
            <a href="shop.php" class="mt-3 px-4 py-2 bg-white/10 hover:bg-white/20 inline-block rounded-lg transition">
                Browse All Products
            </a>
        </div>
    <?php endif; ?>