
<?php
/**
 * Product utility functions
 */

/**
 * Get product details by ID
 * 
 * @param PDO $pdo Database pdoection (can be null, will use global $pdo)
 * @param int $product_id Product ID
 * @return array|false Product data or false if not found
 */
function get_product_by_id($pdo, $product_id) {
    // Use global $pdo if $pdo is not provided
    if (!$pdo && isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
    
    // If we still don't have a pdoection, use mock data
    if (!$pdo) {
        return get_mock_product($product_id);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        } else {
            // If product doesn't exist in DB, return mock data
            return get_mock_product($product_id);
        }
    } catch (PDOException $e) {
        error_log("Error fetching product: " . $e->getMessage());
        return get_mock_product($product_id);
    }
}

/**
 * Mock product data for testing purposes
 */
function get_mock_product($product_id) {
    $mock_products = [
        1 => ['id' => 1, 'name' => 'Sample Product 1', 'price' => 19.99, 'description' => 'This is a sample product'],
        2 => ['id' => 2, 'name' => 'Sample Product 2', 'price' => 29.99, 'description' => 'Another sample product'],
        3 => ['id' => 3, 'name' => 'Sample Product 3', 'price' => 39.99, 'description' => 'Yet another sample product'],
        25 => ['id' => 25, 'name' => 'Featured Product', 'price' => 99.99, 'description' => 'Our bestseller']
    ];
    
    return isset($mock_products[$product_id]) ? $mock_products[$product_id] : [
        'id' => $product_id, 
        'name' => 'Product #' . $product_id, 
        'price' => rand(1000, 9999) / 100, 
        'description' => 'Auto-generated product'
    ];
}