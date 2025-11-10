<?php
// 1. START THE SESSION at the very top of the file
session_start();

// ----------------------------------------------------------------------
// TEMPORARY UTILITY TO HASH CURRENT PASSWORD - USE ONCE THEN REMOVE!
// ----------------------------------------------------------------------
/*
// 2. DEFINE the plain text password that is currently working to log in.
$plainTextPassword = 'YourCurrentPlainTextPassword'; // <-- CHANGE THIS TO THE ACTUAL PASSWORD
$userToFixId = 1; // <-- CHANGE THIS TO THE USER_ID YOU ARE FIXING

if ($plainTextPassword !== 'YourCurrentPlainTextPassword') {
    $hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);
    
    // 3. Output the hash (visible in your browser)
    echo "<div style='background-color:#ffe0b2; padding: 20px; border: 2px solid orange; margin-bottom: 20px; font-family: monospace;'>";
    echo "<h1>PASSWORD HASHING UTILITY</h1>";
    echo "<p>User ID to fix: <strong>{$userToFixId}</strong></p>";
    echo "<p>Plain Text Password: <strong>{$plainTextPassword}</strong></p>";
    echo "<p><strong>NEW SECURE HASH: </strong><br><textarea rows='3' cols='80' onclick='this.select()'>{$hashedPassword}</textarea></p>";
    echo "<p><strong>ACTION:</strong> COPY this NEW SECURE HASH, paste it into the 'password' column of the 'users' table in your database for User ID {$userToFixId}.</p>";
    echo "<p style='color:red; font-weight:bold;'>!!! ONCE UPDATED, DELETE THIS ENTIRE TEMP BLOCK FROM YOUR PHP FILE !!!</p>";
    echo "</div>";
    // You can uncomment the line below if you only want to run this utility once and stop execution
    // exit(); 
}
*/
// ----------------------------------------------------------------------
// END OF TEMPORARY UTILITY
// ----------------------------------------------------------------------

// 2. DATABASE CONNECTION DETAILS
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_coffeeshop";

// --- START: USER IDENTIFICATION LOGIC ---
if (!isset($_SESSION['user_id'])) {
    // FOR DEMO/TESTING: Fallback to ID 1 if no session is set.
    // In a real application, you must redirect to login:
    // header('Location: login.php'); exit();
    $user_id = 1; 
} else {
    $user_id = $_SESSION['user_id'];
}
// --- END: USER IDENTIFICATION LOGIC ---

// 3. CREATE CONNECTION
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------------------------------------
// 4. HANDLERS AND INITIAL DATA FETCH
// ----------------------------------------------------
$default_image = '/Assets/Images/user-placeholder.jpg'; 
$name = "Not Available";
$phone = "Not Available";
$email = "Not Available";
$address = "Not Available"; // <--- ADDED: Address variable
$profile_picture = $default_image; 
$upload_message = "";
$edit_message = "";
// NEW: Message for password changes
$password_message = "";
// Debug variables for console logging - initialize as null
$debug_hash = null;
$debug_length = null;
$debug_verify_result = null; 
$upload_dir = 'uploads/'; 

// --- A. Handle User Details Update (Existing Logic + Address) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "update_details") {
    $new_name = trim($_POST['full_name']);
    $new_email = trim($_POST['email_address']);
    $new_phone = trim($_POST['phone_number']);
    $new_address = trim($_POST['address']); // <--- ADDED: Get the address from the POST request

    // Basic validation (you should add more robust server-side validation here)
    if (!empty($new_name) && !empty($new_email)) {
        // UPDATED SQL: Include 'address = ?'
        $update_sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        // UPDATED bind_param: Include 's' for string (address) and the $new_address variable
        $update_stmt->bind_param("ssssi", $new_name, $new_email, $new_phone, $new_address, $user_id); 

        if ($update_stmt->execute()) {
            $edit_message = "✅ Account details updated successfully!";
        } else {
            $edit_message = "Error updating details: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        $edit_message = "Error: Name and Email cannot be empty.";
    }
}

// --- B. Handle Photo Upload (Existing Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["profile_photo"])) {
    $file = $_FILES["profile_photo"];

    if ($file["error"] === UPLOAD_ERR_OK) {
        $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
        $new_filename = $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;

        // Basic validation checks
        if ($file["size"] > 5000000) {
            $upload_message = "Sorry, your file is too large (max 5MB).";
        } elseif (!getimagesize($file["tmp_name"])) {
            $upload_message = "File is not a valid image.";
        } elseif (move_uploaded_file($file["tmp_name"], $target_file)) {
            
            // Success: Update the database
            $db_path = $target_file; 
            
            $update_sql = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $db_path, $user_id); 

            if ($update_stmt->execute()) {
                $upload_message = "✅ Your profile photo has been updated successfully!";
                $profile_picture = $db_path; // Update PHP variable for immediate display
            } else {
                $upload_message = "Error updating database: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            $upload_message = "Sorry, there was an error moving your file.";
        }
    } else {
        $upload_message = "File upload failed with error code: " . $file["error"];
    }
}

// --- C. Handle Password Change (NEW LOGIC) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "change_password") {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 1. Get current hashed password from DB
    $sql = "SELECT password FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $current_hash = $user['password'] ?? '';

    // 2. Verify old password against the stored hash
    $is_old_password_valid = password_verify($old_password, $current_hash); 
    
    // --- DEBUG OUTPUT CAPTURE (For console logging) ---
    $debug_hash = $current_hash;
    $debug_length = strlen($current_hash);
    $debug_verify_result = $is_old_password_valid;
    // --- END DEBUG OUTPUT CAPTURE ---

    // Client-side validation is supplemented with Server-side checks
    if ($new_password !== $confirm_password) {
        $password_message = "Error: New password and confirm password do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_message = "Error: New password must be at least 8 characters long.";
    } else {
        if ($is_old_password_valid) {
            // 3. Hash and update new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_password_hash, $user_id);
            
            if ($update_stmt->execute()) {
                $password_message = "✅ Password successfully changed!";
            } else {
                $password_message = "Error updating password: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            // This is the message you are seeing. It means the hash verification failed.
            $password_message = "Error: Invalid old password.";
        }
    }
}


// --- D. Re-fetch user data (after potential updates) ---
// UPDATED SELECT: Include 'address'
$sql = "SELECT name, phone, email, address, profile_picture, password FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    // Ensure variables are updated with the latest data, escaped for security
    $name = htmlspecialchars($user_data['name']);
    $phone = htmlspecialchars($user_data['phone']);
    $email = htmlspecialchars($user_data['email']);
    $address = htmlspecialchars($user_data['address'] ?? 'Not Available'); // <--- ADDED: Get the address
    
    // If we didn't run the password change block, get the hash for the debug block on page load
    if ($debug_hash === null) {
        $debug_hash = $user_data['password'] ?? '';
        $debug_length = strlen($debug_hash);
        $debug_verify_result = null; // No verify result on page load
    }
    
    if (!empty($user_data['profile_picture'])) {
        $profile_picture = htmlspecialchars(ltrim($user_data['profile_picture'], '/'));
    }
}
$stmt->close();


// --- E. Fetch Order History (Existing Logic) ---
$orders = [];
$orders_sql = "SELECT order_id, total_price, payment_method, delivery_type, delivery_address, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

if ($orders_result->num_rows > 0) {
    while ($order = $orders_result->fetch_assoc()) {
        $order_id = $order['order_id'];
        $order['items'] = [];

        // Sub-query to get order items and JOIN to get item name
        $items_sql = "
            SELECT oi.quantity, oi.price, oi.addons, mi.name AS item_name
            FROM order_items oi
            JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE oi.order_id = ?";
        
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        while ($item = $items_result->fetch_assoc()) {
            $order['items'][] = $item;
        }
        $items_stmt->close();
        
        $orders[] = $order;
    }
}
$orders_stmt->close();


// 6. CLOSE CONNECTION
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Coffee Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/account.css">
    <style>
        /* --- General Profile Styles (existing) --- */
        .profile-photo-container {
            display: flex;
            flex-direction: column; 
            align-items: center; 
            margin-bottom: 20px;
            padding: 0; 
            border: none; 
            background-color: transparent; 
            position: relative;
        }
        .profile-photo-container img {
            width: 150px; 
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0; 
            border: 3px solid #6F4E37; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            cursor: zoom-in;
            transition: transform 0.2s ease;
        }
        #profile_photo { display: none; }
        .change-photo-btn {
            background-color: #6F4E37; 
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
            transition: background-color 0.2s;
        }
        .change-photo-btn:hover { background-color: #553D2C; }
        .upload-message {
            margin-top: 15px; 
            font-weight: bold;
            text-align: center; 
            width: 100%; 
            font-size: 0.9em;
        }
        .upload-message.error-message { color: red; }
        .upload-message:not(.error-message) { color: green; }

        /* --- MODAL STYLES (existing and updated for multiple modals) --- */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto; 
            background-color: rgba(0,0,0,0.9); 
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
            animation-name: zoom;
            animation-duration: 0.6s;
        }
        /* Style for the Password Modal box itself */
        .password-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            position: relative;
        }
        .password-modal-content h3 {
            color: #6F4E37;
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .password-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .password-form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .password-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 20px;
        }
        /* Reuse existing button styles for modal buttons */
        .password-actions .save-btn, .password-actions .cancel-btn {
            flex-grow: 1;
            padding: 10px;
            font-weight: bold;
        }
        
        @keyframes zoom {
            from {transform: scale(0)} 
            to {transform: scale(1)}
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        /* Specific close button for password modal */
        #passwordModal .close {
            color: #333;
            right: 15px;
            font-size: 30px;
            top: 5px;
        }
        .close:hover, .close:focus {
            color: #bbb;
            text-decoration: none;
        }

        /* --- NEW EDIT MODE STYLES (existing) --- */
        /* Message styling for edit details and password change */
        .feedback-message {
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            background-color: #e6ffe6; 
            color: green;
        }
        .feedback-message.error-message {
            background-color: #ffe6e6;
            color: red;
        }
        /* Removed .edit-message and replaced with generic .feedback-message */

        /* Existing styles for info rows, buttons, etc. */
        .account-info-simple {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .info-row label {
            font-size: 1em;
            font-weight: 600;
            color: #ffffffff; /* Coffee brown label */
            flex: 1;
        }
        .info-value, .edit-input {
            flex: 2;
            text-align: right;
            padding-right: 10px; /* Space between value and button */
            color: #333;
        }
        .edit-input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%; 
            text-align: left; 
        }
        .edit-input:focus {
            border-color: #6F4E37;
            outline: none;
        }
        .edit-btn-simple, .save-btn, .cancel-btn {
            background-color: #555;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            transition: background-color 0.2s;
            margin-left: 10px;
        }
        .edit-btn-simple:hover { background-color: #777; }
        
        .save-btn { background-color: #4CAF50; } 
        .save-btn:hover { background-color: #45a049; }

        .cancel-btn { background-color: #f44336; } 
        .cancel-btn:hover { background-color: #da190b; }

        /* State Classes */
        .edit-mode .info-value, 
        .edit-mode .edit-btn-simple,
        .edit-mode .password-row {
            display: none !important; 
        }

        .edit-mode .edit-input,
        .edit-mode .edit-actions {
            display: block !important;
        }

        .edit-actions {
            display: none; 
            flex-shrink: 0; 
            text-align: right;
        }

        /* --- Order History Styles (Existing) --- */
        .order-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 20px;
            transition: transform 0.2s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .order-id-date {
            font-size: 0.9em;
            color: #777;
        }

        .order-total {
            font-size: 1.2em;
            font-weight: bold;
            color: #6F4E37; /* Coffee brown */
        }

        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed { background-color: #e6ffe6; color: #4CAF50; }
        .status-pending { background-color: #fff3e6; color: #ff9800; }
        .status-cancelled { background-color: #ffe6e6; color: #f44336; }

        .order-items-list {
            list-style: none;
            padding: 0;
            margin: 10px 0;
            border-top: 1px dashed #eee;
            padding-top: 10px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
            padding: 5px 0;
        }

        .item-details span {
            margin-right: 10px;
            color: #555;
        }

        .item-details .item-name {
            font-weight: 600;
            color: #333;
        }

        .order-footer {
            border-top: 1px solid #eee;
            padding-top: 10px;
            font-size: 0.9em;
            color: #555;
        }
        .placeholder-card {
            padding: 20px;
            border: 1px dashed #ccc;
            text-align: center;
            border-radius: 8px;
            color: #555;
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
                <a href="#" class="logout-button">LOGOUT</a>
                <div class="burger-menu" id="burger-menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <img src="<?php echo $profile_picture; ?>" alt="User Profile" class="profile-image">
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge">3</span>
                </div>
            </div>
        </header>
        
        <main class="profile-main">
            <div class="profile-container">
                
                <aside class="profile-sidebar">
                    <nav class="profile-nav">
                        <ul>
                            <li><a href="#account-details" class="profile-nav-link active">Account Details</a></li>
                            <li><a href="#order-history" class="profile-nav-link">Order History</a></li>
                            <li><a href="#favorite-items" class="profile-nav-link">Favorite Items</a></li>
                        </ul>
                    </nav>
                </aside>

                <div class="profile-main-content">
                    <div class="profile-content">
                        
                        <section class="profile-section" id="account-details">
                            <h2 class="section-heading">Account Details</h2>
                            
                            <?php if (!empty($edit_message)): ?>
                            <p class="feedback-message <?php echo strpos($edit_message, 'Error') !== false ? 'error-message' : ''; ?>">
                                <?php echo $edit_message; ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($password_message)): ?>
                            <p class="feedback-message <?php echo strpos($password_message, 'Error') !== false ? 'error-message' : ''; ?>" id="password-feedback-message">
                                <?php echo $password_message; ?>
                            </p>
                            <?php endif; ?>


                            <div class="profile-photo-container">
                                <img src="<?php echo $profile_picture; ?>" alt="Profile Photo" id="profile_img" onclick="openImageModal('<?php echo $profile_picture; ?>')">
                                
                                <form action="account.php" method="post" enctype="multipart/form-data" id="photo-upload-form">
                                    <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
                                    <button type="button" class="change-photo-btn" id="change-photo-trigger">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </button>
                                    <button type="submit" name="upload_btn" style="display: none;" id="upload-submit-btn"></button>
                                </form>
                                
                                <p class="upload-message <?php echo strpos($upload_message, 'Error') !== false || strpos($upload_message, 'Sorry') !== false ? 'error-message' : ''; ?>">
                                    <?php echo $upload_message; ?>
                                </p>
                            </div>
                            <form method="POST" action="account.php" id="account-details-form">
                                <input type="hidden" name="action" value="update_details">

                                <div class="account-info-simple">
                                    
                                    <div class="info-row" data-field="full_name">
                                        <label for="full_name_input">Full Name</label>
                                        <span class="info-value" id="full_name_value"><?php echo $name; ?></span>
                                        <input type="text" name="full_name" id="full_name_input" class="edit-input" style="display: none;" value="<?php echo $name; ?>">
                                        <button type="button" class="edit-btn-simple" onclick="toggleEdit('full_name')">Edit</button>
                                        <div class="edit-actions" style="display: none;">
                                            <button type="submit" class="save-btn" onclick="return validateAndSave('full_name')">Save</button>
                                            <button type="button" class="cancel-btn" onclick="toggleEdit('full_name', true)">Cancel</button>
                                        </div>
                                    </div>

                                    <div class="info-row" data-field="email_address">
                                        <label for="email_address_input">Email Address</label>
                                        <span class="info-value" id="email_address_value"><?php echo $email; ?></span>
                                        <input type="email" name="email_address" id="email_address_input" class="edit-input" style="display: none;" value="<?php echo $email; ?>">
                                        <button type="button" class="edit-btn-simple" onclick="toggleEdit('email_address')">Edit</button>
                                        <div class="edit-actions" style="display: none;">
                                            <button type="submit" class="save-btn" onclick="return validateAndSave('email_address')">Save</button>
                                            <button type="button" class="cancel-btn" onclick="toggleEdit('email_address', true)">Cancel</button>
                                        </div>
                                    </div>

                                    <div class="info-row" data-field="phone_number">
                                        <label for="phone_number_input">Phone Number</label>
                                        <span class="info-value" id="phone_number_value"><?php echo $phone; ?></span>
                                        <input type="tel" name="phone_number" id="phone_number_input" class="edit-input" style="display: none;" value="<?php echo $phone; ?>">
                                        <button type="button" class="edit-btn-simple" onclick="toggleEdit('phone_number')">Edit</button>
                                        <div class="edit-actions" style="display: none;">
                                            <button type="submit" class="save-btn" onclick="return validateAndSave('phone_number')">Save</button>
                                            <button type="button" class="cancel-btn" onclick="toggleEdit('phone_number', true)">Cancel</button>
                                        </div>
                                    </div>

                                    <div class="info-row" data-field="address">
                                        <label for="address_input">Address</label>
                                        <span class="info-value" id="address_value"><?php echo $address; ?></span>
                                        <input type="text" name="address" id="address_input" class="edit-input" style="display: none;" value="<?php echo $address; ?>">
                                        <button type="button" class="edit-btn-simple" onclick="toggleEdit('address')">Edit</button>
                                        <div class="edit-actions" style="display: none;">
                                            <button type="submit" class="save-btn" onclick="return validateAndSave('address')">Save</button>
                                            <button type="button" class="cancel-btn" onclick="toggleEdit('address', true)">Cancel</button>
                                        </div>
                                    </div>
                                    
                                    <div class="info-row password-row">
                                        <label>Password</label>
                                        <span class="info-value">********</span>
                                        <button type="button" class="edit-btn-simple" onclick="openPasswordModal()">Change</button>
                                    </div>

                                </div>
                            </form>
                        </section>

                        <section class="profile-section" id="order-history">
                            <h2 class="section-heading">Order History</h2>
                            <div class="order-list">
                                <?php if (count($orders) > 0): ?>
                                    <?php foreach ($orders as $order): ?>
                                        <div class="order-card">
                                            <div class="order-header">
                                                <div class="order-id-date">
                                                    <strong>Order #<?php echo htmlspecialchars($order['order_id']); ?></strong><br>
                                                    Placed on <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                                </div>
                                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo htmlspecialchars($order['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <ul class="order-items-list">
                                                <?php foreach ($order['items'] as $item): ?>
                                                    <li class="order-item">
                                                        <div class="item-details">
                                                            <span class="item-name"><?php echo htmlspecialchars($item['item_name'] ?? 'Item'); ?></span>
                                                            (x<?php echo htmlspecialchars($item['quantity']); ?>)
                                                            <?php if (!empty($item['addons'])): ?>
                                                                <small>(+ <?php echo htmlspecialchars($item['addons']); ?>)</small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="item-price">
                                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>

                                            <div class="order-footer">
                                                <p class="order-total">Total Paid: $<?php echo number_format($order['total_price'], 2); ?></p>
                                                <p>
                                                    Method: <?php echo htmlspecialchars($order['payment_method']); ?> | 
                                                    Type: <?php echo htmlspecialchars($order['delivery_type']); ?>
                                                    <?php if ($order['delivery_type'] === 'Delivery' && !empty($order['delivery_address'])): ?>
                                                        <br>To: <?php echo htmlspecialchars($order['delivery_address']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="placeholder-card">
                                        <p>You haven't placed any orders yet. Time to check out our menu!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="profile-section" id="favorite-items">
                            <h2 class="section-heading">FAVORITE ITEMS</h2>
                            
                            <div class="favorites-grid-new">
                                <div class="favorite-item-new">
                                    <div class="item-image-new">
                                        <img src="/Assets/Images/coffee1.jpg" alt="Iced Caramel Macchiato">
                                    </div>
                                    <div class="item-info-new">
                                        <h4 class="item-name-new">Iced Caramel Macchiato</h4>
                                        <button class="favorite-btn-new active">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="favorite-item-new">
                                    <div class="item-image-new">
                                        <img src="/Assets/Images/coffee2.jpg" alt="Iced Cinnamon Swirl Pastry">
                                    </div>
                                    <div class="item-info-new">
                                        <h4 class="item-name-new">Iced Cinnamon Swirl Pastry</h4>
                                        <button class="favorite-btn-new active">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="favorite-item-new">
                                    <div class="item-image-new">
                                        <img src="/Assets/Images/pastry1.jpg" alt="Cinnamon Swirl Pastry">
                                    </div>
                                    <div class="item-info-new">
                                        <h4 class="item-name-new">Cinnamon Swirl Pastry</h4>
                                        <button class="favorite-btn-new active">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="favorite-item-new">
                                    <div class="item-image-new">
                                        <img src="/Assets/Images/coffee3.jpg" alt="Caramel Frappuccino">
                                    </div>
                                    <div class="item-info-new">
                                        <h4 class="item-name-new">Caramel Frappuccino</h4>
                                        <button class="favorite-btn-new active">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button class="logout-btn-main">LOGOUT</button>
                        </section>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="imageModal" class="modal" onclick="closeImageModal(event)">
        <span class="close" title="Close Modal">&times;</span>
        <img class="modal-content" id="img01">
    </div>
    <div id="passwordModal" class="modal" onclick="closePasswordModal(event)">
        <div class="password-modal-content">
            <span class="close" title="Close Modal">&times;</span>
            <h3>Change Password</h3>
            
            <form method="POST" action="account.php" class="password-form" id="password-change-form" onsubmit="return validatePasswordChange()">
                <input type="hidden" name="action" value="change_password">

                <label for="old_password">Old Password</label>
                <input type="password" id="old_password" name="old_password" required minlength="8">
                
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
                
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

                <div class="password-actions">
                    <button type="submit" class="save-btn">Save Changes</button>
                    <button type="button" class="cancel-btn" onclick="closePasswordModal()">Cancel</button>
                </div>
                </form>
        </div>
    </div>
    <script src="assets/js/Navbar.js"></script>
    <script>
        // --- Photo Upload Logic ---
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('profile_photo');
            const photoForm = document.getElementById('photo-upload-form');
            const changeButton = document.getElementById('change-photo-trigger');
            
            changeButton.addEventListener('click', function() {
                photoInput.click();
            });

            photoInput.addEventListener('change', function() {
                if (photoInput.files.length > 0) {
                    photoForm.submit();
                }
            });
            
            // Re-hide the edit/password messages after a few seconds if they exist
            const hideMessage = (selector) => {
                const message = document.querySelector(selector);
                if (message && message.textContent.trim() !== '') {
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 5000);
                }
            }
            hideMessage('.feedback-message');
        });

        // --- User Detail Edit Logic ---
        /**
         * Toggles the display mode between static value and input field for a specific row.
         * @param {string} fieldName The data-field attribute of the info-row (e.g., 'full_name').
         * @param {boolean} [isCancel=false] If true, forces a revert to static display and resets input value.
         */
        function toggleEdit(fieldName, isCancel = false) {
            const row = document.querySelector(`.info-row[data-field="${fieldName}"]`);
            const valueSpan = row.querySelector('.info-value');
            const inputField = row.querySelector('.edit-input');
            const editButton = row.querySelector('.edit-btn-simple');
            const editActions = row.querySelector('.edit-actions');
            
            if (isCancel) {
                // If canceling, reset the input value back to the static value
                inputField.value = valueSpan.textContent.trim();
                row.classList.remove('edit-mode');
                editButton.style.display = 'block';
                editActions.style.display = 'none';
                valueSpan.style.display = 'block';
                inputField.style.display = 'none';
                
            } else {
                // Toggle to edit mode
                row.classList.add('edit-mode');
                editButton.style.display = 'none';
                editActions.style.display = 'block';
                valueSpan.style.display = 'none';
                inputField.style.display = 'block';
                inputField.focus();
            }
        }

        /**
         * Ensures only the inputs for the currently edited row are submitted.
         */
        function validateAndSave(fieldName) {
            const inputField = document.getElementById(fieldName + '_input');
            
            if (fieldName === 'email_address' && !inputField.value.includes('@')) {
                console.error("Please enter a valid email address.");
                // In a real app, you would show a non-alert message here
                return false; 
            }
            
            // Allow form submission
            return true;
        }

        // --- Password Modal Logic (NEW) ---
        const passwordModal = document.getElementById("passwordModal");
        const passwordForm = document.getElementById("password-change-form");

        function openPasswordModal() {
            passwordModal.style.display = "flex";
            // Clear previous input values when opening
            passwordForm.reset(); 
        }

        function closePasswordModal(event) {
            // Only close if the event target is the modal backdrop or the specific close button
            if (!event || event.target === passwordModal || event.target.className === 'close') {
                passwordModal.style.display = "none";
                passwordForm.reset(); // Clear inputs on close
            }
        }
        
        // Handle clicking the specific close button on the password modal
        document.querySelector('#passwordModal .close').onclick = function(e) {
            closePasswordModal(e);
        }
        
        /**
         * Client-side validation for the password change form.
         */
        function validatePasswordChange() {
            const newPass = document.getElementById('new_password').value;
            const confPass = document.getElementById('confirm_password').value;
            
            if (newPass.length < 8) {
                console.error("New password must be at least 8 characters long.");
                // Provide user feedback (e.g., set an error state on the input fields)
                return false;
            }
            
            if (newPass !== confPass) {
                console.error("New password and confirmation do not match.");
                // Provide user feedback (e.g., set an error state on the input fields)
                return false;
            }
            
            return true; // Submit the form
        }


        // --- Image Zoom Modal Logic (existing) ---
        const imageModal = document.getElementById("imageModal");
        const modalImg = document.getElementById("img01");

        function openImageModal(imgSrc) {
            imageModal.style.display = "flex"; 
            modalImg.src = imgSrc;
        }

        function closeImageModal(event) {
            if (!event || event.target === imageModal || event.target.className === 'close') {
                imageModal.style.display = "none";
            }
        }

        document.querySelector('#imageModal .close').onclick = function(e) {
            closeImageModal(e);
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                if (imageModal.style.display === "flex") {
                    closeImageModal();
                } else if (passwordModal.style.display === "flex") {
                    closePasswordModal();
                }
            }
        });
        
        // If the page reloaded due to a successful/failed password change, scroll to the message
        <?php if (!empty($password_message)): ?>
            window.onload = function() {
                const message = document.getElementById('password-feedback-message');
                if (message) {
                    message.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };
        <?php endif; ?>
        
        // --- DEBUGGING BLOCK FOR PASSWORD VERIFICATION FAILURE ---
        // PHP variables are encoded to ensure safe passage to JavaScript
        const debugHash = <?php echo json_encode($debug_hash); ?>;
        const debugLength = <?php echo json_encode($debug_length); ?>;
        const debugVerifyResult = <?php echo json_encode($debug_verify_result); ?>;
        const passwordMessage = "<?php echo addslashes($password_message); ?>";
        
        // Only run this if a password post request was sent OR if it's a page load and the hash is short
        if (debugHash !== null && (passwordMessage.includes('Invalid old password') || (debugLength > 0 && debugLength < 60))) {
             console.groupCollapsed("%c Password Hash Debug Information", "color: red; font-weight: bold;");
             console.log("Old Password Hash from DB:", debugHash);
             console.log("Hash Length:", debugLength);
             if (debugVerifyResult !== null) {
                console.log("Verify Result (password_verify()):", debugVerifyResult ? "SUCCESS (TRUE)" : "FAIL (FALSE)");
             }
             
             if (debugLength < 60 && debugLength > 0) {
                 console.warn("%cACTION REQUIRED:", "color: orange; font-weight: bold;", "The stored password is too short. It must be a long hash (typically ~60 chars) created by password_hash(). This is the likely problem.");
                 console.warn("SOLUTION:", "1. Scroll up on this page to find the HASHING UTILITY. 2. Update the 'YourCurrentPlainTextPassword' placeholder with the password that currently works. 3. Copy the resulting long hash and paste it into the 'password' column for your user in the database (e.g., phpMyAdmin). 4. REMOVE the utility code from this file.");
             } else if (debugLength === 0) {
                 console.warn("ACTION REQUIRED:", "The stored password hash is empty in the database. Please ensure the user has a password set.");
             }

             console.groupEnd();
        }
        // --- END DEBUGGING BLOCK ---
    </script>
</body>
</html>