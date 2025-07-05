<?php
session_start(); // Start the session

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

$userId = $_SESSION['user_id'];

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data
$sql = "SELECT firstname, username, club, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die(json_encode(["error" => "User not found"]));
}

$firstname = $user['firstname'];
$userName = $user['username'];
$club = $user['club'];
$email = $user['email'];
$stmt->close();

// Fetch club secrets
        $stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
        $stmt->bind_param("s", $club);
        $stmt->execute();
        $secrets = $stmt->get_result(); // Initialize an array for secrets
        $clubSecrets = [];
        if ($secrets->num_rows > 0) {
            while ($row = $secrets->fetch_assoc()) {
                $clubSecrets[] = $row; // Collect each secret
            }
        }

// Include your Composer autoload file
require 'vendor/autoload.php';  // Adjust the path as necessary

foreach ($clubSecrets as $secret) {
    // Set your secret key. Remember to switch to your live secret key in production!
    if (!empty($secret['stripeAPI'])) {
        \Stripe\Stripe::setApiKey($secret['stripeAPI']); // Replace with your actual secret key.
        $clubStripeApi = $secret['stripeAPI'];
    } elseif (!empty($secret['GoCardlessAPI'])) {
        $client = new \GoCardlessPro\Client([
            'access_token' => $secret['GoCardlessAPI'],
            'environment' => \GoCardlessPro\Environment::SANDBOX // Change to LIVE in production
        ]);
        $clubGoCardlessApi = $secret['GoCardlessAPI'];
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'inbox';
$invNum = isset($_GET['invoiceNumber']) ? $_GET['invoiceNumber'] : '';
$clubInvoiceUserEmail = isset($_GET['clubInvoiceUserEmail']) ? $_GET['clubInvoiceUserEmail'] : '';

// Determine the week offset (how many weeks ahead/behind we are)
$weekOffset = isset($_GET['weekOffset']) ? intval($_GET['weekOffset']) : 0;

// Get the current date
$currentDate = new DateTime();
$currentDayOfWeek = $currentDate->format('N'); // 1 (for Monday) through 7 (for Sunday)

// Calculate the Monday of the relevant week
$monday = clone $currentDate;
$monday->modify('-' . ($currentDayOfWeek - 1) . ' days');
$monday->modify('+' . ($weekOffset * 7) . ' days');

// Generate the dates for the entire week
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[$i] = $monday->format('d/m/y');
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

 $today = $currentDate->format('d/m/y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Emails Dashboard</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
<style>
body {
    background-color: #f0f2f5; /* Light background */
    font-family: Moderustic, Arial, sans-serif; /* Ensure both fonts used */
    color: #333; /* Darker text for readability */
    margin: 0; /* Remove default margin */
}

h1, h2 {
    color: #002061; /* Primary color for headers */
}

.sideNav {
    list-style-type: none;
    margin: 0;
    padding: 0;
    width: 15%;
    background-color: #000;
    position: fixed;
    left: 0;
    height: 100%;
    overflow: auto;
    z-index: 1;
}

li a {
    display: block;
    color: #fff;
    padding: 8px 16px;
    text-decoration: none;
}

li a.active {
    background-color: #3241FF;
    color: white;
}

li a:hover:not(.active) {
    background-color: #565656;
    color: white;
}

.container {
    max-width: 100%;
    position: relative;
    left: 21%; /* Offset for sidebar */
    padding: 20px;
}

.header {
    background-color: #0073e6;
    color: white;
    padding: 15px;
    text-align: center;
}

.header h1 {
    margin: 0;
    font-size: 24px;
}

.sidebar {
    width: 20%;
    float: left;
    background-color: #fff;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    height: 100vh; /* Full viewport height */
}

.sidebar ul {
    list-style: none;
    padding: 0;
}

.sidebar ul li {
    margin: 15px 0;
}

.sidebar ul li a {
    color: #0073e6;
    text-decoration: none;
    font-size: 16px;
}

.inbox {
    width: 75%;
    float: right;
    background-color: #fff;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.inbox table {
    width: 100%;
    border-collapse: collapse;
}

.inbox table th, .inbox table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.inbox table th {
    background-color: #0073e6;
    color: white;
}

.inbox table tr:hover {
    background-color: #f1f1f1;
}

.search-box {
    margin-bottom: 20px;
}

.search-box input[type="text"] {
    width: 100%;
    padding: 10px;
    font-size: 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.clearfix::after {
    content: "";
    clear: both;
    display: table;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .sideNav, .sidebar {
        width: 100%; /* Full width for mobile */
        position: relative; /* Allow layout flow */
        height: auto; /* Change to auto height */
        float: none; /* Remove float for layout flow */
    }

    .header {
        padding: 10px; /* Reduce padding */
    }

    .header h1 {
        font-size: 20px; /* Reduce header font size */
    }

    .container {
        left: 0; /* Reset offset */
        padding: 10px; /* Reduce padding */
    }

    .inbox {
        width: 100%; /* Full width for content on mobile */
        float: none; /* Remove float */
    }
}
</style>
</head>
<body>
<ul class='sideNav'>
            <li><img onclick='document.location="/admin.php"' src='/images/logo-rectangle.png'></li>
          <li><button class="signOut" style="background-color: red; color: white;" onclick="document.location='login.php'">Sign Out</button></li>
	  <li><a href="admin.php">Home</a></li>
          <!--<li><a href="/timetable.php">Timetable</a></li>-->
	  <li><a href="users.php">Users</a></li>
          <li><a href="clubMembership.php">Finances</a></li>
          <li><a href="comingSoon.php">Fundraising</a></li>
          <li><a class="active" href="clubEmails.php">Emails Dashboard</a></li>
          <li style="color: grey;"><a href="clubSettings.php">Subscription Settings</a></li>
        </ul>
    <main>
        <div class="container">
    <div class="clearfix">
        <div class="sidebar">
            <ul>
                <li><a href="?page=beethoven">Compose email</a></li>
                <li><a href="?page=inbox">Inbox</a></li>
                <li><a href="?page=sent">Sent</a></li>
                <li><a href="?page=drafts">Drafts</a></li>
                <li><a href="?page=trash">Trash</a></li>
                <li><a href="?page=spam">Spam</a></li>
            </ul>
        </div>
        <div class="inbox">
            <?php if ($page === 'inbox' || $page === 'sent' || $page === 'drafts' || $page === 'trash' || $page === 'spam') { ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo ($page === 'inbox' || $page === 'spam') ? 'Sender' : 'Recipient'; ?></th>
                            <th>Subject</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($page === 'inbox') {
                            echo '<div class="header">
                                <h1>Email Inbox</h1>
                                </div>';
                            // SQL query to select all emails in inbox
                            $sql = "SELECT id, sender, recipient, subject, date_sent FROM emails WHERE recipient = ? && status = 'normal' ORDER BY date_sent DESC";
                        } elseif ($page === 'sent') {
                            echo '<div class="header">
                                <h1>Email Outbox/Sent</h1>
                                </div>';
                            // SQL query to select all sent emails
                            $sql = "SELECT id, sender, recipient, subject, date_sent FROM emails WHERE sender = ? && status = 'normal' ORDER BY date_sent DESC";
                        } elseif ($page === 'drafts') {
                            echo '<div class="header">
                                <h1>Drafts</h1>
                                </div>';
                            // SQL query to select all sent emails
                            $sql = "SELECT id, sender, recipient, subject, date_sent FROM emails WHERE sender = ? && status = 'draft' ORDER BY date_sent DESC";
                        } elseif ($page === 'trash') {
                            echo '<div class="header">
                                <h1>Trash</h1>
                                </div>';
                            // SQL query to select all sent emails
                            $sql = "SELECT id, sender, recipient, subject, date_sent FROM emails WHERE sender = ? && status = 'trash' ORDER BY date_sent DESC";
                        } elseif ($page === 'spam') {
                            echo '<div class="header">
                                <h1>Spam</h1>
                                </div>';
                            // SQL query to select all sent emails
                            $sql = "SELECT id, sender, recipient, subject, date_sent FROM emails WHERE recipient = ? && status = 'spam' ORDER BY date_sent DESC";
                        }

                        // Prepare and execute query
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $email); // "s" indicates a string parameter
                        $stmt->execute();
                        $result = $stmt->get_result();

                        // Display emails
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $emailId = $row['id']; // Assuming you have an `id` field for the email
                                echo '<tr onclick="window.location.href=\'?page=viewEmail&id=' . $emailId . '\'">';
                                echo "<td>" . htmlspecialchars(($page === 'inbox' || $page === 'spam') ? $row['sender'] : $row['recipient']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['date_sent']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3'>No emails found</td></tr>";
                        }

                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            <?php } elseif ($page === 'beethoven') {
                        if (!empty($clubInvoiceUserEmail)) {

                            // Specific user invoices section
                            $clubInvUserID = $_GET['clubInvoiceUserID'] ?? '';

                            $backButton = '<a href="clubMembership.php?clubInvoiceUserID=' . $clubInvUserID . '" style="background-color: blue; color: white;">Back to Finances</a>';
                            
                            // Fetch user data
                            $sql = "SELECT firstname, lastname, club, email FROM users WHERE email = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("s", $clubInvoiceUserEmail);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $user = $result->fetch_assoc();

                            if (!$user) {
                                header("HTTP/1.1 500 Internal Server Error");
                                echo json_encode(["error" => "User not found"]);
                                exit();
                            }

                            $userfname = $user['firstname'];
                            $userlname = $user['lastname'];
                            $userClub = $user['club'];
                            $userEmail = $user['email'];
                            $stmt->close();
                        }

                        if (!empty($invNum)) {

                            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
                            $stmt->bind_param("s", $clubInvoiceUserEmail);
                            $stmt->execute();
                            $stmt->bind_result($userEmail);
                            $stmt->fetch();
                            $stmt->close();

                            $customerId = $clubInvUserID; // Ensure you have the correct customer ID

                            // Validate customer ID and invoice number
                            if (empty($customerId)) {
                                echo "Customer ID is missing or NULL.";
                                throw new Exception("Customer ID cannot be empty.");
                            }

                            if (empty($invNum)) {
                                echo "Invoice number is missing or NULL.";
                                throw new Exception("Invoice number cannot be empty.");
                            }

                            try {
                                if (!empty($clubStripeApi)) {
                                    echo "Customer ID: " . $customerId . ", Invoice Number: " . $invNum;
                                    $customer = \Stripe\Customer::retrieve($customerId);
                                    $invoices = \Stripe\Invoice::all(['customer' => $customerId, 'invoice' => $invNum]);
                                } elseif (!empty($clubGoCardlessApi)) {
                                    echo "Customer ID: " . $customerId . ", Invoice Number: " . $invNum;
                                    $customer = $client->customers()->get($customerId);
                                    $invoices = $client->payments()->list(['customer' => $customerId, 'payment' => $invNum]);
                                    print_r($invoices);
                                }
                            } catch (\Exception $e) {
                                echo "GoCardless/Stripe API error: " . $e->getMessage();
                                header("HTTP/1.1 500 Internal Server Error");
                                echo 'Error fetching customer or invoice details: ' . htmlspecialchars($e->getMessage());
                                exit;
                            }

                            // Process each invoice
                            if (!empty($clubStripeApi)) {
                                foreach ($invoices->data as $invoice) {
                                    if (!empty($invoice)) {
                                        print_r($customer);
                                        $invoiceDate = date('Y-m-d', $invoice->created);
                                        $subject = 'Unpaid Invoice';
                                        $message = 'Hi ' . $userfname . ',<br><br>We have noticed that your invoice dated ' . $invoiceDate . ' remains unpaid.<br><br>';
                                        $paymentLink = htmlspecialchars($invoice->hosted_invoice_url);
                                        if ($paymentLink === '') {
                                            $message .= 'There was an error retrieving the payment link. Please contact support.';
                                        } else {
                                            $message .= 'Please make a payment using this link: <a href="' . $paymentLink . '">Pay Now</a>';
                                        }
                                        $message .= ' Please inform us about any issues by replying to this email to the following address: <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a><br><br>Best regards,<br>' . $firstname . '.';
                                    } else {
                                        echo 'no data' . $customer;
                                    }
                                }
                            } elseif (!empty($clubGoCardlessApi)) {
                                foreach ($invoices->records as $invoice) {
                                    if (!empty($invoice)) {
                                        print_r($customer);
                                        $invoiceDate = date('Y-m-d', strtotime($invoice->charge_date));
                                        $subject = 'Unpaid Invoice';
                                        $message = 'Hi ' . $userfname . ',<br><br>We have noticed that your invoice dated ' . $invoiceDate . ' remains unpaid.<br><br>';
                                        $message .= ' Please inform us about any issues by replying to this email to the following address: <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a><br><br>Best regards,<br>' . $firstname . '.';
                                    } else {
                                        echo 'no data' . $customer;
                                    }
                                }
                            }
                        }
                        ?>
                <div class="container-beethoven">
                    <h1>Compose Email</h1>
                    <form action="sendEmails.php" method="POST">
						<input type="hidden" name="sender" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                        <label for="recipient">To:</label>
						<input type="email" id="recipient" name="recipient" required placeholder="Recipient email address" value="<?php echo isset($userEmail) ? htmlspecialchars($userEmail) : ''; ?>">

                        <label for="subject">Subject:</label>
                        <input type="text" id="subject" name="subject" required placeholder="Email subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>">

                        <label for="message">Message:</label>
						<textarea id="message" name="message" required placeholder="Type your message here..."><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>

                        <input type="submit" value="Send Email">
                    </form>

                    <div class="back-link">
                        <a href="?page=inbox">Back to Inbox</a>
						<?php echo $backButton; ?>
                    </div>
                </div>
            <?php } elseif ($page === 'viewEmail') {
    echo '<div class="header">
            <h1>Message</h1>
          </div>';

    // Get the email ID from the URL
    $emailId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // SQL query to select the email details
    $stmt = $conn->prepare("SELECT sender, recipient, subject, body, emailType, status, date_sent FROM emails WHERE id = ?");
    $stmt->bind_param("i", $emailId); // Use "i" for integer binding
    $stmt->execute();

    // Bind the results to variables
    $stmt->bind_result($sender, $recipient, $subject, $body, $emailType, $status, $dateSent);
    $stmt->fetch();
    $stmt->close();

    // Check if an email was found
    if ($subject) {
        echo '<div class="email-view">';
        echo '<p><strong>From:</strong> ' . htmlspecialchars($sender) . '</p>';
        echo '<p><strong>To:</strong> ' . htmlspecialchars($recipient) . '</p>';
        echo '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>';
        echo '<p><strong>Date Sent:</strong> ' . htmlspecialchars($dateSent) . '</p>';
        echo '<hr>';
        // Assume $body contains the email content and $isHtml indicates if it's HTML or plain text
		echo '<div class="email-body">';
		//echo $emailType;
		if ($emailType == 'plainText') {
			// For plain text emails, use htmlspecialchars only
			echo '<p>' . htmlspecialchars($body) . '</p>';
		} elseif ($emailType == 'html') {
			// For HTML emails, allow HTML tags but apply nl2br to preserve line breaks
			echo '<p>' . nl2br($body) . '</p>';
		}
		echo '</div>'; // End of email-body
        // Add the button with an onclick event to report spam
		if ($status !== 'trash') {
			echo '<button onclick="reportAsSpam(' . $emailId . ')">Report as Spam</button>';
			echo '<button onclick="moveToTrash(' . $emailId . ')">Move to Trash</button>';
		} else {
			echo '<button onclick="restoreEmail(' . $emailId . ')">Restore Email</button>';
			echo '<button onclick="deleteEmail(' . $emailId . ')">Delete Forever</button>';
		}
        echo '</div>'; // End of email-view
    } else {
        echo '<p>Email not found.</p>';
		header("Location: /clubEmails.php?page=inbox");
    }
}
?>

<script>
function reportAsSpam(emailId) {
    if (confirm("Are you sure you want to report this email as spam?")) {
        fetch("reportSpam.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "id=" + emailId,
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                // Optionally, you can redirect or refresh the page if needed
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("There was an error reporting the email.");
        });
    }
}

function moveToTrash(emailId) {
        fetch("moveToTrash.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "id=" + emailId,
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                // Optionally, you can redirect or refresh the page if needed
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("There was an error reporting the email.");
        });
}

function restoreEmail(emailId) {
    if (confirm("Are you sure you want to restore this email?")) {
        fetch("restoreEmail.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "id=" + emailId,
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                // Optionally, you can redirect or refresh the page if needed
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("There was an error reporting the email.");
        });
    }
}

function deleteEmail(emailId) {
    if (confirm("Are you sure you want to delete this email forever? This action is not reverable!")) {
        fetch("deleteEmail.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "id=" + emailId,
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                // Optionally, you can redirect or refresh the page if needed
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("There was an error reporting the email.");
        });
    }
}
</script>
        </div>
    </div>
</div>
    </main>
</body>
</html>



