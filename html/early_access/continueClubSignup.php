<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
use Stripe\StripeClient;

try {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "test";
    $dbname = "pod_rota";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Sanitize and validate input
    $club = trim($_GET['club'] ?? '');
    $inputUsername = trim($_GET['username'] ?? '');
    $inputPassword = trim($_GET['password'] ?? '');
    $firstName = trim($_GET['first_name'] ?? '');
    $lastName = trim($_GET['last_name'] ?? '');
    $email = trim($_GET['email'] ?? '');
    $phone = trim($_GET['phone'] ?? '');
    $admin = 1; // Default admin value

    // Check for empty fields
    if (empty($inputUsername) || empty($inputPassword) || empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
        throw new Exception("Please fill in all fields.");
    }

    // Check if username or club already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR club = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $inputUsername, $club);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        throw new Exception("Username or Club Name already taken.");
    }
    $stmt->close();

    // Hash the password
    $hashedPassword = password_hash($inputPassword, PASSWORD_BCRYPT);

    // Insert new user into the database
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, phone, club, password, `type-admin`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssssssi", $firstName, $lastName, $inputUsername, $email, $phone, $club, $hashedPassword, $admin);
    if (!$stmt->execute()) {
        throw new Exception("Error inserting user: " . $stmt->error);
    }
    $stmt->close();

    ai_games_setup($conn, $club);
    sendOnboardingEmail($email, $club, $conn);
    header('Location:login');
    exit;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

function ai_games_setup($conn, $club) {
    $games = ["TicTacToe", "Connect4", "SnakeDuel"];
    $leaderboard = json_encode([["name" => "DEV", "highscore" => 20]]);
    $stmt = $conn->prepare("INSERT INTO aigamesFundraising (name, club, leaderboard) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    foreach ($games as $game) {
        $stmt->bind_param("sss", $game, $club, $leaderboard);
        $stmt->execute();
    }
    $stmt->close();
}

function sendOnboardingEmail($recipientEmail, $club, $conn) {
    try {
        require_once 'stripe-sample-code/secrets.php';
        $stripe = new StripeClient($stripeSecretKey);
        $account = $stripe->accounts->create([
            'country' => 'IE',
            'email' => $recipientEmail,
            'controller' => [
                'fees' => ['payer' => 'application'],
                'losses' => ['payments' => 'application'],
                'stripe_dashboard' => ['type' => 'express'],
            ],
        ]);
        if (!isset($account->id) || empty($account->id)) {
            throw new Exception("Stripe account creation failed.");
        }
        $accountLink = $stripe->accountLinks->create([
            'account' => $account->id,
            'refresh_url' => 'https://podrota-ciarandoy.eu1.pitunnel.net/login',
            'return_url' => 'https://podrota-ciarandoy.eu1.pitunnel.net',
            'type' => 'account_onboarding',
        ]);
        $post = [
            'recipient' => $recipientEmail,
            'sender' => 'noreply.mysportmanager@gmail.com',
            'subject' => 'Complete Your Account Setup',
            'message' => "Please complete your account setup by clicking the following link:\n\n" . $accountLink->url,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://podrota-ciarandoy.eu1.pitunnel.net/sendEmails.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("Error sending email: " . curl_error($ch));
        }
        curl_close($ch);
        // Insert Stripe Account ID into clubSecrets
        $sql = "INSERT INTO clubSecrets (stripeAccountID, club) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $account->id, $club);
        if (!$stmt->execute()) {
            throw new Exception("Insert into clubSecrets failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>