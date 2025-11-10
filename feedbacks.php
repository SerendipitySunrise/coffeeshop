<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedbacks - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/feedback.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
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
            </div>
        </header>

        <main class="feedback-main">
            <h1 class="page-title">
                Customer Feedbacks
                <span class="feedback-count">(24)</span>
            </h1>
            
            <div class="feedback-stats">
                <div class="stat-card">
                    <div class="stat-value">4.2</div>
                    <div class="stat-label">Average Rating</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">18</div>
                    <div class="stat-label">Positive Feedbacks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">4</div>
                    <div class="stat-label">Needs Attention</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">2</div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>
            
            <div class="feedback-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search feedbacks...">
                </div>
                
                <div class="filter-controls">
                    <select class="filter-select">
                        <option>All Ratings</option>
                        <option>5 Stars</option>
                        <option>4 Stars</option>
                        <option>3 Stars</option>
                        <option>2 Stars</option>
                        <option>1 Star</option>
                    </select>
                    
                    <select class="filter-select">
                        <option>Sort by Date</option>
                        <option>Newest First</option>
                        <option>Oldest First</option>
                    </select>
                </div>
            </div>
            
            <div class='alert success' style="display: none;">Feedback deleted successfully.</div>

            <div class="feedback-list">
                <div class="feedback-card">
                    <div class="feedback-actions">
                        <button class="action-btn reply-btn" title="Reply to Feedback">
                            <i class="fas fa-reply"></i>
                        </button>
                        <button 
                            class="action-btn delete-btn" 
                            title="Delete Feedback"
                            onclick="confirmDeletion(101)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    
                    <div class="feedback-header">
                        <div class="feedback-user-info">
                            <div class="user-avatar">U42</div>
                            <div>
                                <strong>User ID #42</strong>
                                <div class="user-email">customer42@example.com</div>
                            </div>
                        </div>
                        <div class="feedback-rating">
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                        </div>
                    </div>
                    
                    <div class="feedback-info">
                        <span>Feedback ID: 101</span>
                        <span>Submitted: Oct 28, 2023 10:15 AM</span>
                    </div>
                    
                    <div class="feedback-message">
                        <p>The coffee was absolutely amazing! Best I've had in a long time. The staff was also very friendly and welcoming. Will definitely come back again soon!</p>
                    </div>
                </div>

                <div class="feedback-card">
                    <div class="feedback-actions">
                        <button class="action-btn reply-btn" title="Reply to Feedback">
                            <i class="fas fa-reply"></i>
                        </button>
                        <button 
                            class="action-btn delete-btn" 
                            title="Delete Feedback"
                            onclick="confirmDeletion(102)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    
                    <div class="feedback-header">
                        <div class="feedback-user-info">
                            <div class="user-avatar">U55</div>
                            <div>
                                <strong>User ID #55</strong>
                                <div class="user-email">customer55@example.com</div>
                            </div>
                        </div>
                        <div class="feedback-rating">
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="far fa-star empty-star"></i>
                            <i class="far fa-star empty-star"></i>
                        </div>
                    </div>
                    
                    <div class="feedback-info">
                        <span>Feedback ID: 102</span>
                        <span>Submitted: Oct 27, 2023 04:30 PM</span>
                    </div>
                    
                    <div class="feedback-message">
                        <p>The pastry was a bit dry, but the iced latte was good. The ambiance of the place is nice for working. Could use more power outlets for laptops.</p>
                    </div>
                </div>
                
                <div class="feedback-card">
                    <div class="feedback-actions">
                        <button class="action-btn reply-btn" title="Reply to Feedback">
                            <i class="fas fa-reply"></i>
                        </button>
                        <button 
                            class="action-btn delete-btn" 
                            title="Delete Feedback"
                            onclick="confirmDeletion(103)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    
                    <div class="feedback-header">
                        <div class="feedback-user-info">
                            <div class="user-avatar">U17</div>
                            <div>
                                <strong>User ID #17</strong>
                                <div class="user-email">customer17@example.com</div>
                            </div>
                        </div>
                        <div class="feedback-rating">
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="fas fa-star full-star"></i>
                            <i class="far fa-star empty-star"></i>
                        </div>
                    </div>
                    
                    <div class="feedback-info">
                        <span>Feedback ID: 103</span>
                        <span>Submitted: Oct 26, 2023 11:45 AM</span>
                    </div>
                    
                    <div class="feedback-message">
                        <p>Great atmosphere and friendly staff. The coffee is consistently good. My only suggestion would be to expand the pastry selection.</p>
                    </div>
                </div>
            </div>
            
            <div class="pagination">
                <button class="active">1</button>
                <button>2</button>
                <button>3</button>
                <button>Next</button>
            </div>
        </main>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="close-modal" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong>Feedback ID <span id="deleteId"></span></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" onclick="deleteFeedback()">Delete</button>
            </div>
        </div>
    </div>

    <div class="modal" id="replyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reply to Feedback</h3>
                <button class="close-modal" onclick="closeModal('replyModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="feedback-preview">
                    <p><strong>Original Feedback:</strong></p>
                    <p id="originalFeedback"></p>
                </div>
                <div style="margin-top: 20px;">
                    <label for="replyMessage">Your Response:</label>
                    <textarea id="replyMessage" rows="5" style="width: 100%; padding: 10px; margin-top: 10px; background-color: var(--card-secondary); color: var(--text-light); border: none; border-radius: 5px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('replyModal')">Cancel</button>
                <button class="btn btn-primary" onclick="sendReply()">Send Reply</button>
            </div>
        </div>
    </div>

    <script src="assets/js/Navbar.js"></script>
    <script src="assets/js/feedback.js"></script>
</body>
</html>