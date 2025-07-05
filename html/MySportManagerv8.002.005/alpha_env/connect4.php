<?php
include __DIR__ . '/db.php';

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
        include __DIR__ . '/db.php';

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
    <title>Connect 4 Game</title>
    <link rel="stylesheet" href="connect4_game.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
    <h1>Connect 4 Game</h1>
    <div>
        <button class='back-btn' onclick="window.location.href = 'fundraising.php';">Back to Fundraising Home</button><br>
        <label for="difficulty">AI Difficulty:</label>
        <select id="difficulty">
            <option value="1">Easy</option>
            <option value="2">Medium</option>
            <option value="3">Hard</option>
        </select>
    </div>
    <div id="game"></div>
    <div>
        <p id="player" style="color: red">Player (X) Score: <span id="player-score">0</span></p>
        <p id="ai" style="color: blue;">AI (O) Score: <span id="ai-score">0</span></p>
    </div>
    <button id="reset">Reset Game</button>
    <script src="connect4_with_ai.js"></script>
    <script>
        var id = <?php echo htmlspecialchars($gameId); ?>; // Game ID from PHP
        var playerHighScore = <?php echo intval($gameData[0]['player_high_score']); ?>; // Fetch current high score from the database
        var aiHighScore = <?php echo intval($gameData[0]['ai_high_score']); ?>; // Fetch current high score from the database


        // Function to check if the player score exceeds the high score
        function checkAndUpdateHighScore() {
            if (playerScore > playerHighScore) {
                console.log('Updated player high score');

                fetch('connect4.php', {
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

                fetch('connect4.php', {
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
