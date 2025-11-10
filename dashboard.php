<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Panel | UNLI MAMI SYSTEM</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/navbar.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
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
                        <a href="#" class="nav-link logout-button-mobile">LOGOUT</a>
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

        <main class="admin-main">
            <h2 class="overview-title">TODAY'S OVERVIEW</h2>

            <div class="dashboard-grid">
                <div class="metric-card sales-card">
                    <p class="card-label">TOTAL SALES TODAY</p>
                    <p class="metric-value">$1,250.85</p>
                    <p class="sales-comparison">
                        <i class="fas fa-caret-up" aria-hidden="true"></i>
                        <span class="percentage">+12%</span> vs. Yesterday
                    </p>
                </div>

                <div class="metric-card orders-card">
                    <p class="card-label">NEW ORDERS TODAY</p>
                    <p class="metric-value">58</p>
                </div>

                <div class="chart-card top-sellers">
                    <p class="card-label">TOP 5 SELLING PRODUCTS</p>
                    <div class="product-list">
                        <div class="product-item">
                            <span class="product-name">Iced Caramel Macchiato</span>
                            <span class="product-sales">(120 sold)</span>
                            <div class="sales-bar-container">
                                <div class="sales-bar" style="width: 100%;"></div>
                            </div>
                        </div>
                        <div class="product-item">
                            <span class="product-name">Cinnamon Swirl</span>
                            <span class="product-sales">(95 sold)</span>
                            <div class="sales-bar-container">
                                <div class="sales-bar" style="width: 79%;"></div>
                            </div>
                        </div>
                        <div class="product-item">
                            <span class="product-name">Cold Brew</span>
                            <span class="product-sales">(70 sold)</span>
                            <div class="sales-bar-container">
                                <div class="sales-bar" style="width: 58%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert-card low-stock">
                    <p class="card-label">LOW STOCK ALERTS</p>
                    <ul class="stock-list">
                        <li>Oat Milk - <span class="critical">CRITICAL</span></li>
                        <li>Espresso Beans - <span class="low">LOW</span></li>
                        <li>Chai Syrup - <span class="low">LOW</span></li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/Navbar.js"></script>
</body>
</html>