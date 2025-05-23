<?php

class ProductController {
    private $productService;

    public function __construct() {
        $this->productService = new ProductService();
    }

    public function getProducts($request) {
        $products = $this->productService->fetchAllProducts();
        Response::send($products);
    }

    public function getProduct($request, $id) {
        $product = $this->productService->fetchProductById($id);
        if ($product) {
            Response::send($product);
        } else {
            Response::send(['message' => 'Product not found'], 404);
        }
    }

    public function createProduct($request) {
        $data = json_decode($request->getBody(), true);
        $validationResult = Validator::validateProductData($data);
        
        if ($validationResult['isValid']) {
            $newProduct = $this->productService->createProduct($data);
            Response::send($newProduct, 201);
        } else {
            Response::send(['message' => $validationResult['errors']], 400);
        }
    }

    public function updateProduct($request, $id) {
        $data = json_decode($request->getBody(), true);
        $validationResult = Validator::validateProductData($data);
        
        if ($validationResult['isValid']) {
            $updatedProduct = $this->productService->updateProduct($id, $data);
            if ($updatedProduct) {
                Response::send($updatedProduct);
            } else {
                Response::send(['message' => 'Product not found'], 404);
            }
        } else {
            Response::send(['message' => $validationResult['errors']], 400);
        }
    }

    public function deleteProduct($request, $id) {
        $deleted = $this->productService->deleteProduct($id);
        if ($deleted) {
            Response::send(['message' => 'Product deleted successfully']);
        } else {
            Response::send(['message' => 'Product not found'], 404);
        }
    }
}