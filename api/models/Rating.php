<?php

class Rating {
    private $db;
    private $table = 'ratings';

    public function __construct($database) {
        $this->db = $database;
    }

    public function createRating($productId, $userId, $rating) {
        $query = "INSERT INTO " . $this->table . " (product_id, user_id, rating) VALUES (:product_id, :user_id, :rating)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':rating', $rating);
        return $stmt->execute();
    }

    public function getAverageRating($productId) {
        $query = "SELECT AVG(rating) as average, COUNT(rating) as count FROM " . $this->table . " WHERE product_id = :product_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserRating($productId, $userId) {
        $query = "SELECT rating FROM " . $this->table . " WHERE product_id = :product_id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function updateRating($productId, $userId, $rating) {
        $query = "UPDATE " . $this->table . " SET rating = :rating WHERE product_id = :product_id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':rating', $rating);
        return $stmt->execute();
    }

    public function deleteRating($productId, $userId) {
        $query = "DELETE FROM " . $this->table . " WHERE product_id = :product_id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }
}