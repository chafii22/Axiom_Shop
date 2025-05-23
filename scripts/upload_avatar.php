
<?php
// Start session
session_start();

// Check for user authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../config/connect_db.php';

// Process the uploaded file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['avatar'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "Upload failed. Error code: " . $file['error'];
        header("Location: ../user/profile.php?tab=profile");
        exit();
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error_message'] = "Only JPG, PNG, GIF, and WEBP images are allowed.";
        header("Location: ../user/profile.php?tab=profile");
        exit();
    }
    
    // Validate file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB in bytes
    if ($file['size'] > $max_size) {
        $_SESSION['error_message'] = "File size must be less than 2MB.";
        header("Location: ../user/profile.php?tab=profile");
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/avatars/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = $user_id . '_' . time() . '.' . $file_extension;
    $destination = $upload_dir . $new_filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Update user record in database
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        
        if ($stmt->execute([$new_filename, $user_id])) {
            $_SESSION['success_message'] = "Profile picture updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update profile picture in database.";
            // Delete uploaded file if database update fails
            if (file_exists($destination)) {
                unlink($destination);
            }
        }
    } else {
        $_SESSION['error_message'] = "Failed to upload file. Please try again.";
    }
}

// Redirect back to profile page
header("Location: ../user/profile.php?tab=profile");
exit();