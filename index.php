<?php 
// Assumes db_connect.php is in the same directory or accessible via 'includes/'
include('includes/db_connect.php'); 
session_start(); 

// --- 1. User Authentication and Profile Picture Fetch ---
// Use a safe, default placeholder image path
$profile_picture = '/Assets/Images/user-placeholder.jpg'; 
$user_name = 'GUEST'; // Initialize user name for the greeting

if (isset($_SESSION['user_id']) && isset($conn) && $conn->connect_error === null) {
    $user_id = $_SESSION['user_id'];
    
    // Prepare statement to fetch profile picture path AND NAME
    $sql_user = "SELECT profile_picture, name FROM users WHERE user_id = ?";
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
            
            // Fetch and set the user's name (assuming column is 'name')
            if (!empty($user_data['name'])) {
                 $user_name = htmlspecialchars($user_data['name']); 
            }
        }
        $stmt_user->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Coffee Shop</title>
    <link rel="stylesheet" href="assets/css/home.css"> 
    
    <link rel="icon" type="image/x-icon" href="https://scontent.fmnl17-3.fna.fbcdn.net/v/t1.15752-9/476133121_944707607784720_4222766298493625099_n.jpg?stp=dst-jpg_s100x100_tt6&_nc_cat=106&ccb=1-7&_nc_sid=029a7d&_nc_eui2=AeHbXTSveWEb4OzutQZJ0bo9taI_vWM-p1y1oj-9Yz6nXI0YaxhtxRPTLLJMJmHWtmHktAjCfAJasIl2dW9Xd5mI&_nc_ohc=fujV-m1DLokQ7kNvwHfDq8g&_nc_oc=AdnbzmRf6BknvCWet4iFs18szBlKvHfOLnwPvF_Yz5vVNGXwjLsteEaM2u43sPz8450&_nc_zt=23&_nc_ht=scontent.fmnl17-3.fna&oh=03_Q7cD3gGJjWr_65WSg0tvi9N-0vVvuMYVYKORJ-0c42fXu4VQIg&oe=69191A0E">
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
</head>
<body>

    <div class="page-container">
        <header class="main-header">
            <a href="home.php" class="logo">
                <i class="fas fa-coffee"></i>
            </a>
            
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

        <main>
            <div class="content-card">
                <div class="card-text">
                    <h1>WELCOME,<br><?php echo strtoupper($user_name); ?>!</h1>
                    <p class="special-tag">OUR COMMITMENT</p>
                    <h2>FRESHLY BREWED DAILY</h2>
                    <p class="promo-text">To serve freshly brewed, high-quality coffee made from the finest local beans, crafted with care to bring warmth and connection in every cup.</p>
                </div>
                <div class="card-image">
                    <img src="assets/images/home.png" alt="Freshly Brewed Coffee">
                </div>
            </div>

            <div class="action-buttons">
                <a href="menu.php" class="btn btn-primary">
                    <i class="fas fa-mug-hot"></i> EXPLORE MENU
                </a>
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-list-ul"></i> VIEW MY ORDERS
                </a>
                <a href="account.php" class="btn btn-secondary">
                    <i class="fas fa-user-cog"></i> MANAGE PROFILE
                </a>
            </div>
        </main>
    </div>
    
    <script src="assets/js/Navbar.js"></script>
</body>
</html>