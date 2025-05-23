
<?php
// Add error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log access to the API
error_log('Cart API called with action: ' . (isset($_REQUEST['action']) ? $_REQUEST['action'] : 'none'));

require_once __DIR__ . '/controllers/CartController.php';

// Set JSON content type
header('Content-Type: application/json');

// Handle different actions
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$controller = new CartController();

try {
    switch ($action) {
        case 'add':
            $controller->addToCart();
            break;
            
        case 'remove':
            $controller->removeFromCart();
            break;
            
        case 'update':
            $controller->updateQuantity();
            break;
            
        case 'clear':
            $controller->clearCart();
            break;
            
        case 'get':
            $controller->getCart();
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    error_log('Error in cart_api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>