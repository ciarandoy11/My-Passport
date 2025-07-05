<?php
error_reporting(E_ALL); // Show all errors
ini_set('display_errors', 1); // Display errors

include __DIR__ . '/db.php';
require 'vendor/autoload.php';
require 'stripe-sample-code/secrets.php'; // Contains your Stripe API keys

// Fetch payment API keys for the user's club
$sql = "SELECT stripeAPI, GoCardlessAPI FROM clubSecrets WHERE club = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare statement failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param("s", $club);
$stmt->execute();
$result = $stmt->get_result();
$paymentData = $result->fetch_assoc();
$stmt->close();

$clubStripeSecretKey = $paymentData['stripeAPI'];
$goCardlessSecretKey = $paymentData['GoCardlessAPI'];

if (!$clubStripeSecretKey && !$goCardlessSecretKey) {
    http_response_code(500);
    echo json_encode(["error" => "Payment API not found for this club."]);
    exit();
}

// Check if GoCardless is used (not supported for this feature)
if ($goCardlessSecretKey && !$clubStripeSecretKey) {
    http_response_code(500);
    echo json_encode(["error" => "GoCardless doesn't currently support this feature, please ask your club's admin to use Stripe instead."]);
    exit();
}

// Set API Key
\Stripe\Stripe::setApiKey($stripeSecretKey); // Your platform's live key

$YOUR_DOMAIN = 'https://podrota-ciarandoy.eu1.pitunnel.net';

// Get game ID
$gameID = isset($_GET['gameId']) ? (int) $_GET['gameId'] : 0;
if ($gameID <= 0) {
    http_response_code(500);
    echo json_encode(["error" => "Invalid game ID"]);
    exit();
}

// Fetch game price
$sql = "SELECT price FROM fundraising WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare statement failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $gameID);
$stmt->execute();
$result = $stmt->get_result();
$price = $result->fetch_assoc();
$stmt->close();

if (!$price) {
    http_response_code(500);
    echo json_encode(["error" => "Game not found"]);
    exit();
}

$priceCents = $price['price'] * 100; // Convert to cents

// Get data from the POST request
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$realAmount = isset($_POST['amount']) ? intval($_POST['amount']) : 0; // Ensure this is an integer
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0; // User ID for donations

$amountCents = $realAmount * 100;
if ($amountCents < 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid amount']);
    exit();
}

// Retrieve the club's Stripe connected account
$sql = "SELECT stripeAccountID FROM clubSecrets WHERE club = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $club);
$stmt->execute();
$result = $stmt->get_result();
$clubSecrets = $result->fetch_assoc();
$stmt->close();

if (!$clubSecrets || !$clubSecrets['stripeAccountID']) {
    http_response_code(500);
    echo json_encode(["error" => "Club's Stripe account not found."]);
    exit();
}

$clubAccountID = $clubSecrets['stripeAccountID']; // Connected account

try {
    // Create a PaymentIntent for the total amount
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $amountCents + $priceCents, // Total amount (donation + game price)
        'currency' => 'eur',
        'description' => 'Payment for AI Game and Donation',
        'application_fee_amount' => intval($amountCents * 0.1), // 10% fee for the platform
        'transfer_data' => [
            'destination' => $clubAccountID, // Transfer 90% to club
        ],
        'metadata' => [
            'user_id' => $userId,
            'club' => $clubAccountID
        ],
    ]);

    // Redirect to Stripe Checkout
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'customer_email' => $email,
        'line_items' => [
            [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Added Donation from ' . htmlspecialchars($name),
                    ],
                    'unit_amount' => $amountCents, // Donation amount
                ],
                'quantity' => 1,
            ],
            [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'AI Game Access/Donation',
                    ],
                    'unit_amount' => $priceCents, // Game price
                ],
                'quantity' => 1,
            ],
        ],
        'mode' => 'payment',
        'success_url' => $YOUR_DOMAIN . '/alpha_env/ai-games-process_participation.php?gameId=' . $gameID . '&name=' . urlencode($name) . '&amount=' . $realAmount,
        'cancel_url' => $YOUR_DOMAIN . '/ai-games-participate.php?gameId=' . $gameID,
        'customer_email' => $email,
        'billing_address_collection' => 'auto',
        'payment_intent_data' => [
            'payment_intent' => $paymentIntent->id,
        ],
    ]);

    // Create a payout to send the club's 90% after payment is confirmed
    $clubShare = intval($amountCents * 0.9);
    $payout = \Stripe\Payout::create([
        "amount" => $clubShare,
        "currency" => "eur",
    ], ["stripe_account" => $clubAccountID]);

    // Redirect to Stripe Checkout
    header("HTTP/1.1 303 See Other");
    header("Location: " . $checkout_session->url);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Close database connection
$conn->close();
?>
