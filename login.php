<?php 
// 1. START SESSION AND CONNECT
include('includes/db_connect.php'); 
session_start(); 

// 2. RATE LIMITING LOGIC
$max_attempts = 5;
$lockout_duration = 300; // 5 minutes in seconds

// initialize login attempts counter if not present
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
// initialize lock expiry
if (!isset($_SESSION['login_lock_expires'])) {
    $_SESSION['login_lock_expires'] = 0;
}

// Variable to hold error message
$error = '';

// If a lock expiry was set but the time has passed, clear attempts and lock
if (isset($_SESSION['login_lock_expires']) && $_SESSION['login_lock_expires'] > 0) {
    if (time() > $_SESSION['login_lock_expires']) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lock_expires'] = 0;
    }
}

// 3. HANDLE LOGIN POST REQUEST
if (isset($_POST['login'])) {
    $now = time();
    
    // Check if user is currently locked out
    if ($_SESSION['login_lock_expires'] > $now) {
        $remaining_sec = $_SESSION['login_lock_expires'] - $now;
        $minutes = floor($remaining_sec / 60);
        $seconds = $remaining_sec % 60;
        $error = "Too many login attempts. Please wait <span id='countdown'>$minutes" . "m " . "$seconds" . "s</span>.";
    } else {
        // No lock, proceed with login attempt
        $email = trim($_POST['email']);
        $password = $_POST['password']; // Plain text password submitted by user

        // Use prepared statements for secure querying
        // --- CHANGE 1: Select the 'role' column from the users table ---
        $stmt = $conn->prepare("SELECT user_id, name, password, role FROM users WHERE email = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $stored_hash = $user['password']; // This is the HASH from the database
                $user_role = $user['role'];      // Retrieve the user's role

                // --- CRITICAL FIX: Use password_verify() to check the plain password against the hash ---
                if (password_verify($password, $stored_hash)) {
                    // SUCCESSFUL LOGIN
                    
                    // Reset failure counters
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lock_expires'] = 0; 
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name']; 
                    $_SESSION['role'] = $user_role; // Store the role in the session
                    
                    // --- CHANGE 2: Conditional Redirect based on role ---
                    if ($user_role === 'admin') {
                        // Assuming your admin dashboard is named 'admin_dashboard.php'
                        header('Location: admin/dashboard.php'); 
                    } else {
                        // Default user dashboard
                        header('Location: index.php'); 
                    }
                    exit(); 
                } else {
                    // FAILED LOGIN: Password does not match hash
                    $error = "Invalid email or password.";
                    $_SESSION['login_attempts']++; 
                }
            } else {
                // FAILED LOGIN: User not found
                $error = "Invalid email or password.";
                $_SESSION['login_attempts']++; 
            }
            $stmt->close();
        } else {
            // Database preparation error
            $error = "A system error occurred. Please try again later.";
        }

        // 4. RATE LIMITING CHECK AFTER ATTEMPT
        if ($_SESSION['login_attempts'] >= $max_attempts) {
            $_SESSION['login_lock_expires'] = time() + $lockout_duration;
            $error = "Too many failed attempts. You are locked out for 5 minutes. Please wait <span id='countdown'>05m 00s</span>.";
        }
    }
}

// Pass the lock expiry time to the JavaScript for countdown display
$js_lock_expires = isset($_SESSION['login_lock_expires']) ? $_SESSION['login_lock_expires'] : 0;
echo "<script>window.LOGIN_LOCK_EXPIRES = $js_lock_expires;</script>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UNLI MAMI SYSTEM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="main-container">
        <form class="form" action="" method="POST">
            <div class="form-header">
                <h2>Welcome Back!</h2>
                <p>Login to your account</p>
            </div>

            <?php if (isset($error)): ?>
                <p style="color: red; text-align:center;"><?php echo $error; ?></p>
            <?php endif; ?>

            <?php if (isset($blocked) && $blocked): ?>
                <?php
                    $remaining = $_SESSION['login_lock_expires'] - time();
                    if ($remaining < 0) $remaining = 0;
                    $mins = floor($remaining / 60);
                    $secs = $remaining % 60;
                ?>
                <p id="lock-message" style="color: red; text-align:center;">Try again in <span id="countdown"><?php echo $mins . 'm ' . $secs . 's'; ?></span>.</p>
                <!-- expose lock expiry to JS in unix timestamp (seconds) -->
                <script>
                    window.LOGIN_LOCK_EXPIRES = <?php echo isset($_SESSION['login_lock_expires']) ? (int)$_SESSION['login_lock_expires'] : 0; ?>;
                </script>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email or Username</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="form-options">
                <div class="checkbox-group">
                    <input type="checkbox" id="remember-me" name="remember-me">
                    <label for="remember-me">Remember me</label>
                </div>
                <a href="forgotpassword.php" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" name="login" <?php echo (isset($blocked) && $blocked) ? 'disabled' : ''; ?>>Log In</button>

            <div class="form-footer">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </form>
    </div>

</body>
</html>

<script>
// If server provided a lock expiry timestamp, run a countdown
if (typeof window.LOGIN_LOCK_EXPIRES !== 'undefined' && window.LOGIN_LOCK_EXPIRES > 0) {
    function pad(n) { return n < 10 ? '0' + n : n; }
    const countdownEl = document.getElementById('countdown');
    const loginBtn = document.querySelector('button[name="login"]');
    let timer;

    function updateCountdown() {
        // Use a persistent timer reference
        if (!timer) {
             timer = setInterval(updateCountdown, 1000);
        }

        const now = Math.floor(Date.now() / 1000);
        let remaining = window.LOGIN_LOCK_EXPIRES - now;
        
        if (remaining <= 0) {
            // Lock expired: remove message and enable button
            if (countdownEl) countdownEl.textContent = '00m 00s';
            if (loginBtn) {
                loginBtn.disabled = false;
                loginBtn.textContent = 'LOG IN';
            }
            // Update lock message display
            const lockMsg = document.getElementById('lock-message');
            if (lockMsg) lockMsg.innerHTML = 'You can try logging in again.';

            clearInterval(timer);
            return;
        }

        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        
        if (countdownEl) countdownEl.textContent = pad(mins) + 'm ' + pad(secs) + 's';
        if (loginBtn) loginBtn.disabled = true;
    }

    // Initialize the countdown on page load if the lock is active
    if (window.LOGIN_LOCK_EXPIRES > Math.floor(Date.now() / 1000)) {
        updateCountdown();
    }
}
</script>
