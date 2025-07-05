<?php
require 'vendor/autoload.php';
session_start();

// Get payment intent ID from URL
$payment_intent_id = $_GET['payment_intent'] ?? null;

if (!$payment_intent_id) {
    die('No payment intent ID provided');
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

if (!$clubSecrets || empty($clubSecrets['stripeAPI'])) {
    die('Stripe API key not found for this club');
}

// Set Stripe API key
\Stripe\Stripe::setApiKey($clubSecrets['stripeAPI']);

try {
    // Retrieve the payment intent
    $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
    
    // Get the client secret
    $client_secret = $payment_intent->client_secret;
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    die('Error retrieving payment intent: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment</title>
    <script src="https://js.stripe.com/v3/"></script>
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
        }
        #payment-form {
            margin-top: 1rem;
        }
        #payment-element {
            margin-bottom: 1rem;
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
        }
        button:disabled {
            opacity: 0.5;
            cursor: default;
        }
        #payment-message {
            color: rgb(105, 115, 134);
            text-align: center;
            font-size: 16px;
            line-height: 20px;
            padding-top: 12px;
        }
        #payment-element .Elements {
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>Complete Your Payment</h1>
        <form id="payment-form">
            <div id="payment-element"></div>
            <button id="submit">Pay Now</button>
            <div id="payment-message"></div>
        </form>
    </div>

    <script>
        const stripe = Stripe('<?php echo $clubSecrets['stripeAPI']; ?>');
        const elements = stripe.elements({
            clientSecret: '<?php echo $client_secret; ?>',
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#3241FF',
                }
            }
        });

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit');
        const messageDiv = document.getElementById('payment-message');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitButton.disabled = true;

            const {error} = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: window.location.origin + '/payment_success',
                }
            });

            if (error) {
                messageDiv.textContent = error.message;
                submitButton.disabled = false;
            }
        });
    </script>
</body>
</html> 