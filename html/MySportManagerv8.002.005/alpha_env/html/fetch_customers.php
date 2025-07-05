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

// Include your Composer autoload file
require 'vendor/autoload.php';  // Adjust the path as necessary

$stripeApiKey = '';
$goCardlessClient = null;

foreach ($clubSecrets as $secret) {
    if (!empty($secret['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($secret['stripeAPI']); // Set Stripe API key
        $stripeApiKey = $secret['stripeAPI'];
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
    $customerList = [];

    if (!empty($stripeApiKey)) {
        $customers = \Stripe\Customer::all(['limit' => 5, 'email' => $query]); // Fetch customers from Stripe
        foreach ($customers->data as $customer) {
            $customerList[] = [
                'id' => $customer->id,
                'name' => $customer->name ?? 'No name',
                'email' => $customer->email
            ];
        }
    }

    if ($goCardlessClient !== null) {
        $goCardlessCustomers = $goCardlessClient->customers()->list(['limit' => 5, 'email' => $query, 'status' => 'active']); // Fetch active customers from GoCardless
        foreach ($goCardlessCustomers->records as $customer) {
            $customerList[] = [
                'id' => $customer->id,
                'name' => $customer->given_name . ' ' . $customer->family_name,
                'email' => $customer->email
            ];
        }
    }

    echo json_encode($customerList);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
