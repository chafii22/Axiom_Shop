
<?php
session_start();
require_once '../config/connect_db.php';

header('Content-Type: application/json');

// Get params
$category_id = isset($_GET['category_id']) ? $_GET['category_id'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$products_per_page = 8; // Number of products per page
$offset = ($page - 1) * $products_per_page;

// Prepare the count query
$count_query = "SELECT COUNT(*) FROM products";
$count_params = [];

// Add category filter if category_id is set
if ($category_id) {
    $count_query .= " WHERE category_id = :category_id";
    $count_params[':category_id'] = $category_id;
}

// Execute the query
$stmt = $pdo->prepare($count_query);
$stmt->execute($count_params);
$total_products = $stmt->fetchColumn();

// Calculate total pages
$total_pages = ceil($total_products / $products_per_page);

// Check if there are more products
$has_more = $page < $total_pages;

echo json_encode([
    'has_more' => $has_more,
    'total_products' => $total_products,
    'total_pages' => $total_pages,
    'current_page' => $page
]);