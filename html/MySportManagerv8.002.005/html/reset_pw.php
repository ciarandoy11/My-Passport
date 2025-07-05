<?php
session_start(); // Start the session

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';

// Handle the first step: email submission
if (isset($_POST['submit_email'])) {
    $email = $_POST['email'];

    // Prepare and bind
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Email exists, generate and send the verification code
        $stmt->bind_result($userId);
        $stmt->fetch();

        $verification_code = rand(100000, 999999);
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;

        // Send the email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to send through
            $mail->SMTPAuth = true;
            $mail->Username = 'ciarandoy11@gmail.com'; // SMTP username
            $mail->Password = 'ovpapkoerwyolynb'; // App-specific password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            //Recipients
            $mail->setFrom('ciarandoy11@gmail.com', 'Mailer');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Verification Code';
            $mail->Body    = 'Your verification code is: ' . $verification_code;

            $mail->send();
            $success = 'Verification code sent to your email.';
        } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = 'Email address not found.';
    }

    $stmt->close();
}

// Handle the second step: verification code submission
if (isset($_POST['submit_code'])) {
    $code = $_POST['verification_code'];

    if ($code == $_SESSION['verification_code']) {
        $_SESSION['code_verified'] = true;
        $success = 'Code verified. You can now reset your password.';
    } else {
        $error = 'Invalid verification code.';
    }
}

// Handle the third step: password reset
if (isset($_POST['reset_password'])) {
    if ($_SESSION['code_verified']) {
        $new_password = $_POST['new_password'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success = 'Password reset successfully. You can now log in.';
            session_unset();
            session_destroy();
            echo "<script>location='login.php';</script>";
        } else {
            $error = 'Failed to reset password. Please try again.';
        }

        $stmt->close();
    } else {
        $error = 'Verification code not verified.';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Reset Password</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
</head>
<body>
    <main>
        <h1>Reset Password</h1>
        <?php if ($error) { echo "<p style='color:red;'>$error</p>"; } ?>
        <?php if ($success) { echo "<p style='color:green;'>$success</p>"; } ?>

        <?php if (!isset($_SESSION['verification_code'])): ?>
        <!-- Step 1: Ask for email -->
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit" name="submit_email">Send Verification Code</button>
        </form>
        <?php elseif (!isset($_SESSION['code_verified'])): ?>
        <!-- Step 2: Ask for verification code -->
        <form method="POST" action="">
            <input type="text" name="verification_code" placeholder="Enter the verification code" required>
            <button type="submit" name="submit_code">Verify Code</button>
        </form>
        <?php else: ?>
        <!-- Step 3: Allow password reset -->
        <form method="POST" action="">
            <input type="password" name="new_password" placeholder="Enter new password" required>
            <button type="submit" name="reset_password">Reset Password</button>
        </form>
        <?php endif; ?>

    </main>
    <script>
        window.onload = function() {
            console.log(<?php echo json_encode($_SESSION); ?>);
        };
    </script>
</body>
</html>
