<?php
include __DIR__ . '/db.php';

use Stripe\BillingPortal\Session;
use Stripe\Customer;

// Include the Stripe PHP library
require_once 'vendor/autoload.php';
require_once 'stripe-sample-code/secrets.php'; // Updated path to secrets.php

// Set the API key
try {
    \Stripe\Stripe::setApiKey($stripeSecretKey);
} catch (\Stripe\Exception\InvalidRequestException $e) {
    die(json_encode(["error" => "Stripe API error: " . $e->getMessage()]));
}

// Lookup customer by email
try {
    $customers = Customer::all(["email" => $email, "limit" => 1])->data; // Get first customer match
    if (empty($customers)) {
        die(json_encode(["error" => "No Stripe customer found for this email."]));
    }
    $customerId = $customers[0]->id;
} catch (\Stripe\Exception\ApiErrorException $e) {
    die(json_encode(["error" => "Error retrieving customer: " . $e->getMessage()]));
}

// Define return URL
$YOUR_DOMAIN = 'https://podrota-ciarandoy.eu1.pitunnel.net';

// Create a Billing Portal session
try {
    $session = Session::create([
        'customer' => $customerId,
        'return_url' => $YOUR_DOMAIN . '/admin',
    ]);

    // Redirect user to Stripe Billing Portal

    header("Location: " . $session->url);
    exit();
} catch (\Stripe\Exception\ApiErrorException $e) {
    die(json_encode(["error" => "Error creating billing session: " . $e->getMessage()]));
}
?>
