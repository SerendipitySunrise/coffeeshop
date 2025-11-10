<?php
include('includes/db_connect.php');

// Message shown to the user (success or error)
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Basic validation
    if ($new_password === '' || $confirm_password === '' || $email === '' || $phone === '') {
        $message = "<p style='color:red;'>⚠️ Please fill in all fields.</p>";
    } elseif ($new_password !== $confirm_password) {
        $message = "<p style='color:red;'> Passwords do not match.</p>";
    } else {
        // --- START: Security Check ---
        // Step 1: Check if the user exists with the provided email and phone number
        $sql = "SELECT * FROM users WHERE email = ? AND phone = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $email, $phone);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                
                // --- Step 2: HASH THE NEW PASSWORD BEFORE STORAGE (SECURITY) ---
                // We use PASSWORD_DEFAULT which currently uses BCRYPT and is updated as better algorithms become available.
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Step 3: Update the user's password in the database
                $update = "UPDATE users SET password = ? WHERE email = ? AND phone = ?";
                if ($stmt_update = $conn->prepare($update)) {
                    // We bind the HASHED password here
                    $stmt_update->bind_param("sss", $hashed_password, $email, $phone);
                    
                    if ($stmt_update->execute()) {
                        $message = "<p style='color:green;'>Password successfully reset. You can now log in.</p>";
                    } else {
                        $message = "<p style='color:red;'>Error updating password. Please try again.</p>";
                    }
                    $stmt_update->close();
                } else {
                    $message = "<p style='color:red;'>Database error on update preparation.</p>";
                }
            } else {
                $message = "<p style='color:red;'>Account not found. Please check your email and phone number.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p style='color:red;'>Database error on select preparation.</p>";
        }
    }
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
                <h2>Reset Password</h2>
                <p>Enter your details to reset password.</p>
            </div>

            <?php if (!empty($message)) { echo $message; } ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="phone" id="phone" name="phone" placeholder="Enter Phone Number" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>

            <button type="submit">Reset Password</button>

            <div class="form-footer">
                <p>Remember your password? <a href="login.php">Log In</a></p>
            </div>
        </form>
    </div>


</body>
</html>


    
