<?php
session_start();
// Include database connection file
require_once 'config/connect_db.php';

// Initialize wishlist in session if it doesn't exist
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}


// Get the category id of the product
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$page = 1;
$products_per_page = 1000; // A large number to effectively show all products
$offset = 0;


// Fetch categories from the database
$cat_stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the base query for products count
$count_query = "SELECT COUNT(*) FROM products";
$count_params = [];

// Add category filter if category_id is set
if ($category_id) {
    $count_query .= " WHERE category_id = :category_id";
    $count_params[':category_id'] = $category_id;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_products = $count_stmt->fetchColumn();

// Prepare the base query for products
$query = "SELECT p.* FROM products p";
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

// Fetch products from the database
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

// Set page variables
$current_page = 'store';
$base_url = '';

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
        // If table doesn't exist or other database error, return default values
        return [
            'average' => 0,
            'count' => 0
        ];
    }
}

// Function to check if product is in user's wishlist
function isInWishlist($product_id) {
    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }
    return in_array($product_id, $_SESSION['wishlist']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/shopstyle.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <h2>Products</h2>

        <d class="shop-container">
            <aside class="sticky-sidebar">
                <div class="sidebar-filter">
                    <a href="#" class="category-icon-item <?php echo !$category_id ? 'active' : ''; ?>" data-category="" id="category-all">
                        <div class="icon-container">
                            <img src="assets/images/icons/all.png" alt="All Categories">
                        </div>
                        <span class="category-name">All Categories</span>
                    </a>
                    
                    <?php foreach ($categories as $category): ?>
                    <a href="#" class="category-icon-item <?php echo ($category_id == $category['id']) ? 'active' : ''; ?>" 
                       data-category="<?php echo htmlspecialchars($category['id']); ?>">
                        <div class="icon-container">
                            <img src="assets/images/icons/<?php echo strtolower(str_replace(' ', '-', $category['name'])); ?>.png" 
                                 alt="<?php echo htmlspecialchars($category['name']); ?>">
                        </div>
                        <span class="category-name"><?php echo htmlspecialchars($category['name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </aside>

                      
                <div class="product-list" id="product-list">
                    <?php 
                    // Check if there are products
                    if (count($products) > 0): 
                        // Loop through each product
                        foreach ($products as $product): 
                            $rating = getProductRating($pdo, $product['id']);
                            $wishlist_class = isInWishlist($product['id']) ? 'in-wishlist' : '';
                            $estimate_days = isset($product['estimate_days']) ? $product['estimate_days'] : '3-5';
                    ?>
                        <div class="product-item" data-product-id="<?php echo $product['id']; ?>">
                            <div class="product-image-container">
                                <button class="wishlist-btn <?php echo $wishlist_class; ?>" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            </div>
                            <div class="product-details">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="price">$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
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
                        <p class="no-products">No products available at the moment.</p>
                    <?php endif; ?>
                </div>
                
            
        </div>
    </main>
    
    <!-- Product Modal -->
    <div id="product-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-body">
                <div class="modal-left">
                    <img id="modal-image" src="" alt="">
                </div>
                <div class="modal-right">
                    <h2 id="modal-name"></h2>
                    <p class="modal-price">Price: $<span id="modal-price"></span></p>
                    <p class="modal-estimate">Estimated delivery: <span id="modal-estimate"></span></p>
                    <p class="modal-admin">Added by: <span id="modal-admin"></span></p>
                    
                    <div class="modal-rating">
                        <div class="stars">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <span class="rating-avg">0.0</span>
                        <span class="rating-count">(0 ratings)</span>
                    </div>
                    
                    <button class="modal-add-to-cart">Add to Cart</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'sticky_nav.php'; ?>

    <script src="<?php echo $root_path ?? ''; ?>js/utils.js"></script>
    <script src="js/shop.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>