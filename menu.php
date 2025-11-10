<?php 
// Assumes db_connect.php is in the same directory or accessible via 'includes/'
include('includes/db_connect.php'); 
session_start(); 

// --- 1. Database Logic: Fetch Menu Items ---
$menu_items = [];
$db_error = null;
$profile_picture = '/Assets/Images/user-placeholder.jpg'; 

if (isset($_SESSION['user_id']) && isset($conn) && $conn->connect_error === null) {
    $user_id = $_SESSION['user_id'];
    
    // Prepare statement to fetch profile picture path
    $sql_user = "SELECT profile_picture FROM users WHERE user_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        
        if ($result_user && $result_user->num_rows === 1) {
            $user_data = $result_user->fetch_assoc();
            // Check if profile_picture path is valid/set in the database
            if (!empty($user_data['profile_picture'])) {
                $profile_picture = $user_data['profile_picture'];
            }
        }
        $stmt_user->close();
    }
}
// Ensure the connection is established before querying
if (isset($conn) && $conn->connect_error === null) {
    // FIX: Use LOWER(category) for case-insensitive exclusion of add-ons
    $sql = "SELECT 
                item_id, 
                name,             
                description, 
                price, 
                category, 
                image             
            FROM menu_items 
            WHERE status = 'active' 
                AND LOWER(category) NOT IN ('add ons', 'addons', 'add-ons / extras')
            ORDER BY category, name ASC";
            
    $result = $conn->query($sql);
    
    if ($result === false) {
        $db_error = "SQL Error: " . $conn->error;
        error_log($db_error);
    } elseif ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $menu_items[] = $row;
        }
    } else {
        // No items found, but query was successful
    }
} else {
    $db_error = "Database connection failed or is not available. Please check `includes/db_connect.php`.";
    error_log($db_error);
}

// --- 2. Utility Function (to map DB category to clean display name) ---
function format_category_display($db_category) {
    // Use the exact database name, but capitalize it for display
    return strtoupper($db_category);
}

// --- 3. Utility Function (to create a filter slug from the DB category) ---
function create_category_slug($db_category) {
    // Convert 'Hot Coffee' -> 'hot-coffee'
    return strtolower(str_replace(' ', '-', $db_category));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Coffee Shop</title>
    <link rel="stylesheet" href="assets/css/menu.css"> 
    <link rel="stylesheet" href="assets/css/navbar.css"> 
    <link rel="icon" type="image/x-icon" href="https://scontent.fmnl17-3.fna.fbcdn.net/v/t1.15752-9/476133121_944707607784720_4222766298493625099_n.jpg?stp=dst-jpg_s100x100_tt6&_nc_cat=106&ccb=1-7&_nc_sid=029a7d&_nc_eui2=AeHbXTSveWEb4OzutQZJ0bo9taI_vWM-p1y1oj-9Yz6nXI0YaxhtxRPTLLJMJmHWtmHktAjCfAJasIl2dW9Xd5mI&_nc_ohc=fujV-m1DLokQ7kNvwHfDq8g&_nc_oc=AdnbzmRf6BknvCWet4iFs18szBlKvHfOL...">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Add basic styling for the profile image if not already in navbar.css */
        .profile-picture-container .profile-img {
            width: 40px; /* Adjust size as needed */
            height: 40px; /* Adjust size as needed */
            border-radius: 50%; /* Makes it circular */
            object-fit: cover;
            border: 2px solid var(--accent-color); /* Assuming you have a CSS variable for accent color */
            display: block; /* Ensure no extra space below the image */
        }
        /* Hide the default icon if a picture is displayed */
        .profile-picture-container .fas.fa-user-circle {
            display: none;
        }
    </style>

    <style>
        /* CSS to style the dynamically generated menu item cards */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .menu-item-card {
            background-color: #2e2e2e;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            transition: transform 0.2s, box-shadow 0.2s;
            color: #fff;
        }

        .menu-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.7);
        }

        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 3px solid #e67e22; /* Accent color separator */
        }

        .item-details {
            padding: 15px;
        }

        .item-details h3 {
            color: #e67e22; /* Title color */
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1.4em;
        }

        .item-details p {
            font-size: 0.9em;
            color: #ccc;
            margin-bottom: 10px;
        }

        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .item-price {
            font-size: 1.6em;
            font-weight: bold;
            color: #ffffff;
        }

        .add-to-cart-btn {
            background-color: #e67e22;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .add-to-cart-btn:hover {
            background-color: #d35400;
        }
        
        /* Category Header Separator */
        .category-header {
            color: #e67e22;
            font-size: 1.8em;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #555;
            grid-column: 1 / -1; /* Make the header span all columns */
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
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge">3</span>
                </div>
            </div>
        </header>

        <main class="menu-main">
            <aside class="sidebar">
                <nav class="menu-nav">
                    <ul>
                        <li><a href="#" class="category-link" data-category="all">ALL ITEMS</a></li>
                        <li><a href="#" class="category-link" data-category="hot-coffee">HOT COFFEE</a></li>
                        <li><a href="#" class="category-link" data-category="iced-coffee">ICED COFFEE</a></li>
                        <li><a href="#" class="category-link" data-category="espresso">ESPRESSO DRINKS</a></li>
                        <li><a href="#" class="category-link" data-category="tea">TEA</a></li>
                        <li><a href="#" class="category-link" data-category="pastries">PASTRIES</a></li>
                    </ul>
                </nav>
            </aside>
            
            <section class="menu-content">
                <h1 class="menu-title">OUR MENU</h1>
                
                <?php if ($db_error): ?>
                    <div style="padding: 15px; margin-bottom: 20px; background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 5px;">
                        <i class="fas fa-exclamation-triangle"></i> Cannot load menu. <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>

                <div class="menu-grid" id="menu-grid">
                    <?php 
                    $current_db_category = ''; 

                    if (empty($menu_items)) {
                        echo "<p style='color: #ccc; grid-column: 1 / -1; text-align: center; padding: 50px;'>No menu items are currently available.</p>";
                    } else {
                        foreach ($menu_items as $item) {
                            
                            $item_db_category = $item['category']; 
                            $item_filter_slug = create_category_slug($item_db_category); 

                            $item_name = htmlspecialchars($item['name']); 
                            $item_desc = htmlspecialchars($item['description']);
                            $item_price = number_format($item['price'], 2);
                            
                            $item_image_db = $item['image'];
                            $item_image = 'assets/images/default.jpg'; 

                            if (!empty($item_image_db)) {
                                $item_image = htmlspecialchars($item_image_db); 
                            }
                            
                            
                            // Check if the database category has changed to insert a section header
                            if ($item_db_category !== $current_db_category) {
                                $current_db_category = $item_db_category;
                                $display_category = format_category_display($current_db_category);
                                
                                // Use the slug for the header's data-category attribute
                                echo "<h2 class='category-header' data-category='{$item_filter_slug}'>{$display_category}</h2>";
                            }
                            
                            // Menu Item Card HTML Structure
                            // Use the slug for the card's data-category attribute
                            echo "<div class='menu-item-card' data-category='{$item_filter_slug}'>";
                            echo "    <img src='{$item_image}' alt='{$item_name}' class='item-image'>";
                            echo "    <div class='item-details'>";
                            echo "        <h3>{$item_name}</h3>";
                            echo "        <p>{$item_desc}</p>";
                            echo "        <div class='item-footer'>";
                            // Pass item details via data attributes for the modal to use
                            echo "            <span class='item-price'>₱{$item_price}</span>";
                            // The data-id attribute is crucial for the JavaScript redirect
                            echo "            <button class='add-to-cart-btn' data-id='{$item['item_id']}'>ADD TO CART</button>";
                            echo "        </div>";
                            echo "    </div>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
            </section>
        </main>
    </div>
    
    <script src="assets/js/Navbar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categoryLinks = document.querySelectorAll('.category-link');
            const menuCards = document.querySelectorAll('.menu-item-card');
            const categoryHeaders = document.querySelectorAll('.category-header');
            const menuTitle = document.querySelector('.menu-title');
            
            // --- Elements for the Add to Cart action ---
            const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

            // Default click handler for category filtering
            categoryLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // 1. Update active state on sidebar links
                    categoryLinks.forEach(l => l.classList.remove('active'));
                    e.target.classList.add('active');
                    
                    // 2. Get the selected category (e.g., 'hot-coffee')
                    const selectedCategory = e.target.getAttribute('data-category');
                    
                    // 3. Update the main title display
                    menuTitle.textContent = selectedCategory === 'all' ? 'OUR MENU' : e.target.textContent;

                    // 4. Filter the menu cards and headers
                    menuCards.forEach(card => {
                        const cardCategory = card.getAttribute('data-category');
                        if (selectedCategory === 'all' || cardCategory === selectedCategory) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    categoryHeaders.forEach(header => {
                        const headerCategory = header.getAttribute('data-category');
                        if (selectedCategory === 'all' || headerCategory === selectedCategory) {
                            header.style.display = 'block';
                        } else {
                            header.style.display = 'none';
                        }
                    });

                    // Ensure the 'all' link always shows all category headers
                    if (selectedCategory === 'all') {
                         categoryHeaders.forEach(header => {
                            header.style.display = 'block';
                         });
                    }
                });
            });

            // Set 'ALL DRINKS' or 'ALL ITEMS' as default active on load if none is set
            const allLink = document.querySelector('.category-link[data-category="all"]');
            if (allLink && !document.querySelector('.category-link.active')) {
                allLink.classList.add('active');
            }
            
            // --- ADD TO CART REDIRECT LOGIC ---
            
            // Event listener for all "ADD TO CART" buttons
            addToCartButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    // Get the item ID from the data attribute
                    const itemID = e.target.getAttribute('data-id');
                    
                    // Redirect to addons.php, passing the item ID as a query parameter
                    // Example URL: addons.php?item_id=5
                    window.location.href = `addons.php?item_id=${itemID}`;
                });
            });

        });
    </script>
</body>
</html>