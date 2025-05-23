<?php

class Wishlist {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function addToWishlist($userId, $productId) {
        $query = "INSERT INTO wishlist (user_id, product_id) VALUES (:user_id, :product_id)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        return $stmt->execute();
    }

    public function removeFromWishlist($userId, $productId) {
        $query = "DELETE FROM wishlist WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        return $stmt->execute();
    }

    public function getWishlist($userId) {
        $query = "SELECT product_id FROM wishlist WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}