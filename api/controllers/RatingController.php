<?php

class RatingController {
    private $ratingModel;

    public function __construct() {
        // Include the Rating model
        require_once '../models/Rating.php';
        $this->ratingModel = new Rating();
    }

    public function submitRating($productId, $rating) {
        // Validate input
        if (!$this->validateRating($rating)) {
            return $this->response(400, 'Invalid rating value.');
        }

        // Submit the rating
        $result = $this->ratingModel->addRating($productId, $rating);
        if ($result) {
            return $this->response(201, 'Rating submitted successfully.');
        } else {
            return $this->response(500, 'Failed to submit rating.');
        }
    }

    public function getAverageRating($productId) {
        // Fetch average rating
        $averageRating = $this->ratingModel->getAverageRating($productId);
        if ($averageRating !== null) {
            return $this->response(200, 'Average rating fetched successfully.', $averageRating);
        } else {
            return $this->response(404, 'Product not found.');
        }
    }

    private function validateRating($rating) {
        return is_numeric($rating) && $rating >= 1 && $rating <= 5;
    }

    private function response($statusCode, $message, $data = null) {
        http_response_code($statusCode);
        return json_encode([
            'status' => $statusCode,
            'message' => $message,
            'data' => $data
        ]);
    }
}