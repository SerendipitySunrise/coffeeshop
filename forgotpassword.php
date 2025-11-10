<?php
include('includes/db_connect.php');
session_start();

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Check if both email and phone exist in the database
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND phone = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Save email and phone in session for use in reset_password.php
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_phone'] = $phone;

        // Redirect to reset password page
        header("Location: reset_password.php");
        exit();
    } else {
        $message = "<p style='color:red; text-align:center;'>❌ No account found with that email and phone number.</p>";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UNLI MAMI SYSTEM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="main-container">
        <form class="form" method="POST" action="">
            <div class="form-header">
                <h2>Forgot Your Password?</h2>
                <p>Enter your registered email and phone number.</p>
            </div>

            <?= $message ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="09XXXXXXXXX" pattern="[0-9]{11}" maxlength="11" required title="Enter 11-digit phone number">
            </div>

            <button type="submit">Verify Account</button>

            <div class="form-footer">
                <p>Remember your password? <a href="login.php">Sign In</a></p>
            </div>
        </form>
    </div>
</body>
</html>
