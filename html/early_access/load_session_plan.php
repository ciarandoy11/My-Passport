<?php
session_start(); // Start the session

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login"); // Redirect to login if not logged in
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch the session plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $selectedPlan = $data['plan'];

   // Database credentials
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

// Create a new MySQLi connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Ensure userId is available and valid
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    die(json_encode(["error" => "User not logged in or invalid user ID"]));
}

// Fetch user data
$sql = "SELECT username, club, `type-coach` FROM users WHERE id = ?";
 $stmt = $conn->prepare($sql);

  if ($stmt === false) {
   die(json_encode(["error" => "Error preparing the statement: " . $conn->error]));
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        die(json_encode(["error" => "User not found"]));
    } // Extract user data from result

    $userName = $user['username'];
    $club = $user['club'];
    $typeCoach = (int)$user['type-coach']; // Cast to integer
    $stmt->close(); // Close the statement

    // Check if the user is a coach
    if ($typeCoach !== 1) {
    header("Location: login"); // Redirect to login if not logged in
    exit();
    }

    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    // Ensure plan and name are received
    if (!isset($data['name'])) {
        die(json_encode(["error" => "Plan name not posted"]));
    }

    $name = $data['name'];

    // Prepare and bind
    $stmt = $conn->prepare("SELECT * FROM sessionPlans WHERE club = ? AND name = ?");
    $stmt->bind_param("ss", $club, $name);

    // Execute the statement
    $stmt->execute();

    // Get the result
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sessionPlan = $row['plan'];
    }

    // Close the statement
    $stmt->close();

    // Return the timetable
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'plan' => $sessionPlan]);
} else {
    // If the request method is not POST, return an error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
