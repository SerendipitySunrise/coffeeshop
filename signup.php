<?php 
include('includes/db_connect.php'); 
session_start();

$error = "";
$success = "";

if (isset($_POST['signup'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    if (!preg_match("/^[0-9]{11}$/", $phone)) {
        $error = "Phone number must be exactly 11 digits.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    }
    else {
        $check_query = "SELECT * FROM users WHERE email='$email' LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already registered!";
        } 
        else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $query = "INSERT INTO users (name, email, password, role, phone_number, created_at) 
                      VALUES ('$name', '$email', '$hashed_password', 'customer', '$phone', NOW())";

            if (mysqli_query($conn, $query)) {
                $success = "Account created successfully! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Something went wrong: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - UNLI MAMI SYSTEM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="main-container">
        <form class="form" action="" method="POST">
            <div class="form-header">
                <h2>Create an Account</h2>
                <p>Signup to continue</p>
            </div>

            <?php if (!empty($error)): ?>
                <p style="color: red; text-align:center;"><?php echo $error; ?></p>
            <?php elseif (!empty($success)): ?>
                <p style="color: green; text-align:center;"><?php echo $success; ?></p>
            <?php endif; ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Enter your name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="e.g. 09XXXXXXXXX" maxlength="11" pattern="[0-9]{11}" required title="Phone number must be exactly 11 digits">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
            </div>

            <button type="submit" name="signup">Sign Up</button>

            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </form>
    </div>

    <script>
        document.querySelector("form").addEventListener("submit", function(e) {
            const phone = document.getElementById("phone").value;
            const phonePattern = /^[0-9]{11}$/;
            if (!phonePattern.test(phone)) {
                alert("Phone number must be exactly 11 digits.");
                e.preventDefault();
            }
        });
    </script>

</body>
</html>
