<?php
$loginNeeded = $loginNeeded ?? 'true';
if ($loginNeeded == 'false') {
	// Database connection
	$servername = "localhost";
	$username = "root";
	$password = "test";
	$dbname = "pod_rota";

	$conn = new mysqli($servername, $username, $password, $dbname);

	// Check for connection error
	if ($conn->connect_error) {
    		die("Connection failed: " . htmlspecialchars($conn->connect_error)); // Prevent XSS by escaping
	}
} else {

	session_start(); // Start the session

	// Check if user is logged in
	if (!isset($_SESSION['user_id'])) {
	    header("Location: login"); // Redirect to login if not logged in
	    exit();
	}

	// Get user ID from session
	$userId = $_SESSION['user_id'];

	// Database connection
	$servername = "localhost";
	$username = "root";
	$password = "test";
	$dbname = "pod_rota";

	$conn = new mysqli($servername, $username, $password, $dbname);

	// Check for connection error
	if ($conn->connect_error) {
	    die("Connection failed: " . htmlspecialchars($conn->connect_error)); // Prevent XSS by escaping
	}

	// Fetch user data
	$sql = "SELECT username, club, email, `type-admin`, `type-coach`, subscription_tier, tutorial_progress FROM users WHERE id = ?";
	$stmt = $conn->prepare($sql);
	$stmt->bind_param("i", $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$user = $result->fetch_assoc();

	if (!$user) {
	    die(json_encode(["error" => "User not found"]));
	}

	$userName = htmlspecialchars($user['username']); // HTML escape user data
	$club = htmlspecialchars($user['club']);
	$email = htmlspecialchars($user['email']);
	$typeAdmin = htmlspecialchars($user['type-admin']);
	$typeCoach = htmlspecialchars($user['type-coach']);
	$subscriptionTier = htmlspecialchars($user['subscription_tier']);
	$tutorialProgress = htmlspecialchars($user['tutorial_progress']);
	$stmt->close();

	$url = $_SERVER['REQUEST_URI'];
	$urlParts = parse_url($url);
	$path = $urlParts['path'];
	$directories = explode('/', $path);
	$file = array_pop($directories);
	$subDirectory = array_pop($directories);
	$base = implode('/', $directories);

	if ($subscriptionTier == 'earlyAccess' && $subDirectory != 'early_access') {
		header("Location: /early_access/$file");
		exit();
	} elseif ($subscriptionTier == 'basic' && $subDirectory == 'early_access') {
		header("Location: /$file");
		exit();
	}

	if ($tutorialProgress === 'incomplete') {
		include 'tutorial/main.php';
		$club = $userName;
	}
}
?>
