<?php
$email = isset($_POST['email']) ? $_POST['email'] : '';
$clubName = isset($_POST['club']) ? $_POST['club'] : '';
$inputUsername = isset($_POST['username']) ? $_POST['username'] : '';
$inputPassword = isset($_POST['password']) ? $_POST['password'] : '';
$firstName = isset($_POST['first_name']) ? $_POST['first_name'] : '';
$lastName = isset($_POST['last_name']) ? $_POST['last_name'] : '';
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';

require_once 'vendor/autoload.php';
require_once 'stripe-sample-code/secrets.php'; // Updated path to secrets.php

\Stripe\Stripe::setApiKey($stripeSecretKey);
//\Stripe\Stripe::setApiKey($stripeTestSecretKey);
header('Content-Type: application/json');

$YOUR_DOMAIN = 'https://podrota-ciarandoy.eu1.pitunnel.net';

	try {
    $trialPeriodDays = 15; // Number of trial days
    $trialEndTimestamp = time() + ($trialPeriodDays * 86400); // Calculate the trial end timestamp

    $checkout_session = \Stripe\Checkout\Session::create([
        'customer_email' => $email,
        'billing_address_collection' => 'required',
        'shipping_address_collection' => [
            'allowed_countries' => ['US', 'CA', 'IE'],
        ],
        'line_items' => [[
            //'price' => 'price_1QnJR000D9NldbeXQBAOK90g', //test mode price id  
            'price' => 'price_1Q6Xxc00D9NldbeXDqCzmnxe', //live mode price id  
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $YOUR_DOMAIN . '/continueClubSignup.php?club=' . rawurlencode($clubName) 
            . '&username=' . rawurlencode($inputUsername) 
            . '&password=' . rawurlencode($inputPassword) 
            . '&first_name=' . rawurlencode($firstName) 
            . '&last_name=' . rawurlencode($lastName) 
            . '&email=' . rawurlencode($email) 
            . '&phone=' . rawurlencode($phone),
        'cancel_url' => $YOUR_DOMAIN . '/club-signup.php',
        'automatic_tax' => [
            'enabled' => true,
        ],
        'subscription_data' => [
            'trial_end' => $trialEndTimestamp, // Specify the trial end date
            'metadata' => [
                'club_name' => $clubName,
            ],
        ],
    ]);
    

	header("HTTP/1.1 303 See Other");
	header("Location: " . $checkout_session ->url);

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Handle the error and return the error message
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
