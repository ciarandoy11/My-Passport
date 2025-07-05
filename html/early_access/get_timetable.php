<?php
include './db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Not logged in']));
}

$userId = $_SESSION['user_id'];
$lastUpdate = isset($_GET['lastUpdate']) ? intval($_GET['lastUpdate']) : 0;

// Get user's club
$sql = "SELECT club FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die(json_encode(['status' => 'error', 'message' => 'User not found']));
}

$club = $user['club'];

// Get all sessions for the club
$sql = "SELECT * FROM timetable WHERE club = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $club);
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];
while ($row = $result->fetch_assoc()) {
    // Ensure otherActivity is an integer
    $row['otherActivity'] = (int)$row['otherActivity'];
    $sessions[] = $row;
}

// Get current timestamp
$timestamp = time();

echo json_encode([
    'status' => 'success',
    'sessions' => $sessions,
    'timestamp' => $timestamp
]);
?> 