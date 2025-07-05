<?php
include __DIR__ . '/db';

// Retrieve the raw POST data
$inputData = json_decode(file_get_contents('php://input'), true);

// Validate and sanitize input data
$name = isset($inputData['name']) ? trim($inputData['name']) : '';
$sessions = isset($inputData['sessions']) ? json_encode($inputData['sessions']) : '';

// Input validation for the name
if (empty($name) || strlen($name) > 255) {
    echo json_encode(["success" => false, "message" => "Invalid presave name"]);
    exit;
}

if (empty($sessions)) {
    echo json_encode(["success" => false, 'message' => 'Sessions data is empty']);
    exit;
}

// Prepare the SQL statement
$sql = "INSERT INTO presave (user_id, name, sessions, club) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(["success" => false, "message" => "SQL prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param('isss', $userId, $name, $sessions, $club);

// Execute the statement and provide feedback
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Presave saved successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to save presave: " . $stmt->error]);
}

// Clean up
$stmt->close();
$conn->close();
?>
