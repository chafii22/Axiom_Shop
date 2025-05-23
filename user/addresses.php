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

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    // If user doesn't exist or is an admin
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Get user addresses
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Handle adding a new address
if (isset($_POST['action']) && $_POST['action'] === 'add_address') {
    $name = $_POST['name'] ?? '';
    $street_address = $_POST['street_address'] ?? '';
    $street_address_2 = $_POST['street_address_2'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $country = $_POST['country'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // If this is set as default, unset all other default addresses
    if ($is_default) {
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, full_name, street_address, street_address_2, city, state, zip_code, country, phone, is_default) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([
        $user_id, $name, $street_address, $street_address_2, $city, $state, $zip_code, $country, $phone, $is_default
    ])) {
        $_SESSION['success_message'] = "Address added successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to add address.";
    }
    
    header("Location: addresses.php");
    exit();
}

// Handle updating an address
if (isset($_POST['action']) && $_POST['action'] === 'update_address') {
    $address_id = $_POST['address_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $street_address = $_POST['street_address'] ?? '';
    $street_address_2 = $_POST['street_address_2'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $country = $_POST['country'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // If this is set as default, unset all other default addresses
    if ($is_default) {
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    $stmt = $pdo->prepare("UPDATE user_addresses SET 
                            full_name = ?, 
                            street_address = ?, 
                            street_address_2 = ?, 
                            city = ?, 
                            state = ?, 
                            zip_code = ?, 
                            country = ?, 
                            phone = ?, 
                            is_default = ? 
                          WHERE id = ? AND user_id = ?");
                          
    if ($stmt->execute([
        $name, $street_address, $street_address_2, $city, $state, $zip_code, $country, $phone, $is_default, $address_id, $user_id
    ])) {
        $_SESSION['success_message'] = "Address updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update address.";
    }
    
    header("Location: addresses.php");
    exit();
}

// Handle setting default address
if (isset($_GET['set_default']) && is_numeric($_GET['set_default'])) {
    $address_id = $_GET['set_default'];
    
    // First, unset all default addresses
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Then set the selected one as default
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$address_id, $user_id])) {
        $_SESSION['success_message'] = "Default address updated!";
    } else {
        $_SESSION['error_message'] = "Failed to update default address.";
    }
    
    header("Location: addresses.php");
    exit();
}

// Handle deleting an address
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $address_id = $_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$address_id, $user_id])) {
        $_SESSION['success_message'] = "Address deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete address.";
    }
    
    header("Location: addresses.php");
    exit();
}

// Handle editing an address - fetch data for form
$editAddress = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $address_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$address_id, $user_id]);
    $editAddress = $stmt->fetch();
    
    if (!$editAddress) {
        $_SESSION['error_message'] = "Address not found.";
        header("Location: addresses.php");
        exit();
    }
}

// Should we show the add address form?
$showAddForm = isset($_GET['add']) && $_GET['add'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Addresses | Axiom</title>
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
            background-position:
                0 0,
                0 0,
                -1px -1px,
                -3px -3px;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.05;
            background-image: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.3) 0, rgba(255, 255, 255, 0.3) 1px, transparent 1px, transparent 40px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.3) 0, rgba(255, 255, 255, 0.3) 1px, transparent 1px, transparent 80px);
            pointer-events: none;
        }
    
        .heading-font {
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .glass-card {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .glass-sidebar {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .menu-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid rgba(255, 255, 255, 0.5);
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid white;
        }
        
        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-input:focus {
            border-color: rgba(255, 255, 255, 0.5);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.25);
        }
    </style>
</head>
<body class="text-white min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="glass-sidebar w-64 p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <i class="fas fa-gem text-white"></i>
                </div>
                <h1 class="text-xl font-bold heading-font tracking-wide">Axiom</h1>
            </div>
            
            <div class="flex-1">
                <a href="account.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-user w-5 text-center"></i>
                    <span>My Account</span>
                </a>
                
                <a href="orders.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-shopping-bag w-5 text-center"></i>
                    <span>My Orders</span>
                </a>
                
                <a href="wishlist.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-heart w-5 text-center"></i>
                    <span>Wishlist</span>
                </a>
                
                <a href="addresses.php" class="menu-item active flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-map-marker-alt w-5 text-center"></i>
                    <span>My Addresses</span>
                </a>
                
                <a href="profile.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span>Account Settings</span>
                </a>
            </div>
            
            <div class="mt-auto">
                <a href="../home.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg mb-2">
                    <i class="fas fa-home w-5 text-center"></i>
                    <span>Return to home</span>
                </a>
                
                <a href="../auth/logout.php" class="menu-item flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-red-500/20">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Top Bar -->
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold heading-font">My Addresses</h2>
                
                <div class="flex items-center gap-6">
                    
                    
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center overflow-hidden">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Profile picture" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="text-xs text-white/70">Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status Message Display -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-500/20 border border-green-500/50 text-green-300 px-4 py-3 rounded mb-6 flex items-center justify-between">
                    <p><?= $_SESSION['success_message'] ?></p>
                    <button onclick="this.parentElement.style.display='none'" class="text-green-300 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded mb-6 flex items-center justify-between">
                    <p><?= $_SESSION['error_message'] ?></p>
                    <button onclick="this.parentElement.style.display='none'" class="text-red-300 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Address Management Section -->
            <div class="glass-card p-6 rounded-xl mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold heading-font">Address Book</h3>
                    
                    <?php if (!$showAddForm && !$editAddress): ?>
                    <a href="?add=1" class="px-4 py-2 bg-white/10 hover:bg-white/20 transition rounded-lg flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        <span>Add New Address</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Add/Edit Address Form -->
                <?php if ($showAddForm || $editAddress): ?>
                    <div class="mb-8 bg-black/20 p-6 rounded-lg">
                        <h4 class="text-lg font-medium mb-4">
                            <?= $editAddress ? 'Edit Address' : 'Add New Address' ?>
                        </h4>
                        
                        <form method="post" action="addresses.php">
                            <input type="hidden" name="action" value="<?= $editAddress ? 'update_address' : 'add_address' ?>">
                            <?php if ($editAddress): ?>
                                <input type="hidden" name="address_id" value="<?= $editAddress['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="name" class="block text-sm mb-1 opacity-70">Address Name / Label</label>
                                    <input type="text" id="name" name="name" value="<?= $editAddress ? htmlspecialchars($editAddress['full_name']) : '' ?>" 
                                           placeholder="Home, Work, etc." required
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm mb-1 opacity-70">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?= $editAddress ? htmlspecialchars($editAddress['phone']) : '' ?>" 
                                           placeholder="Phone number for this address"
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="street_address" class="block text-sm mb-1 opacity-70">Street Address</label>
                                <input type="text" id="street_address" name="street_address" value="<?= $editAddress ? htmlspecialchars($editAddress['street_address']) : '' ?>" 
                                       placeholder="Street address or P.O. Box" required
                                       class="w-full px-3 py-2 rounded form-input">
                            </div>
                            
                            <div class="mb-4">
                                <label for="street_address_2" class="block text-sm mb-1 opacity-70">Apartment, Suite, etc. (optional)</label>
                                <input type="text" id="street_address_2" name="street_address_2" value="<?= $editAddress ? htmlspecialchars($editAddress['street_address_2']) : '' ?>" 
                                       placeholder="Apartment, suite, unit, building, floor, etc."
                                       class="w-full px-3 py-2 rounded form-input">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label for="city" class="block text-sm mb-1 opacity-70">City</label>
                                    <input type="text" id="city" name="city" value="<?= $editAddress ? htmlspecialchars($editAddress['city']) : '' ?>" 
                                           placeholder="City" required
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                                
                                <div>
                                    <label for="state" class="block text-sm mb-1 opacity-70">State/Province</label>
                                    <input type="text" id="state" name="state" value="<?= $editAddress ? htmlspecialchars($editAddress['state']) : '' ?>" 
                                           placeholder="State or province" required
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                                
                                <div>
                                    <label for="zip_code" class="block text-sm mb-1 opacity-70">Zip/Postal Code</label>
                                    <input type="text" id="zip_code" name="zip_code" value="<?= $editAddress ? htmlspecialchars($editAddress['zip_code']) : '' ?>" 
                                           placeholder="ZIP or postal code" required
                                           class="w-full px-3 py-2 rounded form-input">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="country" class="block text-sm mb-1 opacity-70">Country</label>
                                <select id="country" name="country" required class="w-full px-3 py-2 rounded form-input">
                                    <?php 
                                    $countries = [
                                        'United States', 'Canada', 'United Kingdom', 'Australia', 
                                        'Germany', 'France', 'Japan', 'China', 'Brazil', 'Mexico'
                                    ];
                                    $selectedCountry = $editAddress ? $editAddress['country'] : '';
                                    foreach ($countries as $country): ?>
                                        <option value="<?= $country ?>" <?= $selectedCountry === $country ? 'selected' : '' ?>>
                                            <?= $country ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_default" <?= ($editAddress && $editAddress['is_default']) ? 'checked' : '' ?>
                                           class="w-4 h-4">
                                    <span>Set as default address</span>
                                </label>
                            </div>
                            
                            <div class="flex gap-3">
                                <button type="submit" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded transition">
                                    <?= $editAddress ? 'Update Address' : 'Save Address' ?>
                                </button>
                                <a href="addresses.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Address List -->
                <?php if (empty($addresses)): ?>
                    <div class="text-center py-12 opacity-70">
                        <i class="fas fa-map-marker-alt text-4xl mb-4"></i>
                        <p class="text-xl mb-4">No addresses saved yet</p>
                        <?php if (!$showAddForm): ?>
                            <a href="?add=1" class="px-4 py-2 bg-white/10 hover:bg-white/20 transition rounded-lg inline-block">
                                Add Your First Address
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($addresses as $address): ?>
                            <div class="bg-white/10 rounded-lg p-5 relative">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-semibold"><?= htmlspecialchars($address['full_name']) ?></h4>
                                        <?php if ($address['is_default']): ?>
                                            <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <a href="?edit=<?= $address['id'] ?>" class="text-white/70 hover:text-white" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?= $address['id'] ?>" onclick="return confirm('Are you sure you want to delete this address?')" 
                                           class="text-white/70 hover:text-red-400" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="text-sm opacity-80 mb-4">
                                    <?= htmlspecialchars($address['street_address']) ?><br>
                                    <?php if (!empty($address['street_address_2'])): ?>
                                        <?= htmlspecialchars($address['street_address_2']) ?><br>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['zip_code']) ?><br>
                                    <?= htmlspecialchars($address['country']) ?>
                                    
                                    <?php if (!empty($address['phone'])): ?>
                                        <div class="mt-2">
                                            <i class="fas fa-phone-alt mr-2"></i><?= htmlspecialchars($address['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!$address['is_default']): ?>
                                <a href="?set_default=<?= $address['id'] ?>" class="text-sm text-white/60 hover:text-white underline">
                                    Set as default
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Address Usage Info -->
            <div class="glass-card p-6 rounded-xl">
                <h3 class="text-lg font-semibold heading-font mb-4">About Your Addresses</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div class="p-4 bg-white/10 rounded-lg">
                        <div class="mb-3 flex items-center gap-2">
                            <i class="fas fa-truck text-xl"></i>
                            <h4 class="font-medium">Shipping Addresses</h4>
                        </div>
                        <p class="opacity-70">Your default address will be pre-selected during the checkout process for shipping.</p>
                    </div>
                    
                    <div class="p-4 bg-white/10 rounded-lg">
                        <div class="mb-3 flex items-center gap-2">
                            <i class="fas fa-credit-card text-xl"></i>
                            <h4 class="font-medium">Billing Addresses</h4>
                        </div>
                        <p class="opacity-70">For payments, you can choose any of your saved addresses or add a new one during checkout.</p>
                    </div>
                    
                    <div class="p-4 bg-white/10 rounded-lg">
                        <div class="mb-3 flex items-center gap-2">
                            <i class="fas fa-shield-alt text-xl"></i>
                            <h4 class="font-medium">Address Security</h4>
                        </div>
                        <p class="opacity-70">Your addresses are securely stored and only used for shipping and billing purposes.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>