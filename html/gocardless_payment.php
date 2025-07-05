<?php
require 'vendor/autoload.php';
session_start();

// Get payment ID from URL
$payment_id = $_GET['payment_id'] ?? null;

if (!$payment_id) {
    die('No payment ID provided');
}

// Get user's club
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT club FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if (!$userData || !isset($userData['club'])) {
    die('Could not determine user\'s club');
}

// Get club secrets
$stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
$stmt->bind_param("s", $userData['club']);
$stmt->execute();
$result = $stmt->get_result();
$clubSecrets = $result->fetch_assoc();

if (!$clubSecrets || empty($clubSecrets['GoCardlessAPI'])) {
    die('GoCardless API key not found for this club');
}

// Initialize GoCardless client
$goCardlessClient = new \GoCardlessPro\Client([
    'access_token' => $clubSecrets['GoCardlessAPI'],
    'environment' => \GoCardlessPro\Environment::LIVE // Changed from SANDBOX to LIVE
]);

try {
    // Retrieve the payment
    $payment = $goCardlessClient->payments()->get($payment_id);
    
    // Get the redirect flow URL
    $redirectFlow = $goCardlessClient->redirectFlows()->create([
        'params' => [
            'description' => 'Payment for invoice',
            'session_token' => session_id(),
            'success_redirect_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/payment_success.php',
            'prefilled_customer' => [
                'email' => $payment->customer->email,
                'given_name' => $payment->customer->given_name,
                'family_name' => $payment->customer->family_name
            ]
        ]
    ]);
    
    $redirectUrl = $redirectFlow->redirect_url;
    
} catch (\GoCardlessPro\Core\Exception\ApiException $e) {
    die('Error retrieving payment: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }
        .payment-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .payment-details {
            margin: 2rem 0;
            text-align: left;
        }
        .payment-details p {
            margin: 0.5rem 0;
        }
        button {
            background: #3241FF;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            margin-top: 1rem;
        }
        button:hover {
            background: #2a35cc;
        }
        .amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3241FF;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>Complete Your Payment</h1>
        <div class="payment-details">
            <p><strong>Amount:</strong> <span class="amount">£<?php echo number_format($payment->amount / 100, 2); ?></span></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($payment->description); ?></p>
            <p><strong>Payment Method:</strong> Direct Debit</p>
        </div>
        <p>You will be redirected to GoCardless to complete your payment securely.</p>
        <button onclick="window.location.href='<?php echo htmlspecialchars($redirectUrl); ?>'">
            Continue to Payment
        </button>
    </div>
</body>
</html> 