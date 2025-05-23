<?php

class WishlistController {
    private $wishlistModel;

    public function __construct() {
        // Include the Wishlist model
        require_once '../models/Wishlist.php';
        $this->wishlistModel = new Wishlist();
    }

    public function addToWishlist($productId, $userId) {
        // Validate input
        if (empty($productId) || empty($userId)) {
            return Response::json(['message' => 'Product ID and User ID are required.'], 400);
        }

        // Add product to wishlist
        $result = $this->wishlistModel->addProduct($productId, $userId);
        if ($result) {
            return Response::json(['message' => 'Product added to wishlist successfully.'], 200);
        } else {
            return Response::json(['message' => 'Failed to add product to wishlist.'], 500);
        }
    }

    public function removeFromWishlist($productId, $userId) {
        // Validate input
        if (empty($productId) || empty($userId)) {
            return Response::json(['message' => 'Product ID and User ID are required.'], 400);
        }

        // Remove product from wishlist
        $result = $this->wishlistModel->removeProduct($productId, $userId);
        if ($result) {
            return Response::json(['message' => 'Product removed from wishlist successfully.'], 200);
        } else {
            return Response::json(['message' => 'Failed to remove product from wishlist.'], 500);
        }
    }

    public function viewWishlist($userId) {
        // Validate input
        if (empty($userId)) {
            return Response::json(['message' => 'User ID is required.'], 400);
        }

        // Fetch wishlist items
        $items = $this->wishlistModel->getWishlistItems($userId);
        return Response::json(['wishlist' => $items], 200);
    }
}