<?php
include __DIR__ . '/db.php';

// Fetch club secrets
$stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
$stmt->bind_param("s", $club);
$stmt->execute();
$secrets = $stmt->get_result();
$clubSecrets = [];
if ($secrets->num_rows > 0) {
    while ($row = $secrets->fetch_assoc()) {
        $clubSecrets[] = $row;
    }
}

// Include your Composer autoload file
require "vendor/autoload.php";

if (!empty($clubSecrets)) {
    \Stripe\Stripe::setApiKey($clubSecrets[0]["stripeAPI"]); // Assuming you only use the first one for simplicity
}

function getAllCustomers($email) {
    try {
        // Create an array to hold the parameters
        $params = ['limit' => 10];

        // Add email to parameters if it's provided
        if (!empty($email)) {
            $params['email'] = $email;
        }

        // Retrieve the customers from Stripe
        $customers = \Stripe\Customer::all($params);
        return $customers->data;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe error: " . $e->getMessage());
        return [];
    }
}

// The original code retrieved a list of customers but only used the last one,
// and didn't handle the case where no customer was found.
// $customerList = getAllCustomers($email);
// foreach ($customerList as $customer) {
//     $customerId = $customer->id;
// }

// This query was not used, so it has been removed.
// Fetch invoices
// $stmt = $conn->prepare("SELECT invoices FROM memberships WHERE userId = ?");
// $stmt->bind_param("s", $userId);
// $stmt->execute();
// $stmt->bind_result($userInvoices);
// $stmt->fetch();
// $stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Membership - Swimming Club Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
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
        margin: 20px auto; /* Centered and add margin */
		width: auto; /* Full width */
		table-layout: fixed; /* Optional: Ensures the table respects the width set */
    }
    .styled-table thead tr {
        background-color: #3241FF;
        color: #fff;
        text-align: left;
    }
    .styled-table th, .styled-table td {
        padding: 12px 15px;
        border: 1px solid #000;
    }
    .styled-table tbody tr {
        background-color: #f9f9f9;
        color: #565656;
    }
    .styled-table tbody tr:nth-child(even) {
        background-color: #eaeaea;
    }
    .styled-table tbody tr:hover {
        background-color: #ddd;
    }
    .styled-table td {
        color: #000;
    }
    .styled-table th {
        font-weight: bold;
        color: #fff;
        background-color: #3241FF;
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
			width: auto; /* Full width */
			border-collapse: collapse; /* Collapse borders */
			table-layout: fixed; /* Optional: Ensures the table respects the width set */
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
</style>
</head>
<body>
<?php include 'includes/navigation.php'; ?>
<main>
    <h2>Invoices:</h2>
    <?php
    $customerList = getAllCustomers($email);

    if (empty($customerList)) {
        echo '<p>No customer account found for: <u>' . $email . '</u>. If you believe this is an error, please contact your club or support at: <a href="mailto:ciarandoy11@gmail.com?subject=Finance Error: No Customer Record">My Sport Manager Support</a></p>';
    } else {
        // Assuming the first customer found is the correct one.
        $customer = $customerList[0];
        $customerId = $customer->id;

        try {
            // Fetch Invoices for the specific customer
            $invoices = \Stripe\Invoice::all(['customer' => $customerId]);

            // Display invoices in a table
            if (count($invoices->data) > 0) {
                echo '<div class="table-wrapper">';
                echo '<table class="styled-table" border="1" cellpadding="10" cellspacing="0">';
                echo '<thead><tr>';
                echo '<th>Invoice ID</th>';
                echo '<th>Amount Due</th>';
                echo '<th>Status</th>';
                echo '<th>Created</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($invoices->data as $invoice) {
                    if ($invoice->status == 'draft') {
                        continue;
                    }
                    echo '<tr onclick="window.location=\'' . htmlspecialchars($invoice->hosted_invoice_url) . '\'">'; // Safe URL
                    echo '<td>' . htmlspecialchars($invoice->id) . '</td>';
                    echo '<td>' . htmlspecialchars(number_format($invoice->amount_due / 100, 2)) . ' ' . htmlspecialchars(strtoupper($invoice->currency)) . '</td>'; // Formatting amount and currency
                    echo '<td>' . htmlspecialchars(ucfirst($invoice->status)) . '</td>'; // Capitalizing status
                    echo '<td>' . (new DateTime())->setTimestamp($invoice->created)->format('Y-m-d H:i:s') . '</td>'; // Proper date formatting
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
                echo '<hr>';
            } else {
                echo '<p>No invoices found.</p>';
                echo '<hr>';
            }
        } catch (\Stripe\Exception\ApiErrorException $e) { // More specific exception handling
            error_log("Error fetching invoices for customer ID $customerId: " . $e->getMessage()); // Log error
            echo 'Error fetching invoices: ' . htmlspecialchars($e->getMessage());
        }
    }
?>
</main>
<script>
// Add this at the beginning of your script section
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading screen when page is fully loaded
    const loadingScreen = document.querySelector('.loading-screen');
    if (loadingScreen) {
        loadingScreen.classList.add('fade-out');
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }

    // Scroll reveal effect
    const sections = document.querySelectorAll('section');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            } else {
                entry.target.classList.remove('active');
            }
        });
    }, { threshold: 0.2 });

    sections.forEach(section => {
        observer.observe(section);
    });
});
</script>
</body>
</html>
