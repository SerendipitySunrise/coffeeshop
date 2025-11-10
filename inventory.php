<?php 
include('../includes/db_connect.php');
session_start(); 

// --- 1. Database Logic (UNMODIFIED) ---

$sql = "SELECT 
            i.ingredient_name, 
            i.quantity, 
            i.unit, 
            i.category, 
            inv.low_stock_level 
        FROM ingredients i
        INNER JOIN inventory inv ON i.ingredient_name = inv.ingredient_name 
        ORDER BY i.ingredient_name ASC";
        
$result = null;
$inventory_items = [];

if (isset($conn) && $conn->ping()) {
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $inventory_items[] = $row;
        }
    }
} else {
    error_log("Database connection failed or is not available.");
}

// Function to determine stock status (UNMODIFIED)
function get_stock_status($current_stock, $low_stock_level) {
    if (!is_numeric($current_stock) || !is_numeric($low_stock_level) || $low_stock_level <= 0) {
        return ['class' => 'good', 'text' => 'GOOD'];
    }
    if ($current_stock <= ($low_stock_level * 0.25)) {
        return ['class' => 'critical', 'text' => 'CRITICAL - REORDER'];
    } 
    elseif ($current_stock <= $low_stock_level) { 
        return ['class' => 'low', 'text' => 'LOW - ORDER SOON'];
    } 
    else {
        return ['class' => 'good', 'text' => 'GOOD'];
    }
}

// Function to format category (UNMODIFIED)
function format_category($db_category) {
    switch ($db_category) {
        case 'beans': return 'Coffee Beans & Ground';
        case 'dairy': return 'Dairy & Milk Alternatives';
        case 'syrups': return 'Syrups & Flavorings';
        case 'pastry': return 'Baking & Pastry';
        case 'tea': return 'Tea';
        case 'other': return 'Add-Ons & Other';
        default: return 'Uncategorized';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Coffee Shop</title>
    <link rel="stylesheet" href="../assets/css/inventory.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="icon" type="image/x-icon" href="https://scontent.fmnl17-3.fna.fbcdn.net/v/t1.15752-9/476133121_944707607784720_4222766298493625099_n.jpg?stp=dst-jpg_s100x100_tt6&_nc_cat=106&ccb=1-7&_nc_sid=029a7d&_nc_eui2=AeHbXTSveWEb4OzutQZJ0bo9taI_vWM-p1y1oj-9Yz6nXI0YaxhtxRPTLLJMJmHWtmHktAjCfAJasIl2dW9Xd5I&_nc_ohc=fujV-m1DLokQ7kNvwHfDq8g&_nc_oc=AdnbzmRf6BknvCWet4iFs18szBlKvHfOLnwPvF_Yz5vVNGXwjWsteEaM2u43sPz8450&_nc_zt=23&_nc_ht=scontent.fmnl17-3.fna&oh=03_Q7cD3gGJjWr_65WSg0tvi9N-0vVvuMYVYKORJ-0c42fXu4VQIg&oe=69191A0E">
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
                    <li><a href="dashboard.php" class="nav-link active">DASHBOARD</a></li>
                    <li><a href="adminorders.php" class="nav-link">ORDER</a></li>
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

        <main class="inventory-management">
            <h1 class="page-title">INVENTORY MANAGEMENT</h1>
            
            <div class="inventory-card">
                <div class="search-and-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search-input" placeholder="Search by name...">
                    </div>
                    <div class="category-filter">
                        <select id="category-filter-select">
                            <option value="">All Categories</option>
                            <option value="beans">Coffee Beans & Ground</option>
                            <option value="dairy">Dairy & Milk Alternatives</option>
                            <option value="syrups">Syrups & Flavorings</option>
                            <option value="pastry">Baking & Pastry</option>
                            <option value="tea">Tea</option>
                            <option value="other">Add-Ons & Other</option>
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
                
                <form action="handle_inventory_update.php" method="POST" id="inventory-update-form">

                    <div class="table-responsive">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th class="col-name">NAME</th>
                                    <th>CATEGORY</th>
                                    <th>UPDATE STOCK</th> <th>PAR LEVEL (Reorder Point)</th>
                                    <th class="col-status">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($inventory_items)) {
                                    foreach ($inventory_items as $item) {
                                        $name = $item['ingredient_name'];
                                        $current_stock = $item['quantity'];
                                        $unit = $item['unit'];
                                        $par_level = $item['low_stock_level']; 
                                        $category = $item['category']; 
                                        
                                        $status = get_stock_status($current_stock, $par_level);

                                        $display_category = format_category($category); 
                                        $display_par_level = htmlspecialchars($par_level) . ' ' . htmlspecialchars($unit); 
                                        
                                        // Sanitize ingredient name for use in HTML attributes
                                        $ingredient_name_safe = htmlspecialchars($name);
                                        $original_quantity = htmlspecialchars($current_stock); // Used for reset button

                                        echo "<tr class='item-row' data-category='" . htmlspecialchars($category) . "'>";
                                        echo "<td class='item-name'>" . $ingredient_name_safe . "</td>";
                                        echo "<td>" . $display_category . "</td>"; 
                                        
                                        // --- NEW STOCK CONTROL CELL ---
                                        echo "<td>";
                                        echo "  <div class='stock-controls' data-original='" . $original_quantity . "'>";
                                        // Decrement Button
                                        echo "      <button type='button' class='decrement-btn' data-step='1'>-</button>";
                                        // Quantity Input Field (name=updates[Ingredient Name] to send as a batch array)
                                        echo "      <input type='number' name='updates[" . $ingredient_name_safe . "]' value='" . $original_quantity . "' step='any' min='0' class='stock-input'>";
                                        // Increment Button
                                        echo "      <button type='button' class='increment-btn' data-step='1'>+</button>";
                                        // Reset/Correction Button
                                        echo "      <button type='button' class='reset-btn' title='Reset to original'><i class='fas fa-undo-alt'></i></button>";
                                        // Unit Display
                                        echo "      <span class='unit-display'>" . htmlspecialchars($unit) . "</span>";
                                        echo "  </div>";
                                        echo "</td>";
                                        // --- END NEW STOCK CONTROL CELL ---
                                        
                                        echo "<td>" . $display_par_level . "</td>";
                                        echo "<td class='status-cell'>";
                                        echo "<span class='status-badge " . $status['class'] . "'>" . $status['text'] . "</span>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' style='text-align: center; color: #cc3333; padding: 10px;'>No inventory items found. Check your database connection and table join condition.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="submit" class="save-all-changes-btn">
                        <i class="fas fa-save"></i> SAVE ALL CHANGES
                    </button>
                </form>
                </div>

            <div class="low-stock-alerts">
                <h3 class="alerts-title">LOW STOCK ALERTS:</h3>
                <p>
                    <?php
                    $low_stock_names = [];
                    foreach ($inventory_items as $item) {
                        $current_stock = $item['quantity'];
                        $par_level = $item['low_stock_level'];
                        $status = get_stock_status($current_stock, $par_level);
                        if ($status['class'] === 'critical' || $status['class'] === 'low') {
                            $low_stock_names[] = $item['ingredient_name'];
                        }
                    }
                    if (!empty($low_stock_names)) {
                        echo htmlspecialchars(implode(', ', $low_stock_names));
                    } else {
                        echo "All stock levels are good.";
                    }
                    ?>
                </p>
            </div>
        </main>

    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- COMBINED FILTER/SEARCH LOGIC (UNMODIFIED) ---
            const categorySelect = document.getElementById('category-filter-select');
            const searchInput = document.getElementById('search-input');
            const tableRows = document.querySelectorAll('.inventory-table tbody tr');

            function applyFilters() {
                const selectedCategory = categorySelect.value;
                const searchTerm = searchInput.value.toLowerCase();

                tableRows.forEach(row => {
                    const rowCategory = row.getAttribute('data-category');
                    const ingredientName = row.querySelector('.item-name').textContent.toLowerCase(); 

                    const passesCategory = (selectedCategory === '' || rowCategory === selectedCategory);
                    const passesSearch = (ingredientName.includes(searchTerm));

                    if (passesCategory && passesSearch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            categorySelect.addEventListener('change', applyFilters);
            searchInput.addEventListener('keyup', applyFilters); 
            // --- END COMBINED FILTER/SEARCH LOGIC ---


            // --- NEW STOCK CONTROL LOGIC (INCREMENT/DECREMENT/RESET) ---
            const stockControls = document.querySelectorAll('.stock-controls');

            stockControls.forEach(controlDiv => {
                const input = controlDiv.querySelector('.stock-input');
                const originalValue = parseFloat(controlDiv.getAttribute('data-original'));
                
                // 1. Decrement Button (-)
                controlDiv.querySelector('.decrement-btn').addEventListener('click', function() {
                    let currentValue = parseFloat(input.value);
                    if (!isNaN(currentValue) && currentValue > 0) {
                        // Decrement by 1, but don't go below zero
                        input.value = Math.max(0, currentValue - 1);
                    }
                });

                // 2. Increment Button (+)
                controlDiv.querySelector('.increment-btn').addEventListener('click', function() {
                    let currentValue = parseFloat(input.value);
                    if (isNaN(currentValue)) {
                        currentValue = 0;
                    }
                    // Increment by 1
                    input.value = currentValue + 1;
                });

                // 3. Reset Button (Circular Arrow)
                controlDiv.querySelector('.reset-btn').addEventListener('click', function() {
                    // Reset the input value to the quantity pulled from the database
                    input.value = originalValue;
                });

                // Optional: Force input to a non-negative number on change
                input.addEventListener('change', function() {
                    let currentValue = parseFloat(input.value);
                    if (isNaN(currentValue) || currentValue < 0) {
                        input.value = 0;
                    }
                });
            });
        });
    </script>
    <script src="assets/js/Navbar.js"></script>
</body>
</html>