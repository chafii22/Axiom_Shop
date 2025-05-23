<?php
require '../config/connect_db.php';
// Start session
session_start();

// Check if already logged in
/*if (isset($_SESSION['user_id'])) {
    header("Location: ../home.php");
    exit();
}*/

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for failed login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Initialize variables
$login_successful = false; 
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; 
$is_locked = false;

// Check if account is temporarily locked
if ($_SESSION['login_attempts'] >= 5) {
    $time_passed = time() - $_SESSION['last_attempt_time'];
    $lock_duration = 1 * 60; // 15 minutes
    
    if ($time_passed < $lock_duration) {
        $is_locked = true;
        $lock_time_remaining = $lock_duration - $time_passed;
    } else {
        // Reset attempts after lock period
        $_SESSION['login_attempts'] = 0;
    }
}

// Process login form if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_locked) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission.";
    } else {
        // Include database connection
        require_once '../config/connect_db.php';
        
        $email = $_POST['email'];
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember']);
        $error = "";
        
        // Simple validation
        if (empty($email) || empty($password)) {
            $error = "Please fill in all fields";
        } else {
            $stmt = $pdo->prepare("SELECT id, email, password, username, is_admin FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verify password using password_verify()
                if (password_verify($password, $user['password'])) {
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];

                    $user_id = $user['id'];
                    $login_successful = true;
                    
                    // Check if user is admin
                    if ($user['is_admin'] == 1) {
                        $_SESSION['admin_id'] = $user['id'];
                    }
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
                        $stmt->execute([$token, $expiry, $user['id']]);
                        
                        setcookie('remember_token', $token, time() + 30*24*60*60, '/', '', true, true);
                    }
                    
                    // Redirect based on role
                    if (isset($_SESSION['admin_id'])) {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: ../home.php");
                    }
                    exit();
                } else {
                    // incorrect password
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    $error = "Invalid email or password";
                }
            } else {
                // Increment login attempts
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = "Invalid email or password";
            }
        }
    }
}

if ($login_successful) {
    // Get user's wishlist from database
    $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $db_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Initialize session wishlist if needed
    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }
    
    // Merge database wishlist with session wishlist
    $_SESSION['wishlist'] = array_unique(array_merge($_SESSION['wishlist'], $db_wishlist));
}

if ($user_id > 0) {
    try {
        // First check if wishlist table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'wishlist'")->rowCount() > 0;
        
        if ($tableExists) {
            // Get the user's saved wishlist items from wishlist table
            $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $db_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Initialize session wishlist if needed
            if (!isset($_SESSION['wishlist'])) {
                $_SESSION['wishlist'] = [];
            }
            
            // Merge the database wishlist with session wishlist
            $_SESSION['wishlist'] = array_unique(array_merge($_SESSION['wishlist'], $db_wishlist));
        }
        
        // Also check user_wishlist table as a fallback
        $tableExists = $pdo->query("SHOW TABLES LIKE 'user_wishlist'")->rowCount() > 0;
        
        if ($tableExists) {
            // Get the user's saved wishlist items from user_wishlist table
            $stmt = $pdo->prepare("SELECT product_id FROM user_wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $db_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Initialize session wishlist if needed
            if (!isset($_SESSION['wishlist'])) {
                $_SESSION['wishlist'] = [];
            }

            $_SESSION['wishlist'] = array_unique(array_merge($_SESSION['wishlist'], $db_wishlist));
        }
    } catch (PDOException $e) {
        error_log("Wishlist sync error: " . $e->getMessage());
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Noto Sans', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            background-image: url('../assets/background/frontsub.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .glass-card {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            padding-top: 1.25rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: white;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }
        
        .form-control:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .form-label {
            position: absolute;
            left: 0.75rem;
            top: 0.75rem;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .form-control:focus + .form-label,
        .form-control:not(:placeholder-shown) + .form-label {
            top: 0.25rem;
            left: 0.75rem;
            font-size: 0.75rem;
            color: white;
            opacity: 0.9;
        }
        
        /* Hide the placeholder when input is focused */
        .form-control:focus::placeholder {
            color: transparent;
        }
        
        .heading-font {
            font-family: 'Space Grotesk', sans-serif;
        }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="glass-card p-8 rounded-3xl w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white heading-font tracking-wider mb-2">SIGN IN</h1>
            <p class="text-white text-opacity-80">Access your Axiom account</p>
        </div>

        <!--show delete message -->
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="bg-green-500 bg-opacity-20 border border-green-200 text-white text-center p-4 rounded-lg mb-6">
                Your account has been successfully deleted.
            </div>
        <?php endif; ?>
        
        <?php if (isset($error) && !empty($error)): ?>
            <div class="bg-red-500 bg-opacity-30 border border-red-200 text-white text-center p-3 rounded-lg mb-6">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_locked): ?>
            <div class="bg-yellow-500 bg-opacity-30 border border-yellow-200 text-white text-center p-3 rounded-lg mb-6">
                Your account is temporarily locked. Please try again in <?php echo round($lock_time_remaining / 60); ?> minutes.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" <?php echo $is_locked ? 'class="opacity-50 pointer-events-none"' : ''; ?>>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <input type="email" name="email" id="email" class="form-control" placeholder=" " required <?php echo $is_locked ? 'disabled' : ''; ?>>
                <label for="email" class="form-label">Email Address</label>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" id="password" class="form-control" placeholder=" " required <?php echo $is_locked ? 'disabled' : ''; ?>>
                <label for="password" class="form-label">Password</label>
            </div>
            
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="mr-2 h-4 w-4 accent-white" <?php echo $is_locked ? 'disabled' : ''; ?>>
                    <label for="remember" class="text-white text-sm">Remember me</label>
                </div>
                
                <a href="forgot_password.php" class="text-white text-opacity-90 hover:text-opacity-100 text-sm transition">Forgot Password?</a>
            </div>
            
            <button type="submit" class="w-full py-3 bg-black hover:text-green-200 text-white font-bold uppercase rounded-lg transition-colors duration-300" <?php echo $is_locked ? 'disabled' : ''; ?>>
                Sign In
            </button>
        </form>
        
        <div class="text-center mt-6">
            <p class="text-white">
                Don't have an account? 
                <a href="register.php" class="font-bold underline hover:text-white transition">Sign Up</a>
            </p>
        </div>
    </div>
    
</body>
</html>