<?php 
// Assumes db_connect.php is in the same directory or accessible via 'includes/'
include('includes/db_connect.php'); 
session_start(); 

// --- Helper Functions ---
function get_drink_type_slug($category) {
    $standardized = strtoupper(str_replace([' ', '/', '-'], '_', $category));

    switch ($standardized) {
        case 'HOT_COFFEE':
        case 'ICED_COFFEE':
            return $standardized;
        case 'ESPRESSO_DRINKS':
        case 'ESPRESSO':
            return 'ESPRESSO';
        case 'TEA':
            return 'TEA';
        case 'PASTRIES':
            return 'PASTRIES'; 
        default:
            return 'OTHER';
    }
}

// --- MOCK ADDONS DATA (Moved to PHP for Server-Side Price Calculation) ---
// This is the source of truth for add-on pricing
$ADDONS_DATA_SERVER = [
    'EXTRA_SHOT' => ['name' => "Extra Espresso Shot", 'price' => 0.75],
    'FLAVORED_SYRUP' => ['name' => "Flavored Syrups", 'price' => 0.50],
    'SUGAR_FREE_SYRUP' => ['name' => "Sugar-Free Syrup", 'price' => 0.50],
    'OAT_MILK' => ['name' => "Oat/Almond/Soy Milk", 'price' => 0.75],
    'WHIPPED_CREAM' => ['name' => "Whipped Cream", 'price' => 0.50],
    'CHOCOLATE_DRIZZLE' => ['name' => "Chocolate Drizzle", 'price' => 0.50],
    'EXTRA_FOAM' => ['name' => "Extra Foam", 'price' => 0.50],
    'ICE_CREAM_SCOOP' => ['name' => "Ice Cream Scoop", 'price' => 1.50],
    // Size prices must also be accessible here for security
    'size-medium' => ['price' => 1.00],
    'size-large' => ['price' => 1.50],
    'size-small' => ['price' => 0.00], // Base size
];

// --- 1. Handle Form Submission (Add to Cart) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    // Server-side calculation and cart insertion (MOCK/SIMULATED)
    $item_id = (int)$_POST['item_id'];
    // The selected size is sent directly from the radio button input
    $size = htmlspecialchars($_POST['size']); 
    $quantity = (int)$_POST['quantity'];
    // The client sends a comma-separated string of keys (e.g., 'EXTRA_SHOT,WHIPPED_CREAM')
    $selected_addon_keys = isset($_POST['addons']) ? explode(',', $_POST['addons']) : [];
    
    // --- Securely re-fetch product data for base price and name ---
    $base_price = 0.00;
    $product_name = "Custom Item";
    if ($item_id > 0 && isset($conn) && $conn->connect_error === null) {
        $sql_fetch = "SELECT name, price FROM menu_items WHERE item_id = ?";
        $stmt_fetch = $conn->prepare($sql_fetch);
        if ($stmt_fetch) {
            $stmt_fetch->bind_param("i", $item_id);
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();
            if ($result_fetch && $result_fetch->num_rows === 1) {
                $data = $result_fetch->fetch_assoc();
                $base_price = (float)$data['price'];
                $product_name = $data['name'];
            }
            $stmt_fetch->close();
        }
    }
    
    // *** SERVER-SIDE PRICE CALCULATION (SECURITY CRITICAL) ***
    $final_price = $base_price;
    
    // Add Size Price
    $size_key = 'size-' . $size;
    if (isset($ADDONS_DATA_SERVER[$size_key])) {
        $final_price += $ADDONS_DATA_SERVER[$size_key]['price'];
    }

    // Add Add-on Prices
    foreach ($selected_addon_keys as $addon_key) {
        $key = strtoupper(trim($addon_key));
        if (isset($ADDONS_DATA_SERVER[$key])) {
            $final_price += $ADDONS_DATA_SERVER[$key]['price'];
        }
    }

    // Apply Quantity
    $final_price *= $quantity;
    
    
    // --- START: DATA PREPARATION FOR 'order_items' TABLE ---
    
    // 1. Convert selected add-on keys to their full names (e.g., 'Extra Espresso Shot')
    $addon_names = array_map(function($key) use ($ADDONS_DATA_SERVER) {
        // Find the full name for the given key, ensuring case-insensitivity and trimming
        $key_upper = strtoupper(trim($key));
        return $ADDONS_DATA_SERVER[$key_upper]['name'] ?? null;
    }, $selected_addon_keys);
    
    // Remove any null entries (in case of invalid keys)
    $addon_names = array_filter($addon_names);

    // 2. Format the addon string as requested: "Extra Espresso Shot, Whipped Cream"
    $addon_string_for_db = implode(', ', $addon_names);
    
    // The $size variable already holds the correct value (e.g., 'small', 'medium') for the 'size' field.
    
    // --- END: DATA PREPARATION FOR 'order_items' TABLE ---
    
    
    // --- 2. Store in Session Cart ---
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Create the cart item payload
    $cart_item = [
        'id' => $item_id,
        'name' => $product_name,
        // ** (a) This is the value for the 'size' field in order_items **
        'size' => $size, 
        'quantity' => $quantity,
        // ** (b) This is the value for the 'addons' field in order_items **
        'addons_string' => $addon_string_for_db,
        
        // Retain 'addons' array for backward compatibility if other files use it
        'addons' => $addon_names, 
        
        'price' => $final_price,
    ];
    
    $_SESSION['cart'][] = $cart_item;
    
    // --- 3. REDIRECT TO ORDERSUMMARY.PHP ---
    // The user's customized order is now saved on the server, redirect to the summary page.
    header('Location: ordersummary.php'); 
    exit;
}

// --- 4. Fetch Product Data from DB (GET Request for initial load) ---
$product_data = null;
$db_error = null;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($item_id > 0 && isset($conn) && $conn->connect_error === null) {
    $sql = "SELECT item_id, name, description, price, category, image FROM menu_items WHERE item_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $product_data = $result->fetch_assoc();
            $product_data['drink_type'] = get_drink_type_slug($product_data['category']);
        } else {
            $db_error = "Product with ID #{$item_id} not found.";
        }
        $stmt->close();
    } else {
        $db_error = "Database query preparation failed.";
    }
} else {
    $db_error = "Invalid item ID or database connection failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Your Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Define colors and font for Tailwind */
        :root {
            --primary-orange: #e67e22; 
            --dark-bg: #1e1e1e; 
            --dark-card: #2e2e2e; 
            --dark-option: #374151; 
        }
        
        body { background-color: var(--dark-bg); }

        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--primary-orange); border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #252525; }

        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
        input:checked + .slider { background-color: var(--primary-orange); }
        input:checked + .slider:before { transform: translateX(20px); }
        .slider.round { border-radius: 24px; }
        .slider.round:before { border-radius: 50%; }

        .radio-card input[type="radio"]:checked + .radio-label {
            border-color: var(--primary-orange);
            background-color: rgba(230, 126, 34, 0.1); 
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-orange': 'var(--primary-orange)',
                        'dark-bg': 'var(--dark-bg)',
                        'dark-card': 'var(--dark-card)',
                        'dark-option': 'var(--dark-option)',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-dark-bg text-white min-h-screen flex items-center justify-center p-4" style="font-family: 'Inter', sans-serif;">

    <?php if ($product_data): ?>
    <form method="POST" action="addons.php" id="customization-form" class="w-full max-w-4xl bg-dark-card rounded-2xl shadow-2xl p-6 md:p-10 flex flex-col md:flex-row space-y-6 md:space-y-0 md:space-x-8">

        <input type="hidden" name="item_id" value="<?php echo $product_data['item_id']; ?>">
        <!-- This hidden field now holds comma-separated keys (e.g., EXTRA_SHOT,WHIPPED_CREAM) -->
        <input type="hidden" name="addons" id="hidden-addons-input"> 

        <div class="md:w-1/2 flex flex-col items-center">
            <img 
                src="<?php echo htmlspecialchars($product_data['image'] ?: 'assets/images/default.jpg'); ?>" 
                alt="<?php echo htmlspecialchars($product_data['name']); ?>" 
                class="rounded-xl object-cover w-full h-auto max-h-96 shadow-xl mb-6 border-b-2 border-primary-orange"
                onerror="this.onerror=null; this.src='assets/images/default.jpg';"
            >
            
            <div class="text-center w-full">
                <h1 class="text-3xl font-bold mb-2 text-white"><?php echo htmlspecialchars($product_data['name']); ?></h1>
                <p class="text-gray-400 mb-4"><?php echo htmlspecialchars($product_data['description']); ?></p>
                
                <div class="flex items-center justify-center space-x-2 text-xl font-semibold mb-4">
                    <p class="text-gray-300">Base Price:</p>
                    <p id="base-price-display" class="text-primary-orange">
                        ₱<?php echo number_format($product_data['price'], 2); ?>
                    </p>
                </div>

                <div class="flex items-center justify-center space-x-4">
                    <button type="button" id="decrement-quantity" class="bg-primary-orange hover:bg-orange-700 text-white font-bold p-2 rounded-full w-8 h-8 flex items-center justify-center transition duration-150 shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                    </button>
                    <input type="number" name="quantity" id="quantity-display" value="1" min="1" max="99" readonly class="text-2xl font-extrabold w-10 text-center bg-transparent border-none text-white p-0">
                    <button type="button" id="increment-quantity" class="bg-primary-orange hover:bg-orange-700 text-white font-bold p-2 rounded-full w-8 h-8 flex items-center justify-center transition duration-150 shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="md:w-1/2 flex flex-col space-y-6">
            
            <div class="flex-grow max-h-96 custom-scrollbar overflow-y-auto pr-2 space-y-5">
                
                <div class="option-group">
                    <h3 class="text-xl font-semibold mb-3 text-primary-orange">1. Select Size (Required)</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="radio-card">
                            <!-- The 'name="size"' attribute ensures the value ('small') is sent directly to PHP for the 'size' field -->
                            <input type="radio" id="size-small" name="size" data-price="0.00" value="small" checked required class="hidden">
                            <label for="size-small" class="radio-label block border border-gray-600 p-3 rounded-xl cursor-pointer text-center transition duration-200 hover:border-primary-orange">
                                <span class="text-white font-medium">Small</span>
                                <span class="block text-sm text-gray-400">₱0.00</span>
                            </label>
                        </div>
                        <div class="radio-card">
                            <!-- The 'name="size"' attribute ensures the value ('medium') is sent directly to PHP for the 'size' field -->
                            <input type="radio" id="size-medium" name="size" data-price="1.00" value="medium" class="hidden">
                            <label for="size-medium" class="radio-label block border border-gray-600 p-3 rounded-xl cursor-pointer text-center transition duration-200 hover:border-primary-orange">
                                <span class="text-white font-medium">Medium</span>
                                <span class="block text-sm text-primary-orange">+₱1.00</span>
                            </label>
                        </div>
                        <div class="radio-card">
                            <!-- The 'name="size"' attribute ensures the value ('large') is sent directly to PHP for the 'size' field -->
                            <input type="radio" id="size-large" name="size" data-price="1.50" value="large" class="hidden">
                            <label for="size-large" class="radio-label block border border-gray-600 p-3 rounded-xl cursor-pointer text-center transition duration-200 hover:border-primary-orange">
                                <span class="text-white font-medium">Large</span>
                                <span class="block text-sm text-primary-orange">+₱1.50</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="option-group">
                    <h3 class="text-xl font-semibold mb-3 text-primary-orange">2. Additional Options (Optional)</h3>
                    <div id="addons-container" class="space-y-3">
                        <p class="text-gray-500 text-center py-4">Loading add-ons...</p>
                    </div>
                </div>

            </div>

            <div class="border-t border-gray-700 pt-6">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-2xl font-bold text-gray-200">Total Price:</span>
                    <span class="text-3xl font-extrabold text-primary-orange">₱<span id="total-price-display">0.00</span></span>
                </div>
                
                <button type="submit" id="add-to-cart-btn" class="w-full bg-primary-orange text-white py-3 rounded-xl text-lg font-bold hover:bg-orange-700 transition duration-150 shadow-lg shadow-orange-900/50">
                    Add to Cart
                </button>

                <button type="button" id="close-modal-btn" class="w-full mt-2 text-gray-400 py-2 rounded-xl text-sm hover:text-white transition duration-150" onclick="window.location.href='menu.php'">
                    Cancel
                </button>
            </div>
        </div>
    </form>
    
    <?php else: ?>
    <div class="max-w-md bg-dark-card rounded-xl p-8 text-center shadow-2xl">
        <p class="text-2xl font-bold text-primary-orange mb-4">Error Loading Product</p>
        <p class="text-gray-400 mb-6"><?php echo htmlspecialchars($db_error); ?></p>
        <button onclick="window.location.href='menu.php'" class="bg-primary-orange text-white py-2 px-6 rounded-lg hover:bg-orange-700 transition">
            Go Back to Menu
        </button>
    </div>
    <?php endif; ?>


    <script>
        // --- DYNAMIC PHP VARIABLES PASSED TO JAVASCRIPT ---
        const BASE_PRICE = <?php echo $product_data ? (float)$product_data['price'] : '0.00'; ?>;
        const PRODUCT_ID = <?php echo $product_data ? (int)$product_data['item_id'] : '0'; ?>;
        const PRODUCT_NAME = '<?php echo $product_data ? htmlspecialchars($product_data['name'], ENT_QUOTES) : ''; ?>';
        const CURRENT_DRINK_TYPE = '<?php echo $product_data ? $product_data['drink_type'] : 'OTHER'; ?>'; 

        // --- MOCK ADDONS DATA (Client-side display only) ---
        const ADDONS_DATA = {
            'EXTRA_SHOT': { name: "Extra Espresso Shot", price: 0.75, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>' },
            'FLAVORED_SYRUP': { name: "Flavored Syrups", price: 0.50, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 6H9m1 4h-1m2 4h-1m3 4h-1m2-4h-1m-1-4h-1m-1-4h-1m-1 8h1m-1 4h1m-2-8h1m-2 4h1m2 4h1m-1 4h1"></path></svg>' },
            'SUGAR_FREE_SYRUP': { name: "Sugar-Free Syrup", price: 0.50, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c1.657 0 3 .895 3 2s-1.343 2-3 2h-1v2h2"></path></svg>' },
            'OAT_MILK': { name: "Oat/Almond/Soy Milk", price: 0.75, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5a2 2 0 00-2 2v6a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2zM8 3h8a2 2 0 012 2v2H6V5a2 2 0 012-2z"></path></svg>' },
            'WHIPPED_CREAM': { name: "Whipped Cream", price: 0.50, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 20H4a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v6m-7 4l-4 4m-4-4l4 4"></path></svg>' }, 
            'CHOCOLATE_DRIZZLE': { name: "Chocolate Drizzle", price: 0.50, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>' },
            'EXTRA_FOAM': { name: "Extra Foam", price: 0.50, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>' },
            'ICE_CREAM_SCOOP': { name: "Ice Cream Scoop", price: 1.50, icon: '<svg class="w-6 h-6 text-primary-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>' },
        };

        const DRINK_ADDONS_MAP = {
            'ICED_COFFEE': [
                'FLAVORED_SYRUP', 'SUGAR_FREE_SYRUP', 'OAT_MILK', 'WHIPPED_CREAM', 'EXTRA_SHOT', 'CHOCOLATE_DRIZZLE', 'ICE_CREAM_SCOOP'
            ],
            'ESPRESSO': [
                'EXTRA_SHOT', 'FLAVORED_SYRUP', 'SUGAR_FREE_SYRUP', 'OAT_MILK', 'WHIPPED_CREAM', 'CHOCOLATE_DRIZZLE', 'EXTRA_FOAM'
            ],
            'HOT_COFFEE': [
                'FLAVORED_SYRUP', 'SUGAR_FREE_SYRUP', 'OAT_MILK', 'EXTRA_SHOT', 'WHIPPED_CREAM', 'CHOCOLATE_DRIZZLE', 'EXTRA_FOAM'
            ],
            'TEA': [
                'FLAVORED_SYRUP', 'SUGAR_FREE_SYRUP', 'OAT_MILK'
            ],
            'PASTRIES': [
                'ICE_CREAM_SCOOP', 'CHOCOLATE_DRIZZLE'
            ],
            'OTHER': []
        };
        // --- END MOCK ADDONS DATA ---


        // --- DOM Elements ---
        const totalPriceDisplay = document.getElementById('total-price-display');
        const quantityInput = document.getElementById('quantity-display');
        const incrementBtn = document.getElementById('increment-quantity');
        const decrementBtn = document.getElementById('decrement-quantity');
        const sizeOptions = document.querySelectorAll('input[name="size"]');
        const addonsContainer = document.getElementById('addons-container');
        const customizationForm = document.getElementById('customization-form');
        const hiddenAddonsInput = document.getElementById('hidden-addons-input');

        // --- Variables ---
        let quantity = parseInt(quantityInput.value) || 1;

        // Function to calculate the total price based on selected options and quantity
        function calculateTotal() {
            let currentTotal = BASE_PRICE;

            // 1. Calculate Size Add-ons
            const selectedSize = document.querySelector('input[name="size"]:checked');
            if (selectedSize) {
                currentTotal += parseFloat(selectedSize.dataset.price || 0);
            }

            // 2. Calculate Dynamic Add-on Toggles Price
            const addonToggles = addonsContainer.querySelectorAll('input[type="checkbox"]');
            addonToggles.forEach(toggle => {
                if (toggle.checked) {
                    currentTotal += parseFloat(toggle.dataset.price || 0);
                }
            });
            
            // 3. Apply Quantity
            const finalPrice = currentTotal * quantity;

            // 4. Update Display with Intl API for correct currency formatting
            totalPriceDisplay.textContent = `${finalPrice.toFixed(2)}`;
        }

        // Function to dynamically render add-ons based on drink type
        function renderAddons(drinkType) {
            const addonKeys = DRINK_ADDONS_MAP[drinkType] || [];
            addonsContainer.innerHTML = ''; 

            if (addonKeys.length === 0) {
                 addonsContainer.innerHTML = '<p class="text-gray-500 text-center py-4">No specific add-ons are available for this product category.</p>';
                 return;
            }

            addonKeys.forEach(key => {
                const data = ADDONS_DATA[key];
                if (!data) return; 

                const priceText = data.price > 0 ? `+₱${data.price.toFixed(2)}` : 'Free';
                const addonHtml = `
                    <div class="option-card flex items-center justify-between p-4 bg-dark-option rounded-xl shadow-lg transition duration-200 hover:shadow-primary-orange/20">
                        <div class="flex items-center space-x-3">
                            ${data.icon}
                            <div>
                                <p class="text-white font-semibold">${data.name}</p>
                                <p class="text-primary-orange text-sm">${priceText}</p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="addon-toggle-${key}" data-price="${data.price.toFixed(2)}" data-addon-key="${key}" class="addon-toggle">
                            <span class="slider round"></span>
                        </label>
                    </div>
                `;
                addonsContainer.insertAdjacentHTML('beforeend', addonHtml);
            });

            addonsContainer.querySelectorAll('.addon-toggle').forEach(toggle => {
                toggle.addEventListener('change', calculateTotal);
            });
        }


        // --- Event Listener for Size Selection ---
        sizeOptions.forEach(radio => {
            radio.addEventListener('change', calculateTotal);
        });

        // --- Event Listeners for Quantity ---
        incrementBtn.addEventListener('click', () => {
            quantity = Math.min(99, quantity + 1);
            quantityInput.value = quantity; // Update hidden input
            calculateTotal();
        });

        decrementBtn.addEventListener('click', () => {
            quantity = Math.max(1, quantity - 1);
            quantityInput.value = quantity; // Update hidden input
            calculateTotal();
        });
        
        // --- PRE-SUBMISSION HANDLER (Sends addon KEYS to PHP) ---
        customizationForm.addEventListener('submit', (e) => {
            const selectedAddons = [];
            
            // Collect selected add-on keys and serialize them into the hidden field
            addonsContainer.querySelectorAll('.addon-toggle:checked').forEach(toggle => {
                // Use the data-addon-key to send only the key (e.g., 'EXTRA_SHOT')
                selectedAddons.push(toggle.getAttribute('data-addon-key'));
            });
            
            // Set the value of the hidden input field for server-side processing
            // e.g., 'EXTRA_SHOT,WHIPPED_CREAM'
            hiddenAddonsInput.value = selectedAddons.join(',');
            
            // The form will now submit the data to the PHP block at the top, which handles the conversion and storage.
        });


        // --- Initial Load ---
        window.onload = () => {
            if (PRODUCT_ID > 0) {
                renderAddons(CURRENT_DRINK_TYPE);
                calculateTotal();
            }
        };
    </script>
</body>
</html>