<?php
include __DIR__ . '/db';

// Fetch user admin type
$sql = "SELECT `type-admin` FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$typeAdmin = (int)$row['type-admin']; // Cast to integer

$email = isset($_POST['email']) ? $_POST['email'] : '';
$inputPassword = isset($_POST['password']) ? $_POST['password'] : '';
$firstName = isset($_POST['first_name']) ? $_POST['first_name'] : '';
$lastName = isset($_POST['last_name']) ? $_POST['last_name'] : '';
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';

require_once 'vendor/autoload';
require_once 'stripe-sample-code/secrets'; // Updated path to secrets

\Stripe\Stripe::setApiKey($stripeTestSecretKey);
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
            'price' => 'price_1QnJR000D9NldbeXQBAOK90g', //test mode price id  
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $YOUR_DOMAIN . '/continueClubSignup?club=' . $clubName . '&username=' . $inputUsername . '&password=' . $inputPassword . '&first_name=' . $firstName . '&last_name=' . $lastName . '&email=' . $email . '&phone=' . $phone,
        'cancel_url' => $YOUR_DOMAIN . '/club-signup',
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
