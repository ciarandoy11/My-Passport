<?php
// fetch_selected_numbers.php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "User not logged in"]);
    exit();
}

$userId = $_SESSION['user_id'];
$gameID = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;

if ($gameID <= 0) {
    echo json_encode(["error" => "Invalid game ID"]);
    exit();
}

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "test"; // Ensure this is your correct password
$dbname = "pod_rota"; // Database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check for Bingo
function checkForBingo($conn, $gameID, $userID) {
    $sql = "SELECT id FROM games WHERE gameID = ? AND user_id = ? AND bingo = '1'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $gameID, $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    $bingoFound = false; // Initialize a variable to track if Bingo is found
	$cardID = null; // Initialize cardID

	while ($row = $result->fetch_assoc()) {
		$cardID .= ', ' . $row['id']; // Get the card ID from the row
		$bingoFound = true; // Set Bingo found flag

		// Optionally, you can add more logic here to directly check the criteria for Bingo
		// For example, checking specific columns or conditions for Bingo
	}

	if ($bingoFound) {
		return ['bingo' => true, 'cardID' => $cardID]; // Return true with cardID
	} else {
		return ['bingo' => false]; // Return false if no Bingo
	}

    $stmt->close();
}

// Retrieve selected numbers and last updated timestamp
$sql = "SELECT last_updated, numbers FROM fundraising WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameID);
$stmt->execute();
$result = $stmt->get_result();

$response = [];
if ($row = $result->fetch_assoc()) {
    $response['numbers'] = explode(',', $row['numbers']);
    $response['last_updated'] = $row['last_updated'];

    // Format the numbers
    $formattedNumbers = "";
    foreach ($response['numbers'] as $index => $number) {
        if ($index == 0) {
            $formattedNumbers .= "<br>";
        }
        $formattedNumbers .= $number;
        if (in_array($index, [9, 19, 29, 39, 49, 59, 69])) {
            $formattedNumbers .= "<br>"; // Add a newline after every 10th number
        } else {
            $formattedNumbers .= ","; // Add a comma otherwise
        }
    }

    // Remove the trailing comma or newline
    $response['numbers'] = rtrim($formattedNumbers, ",<br>");
} else {
    $response['error'] = "No data found for this game.";
}

// Check for Bingo status and merge it into the response
$bingoResult = checkForBingo($conn, $gameID, $userId);
$response = array_merge($response, $bingoResult);

// Close the statement and connection
$stmt->close();
$conn->close();

// Return the JSON response
echo json_encode($response);

?>
