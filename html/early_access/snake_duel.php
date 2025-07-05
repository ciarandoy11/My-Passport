<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    $error = json_encode(array("error" => "Connection failed: " . $conn->connect_error));
    error_log($error);
    exit();
}

$userId = $_SESSION['user_id'];
$gameId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;

// Fetch current game status
$sql = "SELECT * FROM aigames WHERE userId = ? AND game_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $gameId);
if (!$stmt->execute()) {
    $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
    error_log($error);
    exit();
}
$result = $stmt->get_result();
$gameData = array();
while ($row = $result->fetch_assoc()) {
    $gameData[] = $row;
}

$sql = "SELECT * FROM aigamesFundraising WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameId);
if (!$stmt->execute()) {
    $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
    error_log($error);
    exit();
}
$result = $stmt->get_result();
$fundraisingData = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_high_score') {
    $gameId = isset($_POST['gameId']) ? (int)$_POST['gameId'] : 0;
    $playerHighScore = isset($_POST['player_high_score']) ? (int)$_POST['player_high_score'] : 0;
    $aiHighScore = isset($_POST['ai_high_score']) ? (int)$_POST['ai_high_score'] : 0;

    if ($gameId > 0) {
        // Database connection
        $servername = "localhost";
        $username = "root";
        $password = "test";
        $dbname = "pod_rota";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            $error = json_encode(array("error" => "Connection failed: " . $conn->connect_error));
            error_log($error);
            exit();
        }

        $stmt = $conn->prepare("UPDATE aigames SET player_high_score = ?, ai_high_score = ? WHERE game_id = ?");
        $stmt->bind_param("iii", $playerHighScore, $aiHighScore, $gameId);

        if ($stmt->execute()) {
            echo "High score updated successfully.";
        } else {
            echo "Error updating high score: " . htmlspecialchars($stmt->error);
        }

        $stmt->close();

        $gameData = '';

        // Fetch current game status
        $sql = "SELECT * FROM aigames WHERE userId = ? AND game_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $gameId);
        if (!$stmt->execute()) {
            $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
            error_log($error);
            exit();
        }
        $result = $stmt->get_result();
        $gameData = array();
        while ($row = $result->fetch_assoc()) {
            $gameData[] = $row;
        }

        $conn->close();
    } else {
        echo "Invalid game ID.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snake Duel</title>
    <link rel="stylesheet" href="snake_duel.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            padding: 0;
        }
        #game-container {
            margin: 20px 0;
        }
        #scores, #rules, #resetButton {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Snake Duel</h1>
    <div id="game-container">
        <canvas id="gameCanvas" width="600" height="400"></canvas>
        <div id="rules">
        <br><button class='back-btn' onclick="window.location.href = 'fundraising';">Back to Fundraising Home</button><br>
            <h2>Rules:</h2>
            <ul>
                <li>Control <span style="color: green;">Snake 1</span> with arrow keys or WASD</li>
                <li><span style="color: blue;">Snake 2</span> is controlled by Ai</li>
                <li>Collect <span style="color: red;">food</span> to grow your snake</li>
                <li>Avoid colliding with walls or the other snake</li>
            </ul>
            <div id="scores">
                <h2>Scores:</h2>
                <p style="color: green;">Player 1: <span id="score1">0</span></p>
                <p style="color: blue;">Player 2: <span id="score2">0</span></p>
            </div>
            <button id="resetButton">Reset Game</button>
        </div>
    </div>
    <script src="snake_duel.js"></script>
    <script>
        var id = <?php echo htmlspecialchars($gameId); ?>; // Game ID from PHP
        var playerHighScore = <?php echo intval($gameData[0]['player_high_score']); ?>; // Fetch current high score from the database
        var aiHighScore = <?php echo intval($gameData[0]['ai_high_score']); ?>; // Fetch current high score from the database


        // Function to check if the player score exceeds the high score
        function checkAndUpdateHighScore() {
            if (playerScore > playerHighScore) {
                console.log('Updated player high score');

                fetch('snake_duel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        'action': 'update_high_score',
                        'player_high_score': playerScore,
                        'ai_high_score': aiHighScore,
                        'gameId': id // Ensure the correct game is updated
                    })
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data); // Debugging response
                    playerHighScore = playerScore; // Update the local high score after success
                })
                .catch(error => console.error('Error:', error));
            }

            if (aiScore > aiHighScore) {
                console.log('Updated ai high score');

                fetch('snake_duel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        'action': 'update_high_score',
                        'ai_high_score': aiScore,
                        'player_high_score': playerHighScore,
                        'gameId': id // Ensure the correct game is updated
                    })
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data); // Debugging response
                    playerHighScore = playerScore; // Update the local high score after success
                })
                .catch(error => console.error('Error:', error));
            }
        }

        // Set up an interval to check for high score update every 5 seconds
        setInterval(checkAndUpdateHighScore, 5000);
    </script>
</body>
</html>
