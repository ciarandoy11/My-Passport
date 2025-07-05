<?php
include __DIR__ . '/db.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the customer ID from the request
$customerId = isset($_GET['customer_id']) ? $_GET['customer_id'] : null;

if (!$customerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID is required']);
    exit();
}

try {
    // Fetch bank details for the specified customer
    $stmt = $conn->prepare("SELECT id, account_name, account_number, sort_code, bank_name, is_default FROM bank_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bankDetails = $result->fetch_all(MYSQLI_ASSOC);

    // Return the bank details as JSON
    header('Content-Type: application/json');
    echo json_encode($bankDetails);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching bank details: ' . $e->getMessage()]);
} 