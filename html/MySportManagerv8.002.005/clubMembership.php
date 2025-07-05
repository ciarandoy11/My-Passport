<?php
include __DIR__ . '/db.php';
session_start();

// Get user ID from session
$userId = $_SESSION['user_id'];

// Fetch user's club
$stmt = $conn->prepare("SELECT club FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

// Set club in session if not already set
if (!isset($_SESSION['club']) && isset($userData['club'])) {
    $_SESSION['club'] = $userData['club'];
}

// Fetch user admin type
$sql = "SELECT `type-admin` FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$typeAdmin = (int)$row['type-admin']; // Cast to integer
$ci = false; // Initialize ci variable

// Check if user is an admin
if ($typeAdmin == 1) {
    // Fetch club secrets
    $stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
    $stmt->bind_param("s", $club);
    $stmt->execute();
    $secrets = $stmt->get_result();
    $clubSecrets = [];
    if ($secrets->num_rows > 0) {
        while ($row = $secrets->fetch_assoc()) {
            $clubSecrets[] = $row; // Collect each secret
        }
    }
    $ci = 'True';
}

// Include your Composer autoload file
require 'vendor/autoload.php';  // Adjust the path as necessary

// Initialize payment processors
$paymentProcessors = [];
$goCardlessClient = null; // Default to null to handle missing initialization

foreach ($clubSecrets as $secret) {
    // Set Stripe API key if available
    if (isset($secret['stripeAPI']) && !empty($secret['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($secret['stripeAPI']);
        $paymentProcessors['stripe'] = [
            'api_key' => $secret['stripeAPI'],
        ];
    }

    // Set GoCardless access token if available
    if (isset($secret['GoCardlessAPI']) && !empty($secret['GoCardlessAPI'])) {
        $goCardlessClient = new \GoCardlessPro\Client([
            'access_token' => $secret['GoCardlessAPI'],
            'environment' => \GoCardlessPro\Environment::LIVE, // Change to PRODUCTION in live environment
        ]);
        $paymentProcessors['goCardless'] = [
            'access_token' => $secret['GoCardlessAPI'],
        ];
    }
}

// Function to get customers from Stripe and GoCardless
function getAllCustomers($processors, $limit = 10, $startingAfter = null) {
    global $goCardlessClient;

    $allCustomers = [];

    try {
        // Fetch customers from Stripe if it's set
        if (isset($processors['stripe']) && !empty($processors['stripe']['api_key'])) {
            $params = ['limit' => $limit];
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $stripeCustomers = \Stripe\Customer::all($params);

            foreach ($stripeCustomers->data as $customer) {
                $allCustomers[] = [
                    'platform' => 'Stripe',
                    'name' => $customer->name ?? 'N/A',
                    'email' => $customer->email ?? 'N/A',
                    'id' => $customer->id,
		    'phone' => $customer->phone,
                ];
            }
        }

        // Fetch customers from GoCardless if it's set
        if (isset($processors['goCardless']) && !empty($processors['goCardless']['access_token']) && $goCardlessClient instanceof \GoCardlessPro\Client) {
            $params = ['limit' => $limit];
            if ($startingAfter) {
                $params['after'] = $startingAfter;
            }

            $gcCustomers = $goCardlessClient->customers()->list($params);

            foreach ($gcCustomers->records as $customer) {
                $allCustomers[] = [
                    'platform' => 'GoCardless',
                    'name' => trim(($customer->given_name ?? '') . ' ' . ($customer->family_name ?? '')) ?: 'N/A',
                    'email' => $customer->email ?? 'N/A',
                    'id' => $customer->id,
                    'phone' => $customer->phone_number,
                ];
            }
        }

        return $allCustomers;

    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Fetch and display customers
$limit = 10; // Number of customers to fetch per request
$startingAfter = null; // Use for pagination
$customerList = getAllCustomers($paymentProcessors, $limit, $startingAfter);

// Prepare SQL statement for user's invoices
$stmt = $conn->prepare("SELECT invoices FROM memberships WHERE userId = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stmt->bind_result($userInvoices);
if (!$stmt->fetch()) {
    $userInvoices = 'No Invoices Found';
}
$stmt->close();

// Determine the week offset (how many weeks ahead/behind we are)
$clubInvoiceUserID = isset($_GET['clubInvoiceUserID']) ? $_GET['clubInvoiceUserID'] : '';

if ($clubInvoiceUserID !== '') {
    $ci = 'hidden';

    // Fetch specific club user invoices
    $stmt = $conn->prepare("SELECT invoices FROM memberships WHERE userID = ?");
    if ($stmt) {
        $stmt->bind_param("s", $clubInvoiceUserID);
        $stmt->execute();
        $stmt->bind_result($clubUserInvoices);
        $stmt->fetch();
        $stmt->close();
    } else {
        echo "Error retrieving invoices: " . htmlspecialchars($conn->error);
    }
}

// Determine the week offset
$weekOffset = isset($_GET['weekOffset']) ? intval($_GET['weekOffset']) : 0;

// Get the current date
$currentDate = new DateTime();
$currentDayOfWeek = $currentDate->format('N');

// Calculate the Monday of the relevant week
$monday = (clone $currentDate)->modify('-' . ($currentDayOfWeek - 1) . ' days')->modify('+' . ($weekOffset * 7) . ' days');

// Generate the dates for the entire week
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[$i] = $monday->format('d/m/Y');
    $monday->modify('+1 day');
}

$days = [
    'Monday ' . $weekDates[0],
    'Tuesday ' . $weekDates[1],
    'Wednesday ' . $weekDates[2],
    'Thursday ' . $weekDates[3],
    'Friday ' . $weekDates[4],
    'Saturday ' . $weekDates[5],
    'Sunday ' . $weekDates[6]
];

$today = $currentDate->format('d/m/Y');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Membership</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
<style>
    body {
        background-color: #f0f2f5; /* Light background */
        font-family: Moderustic, Arial, sans-serif;
        color: #333; /* Darker text for readability */
    }
    h1, h2 {
        color: #002061; /* Primary color for headers */
        text-align: center; /* Center align headers if needed */
    }
    .sideNav {
            list-style-type: none;
            margin: 0;
            padding: 0;
            width: 15%;
            background-color: #111; /* Darker for a sleeker look */
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            overflow: auto;
            transition: width 0.3s ease-in-out;
            z-index: 9;
        }

        main {
            flex: 1;
            padding: 20px;
            position: relative; /* Ensure it's positioned */
            left: 0; /* Explicit starting position */
            transition: left 0.3s ease-in-out; /* Smooth transition */
        }

        .sideNav.collapsed ~ main {
            left: -150px; /* Moves left when sidebar collapses */
        }

        .sideNav.collapsed {
            width: 50px; /* Slightly larger when collapsed for usability */
        }

        .sideNav.collapsed img,
        .sideNav.collapsed button,
        .sideNav.collapsed a {
            display: none;
            pointer-events: none;
        }
        
        /* Toggle button always visible */
        .toggle-btn {
            position: absolute;
            top: 10px;
            right: 0; /* Matches default sidebar width */
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: left 0.3s ease-in-out;
        }

        .sideNav.collapsed .toggle-btn {
            display: block; /* Ensure it's visible */
            pointer-events: auto; /* Allow interaction */
        }

		li a {
			display: block;
			color: #fff;
			padding: 8px 16px;
			text-decoration: none;
			transition: background-color 0.3s; /* Smooth transition on hover */
		}
		li a.active {
			background-color: #3241FF;
			color: white;
		}
		li a:hover:not(.active) {
			background-color: #565656;
			color: white;
		}
    .styled-table {
        border-collapse: collapse;
        font-size: 16px;
        margin: 20px auto;
        width: 100%; /* Full width */
        table-layout: auto; /* Allow automatic column widths */
    }
    .styled-table thead tr {
        background-color: #3241FF;
        color: #fff;
        text-align: left;
    }
    .styled-table th, .styled-table td {
        padding: 12px 15px;
        border: 1px solid #000;
        text-align: center; /* Center the content in cells */
        white-space: normal; /* Allow text wrapping */
        height: auto; /* Allow automatic height */
        min-height: 50px; /* Minimum height for cells */
        vertical-align: middle; /* Center content vertically */
    }
    .styled-table tbody tr {
        background-color: #f9f9f9;
        color: #565656;
        height: auto; /* Allow automatic row height */
    }
    .styled-table tbody tr:nth-child(even) {
        background-color: #eaeaea;
    }
    .styled-table tbody tr:hover {
        background-color: #ddd;
    }
    .styled-table td {
        color: #000;
        word-wrap: break-word; /* Allow long words to break */
        overflow-wrap: break-word; /* Modern browsers */
    }
    .styled-table th {
        font-weight: bold;
        color: #fff;
        background-color: #3241FF;
        text-align: center; /* Center the header text */
        white-space: normal; /* Allow header text to wrap */
    }
    .styled-table tbody tr td:first-child {
        font-weight: bold; /* Bold first column */
    }

    .form-container {
        max-width: 500px;
        margin: 20px auto; /* Center form */
        padding: 15px; /* Internal spacing */
        background-color: white; /* White background for forms */
        border-radius: 8px; /* Rounded corners */
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Subtle shadow */
    }
    label, select, input {
        display: block;
        margin: 10px 0;
        width: 100%; /* Full width */
    }

	td, th {
			white-space: nowrap; /* Prevents text wrapping in table cells */
			padding: 8px; /* Add some padding for aesthetics */
			border: 1px solid #ccc; /* Optional: add border for table cells */
		}

    /* Media query for mobile responsiveness */
    @media (max-width: 768px) {
        .sideNav {
            width: 100%; /* Full width for mobile screens */
            position: relative; /* Allow it to flow with content */
            height: auto; /* Allow it to grow with content */
            margin: 0;
            z-index: 9;
        }

        main {
				margin: auto;
                left: 0;
                top: 0;
			}
			.sideNav {
				height: auto; /* Allow height to adjust as needed */
				flex-direction: column; /* Stack nav items vertically */
				align-items: stretch; /* Stretch nav items to full width */
				width: 100%; /* Full width for nav items */
				top: 0;
				text-align: center;
                z-index: 9;
			}

        .sideNav.collapsed ~ main {
            top: 10%; /* Moves left when sidebar collapses */
            left: 0;
            margin: auto;
        }

        .sideNav.collapsed {
            height: 50px; /* Slightly larger when collapsed for usability */
        }

        /* Center content on smaller screens */
        .styled-table, .form-container {
            margin: 10px auto; /* Center with reduced margin */
            width: 90%; /* Width set to 90% of the viewport */
        }

        .styled-table {
            font-size: 12px; /* Slightly smaller font size for readability */
			width: 100%; /* Full width */
			border-collapse: collapse; /* Collapse borders */
			table-layout: auto; /* Allow automatic column widths */
        }

        .styled-table th, .styled-table td {
            padding: 8px 10px; /* Slightly reduced padding for mobile */
            min-height: 40px; /* Slightly reduced minimum height for mobile */
        }

        li a {
            padding: 10px 15px; /* Increased padding for touch targets */
            text-align: center; /* Center links for better readability */
        }

		.table-wrapper {
			overflow: auto; /* Allows scrolling */
		}

		td, th {
			white-space: nowrap; /* Prevents text wrapping in table cells */
			padding: 8px; /* Add some padding for aesthetics */
			border: 1px solid #ccc; /* Optional: add border for table cells */
		}
    }

    /* Loading Screen */
    .loading-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #89f7fe, #66a6ff);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.5s ease-out;
    }

    .loading-screen.fade-out {
        opacity: 0;
        pointer-events: none;
    }

    .loading-content {
        text-align: center;
        color: white;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid var(--primary-blue);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .table-wrapper {
        overflow: auto; /* Allows scrolling */
        width: 150%; /* Full width */
        transform: translateX(-200px);
    }

    /* Media query for mobile responsiveness */
    @media (max-width: 768px) {
        .styled-table {
            font-size: 12px; /* Slightly smaller font size for readability */
            width: 100%; /* Full width */
            border-collapse: collapse; /* Collapse borders */
            table-layout: auto; /* Allow automatic column widths */
            margin: 10px auto; /* Center with reduced margin */
        }

        .styled-table th, .styled-table td {
            padding: 8px 10px; /* Slightly reduced padding for mobile */
            min-height: 40px; /* Slightly reduced minimum height for mobile */
        }

        .table-wrapper {
            width: 100%; /* Full width on mobile */
            overflow-x: auto; /* Horizontal scroll on mobile */
		}
    }
</style>
	<script src="https://js.stripe.com/v3/"></script> <!-- Include Stripe.js -->
</head>
<body>
<!-- Loading Screen -->
<div class="loading-screen">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <h2>Loading...</h2>
    </div>
</div>

<?php include 'includes/admin_navigation.php'; ?>
    <main>
    <?php
    if ($ci === 'True' && !empty($clubSecrets)) { // Check both conditions
        // Display Customers from both Stripe and GoCardless
        if (is_array($customerList) && !empty($customerList)) {
            echo '<h2>Club Invoices:</h2>';
            echo '<div class="table-wrapper">';
            echo '<table class="styled-table" border="1" cellpadding="10" cellspacing="0">';
            echo '<thead>';
            echo '<tr><th>Platform</th><th>Name</th><th>Email</th><th>Phone</th><th>ID</th><th>Status</th></tr>';
            echo '</thead><tbody>';

            // First display bank transfer invoices
            $stmt = $conn->prepare("
                SELECT m.userId, m.invoices, u.username, u.email, u.swimmer, u.phone 
                FROM memberships m 
                JOIN users u ON m.userId = u.id 
                WHERE m.club = ? AND m.invoices IS NOT NULL
            ");
            $stmt->bind_param("s", $club);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $invoices = json_decode($row['invoices'], true);
                if (isset($invoices['invoices']) && is_array($invoices['invoices'])) {
                    foreach ($invoices['invoices'] as $invoice) {
                        if ($invoice['payment_method'] === 'bank_transfer') {
                            echo '<tr>';
                            echo '<td>Bank Transfer</td>';
                            echo '<td>' . htmlspecialchars($row['swimmer'] ?? $row['username']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['phone'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($invoice['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($invoice['status']) . '</td>';
                            echo '</tr>';
                        }
                    }
                }
            }

            // Then display Stripe and GoCardless customers
            foreach ($customerList as $customer) {
                echo '<tr onclick="window.location.href=\'?clubInvoiceUserID=' . $customer['id'] . '&platform=' . $customer['platform'] . '\';">';
                echo '<td>' . htmlspecialchars($customer['platform']) . '</td>';
                echo '<td>' . htmlspecialchars($customer['name']) . '</td>';
                echo '<td>' . htmlspecialchars($customer['email']) . '</td>';
                echo '<td>' . htmlspecialchars($customer['phone'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($customer['id']) . '</td>';
                echo '<td>Active</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table></div>';
        } else {
            echo '<h2>Club Invoices:</h2>';
            echo '<p>No customers found or an error occurred.</p>';
        }

        echo '<hr>';
    } elseif ($ci === 'False') {
        echo '<h2>Club Invoices:</h2>';
        echo '<p>No invoices found</p><br>';
        echo '<hr>';
    } elseif ($ci === 'hidden') {
        echo '<div class="back-link">
                <button onclick="window.location.href=\'clubMembership.php\'"><i class="fa fa-arrow-left"></i> Back to All members</button>
              </div>';

        $clubInvUserID = isset($_GET['clubInvoiceUserID']) ? $_GET['clubInvoiceUserID'] : '';
        $platform = isset($_GET['platform']) ? $_GET['platform'] : '';

        try {
            if ($clubInvUserID) {
                if ($platform === 'goCardless') {
                // Fetch customer details from GoCardless
                $customer = $goCardlessClient->customers()->get($clubInvUserID);

                // Fetch payments for this customer from GoCardless
                $payments = $goCardlessClient->payments()->list(['customer' => $clubInvUserID]);
                    $hasPayments = !empty($payments->records);

                    // Display invoices in a table
                    echo '<h2>Invoices for: ' . htmlspecialchars($customer->name) . '</h2>';
                    if (count($payments->records) > 0) {
                    echo '<table class="styled-table" border="1" cellpadding="10" cellspacing="0">';
                    echo '<tr>';
                        echo '<th>Invoice ID</th>';
                        echo '<th>Amount Due</th>';
                    echo '<th>Status</th>';
                    echo '<th>Created</th>';
                    echo '</tr>';
                    foreach ($payments->records as $payment) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($payment->id) . '</td>';
                            echo '<td>' . htmlspecialchars($payment->amount / 100) . ' ' . htmlspecialchars($payment->currency) . '</td>';
                            echo '<td>' . htmlspecialchars($payment->status) . '</td>';
                            echo '<td>' . date('d-m-Y H:i:s', $payment->created) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                        echo '<hr>';
                    } else {
                        echo '<p>No invoices found for this customer.</p>';
                        echo '<hr>';
                    }
                } else {
                    // Fetch customer details from Stripe
                    $customer = \Stripe\Customer::retrieve($clubInvUserID);
                    // Fetch invoices for this customer from Stripe
                    $invoices = \Stripe\Invoice::all(['customer' => $clubInvUserID]);

                    // Display invoices in a table
                    echo '<h2>Invoices for: ' . htmlspecialchars($customer->name) . '</h2>';
                    if (count($invoices->data) > 0) {
                        echo '<table class="styled-table" border="1" cellpadding="10" cellspacing="0">';
                        echo '<tr>';
                        echo '<th>Invoice ID</th>';
                        echo '<th>Amount Due</th>';
                        echo '<th>Status</th>';
                        echo '<th>Created</th>';
                        echo '</tr>';
                        foreach ($invoices->data as $invoice) {
                            if ($invoice->status === 'paid' || $invoice->status === 'void') {
                                echo '<tr style="background-color: green;">';
                            } elseif ($invoice->status === 'overdue') {
                                echo '<tr style="background-color: red;" onclick="document.location=\'/clubEmails.php?clubInvoiceUserEmail=' . htmlspecialchars($customer->email) . '&clubInvoiceUserID=' . htmlspecialchars($customer->id) . '&page=beethoven&invoiceNumber=' . $invoice->id . '\'">';
                            } elseif ($invoice->status === 'draft') {
                                echo '<tr style="background-color: orange;">';
                            } else {
                                echo '<tr onclick="document.location=\'/clubEmails.php?clubInvoiceUserEmail=' . htmlspecialchars($customer->email) . '&clubInvoiceUserID=' . htmlspecialchars($customer->id) . '&page=beethoven&invoiceNumber=' . $invoice->id . '\'">';
                            }
                            echo '<td>' . htmlspecialchars($invoice->id) . '</td>';
                            echo '<td>' . htmlspecialchars($invoice->amount_due / 100) . ' ' . htmlspecialchars($invoice->currency) . '</td>';
                            echo '<td>' . htmlspecialchars($invoice->status) . '</td>';
                            echo '<td>' . date('d-m-Y H:i:s', $invoice->created) . '</td>';
                            echo '</tr>';
                        }
                    echo '</table>';
                    echo '<hr>';
                } else {
                        echo '<p>No invoices found for this customer.</p>';
                    echo '<hr>';
                    }
                }
            } else {
                echo '<p>No customer ID provided.</p>';
            }
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            echo 'Error fetching payments: ' . htmlspecialchars($e->getMessage());
        }
    }

    // Get today's date in YYYY-MM-DD format
    $today = date('Y-m-d');
    ?>

			<div class="form-container">
    <h1>Create Invoice</h1>
    <form action="process_invoice.php" method="POST" id="invoice-form">
        <label for="customer_id">Member:</label>
        <select name="customer_id" id="customer-select" required>
            <option disabled selected value="">Please Select A Member</option>
        </select>

        <label for="price_id">Price:</label>
        <input type="text" name="price_id" id="price-input" placeholder="Enter price" required>

        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity" id="quantity" min="1" value="1" required>

		<label for="due_date">Due Date:</label>
        <input type="date" name="due_date" id="due_date" min="<?php echo $today; ?>" value="<?php echo $today; ?>" required>

        <label for="payment_method">Payment Method:</label>
        <select name="payment_method" id="payment-method" required>
            <option disabled selected value="">Select Payment Method</option>
            <!--<option value="bank_transfer">Bank Transfer</option>-->
            <option value="stripe">Stripe</option>
            <option value="goCardless">GoCardless</option>
        </select>

        <div id="bank-details-section" style="display: none;">
            <label for="bank_details">Member's Bank Details:</label>
            <select name="bank_details" id="bank-details">
                <option value="">Select Bank Details</option>
            </select>
            <p class="help-text">Member needs to add their bank details in their membership page</p>
        </div>

        <button type="submit">Create Invoice</button>
    </form>
	</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading screen when page is fully loaded
    const loadingScreen = document.querySelector('.loading-screen');
    if (loadingScreen) {
        loadingScreen.classList.add('fade-out');
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }

        // Fetch customers
        fetch("fetch_customers.php")
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
            .then(customers => {
            console.log('Received customers:', customers); // Debug log
            
                if (customers.error) {
                console.error('Error from server:', customers.error);
                    return;
                }

                // Populate customer dropdown
                const customerSelect = document.getElementById("customer-select");
            if (!customerSelect) {
                console.error('Customer select element not found!');
                return;
            }

            // Clear existing options except the placeholder
            while (customerSelect.options.length > 1) {
                customerSelect.remove(1);
            }

                // Populate options from customer data
            if (Array.isArray(customers) && customers.length > 0) {
                customers.forEach(customer => {
                    const option = document.createElement("option");
                    option.value = customer.id || '';
                    // Display swimmer name if available, otherwise use username
                    option.textContent = customer.swimmers ? `${customer.name} - ${customer.swimmers}` : customer.name;
                    if (!customer.has_account) {
                        option.disabled = true;
                        option.textContent += ' (No user account or hasn\'t set up their account)';
                    }
                    // Store additional data as data attributes
                    option.dataset.email = customer.email || '';
                    option.dataset.phone = customer.phone || '';
                    customerSelect.appendChild(option);
                });
                const option = document.createElement("option");
                option.value = '';
                option.disabled = true;
                option.textContent = 'If a member is not in the list, they don\'t have a user account or haven\'t set up their account';
                customerSelect.appendChild(option);
            } else {
                console.log('No customers found in the response');
            }
        })
        .catch(error => {
            console.error("Error fetching customers:", error);
            const customerSelect = document.getElementById("customer-select");
            if (customerSelect) {
                const errorOption = document.createElement("option");
                errorOption.disabled = true;
                errorOption.textContent = "Error loading members";
                customerSelect.appendChild(errorOption);
            }
        });

    // Handle payment method change
    const paymentMethod = document.getElementById('payment-method');
    const bankDetailsSection = document.getElementById('bank-details-section');
    const bankDetailsSelect = document.getElementById('bank-details');

    paymentMethod.addEventListener('change', function() {
        if (this.value === 'bank_transfer') {
            bankDetailsSection.style.display = 'block';
            // Fetch bank details for the selected customer
            const customerId = document.getElementById('customer-select').value;
            if (customerId) {
                fetchBankDetails(customerId);
            }
        } else {
            bankDetailsSection.style.display = 'none';
        }
    });

    // Fetch bank details when customer changes
    document.getElementById('customer-select').addEventListener('change', function() {
        if (paymentMethod.value === 'bank_transfer') {
            const customerId = this.value;
            if (customerId) {
                fetchBankDetails(customerId);
            }
        }
    });

    // Function to fetch bank details
    function fetchBankDetails(customerId) {
        fetch(`fetch_bank_details.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(bankDetails => {
                bankDetailsSelect.innerHTML = '<option value="">Select Bank Details</option>';
                if (bankDetails.length === 0) {
                    const option = document.createElement('option');
                    option.disabled = true;
                    option.textContent = 'No bank details found for this member';
                    bankDetailsSelect.appendChild(option);
                } else {
                    bankDetails.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = `${detail.account_name} - ${detail.bank_name} (${detail.account_number})`;
                        if (detail.is_default) {
                            option.selected = true;
                        }
                        bankDetailsSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching bank details:', error);
                bankDetailsSelect.innerHTML = '<option value="">Error loading bank details</option>';
            });
    }

    // Handle form submission
    const invoiceForm = document.getElementById('invoice-form');
    if (invoiceForm) {
        invoiceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading screen
            if (loadingScreen) {
                loadingScreen.style.display = 'flex';
                loadingScreen.classList.remove('fade-out');
            }

            const formData = new FormData(this);
            
            fetch('process_invoice.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Handle successful invoice creation
                    if (data.payment_method === 'stripe') {
                        // Redirect to Stripe payment page
                        window.location.href = `stripe_payment.php?payment_intent=${data.payment_id}`;
                    } else if (data.payment_method === 'goCardless') {
                        // Redirect to GoCardless payment page
                        window.location.href = `gocardless_payment.php?payment_id=${data.payment_id}`;
                    } else {
                        // For bank transfer, show success message
                        alert('Invoice created successfully!');
                        window.location.reload();
                    }
                } else {
                    throw new Error(data.error || 'Failed to create invoice');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating invoice: ' + error.message);
            })
            .finally(() => {
                // Hide loading screen
                if (loadingScreen) {
                    loadingScreen.classList.add('fade-out');
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                    }, 500);
                }
            });
        });
    }
    });
</script>

       <?php // Render payment processor forms
        function renderStripeForm($club, $secret = null) {
            echo '<hr><br><form method="POST" action="stripeDetails.php">';
            echo '<input type="hidden" name="club" value="' . htmlspecialchars($club) . '">';
            echo '<label for="stripeApi">Stripe API</label>';
            echo '<input type="password" name="stripeApi" id="stripeApi" class="stripeApiInput" placeholder="Stripe Api" value="' . htmlspecialchars($secret['stripeAPI'] ?? '') . '" required>';
            echo '<p class="help-text">To get your Stripe API key, go to your Stripe dashboard under Developers > API keys. You need a Secret key.</p>';
            echo '<button type="submit" style="background-color: green;">Submit</button>';
            echo '</form>';
        }

        function renderGoCardlessForm($club, $secret = null) {
            echo '<hr><br><form method="POST" action="gocardlessDetails.php">';
            echo '<input type="hidden" name="club" value="' . htmlspecialchars($club) . '">';
            echo '<label for="gocardlessApi">GoCardless Billing Request API Key</label>';
            echo '<input type="password" name="gocardlessApi" id="gocardlessApi" class="gocardlessApiInput" placeholder="GoCardless Billing Request API Key" value="' . htmlspecialchars($secret['GoCardlessAPI'] ?? '') . '" required>';
            echo '<p class="help-text">To get your GoCardless Billing Request API key, go to your GoCardless dashboard under Developers > API keys > Create new key > Billing Request API.</p>';
            echo '<button type="submit" style="background-color: green;">Submit</button>';
            echo '</form>';
        }

        // Render the appropriate forms based on secrets existence
        if (!empty($clubSecrets)) {
            foreach ($clubSecrets as $secret) {
                renderStripeForm($club, $secret);
                renderGoCardlessForm($club, $secret);
            }
        } else {
            renderStripeForm($club);
            renderGoCardlessForm($club);
        }
        ?>
    </main>
</body>
</html>
