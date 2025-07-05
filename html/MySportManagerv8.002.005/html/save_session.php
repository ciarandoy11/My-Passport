<?php
session_start(); // Start the session

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "User not logged in"]);
    exit;
}

$userId = $_SESSION['user_id'];

header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$sessions = json_decode($input, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Ensure the timetable table exists
$sql = "CREATE TABLE IF NOT EXISTS timetable (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        _day VARCHAR(255) NOT NULL,
        _time VARCHAR(255) NOT NULL,
        stime TIME,
        etime TIME,
        `_group` VARCHAR(255) NOT NULL,
        _location VARCHAR(255) NOT NULL,
        club VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    )";

if ($conn->query($sql) !== TRUE) {
    echo json_encode(["error" => "Error creating table: " . $conn->error]);
    exit;
}

// Retrieve the club from the users table
$clubSql = "SELECT club FROM users WHERE id = ?";
$stmt = $conn->prepare($clubSql);

if ($stmt === false) {
    echo json_encode(["error" => "Database prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($club);
$stmt->fetch();
$stmt->close();

// Check if the club was retrieved successfully
if (empty($club)) {
    echo json_encode(["error" => "Club not found for the user"]);
    exit;
}

$response = ['status' => 'error', 'message' => 'An unknown error occurred'];

if ($sessions) {
    foreach ($sessions as $session) {
        // Validate required fields
        if (!isset($session['day'], $session['time'], $session['group'], $session['location'], $session['stime'], $session['etime'])) {
            $response['message'] = 'Missing required session fields';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO timetable (_day, _time, `_group`, _location, stime, etime, club) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $response['message'] = 'Database prepare failed: ' . $conn->error;
            echo json_encode($response);
            exit;
        }

        $stmt->bind_param("sssssss", $session['day'], $session['time'], $session['group'], $session['location'], $session['stime'], $session['etime'], $club);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Session saved successfully';
        } else {
            $response['message'] = 'Database insert failed: ' . $stmt->error;
            echo json_encode($response);
            exit;
        }

        $stmt->close();
    }

    echo json_encode($response);
} else {
    echo json_encode(["error" => "No session data provided"]);
}

$conn->close();
?>
