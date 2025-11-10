<?php

include('../includes/db_connect.php');

// --- 1. Utility Functions ---
/**
 * @param string $db_status The status value from the database (e.g., 'Pending').
 * @return array Contains 'class' and 'text' for display.
 */
function getStatusDisplay($db_status) {
    // Standardizing status names based on DB schema for CSS classes
    $status_lower = strtolower($db_status);
    return match($status_lower) {
        'pending' => ['class' => 'status-new', 'text' => 'Pending'],
        'preparing' => ['class' => 'status-accepted', 'text' => 'Preparing'],
        'completed' => ['class' => 'status-completed', 'text' => 'Completed'],
        'cancelled' => ['class' => 'status-rejected', 'text' => 'Cancelled'],
        default => ['class' => 'status-unknown', 'text' => 'Unknown'],
    };
}

/**
 * Fetches orders from the database based on a time filter, status filter, and sort order.
 *
 * @param mysqli $conn The database connection object.
 * @param string $time_filter 'daily', 'weekly', 'monthly', or 'all'.
 * @param string $sort_by 'date_desc' (default) or 'customer_asc'.
 * @param string $status_filter 'pending', 'preparing', 'completed', 'cancelled', or 'all'.
 * @return array Array of orders or empty array on failure.
 */
function fetchFilteredOrders($conn, $time_filter = 'all', $sort_by = 'date_desc', $status_filter = 'all') {
    $where_parts = [];

    // 1. Date Filter Logic
    switch (strtolower($time_filter)) {
        case 'daily':
            $where_parts[] = "DATE(o.created_at) = CURDATE()";
            break;
        case 'weekly':
            $where_parts[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'monthly':
            $where_parts[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }

    // 2. Status Filter Logic
    if (strtolower($status_filter) !== 'all') {
        // Escape the status filter value for safe insertion into the query string
        $safe_status = $conn->real_escape_string($status_filter);
        $where_parts[] = "o.status = '{$safe_status}'";
    }

    // Build the final WHERE clause
    $where_clause = "";
    if (!empty($where_parts)) {
        $where_clause = "WHERE " . implode(" AND ", $where_parts);
    }

    
    // 3. Determine the SQL ORDER BY clause
    $order_clause = match(strtolower($sort_by)) {
        'customer_asc' => "u.name ASC, o.created_at DESC",
        default => "o.created_at DESC",
    };


    $sql = "
        SELECT 
            o.order_id, 
            o.delivery_type, 
            o.total_price,
            o.payment_method,
            o.status, 
            o.created_at,
            o.user_id,
            u.name AS customer_name
        FROM 
            orders o
        LEFT JOIN 
            users u ON o.user_id = u.user_id 
        $where_clause
        ORDER BY 
            $order_clause;
    ";

    $result = $conn->query($sql);

    if ($result === false) {
        error_log("SQL Error: " . $conn->error);
        return [];
    }

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    return $orders;
}

// --- 2. Handle Status Updates (POST Logic) ---
// This logic remains, as status updates are often posted from the details page, 
// and the successful update now redirects to orderdetail.php.

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['order_id'])) {
    
    // Use integer coercion if order_id is numeric, or use string if it contains non-numeric chars (like prefixes)
    $order_id = htmlspecialchars($_POST['order_id']); 
    $action = htmlspecialchars($_POST['action']);
    $new_status = "";

    if (!isset($conn) || $conn->connect_error !== null) {
        // Log error and redirect to show an error message.
        error_log("Attempted status update but DB connection failed.");
        header("Location: adminorders.php?error=" . urlencode("Database connection failed for update."));
        exit();
    } 
    
    // --- 2a. Fetch current status securely using prepared statement ---
    $current_status_sql = "SELECT status FROM orders WHERE order_id = ?";
    $stmt_fetch = $conn->prepare($current_status_sql);
    
    $current_status = null;
    if ($stmt_fetch) {
        // Assuming order_id is a string type in the DB for 's'
        $stmt_fetch->bind_param("s", $order_id); 
        $stmt_fetch->execute();
        $current_result = $stmt_fetch->get_result();
        
        if ($current_result && $current_result->num_rows > 0) {
            $current_status = $current_result->fetch_assoc()['status'];
        }
        $stmt_fetch->close();
    } else {
         error_log("Error preparing status fetch statement: " . $conn->error);
    }
    
    // --- 2b. Determine new status based on action and current status ---
    if ($action === 'update_status' && $current_status !== null) {
        // Define the status progression: 'Pending' -> 'Preparing' -> 'Completed'
        // Use lowercase for comparison with database enum if possible, but match DB case for update value.
        $new_status = match(strtolower($current_status)) {
            'pending' => 'Preparing',
            'preparing' => 'Completed',
            default => null, // Stop progression if status is final
        };
    } elseif ($action === 'cancel_order' && $current_status !== 'Completed' && $current_status !== 'Cancelled') {
        // Only allow cancellation if not already completed or cancelled
        $new_status = 'Cancelled';
    }

    if ($new_status) {
        // --- 2c. Update status securely using prepared statement ---
        $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmt_update = $conn->prepare($update_sql);

        if ($stmt_update) {
            // Both new_status and order_id are treated as strings
            $stmt_update->bind_param("ss", $new_status, $order_id);

            if ($stmt_update->execute()) {
                
                // --- MODIFICATION: Redirect directly to the order detail page on success ---
                $redirect_url = "orderdetail.php?order_id=" . urlencode($order_id) . "&success=" . urlencode("Status for order #$order_id updated to **$new_status**.");

                header("Location: " . $redirect_url);
                exit();
            } else {
                 error_log("Error executing update: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
             error_log("Error preparing update statement: " . $conn->error);
        }
    } else {
        // If $new_status is null (i.e., status is Completed/Cancelled/Unknown)
        // Redirect to the order detail page to show the current state/message
        $redirect_url = "orderdetail.php?order_id=" . urlencode($order_id);
        header("Location: " . $redirect_url);
        exit();
    }
}

// --- 3. Execute Fetching and Prepare Sort Button Logic ---
// Get the current filters from the URL
$current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
// NEW: Get current status filter
$current_status_filter = isset($_GET['status']) ? $_GET['status'] : 'all'; 

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null; 
// Removed $open_modal_id


$orders = [];
$db_error = null;
if (isset($conn) && $conn->connect_error === null) {
    // Pass all filters to the updated function
    $orders = fetchFilteredOrders($conn, $current_filter, $current_sort, $current_status_filter); 
} else {
    $db_error = "Database connection object (\$conn) not available or connection failed. Please check `../db_connect.php`.";
    if (isset($conn) && $conn->connect_error !== null) {
        $db_error .= " MySQL Error: " . $conn->connect_error;
    }
}

// Logic for the Alphabetical Sort Button (A-Z by Customer Name)
$is_sorted_by_customer = ($current_sort === 'customer_asc');
$base_url_params = "filter=" . urlencode($current_filter) . "&status=" . urlencode($current_status_filter);

if ($is_sorted_by_customer) {
    // Currently sorted A-Z by Customer Name. Link should switch back to Date (Newest First).
    $alpha_sort_url = "adminorders.php?" . $base_url_params . "&sort=date_desc";
    $alpha_sort_icon = "fa-clock"; 
    $alpha_sort_tooltip = "Currently: Customer Name (A-Z). Click to sort by Newest First.";
} else {
    // Currently sorted by Date. Link should switch to A-Z Customer Name sort.
    $alpha_sort_url = "adminorders.php?" . $base_url_params . "&sort=customer_asc";
    $alpha_sort_icon = "fa-sort-alpha-down"; 
    $alpha_sort_tooltip = "Sort by Customer Name (A-Z)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Coffee Shop</title>
    <link rel="stylesheet" href="../assets/css/order.css">
    <link rel="stylesheet" href="../assets/css/navbar.css"> 
    <link rel="icon" type="image/x-icon" href="https://placehold.co/16x16/000000/FFFFFF?text=☕">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


</head>
<body>

    <div class="page-container">
        <header class="main-header">
            <div class="logo">
                <i class="fas fa-coffee"></i>
            </div>
            
            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="dashboard.php" class="nav-link">DASHBOARD</a></li>
                    <li><a href="adminorders.php" class="nav-link active">ORDER</a></li>
                    <li><a href="inventory.php" class="nav-link">INVENTORY</a></li>
                    <li><a href="products.php" class="nav-link">PRODUCTS</a></li>
                    <li><a href="feedbacks.php" class="nav-link">FEEDBACK & REVIEW</a></li> 
                    <li class="mobile-logout">
                        <a href="../login.php" class="nav-link logout-button-mobile">LOGOUT</a>
                    </li>
                </ul>
            </nav>
            
            <div class="header-actions">
                <a href="../login.php" class="logout-button">LOGOUT</a>
                
                <div class="burger-menu" id="burger-menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <img src="https://scontent.fmnl17-3.fna.fbcdn.net/v/t1.15752-9/476133121_944707607784720_4222766298493625099_n.jpg?stp=dst-jpg_s100x100_tt6&_nc_cat=106&ccb=1-7&_nc_sid=029a7d&_nc_eui2=AeHbXTSveWEb4OzutQZJ0bo9taI_vWM-p1y1oj-9Yz6nXI0YaxhtxRPTLLJMJmHWtmHktAjCfAJasIl2dW9Xd5mI&_nc_ohc=fujV-m1DLokQ7kNvwHfDq8g&_nc_oc=AdnbzmRf6BknvCWet4iFs18szBlKvHfOLnwPvF_Yz5vVNGXwjWsteEaM2u43sPz8450&_nc_zt=23&_nc_ht=scontent.fmnl17-3.fna&oh=03_Q7cD3gGJjWr_65WSg0tvi9N-0vVvuMYVYKORJ-0c42fXu4VQIg&oe=69191A0E" alt="User Profile" class="profile-image">
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge">3</span>
                </div>
            </div>
        </header>
        <main class="orders-management">
            <h1 class="page-title">Customer Orders</h1>
            
            <?php if ($success_message): ?>
                <div style="padding: 15px; margin-bottom: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; font-weight: 500;">
                    <i class="fas fa-check-circle mr-2"></i> **SUCCESS:** <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="padding: 15px; margin-bottom: 20px; background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 5px; font-weight: 500;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> **ERROR:** <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($db_error): ?>
                <div style="padding: 15px; margin-bottom: 20px; background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 5px; font-weight: 500;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> **DATABASE ERROR:** <?php echo htmlspecialchars($db_error); ?>
                    <br>Please ensure your `../db_connect.php` file is correctly set up and the database contains the required tables (`orders`, `users`).
                </div>
            <?php endif; ?>

            <div class="orders-card">
                <div class="order-controls">
                    <div class="filter-group">
                        <a href="<?php echo htmlspecialchars($alpha_sort_url); ?>" class="icon-btn" title="<?php echo htmlspecialchars($alpha_sort_tooltip); ?>">
                            <i class="fas <?php echo htmlspecialchars($alpha_sort_icon); ?>"></i>
                        </a>
                        
                        <div class="status-filter">
                            <select name="status_period" onchange="window.location.href='adminorders.php?status=' + this.value + '&filter=<?php echo urlencode($current_filter); ?>' + '&sort=<?php echo urlencode($current_sort); ?>';">
                                <option value="all" <?php if (strtolower($current_status_filter) === 'all') echo 'selected'; ?>>All Status</option>
                                <option value="pending" <?php if (strtolower($current_status_filter) === 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="preparing" <?php if (strtolower($current_status_filter) === 'preparing') echo 'selected'; ?>>Preparing</option>
                                <option value="completed" <?php if (strtolower($current_status_filter) === 'completed') echo 'selected'; ?>>Completed</option>
                                <option value="cancelled" <?php if (strtolower($current_status_filter) === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    
                    <div class="date-filter">
                        <select name="filter_period" onchange="window.location.href='adminorders.php?filter=' + this.value + '&status=<?php echo urlencode($current_status_filter); ?>' + '&sort=<?php echo urlencode($current_sort); ?>';">
                            <option value="all" <?php if ($current_filter === 'all') echo 'selected'; ?>>All Orders</option>
                            <option value="daily" <?php if ($current_filter === 'daily') echo 'selected'; ?>>Daily</option>
                            <option value="weekly" <?php if ($current_filter === 'weekly') echo 'selected'; ?>>Weekly</option>
                            <option value="monthly" <?php if ($current_filter === 'monthly') echo 'selected'; ?>>Monthly</option>
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th class="col-order-id">ORDER ID</th>
                                <th>CUSTOMER</th>
                                <th>METHOD</th>
                                <th>PAYMENT / TIME SLOT (Assumed)</th>
                                <th class="col-created">CREATED</th>
                                <th class="col-status">LAST STATUS</th>
                                <th class="col-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): ?>
                                <?php foreach ($orders as $order): 
                                    $order_id_safe = htmlspecialchars($order['order_id']);
                                    $status_info = getStatusDisplay($order['status']);
                                    // Format timestamp
                                    $created_at = new DateTime($order['created_at']);
                                    $date_part = $created_at->format('d M Y');
                                    $time_part = $created_at->format('h:i A');

                                    // Determine customer name fallback
                                    $customer_display = $order['customer_name'] ?? 'Guest';
                                    if ($customer_display === 'Guest' && !empty($order['user_id'])) {
                                        $customer_display .= ' (ID: ' . htmlspecialchars($order['user_id']) . ')';
                                    }
                                ?>
                                <tr class="order-row" data-order-id="<?php echo $order_id_safe; ?>" data-status="<?php echo htmlspecialchars($status_info['text']); ?>">
                                    <td class="order-id"><?php echo $order_id_safe; ?></td>
                                    <td><?php echo htmlspecialchars($customer_display); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords($order['delivery_type'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['payment_method'] ?? 'Immediately'); ?>
                                    </td>
                                    <td>
                                        <div class="date-time">
                                            Date: <?php echo $date_part; ?><br>
                                            Time: <?php echo $time_part; ?>
                                        </div>
                                    </td>
                                    <td class="status-cell">
                                        <span class="status-badge <?php echo $status_info['class']; ?>">
                                            <?php echo $status_info['text']; ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="orderdetail.php?order_id=<?php echo $order_id_safe; ?>" class="icon-btn action-view-details" title="View Order Details">
                                            <i class="fas fa-eye"></i> </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="order-row">
                                    <td colspan="7" style="text-align: center; padding: 20px; color: #777;">
                                        No customer orders found for the current filters.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

<script>
    // Simple JavaScript for mobile navigation (Burger Menu)
    document.getElementById('burger-menu').addEventListener('click', function() {
        document.getElementById('main-nav').classList.toggle('active');
        this.classList.toggle('open');
    });

    // Note: All complex modal and status action JS logic has been removed
</script>
</body>
</html>