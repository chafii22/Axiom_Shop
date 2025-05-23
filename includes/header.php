<?php

$cart_count = 0;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use database cart count if available
if (isset($pdo)) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : session_id();
    $cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id";
    $cart_stmt = $pdo->prepare($cart_query);
    $cart_stmt->bindParam(':user_id', $userId);
    $cart_stmt->execute();
    $result = $cart_stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['total'] !== null) {
        $cart_count = (int)$result['total'];
    }
} else if (isset($_SESSION['cart'])) {
    // Fallback to session cart if DB not available
    $cart_count = array_sum($_SESSION['cart']);
}

// Determine if we're in a subdirectory
$in_subfolder = strpos($_SERVER['PHP_SELF'], '/user/') !== false || 
                strpos($_SERVER['PHP_SELF'], '/admin/') !== false;

// Set the base path accordingly
$base_path = $in_subfolder ? '../' : '';
?>

<header class="flex justify-between items-center px-12 py-5 mb-8 bg-[#0f172a] rounded-[40px] max-md:px-8 max-md:py-4 max-sm:px-4 max-sm:py-3">
    <h1 class="text-xl tracking-[0.3rem] font-bold text-green-200 heading-font">Axiom</h1>

    <nav class="flex gap-6 max-sm:hidden">
        <a href="<?php echo $base_path?>home.php" class="text-base text-white hover:text-green-200 transition-colors">Home</a>
        <a href="<?php echo $base_path?>shop.php" class="text-base text-white hover:text-green-200 transition-colors">Shop</a>
        <a href="<?php echo $base_path?>about.php" class="text-base text-white hover:text-green-200 transition-colors">About Us</a>
    </nav>

    
    
    <script src="<?php echo $root_path ?? ''; ?>js/utils.js"></script>   
        <!-- For stylesheets -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</header>