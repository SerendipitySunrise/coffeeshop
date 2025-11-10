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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Coffee Shop</title>
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/about.css">
    <link rel="icon" type="image/x-icon" href="https://scontent.fmnl17-3.fna.fbcdn.net/v/t1.15752-9/476133121_944707607784720_4222766298493625099_n.jpg?stp=dst-jpg_s100x100_tt6&_nc_cat=106&ccb=1-7&_nc_sid=029a7d&_nc_eui2=AeHbXTSveWEb4OzutQZJ0bo9taI_vWM-p1y1oj-9Yz6nXI0YaxhtxRPTLLJMJmHWtmHktAjCfAJasIl2dW9Xd5mI&_nc_ohc=fujV-m1DLokQ7kNvwHfDq8g&_nc_oc=AdnbzmRf6BknvCWet4iFs18szBlKvHfOLnwPvF_Yz5vVNGXwjWsteEaM2u43sPz8450&_nc_zt=23&_nc_ht=scontent.fmnl17-3.fna&oh=03_Q7cD3gGJjWr_65WSg0tvi9N-0vVvuMYVYKORJ-0c42fXu4VQIg&oe=69191A0E">
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
        
        
        <main class="about-us-main">
            <h1 class="page-title">About Us</h1>
            
            <div class="about-content">
                <div class="about-card">
                    <div class="icon-circle">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Our Team</h3>
                    <p>At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga.</p>
                </div>
                
                <div class="about-card">
                    <div class="icon-circle">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <h3>Our System</h3>
                    <p>Excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est.</p>
                </div>
                
                <div class="about-card">
                    <div class="icon-circle">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Our Mission</h3>
                    <p>Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae.</p>
                </div>
            </div>

            <section class="management-team-section">
                <h2 class="management-title">OUR MANAGEMENT TEAM</h2>
                <p class="management-subtitle">Meet our experienced leadership team</p>
                <div class="team-members">
                    <div class="team-member">
                        <img src="https://via.placeholder.com/120/E67E22/FFFFFF?text=Gian" alt="Gian Carl Coronel profile photo" class="member-photo">
                        <h4>Gian Carl Coronel</h4>
                        <p>Lorem ipsum dolor sit amet ultrices. Condimentum sed enim. Magna sociosqu arcu</p>
                    </div>
                    <div class="team-member">
                        <img src="https://via.placeholder.com/120/E67E22/FFFFFF?text=Edrian" alt="Edrian Bagohara profile photo" class="member-photo">
                        <h4>Edrian Bagohara</h4>
                        <p>Lorem ipsum dolor sit amet ultrices. Condimentum sed enim. Magna sociosqu arcu</p>
                    </div>
                    <div class="team-member">
                        <img src="https://via.placeholder.com/120/E67E22/FFFFFF?text=Roz" alt="Roz Lagaban profile photo" class="member-photo">
                        <h4>Roz Lagaban</h4>
                        <p>Lorem ipsum dolor sit amet ultrices. Condimentum sed enim. Magna sociosqu arcu</p>
                    </div>
                    <div class="team-member">
                        <img src="https://via.placeholder.com/120/E67E22/FFFFFF?text=Reinier" alt="Reinier Sonio profile photo" class="member-photo">
                        <h4>Reinier Sonio</h4>
                        <p>Lorem ipsum dolor sit amet ultrices. Condimentum sed enim. Magna sociosqu arcu</p>
                    </div>
                    <div class="team-member">
                        <img src="https://via.placeholder.com/120/E67E22/FFFFFF?text=Jedrick" alt="Jedrick Verzosa profile photo" class="member-photo">
                        <h4>Jedrick Verzosa</h4>
                        <p>Lorem ipsum dolor sit amet ultrices. Condimentum sed enim. Magna sociosqu arcu</p>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <script src="assets/js/Navbar.js"></script>
</body>
</html>