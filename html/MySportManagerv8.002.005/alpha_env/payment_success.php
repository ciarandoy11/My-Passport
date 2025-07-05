<?php
session_start();
require 'vendor/autoload.php';

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

$payment_status = 'unknown';
$payment_method = 'unknown';

// Check if this is a Stripe payment
if (isset($_GET['payment_intent'])) {
    if (!empty($clubSecrets['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($clubSecrets['stripeAPI']);
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($_GET['payment_intent']);
            $payment_status = $payment_intent->status;
            $payment_method = 'Stripe';
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $payment_status = 'error';
        }
    }
}
// Check if this is a GoCardless payment
elseif (isset($_GET['redirect_flow_id'])) {
    if (!empty($clubSecrets['GoCardlessAPI'])) {
        $goCardlessClient = new \GoCardlessPro\Client([
            'access_token' => $clubSecrets['GoCardlessAPI'],
            'environment' => \GoCardlessPro\Environment::LIVE // Changed from SANDBOX to LIVE
        ]);
        try {
            $redirectFlow = $goCardlessClient->redirectFlows()->complete($_GET['redirect_flow_id']);
            $payment_status = 'succeeded';
            $payment_method = 'GoCardless';
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            $payment_status = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
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
        .status-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .pending {
            color: #ffc107;
        }
        .button {
            background: #3241FF;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        .button:hover {
            background: #2a35cc;
        }
    </style>
</head>
<body>
    <div class="status-container">
        <?php if ($payment_status === 'succeeded'): ?>
            <div class="status-icon success">✓</div>
            <h1>Payment Successful!</h1>
            <p>Your payment has been processed successfully via <?php echo htmlspecialchars($payment_method); ?>.</p>
        <?php elseif ($payment_status === 'processing'): ?>
            <div class="status-icon pending">⟳</div>
            <h1>Payment Processing</h1>
            <p>Your payment is being processed. This may take a few moments.</p>
        <?php else: ?>
            <div class="status-icon error">✕</div>
            <h1>Payment Status Unknown</h1>
            <p>We couldn't determine the status of your payment. Please contact support if you have any questions.</p>
        <?php endif; ?>
        
        <a href="clubMembership.php" class="button">Return to Membership</a>
    </div>
</body>
</html> 