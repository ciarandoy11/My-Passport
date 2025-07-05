<?php
include __DIR__ . '/db.php';

// Get gameId from URL
if (!isset($_GET['gameId']) || !is_numeric($_GET['gameId'])) {
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <link rel="stylesheet" href="style.css?v=8.002.004">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    </head>
    <body>
        <div class="error-container">
            <h1><i class="fa fa-exclamation-triangle"></i> Invalid Game ID</h1>
            <p>The game ID provided is invalid or missing.</p>
            <a href="javascript:history.back()" class="back-button">Go Back</a>
        </div>
    </body>
    </html>');
}
$gameId = $_GET['gameId'];

// Function to display styled error
function displayError($title, $message) {
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <link rel="stylesheet" href="style.css?v=8.002.004">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
        <style>
            .error-container {
                text-align: center;
                padding: 50px 20px;
                max-width: 600px;
                margin: 50px auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .error-container h1 {
                color: #3241FF;
                margin-bottom: 20px;
            }
            .error-container i {
                color: #ff4444;
                margin-right: 10px;
            }
            .error-container p {
                color: #666;
                margin-bottom: 30px;
                white-space: pre-line;
            }
            .back-button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #3241FF;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s;
            }
            .back-button:hover {
                background-color: #2a35cc;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1><i class="fa fa-exclamation-triangle"></i> ' . htmlspecialchars($title) . '</h1>
            <p>' . $message . '</p>
            <a href="javascript:history.back()" class="back-button">Go Back</a>
        </div>
    </body>
    </html>');
}

// Fetch game data
$sql = "SELECT * FROM aigamesFundraising WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    displayError("Database Error", "Failed to prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $gameId);
if (!$stmt->execute()) {
    displayError("Database Error", "Failed to execute query: " . $stmt->error);
}
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    displayError("Game Not Found", "No game found with ID: " . htmlspecialchars($gameId));
}

$gameData = $result->fetch_assoc();

// Fetch leaderboard data including player name
$sql = "
    SELECT a.userId, MAX(a.player_high_score) as player_high_score, MAX(a.ai_high_score) as ai_high_score, u.donor_name 
    FROM aigames a
    JOIN aiGamesDonations u ON a.userId = u.user_id AND a.game_id = u.fundraising_id
    WHERE a.game_id = ?
    GROUP BY a.userId
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    displayError("Database Error", "Failed to prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $gameId);
if (!$stmt->execute()) {
    displayError("Database Error", "Failed to execute query: " . $stmt->error);
}
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // Check if there are any records in aigames for this game_id
    $checkSql = "SELECT COUNT(*) as count FROM aigames WHERE game_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $gameId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($checkRow['count'] > 0) {
        displayError("No Donations Found", "This game exists but has no donations yet. Please check back later!");
    } else {
        displayError("No Game Records", "No game records found for ID: " . htmlspecialchars($gameId) . "\nNobody has played this game yet!");
    }
}

$conn->close();

$leaderboard = [];

while ($game = $result->fetch_assoc()) {
    $leaderboard[] = [
        'name' => $game['donor_name'],
        'highscore' => ($game['player_high_score'] + $game['ai_high_score']) / 2,
        'player_high_score' => $game['player_high_score'],
        'ai_high_score' => $game['ai_high_score']
    ];
}

// Sort leaderboard by high score (descending)
usort($leaderboard, function ($a, $b) {
    return $b['highscore'] <=> $a['highscore'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?php echo htmlspecialchars($gameData['name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <link rel="stylesheet" href="./style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            text-align: center;
        }
        table {
            margin: auto;
            border-collapse: collapse;
            width: 50%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #3241FF;
            color: white;
        }
        .back-button {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <main>
    <h1>Leaderboard - <?php echo htmlspecialchars($gameData['name']); ?></h1>

    <?php if (!empty($leaderboard)): ?>
        <table>
            <tr>
                <th>Rank</th>
                <th>Name</th>
                <th>Player Highscore</th>
                <th>AI Highscore</th>
            </tr>
            <?php foreach ($leaderboard as $index => $entry): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($entry['name']); ?></td>
                    <td><?php echo htmlspecialchars($entry['player_high_score']); ?></td>
                    <td><?php echo htmlspecialchars($entry['ai_high_score']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No leaderboard data available.</p>
    <?php endif; ?>

    <a href="javascript:history.back()" class="back-button">Back</a>
    </main>
</body>
</html>
