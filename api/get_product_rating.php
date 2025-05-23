
<?php
session_start();
require_once '../config/connect_db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode([
        'error' => 'Invalid product ID',
        'average' => 0,
        'count' => 0
    ]);
    exit;
}

// Get rating data
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM product_ratings WHERE product_id = ?");
$stmt->execute([$product_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'average' => $result['avg_rating'] ? (float)$result['avg_rating'] : 0,
    'count' => (int)$result['count']
]);