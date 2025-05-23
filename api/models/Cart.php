<?php
class Cart {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function addItem($userId, $productId, $quantity) {
        // Check if item already exists in cart
        $checkQuery = "SELECT quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':user_id', $userId);
        $checkStmt->bindParam(':product_id', $productId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Item exists, update quantity
            $currentItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $newQuantity = $currentItem['quantity'] + $quantity;
            
            $query = "UPDATE cart SET quantity = :quantity WHERE user_id = :user_id AND product_id = :product_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':quantity', $newQuantity);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':product_id', $productId);
        } else {
            // Item doesn't exist, insert new
            $query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':quantity', $quantity);
        }
        
        return $stmt->execute();
    }

    public function removeItem($userId, $productId) {
        $query = "DELETE FROM cart WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        return $stmt->execute();
    }

    public function getCartItems($userId) {
        $query = "SELECT c.*, p.name, p.price, p.image 
                 FROM cart c
                 JOIN products p ON c.product_id = p.id
                 WHERE c.user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clearCart($userId) {
        $query = "DELETE FROM cart WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }
    
    public function updateQuantity($userId, $productId, $quantity) {
        $query = "UPDATE cart SET quantity = :quantity WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':quantity', $quantity);
        return $stmt->execute();
    }
}
?>