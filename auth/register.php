<?php
require '../config/connect_db.php';
session_start();
require_once '../includes/email_functions.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$errors = [];
$success = false;
$username = '';
$email = '';
$first_name = '';
$last_name = '';
$is_admin = 0;
$is_supervisor = 0;
$message = '';
$admin_email_sent = false;

// Process admin code request (AJAX)
if (isset($_POST['request_admin_code']) && isset($_POST['admin_email'])) {
    $email = trim($_POST['admin_email']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
        exit;
    }

    require_once '../includes/email_templates.php';
    
    // Generate a code
    $code = generateRandomCode(6);
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Save code in database
    try {
        $emailBody = getAdminCodeEmailTemplate($code);
        
        if (sendEmail($email, 'Axiom Admin Registration Code', $emailBody)) {
            echo json_encode(['status' => 'success', 'message' => 'Registration code sent to your email']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send email. Please try again.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Validate inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = '';
    $last_name ='';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $account_type = $_POST['account_type'] ?? 'customer';
    $is_admin = ($account_type == 'admin') ? 1 : 0;
    $is_supervisor = ($account_type == 'supervisor') ? 1 : 0;
    
    // Code based on account type
    $supervisor_code = ($is_supervisor) ? $_POST['supervisor_code'] ?? '' : '';
    $admin_code = ($is_admin) ? $_POST['admin_code'] ?? '' : '';
    
    // Basic validation
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
    
    // Validate special codes based on account type
    if ($is_supervisor) {
        $supervisor_master_code = "SUPERVISOR_MASTER_2025"; // In production, store this securely
        if (empty($supervisor_code) || $supervisor_code !== $supervisor_master_code) {
            $errors['supervisor_code'] = "Invalid supervisor code";
        }
    } elseif ($is_admin) {
        // Validate admin code from database
        if (empty($admin_code)) {
            $errors['admin_code'] = "Admin code is required";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admin_registration_codes WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$email, $admin_code]);
            $valid_code = $stmt->fetch();
            
            if (!$valid_code) {
                $errors['admin_code'] = "Invalid or expired admin code";
            }
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
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, password, is_admin, is_supervisor, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $first_name, $last_name, $hashed_password, $is_admin, $is_supervisor]);
            
            // If admin, mark the code as used
            if ($is_admin) {
                $stmt = $pdo->prepare("UPDATE admin_registration_codes SET used = 1 WHERE email = ? AND code = ?");
                $stmt->execute([$email, $admin_code]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $success = true;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors['db'] = "Registration failed: " . $e->getMessage();
        }
    }
}

// Helper function to generate random code
function generateRandomCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNPQRSTUVWXYZ'; // Removed O and 0 to avoid confusion
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        .modal-card {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            color: white;
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

        .role-fields {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        
        .role-fields.active {
            max-height: 300px;
        }
        
        /* Style for error messages */
        .field-error {
            color: #ff6b6b;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        /* Custom radio styling */
        .custom-radio-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .custom-radio {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .custom-radio.active {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .custom-radio input[type="radio"] {
            display: none;
        }
        
        .custom-radio .icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .custom-radio .label {
            font-weight: 600;
        }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="glass-card p-8 rounded-3xl w-full max-w-md mx-4 my-8">
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
            <input type="hidden" name="register" value="1">
            
            <!-- Account Type Selection -->
            <div class="custom-radio-group mb-6">
                <label class="custom-radio active" id="customerRadio">
                    <input type="radio" name="account_type" value="customer" checked>
                    <div class="icon"><i class="fas fa-user"></i></div>
                    <div class="label">Customer</div>
                </label>
                
                <label class="custom-radio" id="adminRadio">
                    <input type="radio" name="account_type" value="admin">
                    <div class="icon"><i class="fas fa-user-cog"></i></div>
                    <div class="label">Admin</div>
                </label>
                
                <label class="custom-radio" id="supervisorRadio">
                    <input type="radio" name="account_type" value="supervisor">
                    <div class="icon"><i class="fas fa-user-shield"></i></div>
                    <div class="label">Supervisor</div>
                </label>
            </div>
            
            <!-- Basic Information -->
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
            
            <!-- Admin-specific fields -->
            <div id="adminFields" class="role-fields">
                <div class="form-group">
                    <input type="text" name="admin_code" id="admin_code" class="form-control" placeholder=" ">
                    <label for="admin_code" class="form-label">Admin Code</label>
                    <?php if (isset($errors['admin_code'])): ?>
                        <div class="field-error"><?php echo htmlspecialchars($errors['admin_code']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-center mb-4">
                    <button type="button" id="requestAdminCode" class="text-sm text-blue-400 hover:text-blue-300">
                        I don't have a code – Request one
                    </button>
                </div>
            </div>
            
            <!-- Supervisor-specific fields -->
            <div id="supervisorFields" class="role-fields">
                <div class="form-group">
                    <input type="password" name="supervisor_code" id="supervisor_code" class="form-control" placeholder=" ">
                    <label for="supervisor_code" class="form-label">Supervisor Code</label>
                    <?php if (isset($errors['supervisor_code'])): ?>
                        <div class="field-error"><?php echo htmlspecialchars($errors['supervisor_code']); ?></div>
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
    
    <!-- Admin Code Request Modal -->
    <div id="adminCodeModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="modal-card p-6 rounded-xl max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold heading-font">Request Admin Code</h3>
                <button id="closeCodeModal" class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalMessage" class="mb-4 hidden"></div>
            <form id="adminCodeForm">
                <div class="mb-4">
                    <label for="admin_email_request" class="block text-sm font-medium text-gray-300 mb-1">Your Email Address</label>
                    <input type="email" id="admin_email_request" name="admin_email" class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white" 
                           required autocomplete="email">
                    <p class="text-xs text-white/60 mt-1">We'll send your admin registration code to this email</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelCodeRequest" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg">
                        Send Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Account type selection
            const accountTypes = document.querySelectorAll('input[name="account_type"]');
            const customerRadio = document.getElementById('customerRadio');
            const adminRadio = document.getElementById('adminRadio');
            const supervisorRadio = document.getElementById('supervisorRadio');
            const adminFields = document.getElementById('adminFields');
            const supervisorFields = document.getElementById('supervisorFields');
            
            function updateAccountTypeUI() {
                const selectedValue = document.querySelector('input[name="account_type"]:checked').value;
                
                // Reset all
                customerRadio.classList.remove('active');
                adminRadio.classList.remove('active');
                supervisorRadio.classList.remove('active');
                adminFields.classList.remove('active');
                supervisorFields.classList.remove('active');
                
                // Set active based on selection
                if (selectedValue === 'customer') {
                    customerRadio.classList.add('active');
                } else if (selectedValue === 'admin') {
                    adminRadio.classList.add('active');
                    adminFields.classList.add('active');
                } else if (selectedValue === 'supervisor') {
                    supervisorRadio.classList.add('active');
                    supervisorFields.classList.add('active');
                }
            }
            
            accountTypes.forEach(radio => {
                radio.addEventListener('change', updateAccountTypeUI);
            });
            
            // Visual feedback when clicking the label
            customerRadio.addEventListener('click', function() {
                document.querySelector('input[name="account_type"][value="customer"]').checked = true;
                updateAccountTypeUI();
            });
            
            adminRadio.addEventListener('click', function() {
                document.querySelector('input[name="account_type"][value="admin"]').checked = true;
                updateAccountTypeUI();
            });
            
            supervisorRadio.addEventListener('click', function() {
                document.querySelector('input[name="account_type"][value="supervisor"]').checked = true;
                updateAccountTypeUI();
            });
            
            // Admin code request modal
            const requestAdminCode = document.getElementById('requestAdminCode');
            const adminCodeModal = document.getElementById('adminCodeModal');
            const closeCodeModal = document.getElementById('closeCodeModal');
            const cancelCodeRequest = document.getElementById('cancelCodeRequest');
            const adminCodeForm = document.getElementById('adminCodeForm');
            const modalMessage = document.getElementById('modalMessage');
            
            // Open modal
            requestAdminCode.addEventListener('click', function() {
                adminCodeModal.classList.remove('hidden');
                document.getElementById('admin_email_request').focus();
                // Auto-fill with the email from the main form if it exists
                const mainEmail = document.getElementById('email').value;
                if (mainEmail) {
                    document.getElementById('admin_email_request').value = mainEmail;
                }
            });
            
            // Close modal functions
            function closeModal() {
                adminCodeModal.classList.add('hidden');
                modalMessage.classList.add('hidden');
                adminCodeForm.reset();
            }
            
            closeCodeModal.addEventListener('click', closeModal);
            cancelCodeRequest.addEventListener('click', closeModal);
            
            // Handle admin code request with AJAX
            adminCodeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const email = document.getElementById('admin_email_request').value;
                const formData = new FormData();
                formData.append('request_admin_code', '1');
                formData.append('admin_email', email);
                
                // Show loading state
                modalMessage.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-5 w-5 border-t-2 border-white"></div><span class="ml-2">Sending code...</span></div>';
                modalMessage.classList.remove('hidden');
                
                fetch('register.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        modalMessage.innerHTML = '<div class="bg-green-900/40 border border-green-500 text-green-200 px-4 py-3 rounded">' + data.message + '</div>';
                        document.getElementById('admin_email_request').disabled = true;
                        
                        // Auto-fill the email in the main form
                        document.getElementById('email').value = email;
                        
                        // Auto-close after 3 seconds
                        setTimeout(function() {
                            closeModal();
                        }, 3000);
                    } else {
                        modalMessage.innerHTML = '<div class="bg-red-900/40 border border-red-500 text-red-200 px-4 py-3 rounded">' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    modalMessage.innerHTML = '<div class="bg-red-900/40 border border-red-500 text-red-200 px-4 py-3 rounded">Error processing request. Please try again.</div>';
                });
            });
        });
    </script>
</body>
</html>