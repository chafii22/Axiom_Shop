<?php
// filepath: /shop-api/shop-api/api/services/ProductService.php

class ProductService {
    private $db;
    private $productModel;

    public function __construct($database) {
        $this->db = $database;
        $this->productModel = new Product($this->db);
    }

    public function getAllProducts($filters = []) {
        return $this->productModel->fetchAll($filters);
    }

    public function getProductById($id) {
        return $this->productModel->fetchById($id);
    }

    public function createProduct($data) {
        return $this->productModel->create($data);
    }

    public function updateProduct($id, $data) {
        return $this->productModel->update($id, $data);
    }

    public function deleteProduct($id) {
        return $this->productModel->delete($id);
    }
}
?>