<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; // Load dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header("Content-Type: application/json");

$validKey = "1RFxhYueYjebjUxz5z60nMSNB0R-j4b_ldleu_T2X_I";

$rawData = file_get_contents("php://input");
file_put_contents("debug.log", "RAW INPUT: " . $rawData . PHP_EOL, FILE_APPEND); // For Debug

// Decode JSON
$data = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(["error" => "Invalid JSON format"]));
}

// Check for API key
if (!isset($data['api_key']) || $data['api_key'] !== $validKey) {
    http_response_code(403);
    die(json_encode(["error" => "Unauthorized", "data" => $data['api_key'], "api" => $validKey]));
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

$error = ""; // Initialize the error message variable

// Check if form is submitted
$inputUsername = $data['username'] ?? '';
$inputPassword = $data['password'] ?? '';


    // Prepare and bind
    $stmt = $conn->prepare("SELECT id, password, `type-admin`, `type-coach`, club FROM users WHERE username = ?");
    $stmt->bind_param("s", $inputUsername);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userId, $hashedPassword, $typeAdmin, $typeCoach, $club);
        $stmt->fetch();

        // Verify password
        if (password_verify($inputPassword, $hashedPassword)) {
            // Store user ID in session
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $inputUsername;

            // Check if the club's subscription is active
            if (payment_verify($userId, $conn, $club)) {
                if ($typeAdmin == "1") {
                    // Send response
                    echo json_encode(["userId" => $userId, "userClub" => $club, "userType" => "admin", "error" => "false"]);
                    exit();
                } else if ($typeCoach == "1") {
                    // Send response
                    echo json_encode(["userId" => $userId, "userClub" => $club, "userType" => "coach", "error" => "false"]);
                    exit();
                } else {
                    // Send response
                    echo json_encode(["userId" => $userId, "userClub" => $club, "userType" => "user", "error" => "false"]);
                    exit();
                }
            } else {
                $error = "Club subscription is not active";
                if ($typeAdmin == "1") {
                    echo '<a href="clubSettings.php">Subscription Settings</a>';
                }
            }
        } else {
            $error = "Invalid username and/or password";
        }
    } else {
        $error = "Invalid username and/or password";
    }

    $stmt->close();

function payment_verify($userId, $conn) {
    // Fetch club name from the database
    $sql = "SELECT club FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return false; // User not found
    }

    $club = htmlspecialchars($user['club']);

    // Include the Stripe PHP library
    require_once 'vendor/autoload.php';
    require_once 'stripe-sample-code/secrets.php'; // Updated path to secrets.php

    // Set the API key
    try {
        \Stripe\Stripe::setApiKey($stripeSecretKey);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        return false; // API error
    }

    // Fetch all subscriptions (limit to 100 for performance reasons)
    try {
        $subscriptions = \Stripe\Subscription::all(['limit' => 100]);

        foreach ($subscriptions->data as $subscription) {
            if (isset($subscription->metadata['clubName']) && $subscription->metadata['clubName'] === $club) {
                if ($subscription->status === "active") {
                    return true; // Subscription is active
                } else {
                    return false; // Subscription exists but is not active
                }
            }
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return false; // API error
    }

    return false; // No active subscription found
}

$conn->close();
echo json_encode(["error" => $error]);
?>