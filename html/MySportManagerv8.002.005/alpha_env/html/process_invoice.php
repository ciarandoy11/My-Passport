<?php
include __DIR__ . '/db.php';

// Retrieve club secrets from database
$stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
$stmt->bind_param("s", $club);
$stmt->execute();
$secrets = $stmt->get_result();
$clubSecrets = [];
while ($row = $secrets->fetch_assoc()) {
    $clubSecrets[] = $row;
}

// Load Composer dependencies
require 'vendor/autoload.php';

$stripe = null;
$goCardlessClient = null;

// Initialize payment clients based on club secrets
foreach ($clubSecrets as $secret) {
    if (!empty($secret['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($secret['stripeAPI']);
        $stripe = new \Stripe\StripeClient($secret['stripeAPI']);
    }
    if (!empty($secret['GoCardlessAPI'])) {
        $environment = $secret['isProduction'] ? \GoCardlessPro\Environment::LIVE : \GoCardlessPro\Environment::SANDBOX;
        $goCardlessClient = new \GoCardlessPro\Client([
            'access_token' => $secret['GoCardlessAPI'],
            'environment' => $environment
        ]);
    }
}

// Handle POST requests for payment processing
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_SANITIZE_STRING);
    $price_id = filter_input(INPUT_POST, 'price_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);

    $currentDate = new DateTime();
    $dueDateObject = DateTime::createFromFormat('Y-m-d', $due_date);

    if (!$dueDateObject) {
        echo "Invalid due date format.<br>";
        exit;
    }

    $interval = $currentDate->diff($dueDateObject);
    $dueDays = $interval->days;
    $dueDays = ($dueDateObject < $currentDate) ? -$dueDays : $dueDays;

    // Process payments through Stripe or GoCardless based on the type of price
    if ($stripe) {
        $price = $stripe->prices->retrieve($price_id);
        switch ($price->type) {
            case 'one_time':
                $stripe->invoiceItems->create([
                    'customer' => $customer_id,
                    'price' => $price->id,
                    'quantity' => $quantity,
                ]);

                $invoice = $stripe->invoices->create([
                    'customer' => $customer_id,
                    'auto_advance' => true,
                    'collection_method' => 'send_invoice',
                    'days_until_due' => $dueDays,
                ]);

                echo "Invoice created successfully!<br>";
                break;
            case 'recurring':
                $stripe->subscriptions->create([
                    'customer' => $customer_id,
                    'items' => [['price' => $price->id, 'quantity' => $quantity]],
                    'collection_method' => 'send_invoice',
                    'days_until_due' => $dueDays,
                ]);

                echo "Subscription created successfully!<br>";
                break;
            default:
                echo "Unsupported price type.<br>";
                break;
        }
    }

    if ($goCardlessClient) {
        try {
            $billingRequest = $goCardlessClient->billingRequests()->create([
                "params" => [
                    "payment_request" => [
                        "description" => "First Payment",
                        "amount" => $price_id * 100, // Convert price_id to cents
                        "currency" => "GBP", // Corrected currency description
                        "app_fee" => $price_id * 100, // App fee set to the same as amount
                    ],
                    "mandate_request" => [
                        "scheme" => "bacs"
                    ]
                ]
            ]);

            $billingRequestFlow = $goCardlessClient->billingRequestFlows()->create([
                "params" => [
                  "redirect_uri" => "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/membership.php",
                  "exit_uri" => "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/membership.php",
                  "links" => [
                    "billing_request" => $billingRequest->id
                  ]
                ]
              ]);
            echo "GoCardless billing request created successfully!<br>" . json_encode($billingRequest) . "<br>Authorisation URL: " . $billingRequestFlow->authorisation_url .  $billingRequestFlow->exit_uri;
            error_log("GoCardless billing request created: " . json_encode($billingRequest));
        } catch (\GoCardlessPro\Core\Exception\ApiException $apiEx) {
            error_log("GoCardless API error: " . $apiEx->getMessage());
            echo "Error creating API billing request. Please contact support.<br>" . $apiEx->getMessage();
        } catch (Exception $e) {
            error_log("General error in GoCardless billing request: " . $e->getMessage());
            echo "Error creating billing request. Please try again later.<br>" . $e->getMessage();
        }
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>
