<?php 
// Assumes db_connect.php is in the same directory or accessible via 'includes/'
include('includes/db_connect.php'); 
session_start(); 

// --- 1. Database Logic: Fetch User Data (Profile Picture & Address) ---
$menu_items = [];
$db_error = null;
$profile_picture = '/Assets/Images/user-placeholder.jpg'; 
$user_address = 'Address not set. Please update your account profile.'; // Default placeholder
$order_error = $_SESSION['order_error'] ?? null; 
unset($_SESSION['order_error']); 
$user_id = $_SESSION['user_id'] ?? null;

if (isset($user_id) && isset($conn) && $conn->connect_error === null) {
    
    // Prepare statement to fetch profile picture path AND address
    $sql_user = "SELECT profile_picture, address FROM users WHERE user_id = ?";
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
            // NEW: Get the user's saved address
            if (!empty($user_data['address'])) {
                $user_address = htmlspecialchars($user_data['address']);
            }
        }
        $stmt_user->close();
    }
} else {
    // If user is not logged in, redirect them immediately if not POSTing
    if (!isset($user_id) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
         header('Location: login.php'); // Redirect to login if not authenticated
         exit;
    }
}

// --- 2. Cart Processing Logic (Read from Session and Calculate Totals) ---
$cart_items = $_SESSION['cart'] ?? [];
$subtotal = 0.00;

// MOCK VALUES (Used for display and final calculation)
$delivery_fee_default = 3.45; 
$pickup_fee = 0.00;
$discount = 5.00;    
$tax_rate = 0.10; 

// Initial calculations based on default/delivery fee. This is calculated on the client-side for final order total.
$subtotal = 0.00;
foreach ($cart_items as $item) {
    $subtotal += (float)($item['price'] ?? 0); 
}
$tax_base = $subtotal - $discount;
$tax_amount = max(0, $tax_base * $tax_rate); 
// Final total is calculated dynamically on the client based on selected delivery type, then confirmed on the server.
$final_total_delivery = $subtotal - $discount + $delivery_fee_default + $tax_amount;
$final_total_pickup = $subtotal - $discount + $pickup_fee + $tax_amount; 
$cart_item_count = count($cart_items);


// --- 3. Handle Order Submission (PLACE ORDER button logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    // Final check for cart and user status
    if (empty($cart_items) || !isset($user_id)) {
        $_SESSION['order_error'] = "Cannot place order: Cart is empty or user is not logged in.";
        header('Location: ordersummary.php'); 
        exit;
    }

    $payment_method = htmlspecialchars($_POST['payment'] ?? 'Cash'); 
    $delivery_type = htmlspecialchars($_POST['delivery_type'] ?? 'Pickup'); 
    
    // Determine which final total and address to use based on delivery type
    if ($delivery_type === 'Delivery') {
        $delivery_address = htmlspecialchars($_POST['delivery_address'] ?? 'Not Provided');
        $order_total = $final_total_delivery; // Use delivery total
        $delivery_fee_final = $delivery_fee_default;
        
        if ($delivery_address === 'Address not set. Please update your account profile.' || $delivery_address === 'Not Provided') {
             $_SESSION['order_error'] = "Order placement failed: Please update your address in the profile section before choosing Delivery.";
             header('Location: ordersummary.php?error=no_address'); 
             exit;
        }

    } else { // Pickup
        $delivery_address = 'Pickup at Shop';
        $order_total = $final_total_pickup; // Use pickup total
        $delivery_fee_final = $pickup_fee;
    }

    // Start database transaction
    $conn->begin_transaction();
    $success = true;

    try {
        // A. INSERT into orders table (Using the column names specified by the user)
        // Fields: order_id, user_id, total_price, payment_method, delivery_type, delivery_address, status, created_at
        // Mapped: (auto), user_id, order_total, payment_method, delivery_type, delivery_address, 'Pending', NOW()
        $sql_order = "INSERT INTO orders (user_id, total_price, payment_method, delivery_type, delivery_address, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt_order = $conn->prepare($sql_order);
        
        if (!$stmt_order) {
            throw new Exception("Error preparing order insertion: " . $conn->error);
        }

        // Binding: i (user_id), d (total_price), s (payment_method), s (delivery_type), s (delivery_address)
        $stmt_order->bind_param("idsss", $user_id, $order_total, $payment_method, $delivery_type, $delivery_address); 
        if (!$stmt_order->execute()) {
            throw new Exception("Error executing order insertion: " . $stmt_order->error);
        }
        
        $order_id = $conn->insert_id;
        $stmt_order->close();
        
        // B. INSERT into order_items table for each item in the cart
        // **MODIFICATION 1: Added 'size' to the SQL columns**
        $sql_item = "INSERT INTO order_items (order_id, item_id, quantity, price, addons, size) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);
        
        if (!$stmt_item) {
            throw new Exception("Error preparing order item insertion: " . $conn->error);
        }
        
        foreach ($cart_items as $item) {
            $item_id = (int)($item['id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 1);
            $price_per_line = (float)($item['price'] ?? 0.00); 

            // **MODIFICATION 2A: Extract size for the new column**
            $item_size = $item['size'] ?? 'small';
            
            // **MODIFICATION 2B: Only include 'addons' in the JSON, size goes to its own column**
            $customization_array = [
                'addons' => $item['addons'] ?? []
            ];
            $customization_details = json_encode($customization_array); 

            // **MODIFICATION 3: Updated Binding to include 'size' (s) at the end**
            // Binding: i (order_id), i (item_id), i (quantity), d (price), s (customization_details), s (item_size)
            $stmt_item->bind_param("iiidss", $order_id, $item_id, $quantity, $price_per_line, $customization_details, $item_size);
            if (!$stmt_item->execute()) {
                throw new Exception("Error executing order item insertion for item ID {$item_id}: " . $stmt_item->error);
            }
        }
        
        $stmt_item->close();

        // C. Commit transaction and clear cart
        $conn->commit();
        unset($_SESSION['cart']);

        // Redirect to orders page or confirmation
        header('Location: orders.php?order_placed=' . $order_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['order_error'] = "Order placement failed. Please try again. Details: " . $e->getMessage();
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'order_items') !== false) {
             $_SESSION['order_error'] .= "\n**DATABASE STRUCTURE ISSUE:** Check that your `order_items` table has an **AUTO_INCREMENT PRIMARY KEY** (e.g., `order_item_id`) and that the `item_id` column is NOT the primary key.";
        }
        header('Location: ordersummary.php?error=db_error'); 
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Summary - Coffee Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/navbar.css">    
    <link rel="stylesheet" href="assets/css/ordersummary.css"> 
    <style>
        /* ... (Styling remains the same) ... */
        :root {
            --accent-color: #e67e22; 
            --dark-bg: #1e1e1e; 
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
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7em;
            font-weight: bold;
            line-height: 1;
            min-width: 15px;
            text-align: center;
            display: <?php echo $cart_item_count > 0 ? 'block' : 'none'; ?>;
        }
        .cart-icon {
            position: relative;
        }
        .product-info {
            flex-grow: 1;
            padding-right: 15px;
        }
        .product-desc {
            display: block;
            font-size: 0.85em;
            color: #ccc; 
            margin-top: 5px;
        }
        .product-price {
            font-weight: bold;
            color: var(--accent-color);
        }
        .product-image-placeholder {
            background-color: #444; 
            color: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            width: 70px;
            height: 70px;
            border-radius: 8px;
            margin-right: 10px;
        }
        .error-message {
            background-color: #ef4444; 
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            white-space: pre-wrap; /* Preserve newlines from PHP error message */
        }
        .address-box {
            background-color: #333;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #555;
        }
        .address-box p {
            font-size: 0.9em;
            color: #ccc;
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
                    <li><a href="menu.php" class="nav-link active">MENU</a></li>
                    <li><a href="orders.php" class="nav-link">MY ORDER</a></li>
                    <li><a href="account.php" class="nav-link">PROFILE</a></li>
                    <li><a href="about.php" class="nav-link">ABOUT</a></li>

                    <li class="mobile-logout">
                        <a href="login.php" class="nav-link logout-button-mobile">LOGIN</a>
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-bag-heart-fill" viewBox="0 0 16 16" style="display: block;">
                        <path d="M11.5 4v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4zM8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1m0 6.993c1.664-1.711 5.825 1.283 0 5.132-5.825-3.85-1.664-6.843 0-5.132"/>
                    </svg>
                    <span class="cart-badge"><?php echo $cart_item_count; ?></span> 
                </div>
            </div>
        </header>
        
        <main class="order-summary-main">
            <div class="summary-container">
                <div class="summary-header">
                    <h1 class="summary-title">ORDER SUMMARY</h1>
                    <div class="order-status">
                        <span class="status-badge">Review & Checkout</span>
                    </div>
                </div>
                
                <?php if ($order_error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo nl2br(htmlspecialchars($order_error)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="content-layout">
                    <div class="order-details">
                        <div class="order-section">
                            <h2 class="section-title">
                                <i class="fas fa-list"></i>
                                ORDER ITEMS
                            </h2>
                            <div class="product-list">
                                <?php if (empty($cart_items)): ?>
                                    <p class="empty-cart-message" style="text-align: center; color: #999; padding: 20px;">
                                        Your cart is empty. <a href="menu.php" style="color: var(--accent-color); text-decoration: underline;">Go to Menu</a>
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($cart_items as $item): ?>
                                    <div class="product-item">
                                        <div class="product-image-container">
                                            <div class="product-image-placeholder">
                                                <i class="fas fa-mug-hot"></i>
                                            </div>
                                            <div class="quantity-badge"><?php echo (int)$item['quantity']; ?></div>
                                        </div>
                                        <div class="product-info">
                                            <span class="product-name"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Item'); ?> (<?php echo htmlspecialchars(ucfirst($item['size'] ?? '')); ?>)</span>
                                            <span class="product-desc">
                                                <?php 
                                                    $addons_list = $item['addons'] ?? [];
                                                    if (!empty($addons_list)) {
                                                        // FIX APPLIED HERE: Casting $addons_list to array to prevent TypeError
                                                        echo 'Add-ons: ' . implode(', ', array_map('htmlspecialchars', (array)$addons_list));
                                                    } else {
                                                        echo 'No customizations.';
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                        <span class="product-price">₱<?php echo number_format($item['price'] ?? 0, 2); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="order-section">
                            <h2 class="section-title">
                                <i class="fas fa-motorcycle"></i>
                                DELIVERY OPTION
                            </h2>
                            <div class="options-group">
                                <label class="radio-option" >
                                    <input type="radio" name="delivery_type_display" value="Pickup" id="delivery_pickup_display" checked>                                
                                    <span class="custom-radio"></span>
                                    <div class="option-content">
                                        <span class="option-title">Pickup</span>
                                        <span class="option-desc">Collect your order at the shop (Fee: ₱<?php echo number_format($pickup_fee, 2); ?>)</span>
                                    </div>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="delivery_type_display" value="Delivery" id="delivery_delivery_display">                                
                                    <span class="custom-radio"></span>
                                    <div class="option-content">
                                        <span class="option-title">Delivery</span>
                                        <span class="option-desc">Deliver to your address (Fee: ₱<?php echo number_format($delivery_fee_default, 2); ?>)</span>
                                    </div>
                                </label>
                            </div>

                            <div id="delivery-address-section" class="address-box" style="display: none;">
                                <h3 style="color: var(--accent-color); font-weight: bold; margin-bottom: 5px;">Delivery Address:</h3>
                                <p id="fetched-address-display"><?php echo $user_address; ?></p>
                                <?php if (strpos($user_address, 'Address not set') !== false): ?>
                                    <p style="color: red; margin-top: 5px;">**Warning: Please set your address in your Profile.**</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="order-section">
                            <h2 class="section-title">
                                <i class="fas fa-credit-card"></i>
                                PAYMENT METHOD
                            </h2>
                            <div class="options-group">
                                <div id="payment-options-display" >
                                    <label class="radio-option" style="margin-bottom: 15px;">
                                        <input type="radio" name="payment_display" value="cod" id="payment_cod_display" checked>                                
                                        <span class="custom-radio"></span>
                                        <div class="option-content">
                                            <span class="option-title">COD (Cash on Delivery/Pickup)</span>
                                            <span class="option-desc">Pay when you receive your order</span>
                                        </div>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="payment_display" value="gcash" id="payment_gcash_display">                                
                                        <span class="custom-radio"></span>
                                        <div class="option-content">
                                            <span class="option-title">GCash (E-Wallet)</span>
                                            <span class="option-desc">Pay with your GCash account</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="order-total-summary">
                        <form method="POST" action="ordersummary.php">
                            <input type="hidden" name="place_order" value="1">
                            <input type="hidden" name="payment" id="hidden_payment_method" value="cod"> 
                            <input type="hidden" name="delivery_type" id="hidden_delivery_type" value="Pickup">
                            <input type="hidden" name="delivery_address" id="hidden_delivery_address" value="Pickup at Shop">
                            <input type="hidden" id="final_subtotal" value="<?php echo $subtotal; ?>">
                            <input type="hidden" id="final_discount" value="<?php echo $discount; ?>">
                            <input type="hidden" id="final_tax_amount" value="<?php echo $tax_amount; ?>">
                            <input type="hidden" id="delivery_fee_value" value="<?php echo $delivery_fee_default; ?>">
                            <input type="hidden" id="pickup_fee_value" value="<?php echo $pickup_fee; ?>">


                            <div class="summary-card">
                                <h2 class="total-title">ORDER TOTAL</h2>
                                
                                <div class="total-lines">
                                    <div class="total-line">
                                        <span>Subtotal</span>
                                        <span class="subtotal-amount">₱<?php echo number_format($subtotal, 2); ?></span>
                                    </div>
                                    <div class="total-line">
                                        <span>Discount</span>
                                        <span class="discount-amount">-₱<?php echo number_format($discount, 2); ?></span>
                                    </div>
                                    <div class="total-line" id="delivery-fee-line">
                                        <span>Delivery Fee</span>
                                        <span id="delivery-fee-display">₱<?php echo number_format($pickup_fee, 2); ?></span>
                                    </div>
                                    <div class="total-line">
                                        <span>Tax (<?php echo $tax_rate * 100; ?>%)</span>
                                        <span>₱<?php echo number_format($tax_amount, 2); ?></span>
                                    </div>
                                </div>
                                
                                <div class="total-separator"></div>
                                
                                <div class="final-total">
                                    <div class="total-line">
                                        <span class="total-amount-label">TOTAL AMOUNT</span>
                                        <span class="final-amount" id="final-total-display">₱<?php echo number_format($final_total_pickup, 2); ?></span>
                                    </div>
                                    <div class="payment-method">
                                        <span class="cash-value" id="final_payment_display">COD (Cash on Pickup)</span>
                                    </div>
                                </div>

                                <?php if ($cart_item_count > 0): ?>
                                    <button type="submit" class="place-order-button" id="place-order-btn" <?php echo !isset($user_id) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-mug-hot"></i> PLACE ORDER
                                    </button>
                                    <?php if (!isset($user_id)): ?>
                                        <p style="text-align:center; color: red; font-size: 0.9em; margin-top: 5px;">Please login to place an order.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="place-order-button" disabled style="opacity: 0.5;">
                                        <i class="fas fa-mug-hot"></i> CART IS EMPTY
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/Navbar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentRadios = document.querySelectorAll('input[name="payment_display"]');
            const deliveryRadios = document.querySelectorAll('input[name="delivery_type_display"]');
            
            // Hidden inputs for form submission
            const hiddenPaymentInput = document.getElementById('hidden_payment_method');
            const hiddenDeliveryType = document.getElementById('hidden_delivery_type');
            const hiddenDeliveryAddress = document.getElementById('hidden_delivery_address');
            
            // Display elements
            const finalPaymentDisplay = document.getElementById('final_payment_display');
            const deliveryFeeDisplay = document.getElementById('delivery-fee-display');
            const finalTotalDisplay = document.getElementById('final-total-display');
            const addressSection = document.getElementById('delivery-address-section');
            const fetchedAddress = document.getElementById('fetched-address-display').textContent;

            // Price values
            const subtotal = parseFloat(document.getElementById('final_subtotal').value);
            const discount = parseFloat(document.getElementById('final_discount').value);
            const taxAmount = parseFloat(document.getElementById('final_tax_amount').value);
            const deliveryFeeValue = parseFloat(document.getElementById('delivery_fee_value').value);
            const pickupFeeValue = parseFloat(document.getElementById('pickup_fee_value').value);

            // --- Payment Method Logic ---
            function updatePaymentMethod() {
                const selectedRadio = document.querySelector('input[name="payment_display"]:checked');
                if (selectedRadio) {
                    const value = selectedRadio.value;
                    hiddenPaymentInput.value = value;

                    let displayText = (value === 'gcash') ? 'GCash (E-Wallet)' : 'COD (Cash on Pickup)';
                    finalPaymentDisplay.textContent = displayText;
                }
            }
            
            // --- Delivery Type and Total Calculation Logic ---
            function updateDeliveryTypeAndTotal() {
                const selectedDelivery = document.querySelector('input[name="delivery_type_display"]:checked');
                let currentDeliveryFee = 0.00;
                let currentDeliveryType = 'Pickup';
                let currentAddress = 'Pickup at Shop';

                if (selectedDelivery) {
                    currentDeliveryType = selectedDelivery.value;
                    hiddenDeliveryType.value = currentDeliveryType;

                    if (currentDeliveryType === 'Delivery') {
                        currentDeliveryFee = deliveryFeeValue;
                        addressSection.style.display = 'block';
                        currentAddress = fetchedAddress;
                    } else {
                        currentDeliveryFee = pickupFeeValue; // 0.00
                        addressSection.style.display = 'none';
                        currentAddress = 'Pickup at Shop';
                    }
                }

                // Update final total calculation
                const totalBase = subtotal - discount + taxAmount;
                const finalTotal = totalBase + currentDeliveryFee;

                // Update display elements
                deliveryFeeDisplay.textContent = `₱${currentDeliveryFee.toFixed(2)}`;
                finalTotalDisplay.textContent = `₱${finalTotal.toFixed(2)}`;
                hiddenDeliveryAddress.value = currentAddress;
            }

            // --- Event Listeners ---
            paymentRadios.forEach(radio => {
                radio.addEventListener('change', updatePaymentMethod);
            });

            deliveryRadios.forEach(radio => {
                radio.addEventListener('change', updateDeliveryTypeAndTotal);
            });
            
            // --- Initial Load ---
            updatePaymentMethod();
            updateDeliveryTypeAndTotal(); 
        });
    </script>
</body>
</html>