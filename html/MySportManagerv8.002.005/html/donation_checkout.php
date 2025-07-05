<?php
include __DIR__ . '/db.php';

// Fetch payment API for the user's club
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

$stripeSecretKey = $paymentData['stripeAPI'];
$goCardlessSecretKey = $paymentData['GoCardlessAPI'];

if (!$stripeSecretKey && !$goCardlessSecretKey) {
    http_response_code(500);
    echo json_encode(["error" => "Payment API not found for this club."]);
    exit();
}

require_once 'vendor/autoload.php'; // Ensure the `vendor` directory is in the correct path

// Check if GoCardless is used
if ($goCardlessSecretKey && !$stripeSecretKey) {
    http_response_code(500);
    echo json_encode(["error" => "GoCardless doesn't currently support this feature, please ask your club's admin to use Stripe instead."]);
    exit();
}

if ($stripeSecretKey) {
    \Stripe\Stripe::setApiKey($stripeSecretKey);
    header('Content-Type: application/json');

    $YOUR_DOMAIN = 'https://podrota-ciarandoy.eu1.pitunnel.net';

    $gameID = isset($_GET['gameId']) ? (int) $_GET['gameId'] : 0;

    if ($gameID <= 0) {
        http_response_code(500);
        echo json_encode(["error" => "Invalid game ID"]);
        exit();
    }

    // Fetch price for the game
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
    $cards = isset($_POST['cards']) ? intval($_POST['cards']) : 0;
    $realAmount = isset($_POST['amount']) ? intval($_POST['amount']) : 0; // Ensure this is an integer

    $amountCents = $realAmount * 100;

    if ($amountCents <= 0 && $cards <= 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid amount or cards count']);
        exit();
    }

    try {
        // Create a Checkout Session
        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Added Donation from ' . htmlspecialchars($name),
                        ],
                        'unit_amount' => $amountCents, // Amount in cents
                    ],
                    'quantity' => 1,
                ],
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Game card(s)',
                        ],
                        'unit_amount' => $priceCents, // Amount in cents
                    ],
                    'quantity' => $cards,
                ],
            ],
            'mode' => 'payment', // Use 'payment' mode for one-time donations
            'success_url' => $YOUR_DOMAIN . '/alpha_env/process_participation.php?gameId=' . $gameID . '&name=' . urlencode($name) . '&amount=' . $realAmount . '&cards=' . $cards,
            'cancel_url' => $YOUR_DOMAIN . '/participate.php', // Change to your failure page
            'customer_email' => $email, // Capture email
            'billing_address_collection' => 'auto',
        ]);

        // Redirect to Stripe Checkout
        header("HTTP/1.1 303 See Other");
        header("Location: " . $checkout_session->url);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Handle the error and return the error message
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Close database connection
$conn->close();
?>
