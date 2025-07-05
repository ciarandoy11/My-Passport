<?php
include __DIR__ . '/db';

// Debug: Check if POST data is coming through
if (!isset($_POST['coach_signature']) || !isset($_POST['sessionID'])) {
    die(json_encode([
        "error" => "Missing required data: coach_signature or sessionID",
        "coach_signature" => $_POST['coach_signature'] ?? 'Not set',
        "sessionID" => $_POST['sessionID'] ?? 'Not set'
    ]));
}

// Sanitize and validate input
$coachSignature = $_POST['coach_signature'];  // Sanitize input to avoid issues with extra spaces
$sessionID = $_POST['sessionID'];  // Ensure sessionID is an integer

// Ensure sessionID is valid
if ($sessionID <= 0) {
    die(json_encode(["error" => "Invalid session ID"]));
}

$sql = "UPDATE attendance SET coachSignature = ? WHERE sessionID = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(json_encode(["error" => "SQL prepare failed: " . $conn->error]));
}

$stmt->bind_param("si", $coachSignature, $sessionID);

if (!$stmt->execute()) {
    die(json_encode(["error" => "Execute failed: " . $stmt->error]));
} else {
    echo json_encode([
        "success" => "Attendance recorded successfully!",
        "coach_signature" => $_POST['coach_signature'] ?? 'Not set',
        "sessionID" => $_POST['sessionID'] ?? 'Not set'
    ]);
}

$stmt->close();
$conn->close();
?>