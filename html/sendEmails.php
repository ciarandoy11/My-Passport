<?php
session_start();

require 'vendor/autoload';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $userId = 2;
}

$userId = $_SESSION['user_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $userId = 2;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data
$sql = "SELECT firstname, username, club, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die(json_encode(["error" => "User not found"]));
}

$firstname = $user['firstname'];
$userName = $user['username'];
$club = $user['club'];
$email = $user['email'];

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data from POST request
    $recipient = htmlspecialchars($_POST['recipient'] ?? '');
    $sender = htmlspecialchars($_POST['sender'] ?? '');
    $subject = htmlspecialchars($_POST['subject'] ?? '');
    $message = $_POST['message'] ?? ''; // HTML not escaped yet for flexibility

    // Set up PHPMailer
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply.mysportmanager@gmail.com';
        $mail->Password = 'dtnb bpgw earq xsgi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Detect if email is HTML or plain text
        $htmlPattern = "/<(p|span|b|strong|i|u|a|br)[^>]*>/i"; // Includes <br> for new lines
        if (preg_match($htmlPattern, $message)) {
            $mail->isHTML(true);
            $emailType = 'html';
        } else {
            $mail->isHTML(false);
            $emailType = 'plainText';
        }

        // Email setup
        $mail->setFrom('noreply.mysportmanager@gmail.com', 'My Sport');
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        // Send email
        $mail->send();
        $success = 'Message sent';

        // Store email in database
        $stmt = $conn->prepare("INSERT INTO emails (sender, recipient, subject, body, emailType) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $sender, $recipient, $subject, $message, $emailType);
            if ($stmt->execute()) {
                $success .= " and info stored successfully.";
            } else {
                $error = "Error inserting record: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $error = "Database error: " . htmlspecialchars($conn->error);
        }
    } catch (Exception $e) {
        $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }

    // Display results or redirect
    if ($error) {
        echo "<p style='color:red;'>$error</p>";
    } else {
        //echo 'email sent';
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?page=inbox');
        exit;
    }
}

$conn->close();
?>