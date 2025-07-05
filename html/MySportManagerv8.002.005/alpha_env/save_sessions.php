<?php
include './db.php';
session_start(); // Start the session

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in"]);
    exit;
}

$userId = $_SESSION['user_id'];

header('Content-Type: application/json');

// Get the JSON data from the request
$json = file_get_contents('php://input');
$sessions = json_decode($json, true);

if (!$sessions) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

// Retrieve the club from the users table
$sql = "SELECT club FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$club = $user['club'];

// Begin transaction
$conn->begin_transaction();

try {
    foreach ($sessions as $session) {
        if (isset($session['delete']) && $session['delete']) {
            // Delete session
            $sql = "DELETE FROM timetable WHERE id = ? AND club = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $session['sessionId'], $club);
            $stmt->execute();
        } else {
            // Check if session exists
            $sql = "SELECT id FROM timetable WHERE id = ? AND club = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $session['sessionId'], $club);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing session
                $sql = "UPDATE timetable SET 
                    _group = ?, 
                    _location = ?, 
                    stime = ?, 
                    etime = ?, 
                    coach = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND club = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssss", 
                    $session['group'],
                    $session['location'],
                    $session['stime'],
                    $session['etime'],
                    $session['coach'],
                    $session['sessionId'],
                    $club
                );
            } else {
                // Insert new session
                $sql = "INSERT INTO timetable (
                    id, _day, _time, _group, _location, stime, etime, coach, club, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssss", 
                    $session['sessionId'],
                    $session['day'],
                    $session['time'],
                    $session['group'],
                    $session['location'],
                    $session['stime'],
                    $session['etime'],
                    $session['coach'],
                    $club
                );
            }
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Timetable updated successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error updating timetable: ' . $e->getMessage()]);
}

// Close the database connection
$conn->close();
?>
