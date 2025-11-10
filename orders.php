<?php 
// Assumes db_connect.php is in the same directory or accessible via 'includes/'
include('includes/db_connect.php'); 
session_start(); 

// --- Message Handling ---
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

// --- 3. Handle Order Cancellation (POST Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    
    if (!isset($_SESSION['user_id']) || !isset($_POST['order_id']) || empty($_POST['order_id'])) {
        header("Location: orders.php?error=" . urlencode("Invalid cancellation request."));
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $order_id = htmlspecialchars($_POST['order_id']);
    $new_status = 'Cancelled';
    $current_status = htmlspecialchars($_POST['current_status'] ?? 'Pending');

    // Only allow cancellation if status is Pending or Preparing
    if (strtolower($current_status) !== 'completed' && strtolower($current_status) !== 'cancelled' && strtolower($current_status) !== 'delivered') {
        
        $update_sql = "UPDATE orders SET status = ? WHERE order_id = ? AND user_id = ?";
        $stmt_update = $conn->prepare($update_sql);

        if ($stmt_update) {
            $stmt_update->bind_param("ssi", $new_status, $order_id, $user_id);

            if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
                
                // --- Insert into history (Audit Trail) ---
                $history_sql = "INSERT INTO order_history (order_id, status_change, notes, timestamp) VALUES (?, ?, ?, NOW())";
                $history_stmt = $conn->prepare($history_sql);
                $notes = "Order cancelled by user from status: {$current_status}.";
                $status_change_note = "Status changed to 'Cancelled'";
                
                if ($history_stmt) {
                    $history_stmt->bind_param("sss", $order_id, $status_change_note, $notes);
                    $history_stmt->execute();
                    $history_stmt->close();
                } 
                
                header("Location: orders.php?success=" . urlencode("Order #{$order_id} has been successfully cancelled."));
                exit();
            } else {
                 $error_message = "Cancellation failed. Order may already be cancelled, completed, or not found.";
            }
            $stmt_update->close();
        } else {
             $error_message = "Error preparing cancellation statement: " . $conn->error;
        }
    } else {
         $error_message = "Order cannot be cancelled because its status is **{$current_status}**.";
    }
    
    if (isset($error_message)) {
        header("Location: orders.php?error=" . urlencode($error_message));
        exit();
    }
}
// --- END Handle Order Cancellation (POST Logic) ---

// --- Function to determine the current step for the tracker display ---
function get_order_step($status) {
    $tracker_steps = ['ORDERED', 'PREPARING', 'READY']; 
    $db_to_tracker = [
        'Pending' => 'ORDERED',
        'Preparing' => 'PREPARING',
        'Ready' => 'READY',
        'Out for Delivery' => 'READY', 
    ];
    
    $current_label = $db_to_tracker[$status] ?? 'ORDERED';
    $current_index = array_search($current_label, $tracker_steps);
    
    $tracker_data = [];
    foreach ($tracker_steps as $index => $label) {
        $is_current = ($index === $current_index);
        $is_completed = ($index < $current_index);
        
        $class = '';
        $time = '--:--';
        
        if ($is_completed) {
            $class = 'active completed';
            $time = 'Done'; 
        } elseif ($is_current) {
            $class = 'active current';
            $time = 'In Progress';
        }
        
        $icon = 'fas fa-check'; 
        if ($label === 'PREPARING') {
            $icon = 'fas fa-mug-hot';
        } elseif ($label === 'READY') {
            $icon = 'fas fa-flag-checkered';
        }
        
        $tracker_data[] = [
            'label' => $label,
            'class' => $class,
            'time' => $time,
            'icon' => $icon
        ];
    }
    return $tracker_data;
}

// Re-used status display function
function getStatusDisplay($db_status) {
    $status_lower = strtolower($db_status);
    return match($status_lower) {
        'pending' => ['class' => 'status-pending', 'text' => 'PENDING'],
        'preparing' => ['class' => 'status-preparing', 'text' => 'PREPARING'],
        'completed' => ['class' => 'status-completed', 'text' => 'COMPLETED'],
        'cancelled' => ['class' => 'status-cancelled', 'text' => 'CANCELLED'],
        'delivered' => ['class' => 'status-delivered', 'text' => 'DELIVERED'],
        default => ['class' => 'status-unknown', 'text' => 'UNKNOWN'],
    };
}


// --- 1. User Authentication and Database Fetching ---
$profile_picture = '/Assets/Images/user-placeholder.jpg'; 
$current_order = null;
$current_order_items = [];
$order_error = null;
$total_item_count = 0;
$order_image_path = '/Assets/Images/default-order.jpg'; 
$total_orders_count = 0; // NEW
$monthly_orders_count = 0; // NEW

if (isset($_SESSION['user_id']) && isset($conn) && $conn->connect_error === null) {
    $user_id = $_SESSION['user_id'];
    
    // --- NEW: Fetch Total and Monthly Order Counts ---
    
    // Calculate the start of the current month
    $start_of_month = date('Y-m-01 00:00:00');

    $sql_count = "
        SELECT 
            COUNT(order_id) AS total_count,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS monthly_count
        FROM 
            orders
        WHERE 
            user_id = ?
    ";
    
    $stmt_count = $conn->prepare($sql_count);
    
    if ($stmt_count) {
        $stmt_count->bind_param("si", $start_of_month, $user_id);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        
        if ($result_count && $result_count->num_rows > 0) {
            $counts = $result_count->fetch_assoc();
            $total_orders_count = (int)$counts['total_count'];
            $monthly_orders_count = (int)$counts['monthly_count'];
        }
        $stmt_count->close();
    }
    // --- END NEW ORDER COUNTS ---


    // Fetch profile picture path
    $sql_user = "SELECT profile_picture FROM users WHERE user_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user && $result_user->num_rows === 1) {
            $user_data = $result_user->fetch_assoc();
            if (!empty($user_data['profile_picture'])) {
                $profile_picture = $user_data['profile_picture'];
            }
        }
        $stmt_user->close();
    }

    // --- 2. Fetch Current Order ---
    $sql_order = "
        SELECT 
            o.order_id, 
            o.total_price, 
            o.status, 
            o.created_at,
            o.delivery_type
        FROM orders o 
        WHERE o.user_id = ? 
        AND o.status NOT IN ('Completed', 'Cancelled', 'Delivered')
        ORDER BY o.created_at DESC 
        LIMIT 1
    ";

    $stmt_order = $conn->prepare($sql_order);
    if ($stmt_order) {
        $stmt_order->bind_param("i", $user_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();

        if ($result_order && $result_order->num_rows > 0) {
            $current_order = $result_order->fetch_assoc();
            $current_order_id = $current_order['order_id'];

            // Fetch order items for current order preview
            $sql_items = "
                SELECT 
                    oi.quantity, 
                    oi.price,
                    oi.addons,
                    oi.size,
                    m.name as item_name,
                    m.image as item_image_path
                FROM order_items oi
                LEFT JOIN menu_items m ON oi.item_id = m.item_id
                WHERE oi.order_id = ?
            ";

            $stmt_items = $conn->prepare($sql_items);
            if ($stmt_items) {
                $stmt_items->bind_param("i", $current_order_id);
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
                
                while ($item = $result_items->fetch_assoc()) {
                    $current_order_items[] = $item;
                    $total_item_count += (int)$item['quantity'];
                    if (empty($order_image_path) || $order_image_path == '/Assets/Images/default-order.jpg') {
                         if (!empty($item['item_image_path'])) {
                             $order_image_path = htmlspecialchars($item['item_image_path']);
                         }
                    }
                }
                $stmt_items->close();
            }
        }
        $stmt_order->close();
    }
    
    // Check for order status placement success 
    if (isset($_GET['order_placed']) && is_numeric($_GET['order_placed'])) {
        $placed_id = htmlspecialchars($_GET['order_placed']);
        if (!$success_message && !$error_message) {
             $order_error = "Order #{$placed_id} has been placed successfully and is now your current order.";
        }
    }

    // --- 4. Fetch Order History ---
    $historical_orders = [];
    $sql_history = "
        SELECT 
            o.order_id, 
            o.total_price, 
            o.status, 
            o.created_at
        FROM orders o 
        WHERE o.user_id = ? 
        AND o.status IN ('Completed', 'Cancelled', 'Delivered')
        ORDER BY o.created_at DESC 
        LIMIT 10 
    ";

    $stmt_history = $conn->prepare($sql_history);
    if ($stmt_history) {
        $stmt_history->bind_param("i", $user_id);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();

        while ($hist_order = $result_history->fetch_assoc()) {
            $hist_order_id = $hist_order['order_id'];
            
            // Fetch items for history card preview
            $sql_hist_items = "
                SELECT 
                    oi.quantity, 
                    oi.addons,
                    oi.size,
                    m.name as item_name
                FROM order_items oi
                LEFT JOIN menu_items m ON oi.item_id = m.item_id
                WHERE oi.order_id = ?
            ";
            
            $stmt_hist_items = $conn->prepare($sql_hist_items);
            $hist_order['items'] = [];
            $hist_order['item_preview'] = [];
            $hist_order['total_item_count'] = 0;

            if ($stmt_hist_items) {
                $stmt_hist_items->bind_param("s", $hist_order_id);
                $stmt_hist_items->execute();
                $result_hist_items = $stmt_hist_items->get_result();
                
                while ($item = $result_hist_items->fetch_assoc()) {
                    $hist_order['items'][] = $item;
                    $hist_order['total_item_count'] += (int)$item['quantity'];

                    $item_name = htmlspecialchars($item['item_name'] ?? 'Unknown');
                    $item_size = !empty($item['size']) ? ' (' . htmlspecialchars($item['size']) . ')' : '';
                    $hist_order['item_preview'][] = $item_name . $item_size;
                }
                $stmt_hist_items->close();
            }
            
            $historical_orders[] = $hist_order;
        }
        $stmt_history->close();
    }

} else {
    // Redirect unauthenticated users
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

$status_steps = $current_order ? get_order_step($current_order['status']) : [];

// --- Group identical order items (for current order display) ---
$grouped_order_items = [];
foreach ($current_order_items as $item) {
    $customization = json_decode($item['addons'] ?? '{}', true);
    $item_size = $item['size'] ?? ($customization['size'] ?? ''); 
    $addons_text = !empty($customization['addons']) ? ' w/ ' . implode(', ', $customization['addons']) : '';
    $key = ($item['item_name'] ?? 'Unknown') . '|' . $item_size . '|' . $addons_text;
    
    if (isset($grouped_order_items[$key])) {
        $grouped_order_items[$key]['quantity'] += (int)$item['quantity'];
        $grouped_order_items[$key]['price'] += (float)$item['price']; 
    } else {
        $grouped_order_items[$key] = [
            'item_name' => $item['item_name'] ?? 'Unknown Item',
            'quantity' => (int)$item['quantity'],
            'price' => (float)$item['price'],
            'size_text' => !empty($item_size) ? ' (' . ucfirst(htmlspecialchars($item_size)) . ')' : '',
            'addons_text' => $addons_text
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Coffee Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/userorder.css">
    <style>
        :root {
            --accent-color: #e67e22; 
        }
        .profile-picture-container .profile-img {
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            object-fit: cover;
            border: 2px solid var(--accent-color); 
            display: block; 
        }
        .profile-picture-container .fas.fa-user-circle {
            display: none;
        }
        .empty-order-card {
            background-color: #2c3e50;
            padding: 20px;
            margin-bottom: 10px;
            border-radius: 8px;
            text-align: center;
            color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .empty-order-card p {
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        .empty-order-card .reorder-button {
            background-color: var(--accent-color);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .empty-order-card .reorder-button:hover {
            background-color: #d35400;
        }
        /* ADDED STYLES FOR MESSAGE BOXES (Dark Theme) */
        .alert-box { 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            font-weight: 500; 
            font-size: 0.9em;
            display: flex; 
            align-items: center;
        }
        .alert-box i {
            margin-right: 10px;
        }
        .alert-success { 
            background-color: #21432c; 
            color: #90ee90; 
            border: 1px solid #3c6a46; 
        }
        .alert-error { 
            background-color: #582f32; 
            color: #f08080; 
            border: 1px solid #8d3e42; 
        }
        
        /* History Status Colors */
        .status-completed { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
        .status-delivered { background-color: #007bff; color: white; }
        
        /* CSS to remove the blue focus outline (focus ring) */
        button:focus,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="main-header">
            <div class="logo">
                <i class="fas fa-coffee"></i>
            </div>
            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-link">HOME</a></li> 
                    <li><a href="menu.php" class="nav-link">MENU</a></li>
                    <li><a href="orders.php" class="nav-link active">MY ORDER</a></li>
                    <li><a href="account.php" class="nav-link">PROFILE</a></li>
                    <li><a href="about.php" class="nav-link">ABOUT</a></li>
                    <li class="mobile-logout">
                        <a href="login.php" class="nav-link logout-button-mobile">LOGOUT</a>
                    </li>
                </ul>
            </nav>
            <div class="header-actions">
                <a href="login.php" class="logout-button">LOGOUT</a>
                <div class="burger-menu" id="burger-menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="profile-picture-container">
                    <a href="account.php">
                        <img 
                            src="<?php echo htmlspecialchars($profile_picture); ?>" 
                            alt="Profile Picture" 
                            class="profile-img"
                            onerror="this.onerror=null; this.src='uploads';"
                        >
                    </a>
                </div>
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge">3</span>
                </div>
            </div>
        </header>
        
        <main class="my-orders-main">
            <?php if ($success_message): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert-box alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="orders-container">
                <div class="page-header">
                    <h1 class="page-title">MY ORDERS</h1>
                    <div class="order-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $total_orders_count; ?></span>
                            <span class="stat-label">Total Orders</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $monthly_orders_count; ?></span>
                            <span class="stat-label">This Month</span>
                        </div>
                    </div>
                </div>

                <section class="current-order">
                    <div class="section-header">
                        <h2 class="section-heading">
                            <i class="fas fa-clock"></i>
                            CURRENT ORDER
                        </h2>
                        <span class="estimated-time">
                            <?php 
                                if ($current_order) {
                                    $type = htmlspecialchars($current_order['delivery_type']);
                                    echo $type === 'Delivery' ? 'Est. Delivery: 30-45 min' : 'Est. Pickup: 15-20 min';
                                } else {
                                    echo 'No active order';
                                }
                            ?>
                        </span>
                    </div>
                    
                    <?php if ($current_order): ?>
                        <div class="order-card current">
                            <div class="order-header">
                                <span class="order-id">ORDER #<?php echo htmlspecialchars($current_order['order_id']); ?></span>
                                <span class="order-date">Placed: <?php echo date('M j, Y, g:i A', strtotime($current_order['created_at'])); ?></span>
                            </div>
                            
                            <div class="order-content">
                                <div class="order-info">
                                    <div class="item-list">
                                        <div class="item-image-container">
                                            <img src="<?php echo $order_image_path; ?>" alt="Order Items Preview" class="order-item-image" onerror="this.onerror=null;this.src='/Assets/Images/default-order.jpg';">
                                            <span class="item-count"><?php echo $total_item_count; ?></span>
                                        </div>
                                        <div class="item-details">
                                            <ul>
                                                <?php 
                                                $preview_count = 0;
                                                foreach ($grouped_order_items as $item): 
                                                    if ($preview_count >= 3) break; 
                                                ?>
                                                <li>
                                                    <span class="item-name">
                                                        <?php echo (int)$item['quantity']; ?>x 
                                                        <?php echo htmlspecialchars($item['item_name']) . htmlspecialchars($item['size_text']) . htmlspecialchars($item['addons_text']); ?>
                                                    </span>
                                                    <span class="item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                                </li>
                                                <?php 
                                                $preview_count++;
                                                endforeach; 
                                                ?>
                                                <?php if (count($grouped_order_items) > 3): ?>
                                                    <li style="font-style: italic; color: #aaa;">
                                                        + <?php echo count($grouped_order_items) - 3; ?> more items...
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="order-status-section">
                                    <div class="order-status-tracker">
                                        <?php foreach ($status_steps as $step): ?>
                                            <div class="status-step <?php echo $step['class']; ?>">
                                                <div class="status-dot">
                                                    <i class="<?php echo $step['icon']; ?>"></i>
                                                </div>
                                                <div class="status-content">
                                                    <span class="status-label"><?php echo $step['label']; ?></span>
                                                    <span class="status-time"><?php echo $step['time']; ?></span>
                                                </div>
                                            </div>
                                            <?php if ($step['label'] !== 'READY'): ?>
                                                <div class="status-line <?php echo $step['class'] === 'active completed' ? 'active' : ''; ?>"></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="order-actions">
                                <form method="POST" action="orders.php" onsubmit="return confirm('Are you sure you want to CANCEL order #<?php echo htmlspecialchars($current_order['order_id']); ?>? This action cannot be undone.');" style="margin: 0;">
                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($current_order['order_id']); ?>">
                                    <input type="hidden" name="action" value="cancel_order">
                                    <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($current_order['status']); ?>">
                                    <button type="submit" class="cancel-button" 
                                        <?php 
                                            $status = strtolower($current_order['status']);
                                            if ($status === 'completed' || $status === 'cancelled' || $status === 'delivered') {
                                                echo 'disabled style="opacity: 0.5; cursor: not-allowed;"';
                                            }
                                        ?>
                                    >
                                        <i class="fas fa-times"></i>
                                        CANCEL ORDER
                                    </button>
                                </form>
                                <div class="order-total">
                                    <span class="order-total-label">TOTAL</span>
                                    <span class="order-price">₱<?php echo number_format($current_order['total_price'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-order-card">
                            <p>You currently have no active orders being prepared.</p>
                            <a href="menu.php" class="reorder-button">
                                <i class="fas fa-mug-hot"></i> START NEW ORDER
                            </a>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="order-history">
                    <div class="section-header">
                        <h2 class="section-heading">
                            <i class="fas fa-history"></i>
                            ORDER HISTORY
                        </h2>
                        <a href="#" class="view-all-link">View All <i class="fas fa-chevron-right"></i></a>
                    </div>

                    <div class="history-cards">
                        <?php if (!empty($historical_orders)): ?>
                            <?php foreach ($historical_orders as $history_order): 
                                $hist_status_info = getStatusDisplay($history_order['status']);
                            ?>
                                <div class="history-card">
                                    <div class="history-card-header">
                                        <span class="order-num">#<?php echo htmlspecialchars($history_order['order_id']); ?></span>
                                        <span class="order-date"><?php echo date('M j, Y', strtotime($history_order['created_at'])); ?></span>
                                    </div>
                                    <div class="history-card-body">
                                        <div class="order-items-preview">
                                            <span class="items-count"><?php echo $history_order['total_item_count']; ?> items</span>
                                            <ul>
                                                <?php 
                                                $preview_items = array_slice($history_order['item_preview'], 0, 3);
                                                foreach ($preview_items as $item_name_preview):
                                                ?>
                                                    <li><?php echo $item_name_preview; ?></li>
                                                <?php endforeach; ?>
                                                <?php if (count($history_order['item_preview']) > 3): ?>
                                                    <li style="font-style: italic; color: #aaa;">+ <?php echo count($history_order['item_preview']) - 3; ?> more</li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <div class="order-total-amount">
                                            <span class="total-label">Total</span>
                                            <span class="total-price">₱<?php echo number_format($history_order['total_price'], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="history-card-footer">
                                        <span class="order-status <?php echo $hist_status_info['class']; ?>"><?php echo $hist_status_info['text']; ?></span>
                                        <div class="history-card-actions">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-order-card" style="background-color: #2A2A2A; color: #E0E0E0; text-align: left;">
                                <p><i class="fas fa-info-circle"></i> No historical orders found yet. Complete an order to see it here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        function redirectToOrderSummary() {
            // NOTE: In a real application, this should implement the reorder logic.
            window.location.href = 'menu.php'; 
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Animation for status steps
            const statusSteps = document.querySelectorAll('.status-step');
            statusSteps.forEach((step, index) => {
                setTimeout(() => {
                    step.style.opacity = '1';
                    step.style.transform = 'translateY(0)';
                }, index * 300);
            });
            
            // NOTE: Hamburger menu logic from Navbar.js would go here or be imported.
        });
    </script>
    <script src="../assets/js/Navbar.js"></script>
</body>
</html>