<?php
include __DIR__ . '/db';

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
    <title>Tic Tac Toe</title>
    <link rel="stylesheet" href="./ticTacToe.css">
</head>
<body>
    <div class="container">
        <h1>Tic Tac Toe</h1>
        <div id='game-info'>
            <h2>Game Information</h2>
            <p>Club: <?php echo $fundraisingData['club']; ?></p>
            <p>Goal: <?php echo $fundraisingData['goal']; ?></p>
            <p>Current Amount Raised: <?php echo $fundraisingData['current_amount_raised']; ?></p>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo max(min(($fundraisingData['current_amount_raised'] / $fundraisingData['goal']) * 100, 100), 0); ?>%"><p><?php echo max(min(round(($fundraisingData['current_amount_raised'] / $fundraisingData['goal']) * 100), 100), 0); ?>%</p></div>
            </div>
        </div>
        <div class="actions">
            <a href="fundraising" class="btn">Back to Dashboard</a>
        </div><br>
        <div>
            <label for="ai-start">AI starts first</label>
            <input type="checkbox" id="ai-start">
        </div>
        <div>
            <label for="difficulty">AI Difficulty:</label>
            <select id="difficulty">
                <option value="1">Easy</option>
                <option value="2">Medium</option>
                <option value="3">Hard</option>
            </select>
        </div>
        <div>
            <p>Player (X) Score: <span id="player-score">0</span></p>
            <p>AI (O) Score: <span id="ai-score">0</span></p>
        </div>
        <div id="game-board" class="game-board">
            <?php for ($i = 0; $i < 9; $i++): ?>
                <div class="cell" data-index="<?php echo $i; ?>"></div>
            <?php endfor; ?>
        </div>
        <button id="reset">Start</button>
    </div>
    <script src="./ticTacToe.js"></script>
    <script>
        var id = <?php echo htmlspecialchars($gameId); ?>; // Game ID from PHP
        var playerHighScore = <?php echo intval($gameData[0]['player_high_score']); ?>; // Fetch current high score from the database
        var aiHighScore = <?php echo intval($gameData[0]['ai_high_score']); ?>; // Fetch current high score from the database


        // Function to check if the player score exceeds the high score
        function checkAndUpdateHighScore() {
            if (playerScore > playerHighScore) {
                console.log('Updated player high score');

                fetch('ticTacToe', {
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

                fetch('ticTacToe', {
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