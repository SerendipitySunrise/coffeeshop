<?php
// Ensure session is started and database connection is available
session_start();
// NOTE: Make sure your '../includes/db_connect.php' file establishes a $conn variable for the database connection.
// This is assumed to exist for the script to run correctly.
include('../includes/db_connect.php');

// --- Authorization Check ---
// Redirect non-admin users or unauthenticated users away
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/**
 * Fetches all details for a single menu item by ID.
 * This is used to load data into the form for editing.
 * @global mysqli $conn The database connection object.
 * @param int $item_id The ID of the item to fetch.
 * @return array|null An array of product data or null if not found/on failure.
 */
function getProductDetailsById($item_id) {
    global $conn;
    $safe_id = mysqli_real_escape_string($conn, $item_id);
    
    // Select all fields needed for the form, including 'description' and 'image_path'.
    $query_sql = "SELECT item_id, name, category, description, price, status, image 
                  FROM menu_items 
                  WHERE item_id = '$safe_id'";
                  
    $result = mysqli_query($conn, $query_sql);
    
    if ($result && mysqli_num_rows($result) === 1) {
        return mysqli_fetch_assoc($result);
    } else {
        error_log("Attempted to load edit product ID: {$item_id} failed. Error: " . mysqli_error($conn));
        return null;
    }
}

// --- HANDLE EDIT PRODUCT GET REQUEST ---
// Initialize variables for the form
$product_to_edit = null;
$is_editing = false;

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $product_to_edit = getProductDetailsById($edit_id); 
    
    if ($product_to_edit) {
        $is_editing = true;
    } else {
        // Clear edit_id if product not found and redirect
        $_SESSION['error_message'] = "Product with ID {$edit_id} not found.";
        header("Location: products.php");
        exit();
    }
}

// --- HANDLE ADD/UPDATE PRODUCT POST REQUEST ---
$error_message = null;
$success_message = null;

// Use a unified submit button name for both Add and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product'])) {
    
    // Determine if this is an update or an insert
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : null;
    $is_update = !empty($item_id);

    // 1. Sanitize and Validate Inputs
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    // Use filter_var for robust price validation
    $price = isset($_POST['price']) ? filter_var($_POST['price'], FILTER_VALIDATE_FLOAT) : 0.00;

    $input_error = '';
    
    // Simple validation checks
    if (empty($name) || empty($category) || $price === false || $price <= 0) {
        $input_error = "Please fill in all required fields (Name, Category, and Price must be a valid number greater than zero).";
    }

    if (empty($input_error)) {
        // Basic sanitization using mysqli_real_escape_string
        $safe_name = mysqli_real_escape_string($conn, $name);
        $safe_category = mysqli_real_escape_string($conn, $category);
        $safe_description = mysqli_real_escape_string($conn, $description);
        $price = $price; // Use the validated float price
        
        // --- Simulated File Upload Handling ---
        $image_update = ''; // Default: no image path change

        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            // Simplified handling: In a real app, move_uploaded_file would be used.
            $new_image = 'uploads/' . basename($_FILES['image_file']['name']);
            // If a new image is uploaded, prepare the query part for the update/insert
            $image_update = ", image = '{$new_image}'"; 
            $image = $new_image;
        } else if (!$is_update) {
            // Only set default path for new inserts if no file was uploaded
             $image = "assets/images/default_product.jpg"; // Default placeholder path
             $image_update = ", image = '{$image}'"; 
        }

        // Default status for a newly added item (or reuse existing)
        $status = 'active';

        if ($is_update) {
            // 2. Database UPDATE
            $update_sql = "UPDATE menu_items SET 
                           name = '$safe_name', 
                           category = '$safe_category', 
                           description = '$safe_description', 
                           price = '$price'
                           {$image_update} -- Add image path update if a new image was uploaded
                           WHERE item_id = $item_id";
            
            if (mysqli_query($conn, $update_sql)) {
                $_SESSION['success_message'] = "Product '{$name}' updated successfully!";
                header("Location: products.php"); // Redirect to clear URL parameter
                exit();
            } else {
                $input_error = "Database update failed: " . mysqli_error($conn);
                error_log($input_error);
            }
        } else {
            // 2. Database INSERT
            // Note: If image_path_update is defined, it means $image_path is also defined.
            $insert_sql = "INSERT INTO menu_items (name, category, description, price, status, image)
                           VALUES ('$safe_name', '$safe_category', '$safe_description', '$price', '$status', '{$image}')";
            
            if (mysqli_query($conn, $insert_sql)) {
                // Success: Use session variable for success message (PRG pattern)
                $_SESSION['success_message'] = "Product '{$name}' added successfully!";
                header("Location: products.php");
                exit();
            } else {
                $input_error = "Database insertion failed: " . mysqli_error($conn);
                error_log($input_error); // Log detailed error to server logs
            }
        }
    }
    
    // If there was an error, store it in the session to display after the redirect (or fall-through if no redirect happened)
    if (!empty($input_error)) {
        $_SESSION['error_message'] = $input_error;
    }
}


// --- Status/Message Handling (read and clear session messages) ---
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
unset($_SESSION['success_message']);

$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['error_message']);


// --- Filtering and Search Input Handling ---
// Retrieve search and filter parameters from the URL
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'All';


/**
 * Fetches all menu items (products) from the database using the provided schema, 
 * optionally applying search and category filters.
 * NOTE: Using mysqli_real_escape_string for basic security due to lack of prepared statements in this context.
 * @global mysqli $conn The database connection object.
 * @param string $search_term The name search query.
 * @param string $category_filter The category to filter by ('All' to ignore).
 * @return array An array of product data or an empty array on failure.
 */
function getMenuItems($search_term, $category_filter) {
    global $conn;
    $products = [];
    $where_clauses = [];
    
    // Selecting 'status' (availability) is kept for display in the table
    $query_sql = "SELECT item_id, name, category, price, status FROM menu_items";

    // 1. Search by Name
    if (!empty($search_term)) {
        // Assume $conn is defined and connected
        $safe_search = mysqli_real_escape_string($conn, $search_term);
        // Search for products whose name contains the search term
        $where_clauses[] = "name LIKE '%" . $safe_search . "%'";
    }

    // 2. Filter by Category (if not 'All')
    if ($category_filter !== 'All' && !empty($category_filter)) {
        // Assume $conn is defined and connected
        $safe_category = mysqli_real_escape_string($conn, $category_filter);
        $where_clauses[] = "category = '" . $safe_category . "'";
    }

    // Append WHERE clauses if any exist
    if (!empty($where_clauses)) {
        $query_sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $query_sql .= " ORDER BY name ASC";

    $result = mysqli_query($conn, $query_sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
    } else {
        // Log error (for debugging, not user display)
        error_log("Database Error in getMenuItems: " . mysqli_error($conn) . " Query: " . $query_sql);
    }
    return $products;
}

/**
 * Fetches unique categories from the database for the filter dropdown.
 * @global mysqli $conn The database connection object.
 * @return array A list of unique categories.
 */
function getUniqueCategories() {
    global $conn;
    $categories = [];
    $query = "SELECT DISTINCT category FROM menu_items ORDER BY category ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row['category'];
        }
        mysqli_free_result($result);
    }
    return $categories;
}

// Fetch data using the filters
$unique_categories = getUniqueCategories();
$menu_items = getMenuItems($search_query, $category_filter);


// Helper function to determine the Font Awesome icon based on category
function getProductIcon($category) {
    $category = strtolower($category);
    if (strpos($category, 'coffee') !== false || strpos($category, 'espresso') !== false || strpos($category, 'latte') !== false) {
        return 'fas fa-mug-hot';
    } elseif (strpos($category, 'tea') !== false) {
        return 'fas fa-tea';
    } elseif (strpos($category, 'pastry') !== false || strpos($category, 'dessert') !== false) {
        return 'fas fa-bread-slice';
    } else {
        return 'fas fa-utensils'; // General food icon
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management | UNLI MAMI SYSTEM</title>
    <!-- Assuming the user has created and linked these CSS files -->
    <link rel="stylesheet" href="../assets/css/product.css">
    <link rel="stylesheet" href="../assets/css/navbar.css"> 
    <link rel="icon" type="image/x-icon" href="https://scontent.fmnl17-3.fna.fbcdn.net/v/t1.15752-9/476133121_944707607784720_4222766298493625099_n.jpg?stp=dst-jpg_s100x100_tt6&_nc_cat=106&ccb=1-7&_nc_sid=029a7d&_nc_eui2=AeHbXTSveWEb4OzutQZJ0bo9taI_vWM-p1y1oj-9Yz6nXI0YaxhtxRPTLLJMJmHWtmHktAjCfAJasIl2dW9Xd5mI&_nc_ohc=fujV-m1DLokQ7kNvwHfDq8g&_nc_oc=AdnbzmRf6BknvCWet4iFs18szBlKvHfOLnwPvF_Yz5vVNGXwjWsteEaM2u43sPz8450&_nc_zt=23&_nc_ht=scontent.fmnl17-3.fna&oh=03_Q7cD3gGJjWr_65WSg0tvi9N-0vVvuMYVYKORJ-0c42fXu4VQIg&oe=69191A0E">
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
                    <li><a href="adminorders.php" class="nav-link">ORDER</a></li>
                    <li><a href="inventory.php" class="nav-link">INVENTORY</a></li>
                    <li><a href="products.php" class="nav-link active">PRODUCTS</a></li>
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

        <main class="product-main">
            <h1 class="page-title">PRODUCT MANAGEMENT</h1>

            <div class="product-management-card product-list-section">
                <h2>CURRENT PRODUCTS (<?php echo count($menu_items); ?>)</h2>
                
                <!-- START: Filter and Search Form -->
                <form method="GET" action="products.php" class="search-filter-form">
                    <div class="search-filter">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <!-- Set name and use current search query as value -->
                            <input type="text" name="search" id="search-input" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <!-- Hidden submit button for enter key functionality -->
                            <button type="submit" style="display:none;"></button>
                        </div>
                        <div class="category-filter">
                            <!-- Set name and submit form on change -->
                            <select name="category" id="category-select" onchange="this.form.submit()">
                                <option value="All" <?php echo ($category_filter === 'All') ? 'selected' : ''; ?>>Category: All</option>
                                <!-- Dynamically populate unique categories and mark current filter as selected -->
                                <?php foreach ($unique_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"
                                        <?php echo ($category_filter === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                </form>
                <!-- END: Filter and Search Form -->
                
                <div class="table-responsive">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>NAME & CATEGORY</th> <th>PRICE</th>
                                <th>AVAILABILITY</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($menu_items)): ?>
                                <tr>
                                    <!-- Display a message if no products match the current filters -->
                                    <td colspan="4" style="text-align: center; padding: 20px;">
                                        No products found matching the current filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($menu_items as $item): ?>
                                    <?php
                                        // Use the database column 'status'
                                        $is_active = $item['status'] === 'active';
                                        
                                        // --- LOGIC: Check status only ---
                                        if ($is_active) {
                                            // Status is active -> Available
                                            $availability_text = 'Available';
                                            $availability_class = 'available';
                                        } else {
                                            // Status is not active -> Not Available
                                            $availability_text = 'Not Available';
                                            $availability_class = 'out-of-stock';
                                        }
                                        // --- END LOGIC ---

                                        // Get product icon
                                        $icon_class = getProductIcon($item['category']);
                                    ?>
                                    <tr class="product-row">
                                        <td>
                                            <div class="product-name">
                                                <div class="product-icon">
                                                    <i class="<?php echo $icon_class; ?>"></i>
                                                </div>
                                                <div class="product-details">
                                                    <span class="product-title"><?php echo htmlspecialchars($item['name']); ?></span>
                                                    <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="product-price">₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <div class="availability">
                                                <span class="availability-badge <?php echo $availability_class; ?>">
                                                    <?php echo $availability_text; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <!-- Edit button now uses JavaScript to redirect with the item ID -->
                                                <button class="edit-btn" data-id="<?php echo $item['item_id']; ?>">Edit</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ADD/EDIT PRODUCT section -->
            <div class="product-management-card new-product-form">
                <!-- Dynamic Header based on edit mode -->
                <h2>
                    <?php 
                    if ($is_editing) {
                        echo 'EDIT PRODUCT (ID: ' . htmlspecialchars($product_to_edit['item_id']) . ')';
                    } else {
                        echo 'ADD NEW PRODUCT';
                    }
                    ?>
                </h2>
                
                <?php if ($success_message): ?>
                    <div class="message success-message" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb; padding: 10px; margin-bottom: 15px; border-radius: 5px;"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="message error-message" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 5px;"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <!-- Form uses POST and handles file uploads -->
                <form action="products.php" method="POST" enctype="multipart/form-data">
                    
                    <!-- Hidden input to unify submission handling (Add or Update) -->
                    <input type="hidden" name="submit_product" value="1">
                    
                    <?php if ($is_editing): ?>
                        <!-- CRITICAL: Hidden input to pass the item ID for the UPDATE query -->
                        <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($product_to_edit['item_id']); ?>">
                    <?php endif; ?>
                    
                    <!-- Row 1: Name and Category -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product-name">NAME</label>
                            <input type="text" id="product-name" name="name" 
                                   placeholder="E.g., Muffins" required 
                                   value="<?php echo $is_editing ? htmlspecialchars($product_to_edit['name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="product-category">CATEGORY</label>
                            <select id="product-category" name="category" required>
                                <option value="" disabled <?php echo !$is_editing ? 'selected' : ''; ?>>Select Category</option>
                                
                                <?php 
                                // Predefined categories (can be expanded)
                                $default_categories = ["Hot Coffee", "Cold Coffee", "Pastries", "Tea", "Add Ons"];
                                $combined_categories = array_unique(array_merge($default_categories, $unique_categories));
                                sort($combined_categories); // Sort for better user experience
                                
                                $current_category = $is_editing ? $product_to_edit['category'] : '';
                                
                                foreach ($combined_categories as $cat): 
                                    $selected = ($current_category === $cat) ? 'selected' : '';
                                ?>
                                   <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Row 2: Description (Full Width) -->
                    <div class="form-row">
                        <div class="form-group" style="flex-grow: 1;">
                            <label for="product-description">DESCRIPTION</label>
                            <textarea id="product-description" name="description" placeholder="A brief description of the product..."><?php echo $is_editing ? htmlspecialchars($product_to_edit['description']) : ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Row 3: Price and Image (Fixed Alignment) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product-price">PRICE</label>
                            <input type="number" step="0.01" id="product-price" name="price" 
                                   placeholder="E.g., 6.95" required 
                                   value="<?php echo $is_editing ? htmlspecialchars($product_to_edit['price']) : ''; ?>">
                        </div>
                        <div class="form-group" style="flex-grow: 1;">
                            <label for="product-image">IMAGE (Browse File)</label>
                            <input type="file" id="product-image" name="image_file">
                            <small style="color: #666; display: block; margin-top: 5px;">
                                <?php if ($is_editing): ?>
                                    Current image: <?php echo htmlspecialchars($product_to_edit['image'] ?? 'None'); ?>. Leave blank to keep current image.
                                <?php else: ?>
                                    Note: File must be handled by a server-side script and its path stored in the database.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Row 4: Submit Button and Cancel Button -->
                    <div class="form-row">
                        <button type="submit" class="add-btn" style="margin-top: 10px;">
                            <?php echo $is_editing ? 'UPDATE PRODUCT' : 'ADD PRODUCT'; ?>
                        </button>
                        <?php if ($is_editing): ?>
                            <!-- Button to clear the edit mode and return to the Add New Product state -->
                            <a href="products.php" class="cancel-edit-btn" style="margin-top: 10px; margin-left: 10px; background-color: #f39c12; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; display: inline-block; border: none;">CANCEL EDIT</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>

    </div>
    
    <script src="../assets/js/Navbar.js"></script>
    <script>
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    // Get the item ID from the button's data attribute
                    const itemId = e.target.getAttribute('data-id');
                    // Redirect to products.php, passing the item ID as a query parameter
                    // PHP on products.php then reads this 'edit_id' to load the form data.
                    window.location.href = 'products.php?edit_id=' + itemId; 
                });
            });        
        document.addEventListener('DOMContentLoaded', () => {
            
            // --- UPDATED EDIT BUTTON FUNCTIONALITY ---
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    const itemId = e.target.getAttribute('data-id');
                    // Redirect to products.php with the item ID to enter edit mode in PHP
                    window.location.href = 'products.php?edit_id=' + itemId; 
                });
            });

            
            // Auto-submit the form when the user types in the search box and presses Enter
            const searchInput = document.getElementById('search-input');
            const form = document.querySelector('.search-filter-form');
            if (searchInput && form) {
                searchInput.addEventListener('keypress', (e) => {
                    // Check if the key pressed is 'Enter' (key code 13)
                    if (e.key === 'Enter') {
                        e.preventDefault(); // Prevent default form submission via Enter key
                        form.submit(); // Manually submit the form to apply the search filter
                    }
                });
            }
        });
    </script>
</body>
</html>
