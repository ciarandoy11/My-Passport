<?php
include __DIR__ . '/db.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include Composer autoloader
require 'vendor/autoload.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - No user_id in session']);
    exit();
}

try {
    // Validate required fields
    $required_fields = ['customer_id', 'price_id', 'quantity', 'due_date', 'payment_method'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Get form data
    $customer_id = (int)$_POST['customer_id'];
    $price = (float)$_POST['price_id'];
    $quantity = (int)$_POST['quantity'];
    $due_date = $_POST['due_date'];
    $payment_method = $_POST['payment_method'];
    $bank_details_id = isset($_POST['bank_details']) ? (int)$_POST['bank_details'] : null;

    // Validate numeric fields
    if ($price <= 0 || $quantity <= 0) {
        throw new Exception("Price and quantity must be greater than zero");
    }

    // Calculate total
    $total = $price * $quantity;

    // Get user's club
    $stmt = $conn->prepare("SELECT club FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    
    if (!$userData || !isset($userData['club'])) {
        throw new Exception("Could not determine user's club");
    }
    $club = $userData['club'];

    // Get customer details
    $stmt = $conn->prepare("SELECT username, email, swimmer FROM users WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customerData = $result->fetch_assoc();
    
    if (!$customerData) {
        throw new Exception("Customer not found");
    }

    // Get club secrets for payment processing
    $stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
    $stmt->bind_param("s", $club);
    $stmt->execute();
    $result = $stmt->get_result();
    $clubSecrets = $result->fetch_assoc();

    if (!$clubSecrets) {
        throw new Exception("Club payment settings not found");
    }

    // Initialize payment processors
    $stripeClient = null;
    $goCardlessClient = null;

    if ($payment_method === 'stripe' && !empty($clubSecrets['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($clubSecrets['stripeAPI']);
        $stripeClient = new \Stripe\StripeClient($clubSecrets['stripeAPI']);
    } elseif ($payment_method === 'goCardless' && !empty($clubSecrets['GoCardlessAPI'])) {
        try {
            // First try to validate the API key
            $goCardlessClient = new \GoCardlessPro\Client([
                'access_token' => $clubSecrets['GoCardlessAPI'],
                'environment' => \GoCardlessPro\Environment::LIVE
            ]);

            // Test the connection by making a simple API call
            $goCardlessClient->customers()->list(['limit' => 1]);
            
        } catch (\GoCardlessPro\Core\Exception\PermissionsException $e) {
            throw new Exception("GoCardless API key doesn't have the required permissions. Please check your API key and ensure it has the necessary access rights.");
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            throw new Exception("GoCardless connection error: " . $e->getMessage());
        }
    }

    // Process payment based on method
    $payment_id = null;
    $payment_status = 'pending';

    if ($payment_method === 'stripe' && $stripeClient) {
        try {
            // Create or get Stripe customer
            $stripeCustomer = \Stripe\Customer::create([
                'email' => $customerData['email'],
                'name' => $customerData['swimmer'] ?? $customerData['username'],
                'metadata' => [
                    'user_id' => $customer_id,
                    'club' => $club
                ]
            ]);

            // Create Stripe invoice
            $invoice = \Stripe\Invoice::create([
                'customer' => $stripeCustomer->id,
                'payment_settings' => [
                    'payment_method_types' => ['card']
                ],
                'auto_advance' => true,
                'description' => 'Invoice for club membership',
                'metadata' => [
                    'user_id' => $customer_id,
                    'club' => $club
                ]
            ]);

            // Create Stripe invoice item
            \Stripe\InvoiceItem::create([
                'customer' => $stripeCustomer->id,
                'amount' => $total * 100, // Convert to cents
                'currency' => 'eur',
                'description' => 'Invoice for club membership',
                'invoice' => $invoice->id
            ]);

            $payment_id = $invoice->id;
            $payment_status = $invoice->status;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception("Stripe payment error: " . $e->getMessage());
        }
    } elseif ($payment_method === 'goCardless' && $goCardlessClient) {
        try {
            // Create or get GoCardless customer
            $gcCustomer = $goCardlessClient->customers()->create([
                'params' => [
                    'email' => $customerData['email'],
                    'given_name' => $customerData['swimmer'] ?? $customerData['username'],
                    'metadata' => [
                        'user_id' => $customer_id,
                        'club' => $club
                    ]
                ]
            ]);

            // Create GoCardless payment
            $payment = $goCardlessClient->payments()->create([
                'params' => [
                    'amount' => $total * 100, // Convert to pence
                    'currency' => 'EUR',
                    'customer' => $gcCustomer->id,
                    'metadata' => [
                        'user_id' => $customer_id,
                        'club' => $club
                  ]
                ]
              ]);

            $payment_id = $payment->id;
            $payment_status = $payment->status;

        } catch (\GoCardlessPro\Core\Exception\PermissionsException $e) {
            throw new Exception("GoCardless payment error: Insufficient permissions. Please check your API key permissions.");
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            throw new Exception("GoCardless payment error: " . $e->getMessage());
        }
    }

    // Get bank details if provided
    $bank_details = null;
    if ($bank_details_id) {
        $stmt = $conn->prepare("SELECT * FROM bank_details WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $bank_details_id, $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $bank_details = $result->fetch_assoc();
        
        if (!$bank_details) {
            throw new Exception("Bank details not found for this customer");
        }
    }

    // Create invoice data
    $invoice_data = [
        'id' => uniqid(),
        'amount' => $price,
        'quantity' => $quantity,
        'total' => $total,
        'due_date' => $due_date,
        'payment_method' => $payment_method,
        'status' => $payment_status,
        'created_at' => date('Y-m-d H:i:s'),
        'bank_details' => $bank_details,
        'payment_id' => $payment_id
    ];

    // Get existing membership data
    $stmt = $conn->prepare("SELECT * FROM memberships WHERE userId = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $membership = $result->fetch_assoc();

    // Prepare new membership data
    $membership_data = [
        'userId' => $customer_id,
        'club' => $club,
        'unpaid' => $total,
        'paid' => 0.00,
        'athleteData' => json_encode([
            'last_invoice' => $invoice_data,
            'swimmer' => $customerData['swimmer'] ?? $customerData['username'],
            'email' => $customerData['email']
        ])
    ];

    // Prepare invoices data
    $invoices = ['invoices' => []];
    if ($membership && !empty($membership['invoices'])) {
        $existing_invoices = json_decode($membership['invoices'], true);
        if (is_array($existing_invoices) && isset($existing_invoices['invoices'])) {
            $invoices = $existing_invoices;
        }
    }
    $invoices['invoices'][] = $invoice_data;
    $membership_data['invoices'] = json_encode($invoices);

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Update or insert membership
        $stmt = $conn->prepare("INSERT INTO memberships 
            (userId, invoices, club, unpaid, paid, athleteData) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            userId = ?,
            invoices = ?,
            club = ?,
            paid = ?,
            unpaid = unpaid + ?,
            athleteData = ?");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("issddssssds", 
            $membership_data['userId'],
            $membership_data['invoices'],
            $membership_data['club'],
            $membership_data['unpaid'],
            $membership_data['paid'],
            $membership_data['athleteData'],
            // Values for UPDATE part
            $membership_data['userId'],
            $membership_data['invoices'],
            $membership_data['club'],
            $membership_data['paid'],
            $membership_data['unpaid'],
            $membership_data['athleteData']
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to create invoice: " . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        // Return success response with payment details
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Invoice created successfully',
            'invoice_id' => $invoice_data['id'],
            'payment_id' => $payment_id,
            'payment_status' => $payment_status,
            'customer' => [
                'name' => $customerData['swimmer'] ?? $customerData['username'],
                'email' => $customerData['email']
            ],
            'details' => [
                'amount' => $price,
                'quantity' => $quantity,
                'total' => $total,
                'due_date' => $due_date,
                'payment_method' => $payment_method
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in process_invoice.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error processing invoice: ' . $e->getMessage(),
        'details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
