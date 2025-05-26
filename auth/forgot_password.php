<?php
session_start();
require_once '../config/connect_db.php';

$error = '';
$success = '';
$step = 1; // Step 1: Enter username, Step 2: Create new password (simplified flow)

// Process Step 1: Username verification
if (isset($_POST['find_account']) && !empty($_POST['username'])) {
    $username = trim($_POST['username']);
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // User found, proceed directly to password reset (Step 2)
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_username'] = $user['username'];
        $step = 2; // Go directly to password reset
    } else {
        $error = "No account found with that username.";
    }
}

// Process Step 2: Set new password (was previously Step 3)
if (isset($_POST['reset_password']) && isset($_SESSION['reset_user_id'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
        
        // Success - clear session variables
        $success = "Password has been reset successfully. You can now log in with your new password.";
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_username']);
        $step = 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Axiom Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Noto Sans', sans-serif;
            background-color: #0f172a;
            position: relative;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.2;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                linear-gradient(to right, rgba(255, 255, 255, 0.05) 2px, transparent 2px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 2px, transparent 2px);
            background-size: 
                20px 30px,
                30px 20px,
                100px 100px,
                100px 100px;
            pointer-events: none;
        }

        .heading-font {
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-input:focus {
            background: rgba(255, 255, 255, 0.15);
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body class="text-white">
    <div class="min-h-screen flex flex-col justify-center items-center p-4">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold heading-font mb-2">Axiom Shop</h1>
            <p class="text-white/70">Password Recovery</p>
        </div>
        
        <div class="glass-card w-full max-w-md p-8 rounded-xl">
            <?php if (!empty($error)): ?>
                <div class="bg-red-500/20 border border-red-500/30 text-red-100 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-500/20 border border-green-500/30 text-green-100 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <!-- Step 1: Enter Username -->
                <h2 class="text-xl font-semibold mb-4">Find Your Account</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="username" class="block text-white/70 mb-2">Enter your username</label>
                        <input type="text" id="username" name="username" required
                               class="form-input w-full p-3 rounded-lg focus:ring-2 focus:ring-blue-500/50">
                    </div>
                    <div class="flex justify-between items-center">
                        <a href="login.php" class="text-blue-400 hover:underline">Back to login</a>
                        <button type="submit" name="find_account" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition-colors">
                            Next
                        </button>
                    </div>
                </form>
            
            <?php elseif ($step == 2): ?> <!-- This was Step 3 before, now it's Step 2 -->
                <!-- Step 2: Set New Password (was previously Step 3) -->
                <h2 class="text-xl font-semibold mb-4">Create New Password</h2>
                <p class="mb-4 text-white/70">
                    Enter a new password for your account: <span class="font-medium text-white"><?php echo htmlspecialchars($_SESSION['reset_username']); ?></span>
                </p>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="password" class="block text-white/70 mb-2">New Password</label>
                        <input type="password" id="password" name="password" required minlength="8"
                               class="form-input w-full p-3 rounded-lg focus:ring-2 focus:ring-blue-500/50">
                        <p class="text-xs text-white/50 mt-1">At least 8 characters</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-white/70 mb-2">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="form-input w-full p-3 rounded-lg focus:ring-2 focus:ring-blue-500/50">
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <a href="forgot_password.php" class="text-blue-400 hover:underline">Cancel</a>
                        <button type="submit" name="reset_password" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition-colors">
                            Reset Password
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>