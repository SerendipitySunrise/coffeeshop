<?php

include('../includes/db_connect.php'); // Your database connection file
session_start(); // Start session to potentially use user_id or display messages

// --- 1. Get Order ID and Handle Redirects ---
$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : null;

if (!$order_id) {
    // If no order ID is provided, redirect back to the main orders list
    header("Location: adminorders.php?error=" . urlencode("No order ID provided for details view."));
    exit();
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

// --- 2. Database Fetching Functions ---

/**
 * @param mysqli $conn The database connection object.
 * @param string $order_id The ID of the order to fetch.
 * @return array|null Full order details if found, null otherwise.
 */
function fetchFullOrderDetails($conn, $order_id) {
    
    // 2a. Fetch main order and customer details
    $sql_main = "
        SELECT 
            o.*, 
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,
            u.address AS customer_address,
            u.profile_picture AS customer_picture -- Fetches profile picture from users table
        FROM 
            orders o
        LEFT JOIN 
            users u ON o.user_id = u.user_id
        WHERE 
            o.order_id = ?
        LIMIT 1";

    $stmt_main = $conn->prepare($sql_main);
    if (!$stmt_main) {
        error_log("SQL Prepare Error (Main): " . $conn->error);
        return null;
    }
    
    $stmt_main->bind_param("s", $order_id);
    $stmt_main->execute();
    $result_main = $stmt_main->get_result();
    $order_data = $result_main->fetch_assoc();
    $stmt_main->close();

    if (!$order_data) {
        return null;
    }

    // 2b. Fetch order items
    $sql_items = "
        SELECT 
            oi.*, 
            m.name AS menu_item_name
        FROM 
            order_items oi
        JOIN 
            menu_items m ON oi.item_id = m.item_id
        WHERE 
            oi.order_id = ?";

    $stmt_items = $conn->prepare($sql_items);
    if (!$stmt_items) {
        error_log("SQL Prepare Error (Items): " . $conn->error);
        return null;
    }
    
    $stmt_items->bind_param("s", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $order_data['items'] = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    return $order_data;
}


// --- 3. Handle Status Update POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && $_POST['order_id'] == $order_id) {
    
    $action = $_POST['action'] ?? '';
    $current_status = $_POST['current_status'] ?? '';
    $new_status = $current_status;
    $status_updated = false;

    // Define the state machine for status progression
    $status_progression = [
        'Pending' => 'Preparing',
        'Preparing' => 'Ready for Pickup/Out for Delivery', // Depends on delivery_type, handled below
        'Ready for Pickup' => 'Completed',
        'Out for Delivery' => 'Completed',
        'Completed' => 'Completed' // No further action
    ];

    if ($action === 'cancel_order') {
        $new_status = 'Cancelled';
        $status_updated = true;
        $message = "Order #{$order_id} successfully CANCELLED.";
    } elseif ($action === 'update_status' && array_key_exists($current_status, $status_progression)) {
        
        if ($current_status === 'Preparing') {
            // Check delivery type to decide between 'Ready for Pickup' or 'Out for Delivery'
            $delivery_type = $_POST['delivery_type'] ?? 'Delivery'; 
            $new_status = ($delivery_type === 'Pickup') ? 'Ready for Pickup' : 'Out for Delivery';
            $status_updated = true;
        } elseif ($current_status !== 'Completed' && $current_status !== 'Cancelled') {
             // For all other statuses, advance to the next in the chain (Pending -> Preparing, etc.)
            $new_status = $status_progression[$current_status];
            $status_updated = true;
        } else {
             $message = "Status cannot be advanced from '{$current_status}'.";
        }
    }

    if ($status_updated) {
        $sql_update = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("ss", $new_status, $order_id);
            if ($stmt_update->execute()) {
                $message = "Order #{$order_id} status updated to **{$new_status}**!";
                header("Location: orderdetail.php?order_id={$order_id}&success=" . urlencode($message));
                exit();
            } else {
                $message = "Database Error: Could not update status.";
                header("Location: orderdetail.php?order_id={$order_id}&error=" . urlencode($message));
                exit();
            }
            $stmt_update->close();
        }
    }
}


// --- 4. Fetch Data & Prepare Variables for Display ---
$order_data = fetchFullOrderDetails($conn, $order_id);

if (!$order_data) {
    header("Location: adminorders.php?error=" . urlencode("Order ID #{$order_id} not found."));
    exit();
}

$current_status = $order_data['status'];
$is_completed = (strtolower($current_status) === 'completed');
$is_cancelled = (strtolower($current_status) === 'cancelled');
$is_actionable = !($is_completed || $is_cancelled);
$delivery_type = $order_data['delivery_type'] ?? 'Delivery';

// Logic for the next status button text
$advance_next = $current_status; // Default
$advance_btn_text = 'Update Status'; // Default

if ($current_status === 'Pending') {
    $advance_next = 'Preparing';
    $advance_btn_text = 'Start PREPARING';
} elseif ($current_status === 'Preparing') {
    $advance_next = ($delivery_type === 'Pickup') ? 'Ready for Pickup' : 'Out for Delivery';
    $advance_btn_text = 'Mark as ' . $advance_next;
} elseif ($current_status === 'Ready for Pickup' || $current_status === 'Out for Delivery') {
    $advance_next = 'Completed';
    $advance_btn_text = 'Mark as COMPLETED';
}

// Status Configuration for Tailwind Styling (Pill Colors - Dark Theme Adjusted)
$status_configs = [
    'Pending' => ['color' => 'bg-yellow-900 text-yellow-300 border-yellow-700', 'dot' => 'bg-yellow-400'],
    'Preparing' => ['color' => 'bg-blue-900 text-blue-300 border-blue-700', 'dot' => 'bg-blue-400'],
    'Ready for Pickup' => ['color' => 'bg-purple-900 text-purple-300 border-purple-700', 'dot' => 'bg-purple-400'],
    'Out for Delivery' => ['color' => 'bg-indigo-900 text-indigo-300 border-indigo-700', 'dot' => 'bg-indigo-400'],
    'Completed' => ['color' => 'bg-green-900 text-green-300 border-green-700', 'dot' => 'bg-green-400'],
    'Cancelled' => ['color' => 'bg-red-900 text-red-300 border-red-700', 'dot' => 'bg-red-400'],
];
$status_class = $status_configs[$current_status]['color'] ?? 'bg-gray-700 text-gray-300 border-gray-600';
$dot_class = $status_configs[$current_status]['dot'] ?? 'bg-gray-400';

// Format date
$date_time = new DateTime($order_data['created_at']);
$formatted_date = $date_time->format('M d, Y');
$formatted_time = $date_time->format('h:i A');

// Customer details placeholders
$customer_name = htmlspecialchars($order_data['customer_name'] ?? 'N/A (Guest)');
$customer_email = htmlspecialchars($order_data['customer_email'] ?? 'N/A');
$customer_phone = htmlspecialchars($order_data['customer_phone'] ?? 'N/A');
// Use delivery_address if set, otherwise fallback to customer address, then a placeholder.
$customer_address = htmlspecialchars($order_data['delivery_address'] ?? $order_data['customer_address'] ?? 'Address not available');

// IMPORTANT: Fetch and sanitize the profile picture path
$customer_pic = htmlspecialchars($order_data['customer_picture'] ?? '/Assets/Images/user-placeholder.jpg');

// --- FIX: Ensure numeric values are not NULL to prevent warnings and deprecated errors ---
// This handles the access of nullable columns like 'subtotal' and 'delivery_fee' from the 'orders' table.
$subtotal = $order_data['subtotal'] ?? 0.00;
$delivery_fee = $order_data['delivery_fee'] ?? 0.00;
$total_price = $order_data['total_price'] ?? ($subtotal + $delivery_fee); // Recalculate if total is null

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order_id; ?> Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* --- DARK THEME STYLES --- */
        body { font-family: 'Inter', sans-serif; background-color: #111827; } /* bg-gray-900 */
        .card-shadow { box-shadow: 0 1px 10px rgba(0, 0, 0, 0.5); } /* Darker shadow */
        .data-label { font-weight: 500; color: #9ca3af; } /* text-gray-400 */
        .data-value { font-weight: 600; color: #f9fafb; } /* text-gray-50 (White/Light) */
        .status-pill {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            border-width: 1px;
        }
    </style>
</head>
<body class="p-4 sm:p-8">

    <!-- Success/Error Message Display -->
    <?php if ($success_message || $error_message): ?>
    <div class="fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl max-w-sm 
        <?php echo $success_message ? 'bg-green-700 text-gray-50' : 'bg-red-700 text-gray-50'; ?>" id="status-message">
        <p class="font-bold"><?php echo $success_message ? 'Success' : 'Error'; ?></p>
        <p class="text-sm"><?php echo htmlspecialchars($success_message ?? $error_message); ?></p>
        <button class="absolute top-1 right-2 text-xl opacity-80 hover:opacity-100" onclick="document.getElementById('status-message').style.display='none'">
            &times;
        </button>
    </div>
    <?php endif; ?>

    <!-- Modal for Confirmation (Replaces confirm()) -->
    <div id="confirmation-modal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden z-50 items-center justify-center">
        <div class="bg-gray-800 p-6 rounded-xl shadow-2xl max-w-sm w-full border border-gray-700">
            <h3 class="text-xl font-bold text-gray-50 mb-4" id="modal-title">Confirm Action</h3>
            <p class="text-gray-300 mb-6" id="modal-message">Are you sure you want to proceed with this action?</p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-300 bg-gray-700 rounded-md hover:bg-gray-600 transition">
                    No, Cancel
                </button>
                <button type="button" id="modal-confirm-btn" class="px-4 py-2 text-gray-900 bg-lime-500 rounded-md hover:bg-lime-400 transition font-semibold">
                    Yes, Proceed
                </button>
            </div>
        </div>
    </div>


    <main class="max-w-4xl mx-auto space-y-6">

        <!-- Back Button -->
        <a href="adminorders.php" class="inline-flex items-center text-gray-400 hover:text-lime-400 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Orders List
        </a>

        <!-- Header: Order ID and Status -->
        <header class="flex flex-col sm:flex-row sm:justify-between sm:items-center border-b border-gray-700 pb-4 mb-4">
            <h1 class="text-3xl font-extrabold text-gray-50">Order #<?php echo htmlspecialchars($order_id); ?></h1>
            <span class="mt-2 sm:mt-0 status-pill <?php echo $status_class; ?>">
                <span class="w-2 h-2 rounded-full inline-block mr-1 <?php echo $dot_class; ?>"></span>
                <?php echo htmlspecialchars($current_status); ?>
            </span>
        </header>


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- COLUMN 1 & 2: Customer Details and Order Items -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Card 1: Customer Details -->
                <div class="bg-gray-800 p-6 rounded-xl card-shadow border border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-200 mb-4 border-b border-gray-700 pb-2">Customer Details</h2>
                    
                    <div class="flex items-center space-x-4 mb-4">
                        <!-- Displaying the fetched profile picture -->
                        <img 
                            src="<?php echo $customer_pic; ?>" 
                            alt="Customer" 
                            class="w-12 h-12 rounded-full object-cover border-2 border-lime-400" 
                            onerror="this.onerror=null; this.src='/Assets/Images/user-placeholder.jpg';"
                        >
                        <div>
                            <p class="text-lg data-value"><?php echo $customer_name; ?></p>
                            <p class="text-sm data-label"><?php echo $customer_email; ?></p>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-start">
                            <span class="data-label w-1/3">Type:</span>
                            <span class="data-value w-2/3 text-right capitalize"><?php echo htmlspecialchars($delivery_type); ?></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="data-label w-1/3">Address:</span>
                            <span class="data-value w-2/3 text-right break-words"><?php echo $customer_address; ?></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="data-label w-1/3">Contact:</span>
                            <span class="data-value w-2/3 text-right"><?php echo $customer_phone; ?></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="data-label w-1/3">Payment:</span>
                            <span class="data-value w-2/3 text-right"><?php echo htmlspecialchars($order_data['payment_method'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Order Items -->
                <div class="bg-gray-800 p-6 rounded-xl card-shadow border border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-200 mb-4 border-b border-gray-700 pb-2">Order Items (<?php echo count($order_data['items']); ?>)</h2>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($order_data['items'] as $item): 
                            // FIX: Handle JSON decoding and array structure for addons gracefully
                            $addons = json_decode($item['addons'], true); 
                            $addons_text_array = [];
                            
                            if (is_array($addons)) {
                                foreach ($addons as $addon) {
                                    // Check if the addon item is an object/associative array with a 'name' key (Correct structure)
                                    if (is_array($addon) && isset($addon['name'])) {
                                        $addons_text_array[] = htmlspecialchars($addon['name']);
                                    } 
                                    // Handle legacy/simple array structure where elements are just strings (The observed structure from the error)
                                    elseif (is_string($addon)) {
                                        $addons_text_array[] = htmlspecialchars($addon);
                                    }
                                }
                            }
                            $addons_text = !empty($addons_text_array) ? ' + ' . implode(', ', $addons_text_array) : '';
                        ?>
                            <div class="flex justify-between items-center pb-2 border-b border-gray-700 last:border-b-0">
                                <div>
                                    <p class="data-value text-base"><?php echo htmlspecialchars($item['menu_item_name']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>)</p>
                                    <p class="text-xs text-gray-400 capitalize">Size: <?php echo htmlspecialchars($item['size'] ?? 'Standard'); ?></p>
                                    <?php if (!empty($addons_text)): ?>
                                        <p class="text-xs text-lime-400 mt-1"><?php echo $addons_text; ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="text-lime-400 text-base font-semibold text-right">
                                    ₱<?php echo number_format($item['price'] ?? 0.00, 2); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- COLUMN 3: Order Summary and Actions -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Card 3: Order Summary -->
                <div class="bg-gray-800 p-6 rounded-xl card-shadow border border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-200 mb-4 border-b border-gray-700 pb-2">Summary</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="data-label">Order Date:</span>
                            <span class="data-value"><?php echo $formatted_date; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="data-label">Order Time:</span>
                            <span class="data-value"><?php echo $formatted_time; ?></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-700 pt-3 mt-3">
                            <span class="data-label">Subtotal:</span>
                            <!-- Value safely checked for NULL using ?? 0.00 -->
                            <span class="data-value">₱<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="data-label"><?php echo htmlspecialchars($delivery_type); ?> Fee:</span>
                            <!-- Value safely checked for NULL using ?? 0.00 -->
                            <span class="data-value">₱<?php echo number_format($delivery_fee, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-600">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-50">Total Price:</span>
                            <span class="text-2xl font-extrabold text-yellow-400">
                                <!-- Value safely checked for NULL using ?? 0.00 -->
                                ₱<?php echo number_format($total_price, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Actions -->
                <div class="bg-gray-800 p-6 rounded-xl card-shadow border border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-200 mb-4 border-b border-gray-700 pb-2">Actions</h2>
                    
                    <div class="space-y-3">
                        
                        <!-- Update Status Button (Primary/Lime Accent) -->
                        <button 
                            type="button" 
                            id="btn-update-status"
                            class="w-full py-3 rounded-lg text-gray-900 font-extrabold transition duration-150 shadow-lg 
                            <?php echo $is_actionable ? 'bg-lime-500 hover:bg-lime-400' : 'bg-gray-600 cursor-not-allowed text-gray-400'; ?>" 
                            <?php echo $is_actionable ? '' : 'disabled'; ?>
                            onclick="confirmAction('update')"
                        >
                            <?php echo htmlspecialchars($advance_btn_text); ?>
                        </button>
                        
                        <!-- Cancel Order Button (Red Warning) -->
                        <button 
                            type="button" 
                            id="btn-cancel-order"
                            class="w-full py-3 rounded-lg text-white font-bold transition duration-150 shadow-lg 
                            <?php echo $is_actionable ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-600 cursor-not-allowed text-gray-400'; ?>" 
                            <?php echo $is_actionable ? '' : 'disabled'; ?>
                            onclick="confirmAction('cancel')"
                        >
                            CANCEL ORDER
                        </button>

                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Hidden Forms for Submission -->
    <form method="POST" action="orderdetail.php?order_id=<?php echo $order_id; ?>" id="form-update-status" class="hidden">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
        <input type="hidden" name="delivery_type" value="<?php echo $delivery_type; ?>">
    </form>

    <form method="POST" action="orderdetail.php?order_id=<?php echo $order_id; ?>" id="form-cancel-order" class="hidden">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="action" value="cancel_order">
        <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
    </form>


    <script>
        function showModal(title, message, confirmCallback) {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-message').innerHTML = message; // Use innerHTML to allow **bold** formatting
            const confirmBtn = document.getElementById('modal-confirm-btn');
            
            // Clear previous listeners and attach the new one
            confirmBtn.onclick = null; 
            confirmBtn.addEventListener('click', () => {
                confirmCallback();
                closeModal();
            }, { once: true }); // Ensure it only runs once

            document.getElementById('confirmation-modal').classList.remove('hidden');
            document.getElementById('confirmation-modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('confirmation-modal').classList.add('hidden');
            document.getElementById('confirmation-modal').classList.remove('flex');
        }

        function confirmAction(actionType) {
            const orderId = '<?php echo $order_id; ?>';
            const currentStatus = '<?php echo $current_status; ?>';

            if (actionType === 'cancel') {
                if (currentStatus === 'Completed' || currentStatus === 'Cancelled') {
                    // Should not happen if buttons are disabled, but good guard rail
                    return; 
                }
                showModal(
                    'Confirm Order Cancellation',
                    `Are you sure you want to CANCEL order #${orderId}? This action cannot be undone.`,
                    () => {
                        document.getElementById('form-cancel-order').submit();
                    }
                );
            } else if (actionType === 'update') {
                const nextStatus = '<?php echo $advance_next; ?>';
                if (currentStatus === 'Completed' || currentStatus === 'Cancelled') {
                    return; 
                }
                showModal(
                    'Confirm Status Update',
                    `Change status for order #${orderId} from **${currentStatus}** to **${nextStatus}**?`,
                    () => {
                        document.getElementById('form-update-status').submit();
                    }
                );
            }
        }
    </script>
</body>
</html>