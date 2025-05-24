
<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'config/connect_db.php';
require_once 'includes/product_utils.php';

global $pdo;

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_log('SESSION DATA: '. print_r($_SESSION, true));

// Check if cart is empty, redirect to cart page if it is
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
    header('Location: cart.php');
    exit;
}

// Define checkout steps
$checkout_steps = [
    1 => 'Shipping Information',
    2 => 'Payment Method',
    3 => 'Review Order',
    4 => 'Confirmation'
];

// Get current step (default to 1)
$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($current_step < 1 || $current_step > count($checkout_steps)) {
    $current_step = 1;
}

// Page title
$page_title = 'Checkout - ' . $checkout_steps[$current_step];

// Include header only (no footer or sticky_nav)

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/shopstyle.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap 5 CSS and JS (required for modals) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-white checkout-container text-gray-800">

    <?php include 'includes/header.php'; ?>

    <!-- Checkout Container -->
    <div class=" container max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Checkout</h1>
        
        <!-- Progress Bar -->
        <div class="mb-10">
            <!-- Progress bar track -->
            <div class="w-full bg-gray-700 h-1 mb-4">
                <div class="bg-blue-600 h-1" style="width: <?php echo (($current_step - 1) / (count($checkout_steps) - 1)) * 100; ?>%"></div>
            </div>
            
            <!-- Step indicators -->
            <div class="flex justify-between">
                <?php foreach($checkout_steps as $step_num => $step_name): ?>
                    <div class="flex flex-col items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full <?php echo $step_num <= $current_step ? 'bg-blue-600' : 'bg-gray-700 border border-gray-600'; ?> text-sm mb-1">
                            <?php echo $step_num; ?>
                        </div>
                        <div class="text-xs <?php echo $step_num <= $current_step ? 'text-blue-400' : 'text-gray-400'; ?>">
                            <?php echo $step_name; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Checkout Content -->
        <div class="glass-card rounded-xl p-6">
            <?php
            // Include the appropriate step content based on current step
            $step_file = 'includes/checkout/step' . $current_step . '.php';
            if (file_exists($step_file)) {
                echo "<!-- loading step file: $step_file -->";
                include $step_file;
            } else {
                // Fallback content if step file doesn't exist
                echo '<div class="bg-red-900/40 border border-red-500 text-red-200 px-4 py-3 rounded mb-6">
                        Checkout step not found.
                    </div>';
            }
            ?>
        </div>
    </div>

    <!-- Navigation Warning Modal -->
    <div class="modal fade" id="leavePageModal" tabindex="-1" aria-labelledby="leavePageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-gray-800 text-white border border-gray-700">
                <div class="modal-header border-gray-700">
                    <h5 class="modal-title" id="leavePageModalLabel">Are you sure?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are in the middle of the checkout process. If you leave now, your progress will not be saved until you complete the checkout and payment.</p>
                    <p>Are you sure you want to leave this page?</p>
                </div>
                <div class="modal-footer border-gray-700">
                    <button type="button" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg" data-bs-dismiss="modal">Stay on this page</button>
                    <a href="#" id="confirmLeave" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg">Leave anyway</a>
                </div>
            </div>
        </div>
    </div>

<!-- Custom styles to integrate Bootstrap with your dark theme -->
    <style>
        
            #shipping-form label.form-label {
        font-weight: 600;
        color: #444;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }
    
    #shipping-form .form-control,
    #shipping-form .form-select {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        transition: all 0.2s ease;
        border: 1px solid #dedede;
        background-color: rgba(255,255,255,0.7);
    }
    
    #shipping-form .form-control:focus,
    #shipping-form .form-select:focus {
        box-shadow: 0 0 0 3px rgba(87, 75, 144, 0.25);
        border-color: #574b90;
        background-color: white;
    }
    
    /* Card styling */
    .card {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .card-header {
        
        color: white;
        font-weight: 600;
        padding: 1rem 1.5rem;
        border-bottom: none;
    }
    
    .card-header h3 {
        font-size: 1.25rem;
        margin: 0;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    /* Order summary styling */
    .list-group-item {
        background-color: transparent;
        border-color: rgba(0,0,0,0.08);
        padding: 0.75rem 0;
    }
    
    .badge.bg-secondary {
        background-color: #574b90 !important;
        font-weight: normal;
    }
    
    /* Button styling */
    .btn-primary {
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        border-radius: 8px;
        letter-spacing: 0.02em;
        box-shadow: 0 4px 10px rgba(87, 75, 144, 0.3);
    }
    
    .btn-primary:hover {
        box-shadow: 0 6px 15px rgba(87, 75, 144, 0.4);
        transform: translateY(-2px);
    }
    
    .btn-outline-secondary {
        border-color: #dedede;
        color: #555;
        font-weight: 500;
        border-radius: 8px;
    }
    
    .btn-outline-secondary:hover {
        background-color: #f5f5f5;
        color: #333;
        border-color: #ccc;
    }
    
    /* Form section spacing */
    .row.mb-3 {
        margin-bottom: 1.5rem !important;
    }

    .row h3, .card-header {
        background-color: rgb(15 23 42 / var(--tw-bg-opacity, 1));
    }
    
    /* Form check styling */
    .form-check-input {
        border-color: #574b90;
    }
    
    .form-check-input:checked {
        background-color: #574b90;
        border-color: #574b90;
    }
    
    /* Total price highlighting */
    .fw-bold {
        color: #333;
        font-size: 1.1rem;
    }
    
    /* Add a subtle border around the form */
    #shipping-form {
        position: relative;
        padding: 1rem;
        border-radius: 8px;
        background-color: rgba(255,255,255,0.5);
    }
    
    /* Add required field indicator */
    .form-label:after {
        content: "*";
        color: #574b90;
        margin-left: 3px;
    }
    
    /* Improve responsive spacing */
    @media (max-width: 768px) {
        .card {
            margin-bottom: 2rem;
        }
    }
        .checkout-container {
            background-image: 
                linear-gradient(rgba(100, 100, 100, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(100, 100, 100, 0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            background-position: center center;
        }

        /* Style progress bar for light theme */
        .bg-gray-700 {
            background-color: #e5e7eb;
        }

        /* Style glass card for light theme */
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
            color: #333;
        }
    

        /* Override Bootstrap form styles to match dark theme */
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #333;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #574b90;
            color: #3333;
            box-shadow: 0 0 0 0.25rem rgba(87, 75, 144, 0.5);
        }
        
        .form-control::placeholder {
            color: rgba(0, 0, 0, 0.4);
        }
        
        /* Style card elements */
        .card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #333;
        }
        
        .card-header {
            
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Update modal styling for light theme */
        .modal-content {
            background-color: white;
            color: #333;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .modal-header, .modal-footer {
            border-color: rgba(0, 0, 0, 0.1);
        }
        
        /* Style buttons to match site theme */
        .btn-primary {
            background-color: #574b90;
            border-color: #574b90;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #483d8b;
            border-color: #483d8b;
        }
        
        
        
        .text-white {
            color: white !important;
        }
    

        /* Fix for any light text that might become invisible */
        .text-muted {
            color: rgba(0, 0, 0, 0.6) !important;
        }

        
        
        

        
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Store all links that would navigate away from checkout
        const links = document.querySelectorAll("a:not([href^='checkout.php']):not([href^='#']):not([href^='javascript:'])");
        
        // Add click event listener to all navigational links
        links.forEach(link => {
            link.addEventListener("click", function(e) {
                if(link.closest('form')) return;

                e.preventDefault();
                const destination = this.getAttribute("href");
                
                // Show the modal
                const leavePageModal = new bootstrap.Modal(document.getElementById('leavePageModal'));
                leavePageModal.show();
                
                // Set the confirm button destination
                document.getElementById("confirmLeave").setAttribute("href", destination);
                
                // Add click event to confirm button
                document.getElementById("confirmLeave").onclick = function() {
                    window.location.href = destination;
                };
            });
        });

        // Disable beforeunload for form submissions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                window.onbeforeunload = null;
            });
        });
        
        // Also intercept browser back/forward buttons
        window.addEventListener('beforeunload', function(e) {
            if (document.activeElement && document.activeElement.type === 'submit') {
                return undefined;
            }
            // Chrome requires returnValue to be set
            e.returnValue = '';
            return 'You are in the middle of checkout. Your progress will not be saved if you leave now.';
        });
    });
    </script>

</body>
</html>