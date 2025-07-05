<?php
include __DIR__ . '/db.php';

$sql = "
    SELECT g.gameId, f.games
    FROM fundraising f
    JOIN games g ON f.id = g.gameId
    WHERE f.club = ? AND g.user_id = ?
    GROUP BY g.gameId
    ORDER BY g.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $club, $userId);
$stmt->execute();
$fundraisingAndGames = $stmt->get_result();
$stmt->close();

$sql = "
    SELECT ai.game_id, af.name     
    FROM aigamesFundraising af     
    JOIN aigames ai ON af.id = ai.game_id     
    WHERE af.club = ? AND ai.userId = ? 
    GROUP BY ai.game_id   
    ORDER BY ai.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $club, $userId);
$stmt->execute();
$aiGamesData = $stmt->get_result();
$stmt->close();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fundraising Games</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" href="./favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }
        h1, h2 {
            color: #002061;
            margin-bottom: 10px;
        }
        main {
            margin-left: 25%;
            padding: 20px;
        }
        ul {
            padding-left: 20px;
        }
        .fundraising-game {
            background-color: #fff;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .fundraising-game a {
            color: #007BFF;
            text-decoration: none;
        }
        .fundraising-game a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            main {
                margin: auto;
                margin-top: 85%;
            }
        }
    </style>
</head>
<body>
    <main>
        <h1>Welcome, <?php echo $userName; ?>!</h1>
        <h2>Your Club: <?php echo $club; ?></h2>

        <button onclick="window.location.href = 'fundraising';">Back to Fundraising Home</button>

        <h2>Your AI Games:</h2>
        <ul>
            <?php while ($game = $aiGamesData->fetch_assoc()): 
                if ($game['name'] === 'TicTacToe') {
                    $location = 'ticTacToe';
                } elseif ($game['name'] === 'Connect4') {
                    $location = 'connect4';
                } elseif ($game['name'] === 'SnakeDuel') {
                    $location = 'snake_duel';
                }
            ?>
                <li class="fundraising-game">
                    <strong>Game: <?php echo htmlspecialchars($game['name']); ?></strong><br>
                    <a href="<?php echo $location; ?>.php?gameId=<?php echo $game['game_id']; ?>">Play</a>
                    <br>
                    <button onclick="window.location.href = 'leaderboard.php?gameId=<?php echo $game['game_id']; ?>';" style="background-color: #00FF00;">
                        Leaderboard
                    </button>
                </li>
            <?php endwhile; ?>
        </ul>

        <h2>Your Regular Games:</h2>
        <ul>
            <?php while ($game = $fundraisingAndGames->fetch_assoc()): 
                if ($game['games'] === 'Bingo') {
                    $location = 'bingo';
                } elseif ($game['games'] === 'Lotto') {
                    $location = 'lotto';
                } elseif ($game['games'] === 'Raffle') {
                    $location = 'raffle';
                }
            ?>
                <li class="fundraising-game">
                    <strong>Game: <?php echo htmlspecialchars($game['games']); ?></strong><br>
                    <a href="<?php echo $location; ?>.php?gameId=<?php echo $game['gameId']; ?>">Play</a>
                    <br>
                    <button onclick="window.location.href = 'leaderboard.php?gameId=<?php echo $game['gameId']; ?>';" style="background-color: #00FF00;">
                        Leaderboard
                    </button>
                </li>
            <?php endwhile; ?>
        </ul>
    </main>
</body>
</html>
