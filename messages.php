<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Debug information - comment out in production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Connect to database
require_once 'config/connect_db.php';

$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['admin_id'];
$is_admin = isset($_SESSION['admin_id']);
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == true;
$error_message = '';
$success_message = '';

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $receiver_id = $_POST['receiver_id'] ?? null;
    
    if (!empty($message) && !empty($receiver_id)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$current_user_id, $receiver_id, $message]);
            $success_message = "Message sent successfully!";
            
            // Redirect to prevent form resubmission
            header("Location: messages.php?user_id=" . $receiver_id);
            exit;
        } catch (PDOException $e) {
            $error_message = "Failed to send message: " . $e->getMessage();
        }
    } else {
        $error_message = "Message cannot be empty";
    }
}

// Get user's conversations (distinct users they've chatted with)
$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END AS other_user_id,
            (SELECT MAX(sent_at) FROM messages 
             WHERE (sender_id = ? AND receiver_id = other_user_id) 
                OR (sender_id = other_user_id AND receiver_id = ?)
            ) as last_message_time,
            (SELECT COUNT(*) FROM messages 
             WHERE sender_id = other_user_id AND receiver_id = ? AND read_at IS NULL
            ) as unread_count
        FROM messages 
        WHERE sender_id = ? OR receiver_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to load conversations: " . $e->getMessage();
}

// Get user details for each conversation
$conversation_users = [];
foreach ($conversations as $conversation) {
    $other_user_id = $conversation['other_user_id'];
    
    try {
        // Check if user or admin
        $stmt = $pdo->prepare("SELECT id, username, 'user' as type FROM users WHERE id = ? AND is_active = 0 
                              UNION 
                              SELECT id, username, 'admin' as type FROM users WHERE id = ? AND is_admin = 1");
        $stmt->execute([$other_user_id, $other_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $conversation_users[$other_user_id] = $user;
        }
    } catch (PDOException $e) {
        // Skip if user details can't be fetched
        continue;
    }
}

// Get messages for selected conversation
$selected_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
if (empty($selected_user_id) && !empty($conversations)) {
    $selected_user_id = $conversations[0]['other_user_id'];
}

$selected_user = null;
$messages = [];

if ($selected_user_id) {
    // Get user details
    try {
        $stmt = $pdo->prepare("SELECT id, username, 'user' as type FROM users WHERE id = ? AND is_admin = 0
                              UNION 
                              SELECT id, username, 'admin' as type FROM users WHERE id = ? AND is_admin = 1");
        $stmt->execute([$selected_user_id, $selected_user_id]);
        $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Failed to load user details: " . $e->getMessage();
    }
    
    // Get messages
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY sent_at ASC
        ");
        $stmt->execute([$current_user_id, $selected_user_id, $selected_user_id, $current_user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark unread messages as read
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET read_at = NOW() 
            WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL
        ");
        $stmt->execute([$selected_user_id, $current_user_id]);
        
    } catch (PDOException $e) {
        $error_message = "Failed to load messages: " . $e->getMessage();
    }
}

// Get available users to start new conversation with
$available_users = [];
try {
    if ($is_admin) {
        // Admins can message all users
        $stmt = $pdo->prepare("SELECT id, username as name FROM users WHERE is_admin = 0");
        $stmt->execute();
        $available_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Users can message admins
        $stmt = $pdo->prepare("SELECT id, username as name FROM users WHERE is_admin = 1");
        $stmt->execute();
        $available_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Failed to load available users: " . $e->getMessage();
}

// Filter out users already in conversations
$available_users = array_filter($available_users, function($user) use ($conversation_users) {
    return !isset($conversation_users[$user['id']]);
});

// Get unread messages count for navbar
$unread_count = 0;
foreach ($conversations as $conversation) {
    $unread_count += $conversation['unread_count'];
}

// Get username for display
$username = "";
$user_role = $is_admin ? "Administrator" : "Customer";

if ($is_admin) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND is_admin = 1");
    $stmt->execute([$current_user_id]);
    $admin = $stmt->fetch();
    $username = $admin ? $admin['username'] : "";
} else {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND is_admin = 0");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch();
    $username = $user ? $user['username'] : "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @font-face {
            font-family: 'Space Grotesk';
            src: url(assets/fonts/Incrediible-BF6814d5097d803.ttf) format('truetype');
        }
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
        
        .search-box input {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .search-box input:focus {
            background: rgba(255, 255, 255, 0.15);
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="text-white min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Top header with back button -->
        <div class="glass-card p-4 mb-6 flex justify-between items-center">
            <a href="<?= $is_admin ? 'admin/dashboard.php' : 'user/account.php' ?>" 
            class="flex items-center gap-2 text-white/80 hover:text-white transition-colors">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
            
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="font-semibold"><?= htmlspecialchars($username) ?></div>
                    <div class="text-xs text-white/70"><?= htmlspecialchars($user_role) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-6">
            <!-- Title header -->
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold heading-font">Messages</h2>
                
                <div class="search-box relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-white/70"></i>
                    <input type="text" placeholder="Search messages..." class="pl-10 pr-4 py-2 rounded-lg w-64">
                </div>
            </div>
            
            
            <?php if ($error_message): ?>
                <div class="glass-card p-4 mb-6 border-l-4 border-red-500 text-red-100">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium"><?php echo $error_message; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="glass-card p-4 mb-6 border-l-4 border-green-500 text-green-100">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium"><?php echo $success_message; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="flex gap-6">
                <!-- Conversations List -->
                <div class="w-1/3">
                    <div class="glass-card rounded-xl overflow-hidden mb-6">
                        <div class="p-4 flex justify-between items-center border-b border-white/10">
                            <h3 class="font-semibold">Conversations</h3>
                            <button class="bg-white/10 hover:bg-white/20 p-2 rounded-lg transition-colors"
                                    onclick="document.getElementById('newConversationModal').classList.remove('hidden')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <div class="divide-y divide-white/10 max-h-[500px] overflow-y-auto">
                            <?php if (empty($conversations)): ?>
                                <div class="p-6 text-center text-white/70">
                                    <i class="fas fa-comments text-3xl mb-2"></i>
                                    <p>No conversations yet</p>
                                    <button class="mt-3 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg"
                                            onclick="document.getElementById('newConversationModal').classList.remove('hidden')">
                                        Start a conversation
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conversation): ?>
                                    <?php 
                                    $other_user_id = $conversation['other_user_id'];
                                    if (!isset($conversation_users[$other_user_id])) continue;
                                    $user = $conversation_users[$other_user_id];
                                    $is_active = $selected_user_id == $other_user_id;
                                    $has_unread = $conversation['unread_count'] > 0;
                                    ?>
                                    <a href="?user_id=<?php echo $other_user_id; ?>" 
                                       class="block hover:bg-white/5 transition-colors duration-150 <?php echo $is_active ? 'bg-white/10' : ''; ?>">
                                        <div class="p-4 flex items-center gap-3">
                                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold">
                                                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="font-medium text-white truncate <?php echo $has_unread ? 'font-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($user['username'] ?? 'Unknown User'); ?>
                                                    </h3>
                                                    <span class="text-xs text-white/50">
                                                        <?php echo date('M d', strtotime($conversation['last_message_time'])); ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-white/50 truncate">
                                                        <?php if ($user['type'] === 'admin'): ?>
                                                            <span class="text-xs text-white bg-purple-500/80 px-1 rounded">Admin</span>
                                                        <?php else: ?>
                                                            <span class="text-xs text-white bg-blue-500/80 px-1 rounded">User</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if ($has_unread): ?>
                                                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                                                            <?php echo $conversation['unread_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Message Area -->
                <div class="w-2/3">
                    <div class="glass-card rounded-xl overflow-hidden h-[600px] flex flex-col">
                        <?php if ($selected_user): ?>
                            <div class="p-4 border-b border-white/10 flex items-center gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold">
                                    <?php echo strtoupper(substr($selected_user['username'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="font-medium text-white">
                                        <?php echo htmlspecialchars($selected_user['username']  ?? 'Unknown User'); ?>
                                    </div>
                                    <div class="text-xs text-white/70">
                                        <?php if ($selected_user['type'] === 'admin'): ?>
                                            <span class="text-xs text-white bg-purple-500/80 px-1 rounded">Admin</span>
                                        <?php else: ?>
                                            <span class="text-xs text-white bg-blue-500/80 px-1 rounded">User</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto p-4" id="message-container">
                                <?php if (empty($messages)): ?>
                                    <div class="flex items-center justify-center h-full">
                                        <div class="text-center text-white/50">
                                            <i class="fas fa-comments text-3xl mb-2"></i>
                                            <p>No messages yet.</p>
                                            <p class="text-sm">Send a message to start the conversation!</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <?php $is_sender = $message['sender_id'] == $current_user_id; ?>
                                        <div class="mb-4 <?php echo $is_sender ? 'flex justify-end' : 'flex justify-start'; ?>">
                                            <div class="max-w-xs lg:max-w-md">
                                                <div class="rounded-lg px-4 py-2 <?php echo $is_sender ? 'bg-blue-600 text-white' : 'bg-white/10 text-white'; ?>">
                                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                </div>
                                                <div class="text-xs text-white/50 mt-1 <?php echo $is_sender ? 'text-right' : 'text-left'; ?>">
                                                    <?php echo date('M d, g:i a', strtotime($message['sent_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" class="p-4 border-t border-white/10">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                                <div class="flex gap-2">
                                    <textarea name="message" rows="1" 
                                            class="w-full bg-white/10 border border-white/30 rounded-lg p-2 text-white focus:ring-2 focus:ring-white/50 focus:outline-none resize-none"
                                            placeholder="Type your message..." required></textarea>
                                    <button type="submit" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 rounded-lg flex items-center hover:shadow-lg transition-all">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="flex items-center justify-center h-full text-center p-6">
                                <div>
                                    <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-comment-alt text-3xl text-white/70"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold mb-2">Your Messages</h3>
                                    <p class="text-white/70 mb-6">Select a conversation or start a new one</p>
                                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                            onclick="document.getElementById('newConversationModal').classList.remove('hidden')">
                                        Start New Conversation
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Conversation Modal -->
    <div id="newConversationModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center hidden z-50">
        <div class="glass-card w-full max-w-md p-6 rounded-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">New Conversation</h3>
                <button onclick="document.getElementById('newConversationModal').classList.add('hidden')" 
                        class="text-white/70 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <?php if (empty($available_users)): ?>
                <div class="p-6 text-center text-white/70 bg-white/5 rounded-lg">
                    <i class="fas fa-users-slash text-3xl mb-2"></i>
                    <p>No users available to message</p>
                    <p class="text-sm mt-2">You're already chatting with everyone!</p>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <input type="text" id="userSearchInput" placeholder="Search users..." 
                           class="w-full bg-white/10 border border-white/30 text-white rounded-lg p-2 focus:ring-2 focus:ring-white/50 focus:outline-none">
                </div>
                <div class="max-h-60 overflow-y-auto mb-4">
                    <div class="divide-y divide-white/10" id="userList">
                        <?php foreach ($available_users as $user): ?>
                            <div class="user-item py-2">
                                <a href="?user_id=<?php echo $user['id']; ?>" 
                                   class="flex items-center gap-3 p-2 hover:bg-white/10 rounded-lg transition-colors">
                                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="user-name font-medium text-white"><?php echo htmlspecialchars($user['username']  ?? 'Unknown User'); ?></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-right">
                <button onclick="document.getElementById('newConversationModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Scroll to bottom of message container
        const messageContainer = document.getElementById('message-container');
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }
        
        // Search functionality for user list
        const userSearchInput = document.getElementById('userSearchInput');
        if (userSearchInput) {
            userSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const userItems = document.querySelectorAll('.user-item');
                
                userItems.forEach(item => {
                    const userName = item.querySelector('.user-name').textContent.toLowerCase();
                    if (userName.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    });
    </script>
</body>
</html>