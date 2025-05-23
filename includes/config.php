
<?php
/**
 * Site Configuration
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Site settings
//define('SITE_NAME', 'My Online Shop');
//define('SITE_URL', 'http://localhost/php_test/shop_newversion');
//define('ADMIN_EMAIL', 'admin@example.com');

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'aetherbox');
define('DB_USER', 'root');
define('DB_PASS', 'kurosensei2468#KURO');

// Shopping settings
define('TAX_RATE', 0.08); // 8% tax rate
define('SHIPPING_COST', 5.99);

// Session lifetime
define('SESSION_LIFETIME', 3600); // 1 hour

// Date and time settings
date_default_timezone_set('UTC');