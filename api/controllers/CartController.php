<?php

require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../../config/connect_db.php';
require( __DIR__ . '/response.php');

class CartController {
    private $cartModel;
    private $db;

    public function __construct() {
        global $pdo;
        $this->db = $pdo;
        $this->cartModel = new Cart($this->db);
    }

    public function getCartCount($userId = null) {
        if (!$userId) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
        }
        
        $cartItems = $this->cartModel->getCartItems($userId);
        $cartCount = 0;
        foreach ($cartItems as $item) {
            $cartCount += $item['quantity'];
        }
        
        return $cartCount;
    }

    
    public function addToCart() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Log user ID for debugging
        error_log("User ID from session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
        
        // Make sure user_id is an integer if that's what the database expects
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        
        // If not logged in, use session ID for guests
        if ($userId === null) {
            $userId = session_id();
        } else {
            // Verify user exists in clients table
            $checkUserQuery = "SELECT id FROM users WHERE id = ?";
            $checkUserStmt = $this->db->prepare($checkUserQuery);
            $checkUserStmt->execute([$userId]);
            
            if ($checkUserStmt->rowCount() === 0) {
                // User doesn't exist in clients table
                Response::error('Invalid user account', 403);
                return;
            }
        }
        
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        if (empty($productId)) {
            Response::error('Product ID is required');
            return;
        }
        
        try {
            $result = $this->cartModel->addItem($userId, $productId, $quantity);
            
            if ($result) {
                $cartCount = $this->getCartCount($userId);
                Response::success(['cart_count' => $cartCount], 'Item added to cart successfully');
                

                       } else {
                Response::error('Failed to add item to cart');
            }
        } catch (PDOException $e) {
            error_log("Cart error: " . $e->getMessage());
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function removeFromCart() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        
        if (empty($productId)) {
            Response::error('Product ID is required');
            return;
        }

        $result = $this->cartModel->removeItem($userId, $productId);
        
        if ($result) {
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
            // Get updated cart count
            
            $cartCount = $this->getCartCount($userId);
            
            Response::success(['cart_count' => $cartCount], 'Item removed from cart successfully');
        } else {
            Response::error('Failed to remove item from cart');
        }
    }

    public function getCart() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
        $cartItems = $this->cartModel->getCartItems($userId);

        $total_price = 0;
        foreach ($cartItems as $item) {
            $total_price += $item['price'] * $item['quantity'];
        }
        
        Response::success([
            'items' => $cartItems,
            'total_price' => $total_price,
            'cart_count' => $this->getCartCount($userId)
        ], 'Cart retrieved successfully');
    }

    public function clearCart() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
        $result = $this->cartModel->clearCart($userId);
        
        if ($result) {
            Response::success([], 'Cart cleared successfully');
        } else {
            Response::error('Failed to clear cart');
        }
    }

    public function updateQuantity() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $change = isset($_POST['change']) ? (int)$_POST['change'] : 0;
        
        if (empty($productId)) {
            Response::error('Product ID is required');
            return;
        }

        // Get current quantity
        $cartItems = $this->cartModel->getCartItems($userId);
        $currentQuantity = 0;
        
        foreach ($cartItems as $item) {
            if ($item['product_id'] == $productId) {
                $currentQuantity = $item['quantity'];
                break;
            }
        }
        $newQuantity = $currentQuantity + $change;
        
        if ($newQuantity <= 0) {
            // Remove item if quantity becomes zero or negative
            $result = $this->cartModel->removeItem($userId, $productId);
            $message = 'Item removed from cart';
        } else {
            // Update quantity
            $result = $this->cartModel->updateQuantity($userId, $productId, $newQuantity);
            $message = 'Cart updated';
        }
        
        if ($result) {
            if (isset($_SESSION['cart'][$productId])) {
                if ($newQuantity <= 0) {
                    unset($_SESSION['cart'][$productId]);
                } else {
                    $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
                }
            }
            // Get updated cart count
            
            $cartCount = $this->getCartCount($userId);
            
            Response::success(['cart_count' => $cartCount], $message);
        } else {
            Response::error('Failed to update cart');
        }
    }
}
?>