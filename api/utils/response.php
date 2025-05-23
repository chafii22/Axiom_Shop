
<?php
class Response {
    /**
     * Send a success response
     * @param array $data Data to include in response
     * @param string $message Success message
     */
    public static function success($data = [], $message = 'Success') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
    
    /**
     * Send an error response
     * @param string $message Error message
     * @param int $code HTTP status code
     */
    public static function error($message = 'Error', $code = 400) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    }
}