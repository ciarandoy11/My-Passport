<?php
include __DIR__ . '/db.php';
// Fetch club secrets
$stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
$stmt->bind_param("s", $club);
$stmt->execute();
$secrets = $stmt->get_result(); // Initialize an array for secrets
$clubSecrets = [];
if ($secrets->num_rows > 0) {
    while ($row = $secrets->fetch_assoc()) {
        $clubSecrets[] = $row; // Collect each secret
    }
}

// Initialize payment clients
require 'vendor/autoload.php';

foreach ($clubSecrets as $secret) {
    if (!empty($secret['stripeAPI'])) {
        $stripe = new \Stripe\StripeClient($secret['stripeAPI']);
    }
    if (!empty($secret['GoCardlessAPI'])) {
        $gocardless = new \GoCardlessPro\Client([
            'access_token' => $secret['GoCardlessAPI'],
            'environment' => \GoCardlessPro\Environment::SANDBOX // or LIVE
        ]);
    }
}

try {
    if (isset($stripe)) {
        // Retrieve customers
        $customers = $stripe->customers->all(['limit' => 100]);

        // Retrieve products and prices
        $products = $stripe->products->all(['limit' => 100]);
        $prices = $stripe->prices->all(['limit' => 100]);

        // Output data as JSON
        header("Content-Type: application/json");
        echo json_encode([
            "customers" => $customers->data,
            "products" => $products->data,
            "prices" => $prices->data,
        ]);
    } elseif (isset($gocardless)) {
        // Example GoCardless API call
        $customers = $gocardless->customers()->list();

        // Output data as JSON
        header("Content-Type: application/json");
        echo json_encode([
            "customers" => $customers->records,
        ]);
    } else {
        throw new Exception("No payment provider configured.");
    }
} catch (Exception $e) {
    header("Content-Type: application/json");
    echo json_encode(["error" => $e->getMessage()]);
}
?>
