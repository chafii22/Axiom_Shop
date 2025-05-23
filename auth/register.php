<?php
require '../config/connect_db.php';
session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$errors = [];
$success = false;
$username = '';
$email = '';
$is_admin = 0;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    // Admin-specific fields if applicable
    $admin_code = $_POST['admin_code'] ?? '';
    
    // Validation
    if (empty($username)) {
        $errors['username'] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors['username'] = "Username must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // Validate admin code if user is registering as admin
    if ($is_admin) {
        if (empty($admin_code) || $admin_code !== "ADMIN123") { // Replace with your actual admin code validation
            $errors['admin_code'] = "Invalid administrator code";
        }
    }
    
    // Check if username or email already exists
    if (!isset($errors['username']) && !isset($errors['email'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['username'] === $username) {
                $errors['username'] = "Username already taken";
            }
            if ($user['email'] === $email) {
                $errors['email'] = "Email already registered";
            }
        }
    }
    
    // If no errors, register the user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Set admin status (0 for regular user, 1 for admin)
            $admin_status = isset($_POST['is_admin']) ? 1 : 0;
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, is_admin, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $hashed_password, $admin_status]);
            
            // Set success message
            $success = true;
        } catch (PDOException $e) {
            $errors['db'] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Noto Sans', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            background-image: url('../assets/background/stockv2.jpg');
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

        .admin-fields {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        
        .admin-fields.active {
            max-height: 300px;
        }
        
        /* Style for error messages */
        .field-error {
            color: #ff6b6b;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        /* Custom checkbox styling */
        .custom-checkbox {
            display: flex;
            align-items: center;
        }
        
        .custom-checkbox input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0.25rem;
            margin-right: 0.5rem;
            position: relative;
            cursor: pointer;
        }
        
        .custom-checkbox input[type="checkbox"]:checked {
            background: rgba(255, 255, 255, 0.8);
        }
        
        .custom-checkbox input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            color: #000;
            font-size: 0.9rem;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
        }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="glass-card p-8 rounded-3xl w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white heading-font tracking-wider mb-2">SIGN UP</h1>
            <p class="text-white text-opacity-80">Create your Axiom account</p>
        </div>
        
        <?php if ($success): ?>
            <div class="bg-green-500 bg-opacity-30 border border-green-200 text-white text-center p-4 rounded-lg mb-6">
                Registration successful! You can now <a href="login.php" class="underline font-bold">login</a>.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors) && isset($errors['db'])): ?>
            <div class="bg-red-500 bg-opacity-30 border border-red-200 text-white text-center p-4 rounded-lg mb-6">
                <?php echo htmlspecialchars($errors['db']); ?>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="<?php echo $success ? 'opacity-50 pointer-events-none' : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <input type="text" name="username" id="username" class="form-control" placeholder=" " value="<?php echo htmlspecialchars($username); ?>" required>
                <label for="username" class="form-label">Username</label>
                <?php if (isset($errors['username'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['username']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="email" name="email" id="email" class="form-control" placeholder=" " value="<?php echo htmlspecialchars($email); ?>" required>
                <label for="email" class="form-label">Email Address</label>
                <?php if (isset($errors['email'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" id="password" class="form-control" placeholder=" " required>
                <label for="password" class="form-label">Password</label>
                <?php if (isset($errors['password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['password']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder=" " required>
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="custom-checkbox mb-6">
                <input type="checkbox" id="is_admin" name="is_admin" <?php echo $is_admin ? 'checked' : ''; ?>>
                <label for="is_admin" class="text-white cursor-pointer">I'm an administrator</label>
            </div>
            
            <div id="adminFields" class="admin-fields <?php echo $is_admin ? 'active' : ''; ?>">
                <div class="form-group">
                    <input type="password" name="admin_code" id="admin_code" class="form-control" placeholder=" ">
                    <label for="admin_code" class="form-label">Administrator Code</label>
                    <?php if (isset($errors['admin_code'])): ?>
                        <div class="field-error"><?php echo htmlspecialchars($errors['admin_code']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="w-full py-3 bg-black hover:text-green-200 text-white font-bold uppercase rounded-lg transition-colors duration-300">
                Create Account
            </button>
        </form>
        
        <div class="text-center mt-6">
            <p class="text-white">
                Already have an account? 
                <a href="login.php" class="font-bold underline hover:text-white transition">Sign In</a>
            </p>
        </div>
    </div>

    <script>
        // Toggle admin fields visibility
        document.addEventListener('DOMContentLoaded', function() {
            const adminCheckbox = document.getElementById('is_admin');
            const adminFields = document.getElementById('adminFields');
            
            adminCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    adminFields.classList.add('active');
                } else {
                    adminFields.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>