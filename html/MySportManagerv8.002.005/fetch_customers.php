<?php
include __DIR__ . '/db
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - No user_id in session']);
    exit();
}

try {
    // Get the club from the session
    if (!isset($_SESSION['club'])) {
        throw new Exception('Club not set in session');
    }
    $club = $_SESSION['club'];
    
    // Debug: Log the club value
    error_log("Club from session: " . $club);

    // Fetch all swimmers from the users table where username starts with 'swimmer'
    $stmt = $conn->prepare("SELECT id, username, email, phone, swimmer FROM users WHERE club = ? AND swimmer != 'NULL' ORDER BY username");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $club);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $swimmers = $result->fetch_all(MYSQLI_ASSOC);
    
    // Debug: Log the number of swimmers found
    error_log("Number of swimmers found: " . count($swimmers));

    // Prepare the response
    $customers = [];
    foreach ($swimmers as $swimmer) {
        $customers[] = [
            'id' => $swimmer['id'],
            'name' => $swimmer['username'],
            'email' => $swimmer['email'],
            'phone' => $swimmer['phone'],
            'swimmers' => $swimmer['swimmer'],
            'has_account' => true
        ];
    }

    // Fetch groups for the club
    $groupStmt = $conn->prepare("SELECT * FROM groups WHERE club = ?");
    if (!$groupStmt) {
        throw new Exception('Failed to prepare group statement: ' . $conn->error);
    }
    
    $groupStmt->bind_param("s", $club);
    if (!$groupStmt->execute()) {
        throw new Exception('Failed to execute group statement: ' . $groupStmt->error);
    }
    
    $groupResult = $groupStmt->get_result();
    $groups = $groupResult->fetch_all(MYSQLI_ASSOC);

    // Check if each swimmer has an account based on group membership
    foreach ($customers as $key => &$details) {
        $hasAccount = false;
        foreach ($groups as $group) {
            if (strpos($details['swimmers'], $group['item_name']) !== false) {
                $hasAccount = true;
                break;
            }
        }
        $details['has_account'] = $hasAccount;
    }

    // Debug: Log the final number of customers
    error_log("Number of customers prepared: " . count($customers));

    // Return the customers as JSON
    header('Content-Type: application/json');
    echo json_encode($customers);
} catch (Exception $e) {
    error_log("Error in fetch_customers. $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error fetching customers: ' . $e->getMessage(),
        'details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
