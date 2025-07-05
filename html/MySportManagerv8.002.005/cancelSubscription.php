<?php
include __DIR__ . '/db.php';

// Fetch user admin type
$sql = "SELECT `type-admin` FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$typeAdmin = (int)$row['type-admin']; // Cast to integer

// Include the Stripe PHP library
require_once 'vendor/autoload.php';
require_once 'stripe-sample-code/secrets.php'; // Updated path to secrets.php

// Set the API key
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Initialize an array to hold the subscription data
$subscriptions = [];

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = $data['subscriptionId'] ?? '';
    if (!empty($subscriptionId)) {
        // Cancel the subscription
        $subscription = \Stripe\Subscription::retrieve($subscriptionId);
        $subscription->cancel();
        echo json_encode(["message" => "Subscription cancelled successfully"]);
    } else {
        echo json_encode(["error" => "Subscription ID is required"]);
    }
}
?>
