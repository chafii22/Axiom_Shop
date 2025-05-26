
<?php
session_start();
require_once '../config/connect_db.php';

header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request',
    'in_wishlist' => false
];

// Initialize wishlist if it doesn't exist
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Get action and product ID
$action = isset($_POST['action']) ? $_POST['action'] : '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

// Validate product_id
if ($product_id <= 0) {
    $response['message'] = 'Invalid product ID';
    echo json_encode($response);
    exit;
}

// Check if user is logged in (optional - you can store wishlist in session for guests too)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

switch ($action) {
    case 'toggle':
        // Check if the product exists first
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        if (!$stmt->fetch()) {
            $response['message'] = 'Product not found';
            echo json_encode($response);
            exit;
        }

        // If user is logged in, update database
        if ($user_id > 0) {
            try {
                    // Check if table exists first
                    $tableExists = $pdo->query("SHOW TABLES LIKE 'wishlist'")->rowCount() > 0;
                    
                    if (!$tableExists) {
                        // Create the table if it doesn't exist
                        $pdo->exec("CREATE TABLE `wishlist` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `user_id` int(11) NOT NULL,
                            `product_id` int(11) NOT NULL,
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `user_product_unique` (`user_id`, `product_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                    }
                    
                    // Check if product is already in wishlist
                    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$user_id, $product_id]);
                    $existingItem = $stmt->fetch();
                    
                    if ($existingItem) {
                        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$user_id, $product_id]);

                        // Remove from session wishlist
                        $index = array_search($product_id, $_SESSION['wishlist']);
                        if ($index !== false) {
                            unset($_SESSION['wishlist'][$index]);
                            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']); // Reindex
                        }
                        $response['in_wishlist'] = false;
                        $response['message'] = 'Product removed from wishlist';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                        $stmt->execute([$user_id, $product_id]);

                        // Add to session wishlist
                        if (!in_array($product_id, $_SESSION['wishlist'])) {
                            $_SESSION['wishlist'][] = $product_id;
                        }
                    
                        $response['in_wishlist'] = true;
                        $response['message'] = 'Product added to wishlist';
                    }

                    $response['success'] = true;
                    
                } catch (PDOException $e) {
                    $response['message'] = 'Database error';
                    error_log("Wishlist database error: " . $e->getMessage());
                }
        } else {
            // For non-logged in users, update session only
            $index = array_search($product_id, $_SESSION['wishlist']);
            if ($index !== false) {
                // Remove from wishlist
                unset($_SESSION['wishlist'][$index]);
                $_SESSION['wishlist'] = array_values($_SESSION['wishlist']); // Reindex
                $response['in_wishlist'] = false;
                $response['message'] = 'Product removed from wishlist';
            } else {
                // Add to wishlist
                $_SESSION['wishlist'][] = $product_id;
                $response['in_wishlist'] = true;
                $response['message'] = 'Product added to wishlist';
            }
            $response['success'] = true;
        }    
        
        break;
        
    case 'check':
        $response['in_wishlist'] = in_array($product_id, $_SESSION['wishlist']);
        $response['success'] = true;
        break;
        
    default:
        $response['message'] = 'Invalid action';
        break;
}

echo json_encode($response);