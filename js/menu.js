// Data for the menu items, compiled from your price tables.
// NOTE: This array is kept as a client-side lookup for cart functionality.
// The actual menu display is rendered by menu.php from the database.
const menuItems = [
    // --- HOT COFFEE --- (Category: hot-coffee)
    { name: "Americano", description: "Hot espresso with water", price: 90, category: "hot-coffee", item_id: 'HOT001' }, 
    { name: "Cappuccino", description: "Espresso with steamed milk and foam", price: 120, category: "hot-coffee", item_id: 'HOT002' },
    { name: "Café Latte", description: "Espresso with steamed milk", price: 120, category: "hot-coffee", item_id: 'HOT003' },
    { name: "Mocha", description: "Espresso with chocolate and milk", price: 130, category: "hot-coffee", item_id: 'HOT004' },
    { name: "Caramel Macchiato", description: "Espresso with caramel syrup and milk foam", price: 140, category: "hot-coffee", item_id: 'HOT005' },
    
    // --- ICED COFFEE --- (Category: iced-coffee)
    { name: "Iced Americano", description: "Chilled espresso with cold water", price: 100, category: "iced-coffee", item_id: 'ICED001' },
    { name: "Iced Latte", description: "Cold milk with espresso", price: 120, category: "iced-coffee", item_id: 'ICED002' },
    { name: "Iced Mocha", description: "Espresso, chocolate, and milk over ice", price: 130, category: "iced-coffee", item_id: 'ICED003' },
    { name: "Iced Caramel Macchiato", description: "Caramel espresso drink over ice", price: 140, category: "iced-coffee", item_id: 'ICED004' },
    { name: "Iced Spanish Latte", description: "Espresso with condensed milk and ice", price: 140, category: "iced-coffee", item_id: 'ICED005' },
    { name: "Cold Brew", description: "Slow-steeped coffee served cold", price: 110, category: "iced-coffee", item_id: 'ICED006' },
    { name: "Iced Hazelnut Latte", description: "Espresso with hazelnut syrup and milk over ice", price: 135, category: "iced-coffee", item_id: 'ICED007' },
    { name: "Iced Vanilla Latte", description: "Espresso with vanilla syrup and milk over ice", price: 135, category: "iced-coffee", item_id: 'ICED008' },
    
    // --- ESPRESSO --- (Category: espresso)
    { name: "Single Shot Espresso", description: "One shot of rich espresso", price: 80, category: "espresso", item_id: 'ESP001' },
    { name: "Double Shot Espresso", description: "Two shots of strong espresso", price: 100, category: "espresso", item_id: 'ESP002' },
    { name: "Macchiato", description: "Espresso topped with a small amount of milk foam", price: 90, category: "espresso", item_id: 'ESP003' },
    { name: "Cortado", description: "Espresso cut with equal part steamed milk", price: 100, category: "espresso", item_id: 'ESP004' },
    { name: "Ristretto", description: "Shorter, more concentrated espresso shot", price: 85, category: "espresso", item_id: 'ESP005' },
    { name: "Long Black", description: "Hot water poured over espresso", price: 95, category: "espresso", item_id: 'ESP006' },
    { name: "Affogato", description: "Espresso poured over vanilla ice cream", price: 150, category: "espresso", item_id: 'ESP007' },
    
    // --- TEA --- (Category: tea)
    { name: "English Breakfast Tea", description: "Classic strong black tea blend", price: 70, category: "tea", item_id: 'TEA001' },
    { name: "Earl Grey Tea", description: "Black tea infused with bergamot flavor", price: 75, category: "tea", item_id: 'TEA002' },
    { name: "Green Tea", description: "Light and refreshing steamed green tea", price: 75, category: "tea", item_id: 'TEA003' },
    { name: "Jasmine Tea", description: "Fragrant floral green tea", price: 80, category: "tea", item_id: 'TEA004' },
    { name: "Chamomile Tea", description: "Relaxing caffeine-free herbal infusion", price: 80, category: "tea", item_id: 'TEA005' },
    { name: "Peppermint Tea", description: "Refreshing and cooling mint herbal tea", price: 80, category: "tea", item_id: 'TEA006' },
    { name: "Lemon Ginger Tea", description: "Zesty herbal tea with lemon and ginger", price: 85, category: "tea", item_id: 'TEA007' },
    { name: "Milk Tea (Classic / Watermelon / Matcha)", description: "Sweetened milk-based tea with flavor options", price: 110, category: "tea", item_id: 'TEA008' },

    // --- PASTRIES --- (Category: pastries)
    { name: "Croissant", description: "Flaky, buttery layered pastry in assorted flavors", price: 70, category: "pastries", item_id: 'PAS001' },
    { name: "Muffins", description: "Soft, sweet baked muffins in different varieties", price: 65, category: "pastries", item_id: 'PAS002' },
    { name: "Cinnamon Roll", description: "Sweet roll with cinnamon filling and icing", price: 80, category: "pastries", item_id: 'PAS003' },
    { name: "Donuts", description: "Soft ring-shaped fried pastry with glaze or sugar", price: 50, category: "pastries", item_id: 'PAS004' },
    { name: "Cheesecake Slice", description: "Creamy cheesecake served per slice", price: 110, category: "pastries", item_id: 'PAS005' },
    { name: "Brownie", description: "Dense, fudgy chocolate square", price: 75, category: "pastries", item_id: 'PAS006' },
    { name: "Banana Bread", description: "Moist loaf made with ripe bananas", price: 70, category: "pastries", item_id: 'PAS007' },
    { name: "Cookies", description: "Freshly baked cookies (chocolate chip or oatmeal)", price: 55, category: "pastries", item_id: 'PAS008' },
    { name: "Ensaymada", description: "Soft, sweet bread topped with butter, sugar, and cheese", price: 60, category: "pastries", item_id: 'PAS009' },
];

// --- CART FUNCTIONALITY (Modified to OPEN MODAL) ---

/**
 * Initializes the "Add to Cart" button listeners.
 */
const initializeCartFunctionality = () => {
    // 1. Add event listeners to all "ADD TO CART" buttons
    const cartButtons = document.querySelectorAll('.add-to-cart-btn');
    cartButtons.forEach(button => {
        button.removeEventListener('click', handleAddToCartClick);
        button.addEventListener('click', handleAddToCartClick);
    });
};

/**
 * Event handler for the "ADD TO CART" button click.
 * Instead of redirecting, this now opens the modal for customization.
 */
const handleAddToCartClick = (e) => {
    e.preventDefault();
    const itemId = e.currentTarget.getAttribute('data-id');
    const item = menuItems.find(i => i.item_id === itemId);

    if (itemId && item) {
        // --- NEW: Trigger the modal to open and populate item details ---
        // We call a function defined in the main menu.php script block
        if (typeof openCustomizationModal === 'function') {
            openCustomizationModal(item); 
        } else {
            // Fallback for non-customizable items or error
            console.error("openCustomizationModal function not found or item is not set.");
            // Optional: If customization is mandatory, you might still redirect here:
            // window.location.href = `ordersummary.php?item_id=${itemId}`;
        }
    }
};


// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    // Original Navigation Logic (Kept for mobile menu functionality)
    const burgerMenu = document.getElementById('burger-menu');
    const mainNav = document.getElementById('main-nav');
    const mainHeader = document.querySelector('.main-header'); 

    // Function to close the mobile menu
    const closeMenu = () => {
        mainNav.classList.remove('active');
        burgerMenu.classList.remove('active');
        mainHeader.classList.remove('menu-open'); 
        document.body.classList.remove('menu-is-open'); 
    };
    
    // Burger Menu & Navigation Logic
    if (burgerMenu && mainNav && mainHeader) {
        // Toggle menu on burger click
        burgerMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            mainNav.classList.toggle('active');
            burgerMenu.classList.toggle('active');
            mainHeader.classList.toggle('menu-open'); 
            document.body.classList.toggle('menu-is-open');
        });
        
        // Close menu when a navigation link is clicked
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', closeMenu);
        });
        
        // Close menu when clicking outside the menu/burger
        document.addEventListener('click', (e) => {
            if (!mainNav.contains(e.target) && !burgerMenu.contains(e.target) && mainNav.classList.contains('active')) {
                closeMenu();
            }
        });
        
        // Close menu on desktop resize (if it was open)
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeMenu();
            }
        });
    }
    
    // Initialize the new item selection/modal functionality
    // This is called again after the PHP script block to ensure all elements are loaded
    // but the final execution is handled at the bottom of menu.php
    // initializeCartFunctionality();
});

// Expose the function globally for menu.php's use
window.initializeCartFunctionality = initializeCartFunctionality;
window.menuItems = menuItems; // Expose items for modal lookups
