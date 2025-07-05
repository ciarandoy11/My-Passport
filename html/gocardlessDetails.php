<?php
include __DIR__ . '/db.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

// Get user's club
$stmt = $conn->prepare("SELECT club FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if (!$userData || !isset($userData['club'])) {
    die('Could not determine user\'s club');
}

$club = $userData['club'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gocardlessApi = $_POST['gocardlessApi'] ?? '';

    // Validate the API key by making a test request
    try {
        require 'vendor/autoload.php';
        $client = new \GoCardlessPro\Client([
            'access_token' => $gocardlessApi,
            'environment' => \GoCardlessPro\Environment::LIVE
        ]);

        // Test the connection with a billing request
        try {
            $client->billingRequests()->create([
                'params' => [
                    'payment_request' => [
                        'amount' => 1000,
                        'currency' => 'GBP',
                        'description' => 'Test billing request'
                    ]
                ]
            ]);
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            if (strpos($e->getMessage(), 'billing_requests') !== false) {
                die("This appears to be a regular API key. Please use a Billing Request API key instead. You can create one in your GoCardless dashboard under Developers > API keys > Create new key > Billing Request API.");
            }
            throw $e;
        }

        // If we get here, the API key is valid
        // Update or insert the API key
        $stmt = $conn->prepare("INSERT INTO clubSecrets (club, GoCardlessAPI) VALUES (?, ?) ON DUPLICATE KEY UPDATE GoCardlessAPI = ?");
        $stmt->bind_param("sss", $club, $gocardlessApi, $gocardlessApi);
        
        if ($stmt->execute()) {
            header("Location: clubMembership.php?success=1");
            exit();
        } else {
            die("Error saving API key: " . $conn->error);
        }
    } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
        die("Invalid GoCardless API key: " . $e->getMessage());
    }
}

// If we get here, something went wrong
header("Location: clubMembership.php?error=1");
exit(); 