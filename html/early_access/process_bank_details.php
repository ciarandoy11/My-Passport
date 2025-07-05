<?php
include __DIR__ . '/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit();
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    // Validate inputs
                    if (empty($_POST['account_name']) || empty($_POST['account_number']) || 
                        empty($_POST['sort_code']) || empty($_POST['bank_name'])) {
                        throw new Exception("All fields are required");
                    }

                    $accountName = trim($_POST['account_name']);
                    $accountNumber = preg_replace('/[^0-9]/', '', $_POST['account_number']);
                    $sortCode = preg_replace('/[^0-9-]/', '', $_POST['sort_code']);
                    $bankName = trim($_POST['bank_name']);
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;

                    // Validate account number length
                    if (strlen($accountNumber) !== 8) {
                        throw new Exception("Account number must be 8 digits");
                    }

                    // Validate sort code format
                    if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $sortCode)) {
                        throw new Exception("Sort code must be in format XX-XX-XX");
                    }

                    // If this is set as default, unset any existing defaults
                    if ($isDefault) {
                        $stmt = $conn->prepare("UPDATE bank_details SET is_default = 0 WHERE user_id = ?");
                        if (!$stmt) {
                            throw new Exception("Database error: " . $conn->error);
                        }
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                    }

                    $stmt = $conn->prepare("INSERT INTO bank_details (user_id, account_name, account_number, sort_code, bank_name, is_default) VALUES (?, ?, ?, ?, ?, ?)");
                    if (!$stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $stmt->bind_param("issssi", $userId, $accountName, $accountNumber, $sortCode, $bankName, $isDefault);
                    $stmt->execute();
                    break;

                case 'delete':
                    if (empty($_POST['id'])) {
                        throw new Exception("Invalid bank details ID");
                    }
                    $id = (int)$_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM bank_details WHERE id = ? AND user_id = ?");
                    if (!$stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $stmt->bind_param("ii", $id, $userId);
                    $stmt->execute();
                    break;

                case 'set_default':
                    if (empty($_POST['id'])) {
                        throw new Exception("Invalid bank details ID");
                    }
                    $id = (int)$_POST['id'];
                    
                    // First unset all defaults
                    $stmt = $conn->prepare("UPDATE bank_details SET is_default = 0 WHERE user_id = ?");
                    if (!$stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();

                    // Then set the new default
                    $stmt = $conn->prepare("UPDATE bank_details SET is_default = 1 WHERE id = ? AND user_id = ?");
                    if (!$stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $stmt->bind_param("ii", $id, $userId);
                    $stmt->execute();
                    break;

                default:
                    throw new Exception("Invalid action");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Redirect back to the previous page
$redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'membership';
header('Location: ' . $redirectUrl);
exit(); 