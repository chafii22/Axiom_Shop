<?php
session_start();
require_once '../config/connect_db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to rate products';
    echo json_encode($response);
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Validate parameters
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

// Validate rating
if ($rating < 1 || $rating > 5) {
    $response['message'] = 'Invalid rating value';
    echo json_encode($response);
    exit;
}

// Validate product ID
if ($product_id <= 0) {
    $response['message'] = 'Invalid product ID';
    echo json_encode($response);
    exit;
}

try {
    // Check if product exists
    $product_check = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $product_check->execute([$product_id]);
    
    if (!$product_check->fetch()) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit;
    }
    
    // Insert or update rating
    $stmt = $pdo->prepare("
        INSERT INTO product_ratings (product_id, user_id, rating) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = ?
    ");
    
    $stmt->execute([$product_id, $user_id, $rating, $rating]);
    
    // Get updated average rating
    $avg_stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as count 
        FROM product_ratings 
        WHERE product_id = ?
    ");
    
    $avg_stmt->execute([$product_id]);
    $result = $avg_stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'message' => 'Rating submitted successfully',
        'average' => round($result['avg_rating'], 1),
        'count' => (int)$result['count']
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);