<?php
include __DIR__ . '/db.php';

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

$sql = "SELECT name FROM presave WHERE club = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $club);
$stmt->execute();
$result = $stmt->get_result();
$nameResult = $result->fetch_assoc();
$stmt->close();

// Prepare the SQL statement
if ($nameResult === $name) {
    $sql = "INSERT INTO presave (user_id, name, sessions, club) VALUES (?, ?, ?, ?)";
} else {
    $sql = "UPDATE presave SET sessions = ? WHERE name = ? AND club = ?";
}

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(["success" => false, "message" => "SQL prepare error: " . $conn->error]);
    exit;
}

if ($nameResult === $name) {
    $stmt->bind_param('isss', $userId, $name, $sessions, $club);
} else {
    $stmt->bind_param('sss', $sessions, $name, $club);
}

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
