<?php
include __DIR__ . '/db

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

// Include your Composer autoload file
require 'vendor/autoload// Adjust the path as necessary

$stripe = null;
$goCardlessClient = null;

foreach ($clubSecrets as $secret) {
    if (!empty($secret['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($secret['stripeAPI']);
        $stripe = new \Stripe\StripeClient($secret['stripeAPI']);
    }
    if (!empty($secret['GoCardlessAPI'])) {
        $goCardlessClient = new \GoCardlessPro\Client([
            'access_token' => $secret['GoCardlessAPI'],
            'environment' => \GoCardlessPro\Environment::SANDBOX // Change to LIVE in production
        ]);
    }
}

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

try {
    $productList = [];

    if ($stripe) {
        $products = $stripe->products->all(['limit' => 5]);
        foreach ($products->data as $product) {
            $price = $stripe->prices->retrieve($product->default_price);
            $productList[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $price->unit_amount,
                'currency' => strtoupper($price->currency)
            ];
        }
    }

    if ($goCardlessClient) {
        // Example GoCardless API call to fetch products or equivalent data
        // This is a placeholder as GoCardless does not handle products directly like Stripe
        $productList[] = [
            'id' => 'gc_prod_example',
            'name' => 'GoCardless Product',
            'price' => 1000,
            'currency' => 'GBP'
        ];
    }

    echo json_encode($productList);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
