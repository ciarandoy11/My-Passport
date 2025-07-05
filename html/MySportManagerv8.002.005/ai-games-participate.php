<?php
include __DIR__ . '/db.php';

// Ensure fundraising_id is available and valid
if (!isset($_GET['fundraising_id']) || !is_numeric($_GET['fundraising_id'])) {
    die(json_encode(["error" => "Invalid fundraising game ID"]));
}

$fundraisingId = intval($_GET['fundraising_id']);

// Fetch fundraising game details
$sql = "SELECT * FROM aigamesFundraising WHERE id = ? AND club = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $fundraisingId, $club);
$stmt->execute();
$result = $stmt->get_result();
$fundraisingGame = $result->fetch_assoc();

if (!$fundraisingGame) {
    die(json_encode(["error" => "Fundraising game not found"]));
}

$stmt->close();

$sql = "
    SELECT ai.game_id, af.name     
    FROM aigamesFundraising af     
    JOIN aigames ai ON af.id = ai.game_id     
    WHERE af.club = ? AND ai.userId = ? AND af.id = ?
    GROUP BY ai.game_id   
    ORDER BY ai.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $club, $userId, $fundraisingId);
$stmt->execute();
$gameData = $stmt->get_result();
$stmt->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Participate in Fundraising</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        body {
            background-color: #f0f2f5; /* Light background */
            font-family: Arial, sans-serif; /* Better fallback font */
            color: #333; /* Darker text for readability */
            margin: 0; /* Remove default margin */
            padding: 0; /* Remove default padding */
            display: flex; /* Use flexbox for centering */
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically if screen height is larger */
            height: 100vh; /* Full height of the viewport */
        }
        main {
            max-width: 800px; /* Limit width for better readability */
            width: 100%; /* Responsive width */
            background: #ffffff; /* White background for container */
            padding: 20px; /* Padding inside the main container */
            border-radius: 8px; /* Rounded corners */
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* Subtle shadow */
            text-align: center; /* Center text within main */
			margin-left: 10%;
        }
        h1, h2 {
            color: #002061; /* Primary color for headers */
            font-weight: 600; /* Slightly bolder text for headers */
            margin-top: 0; /* Remove default margin for h1 */
        }
        p {
            margin: 10px 0; /* Consistent margin for paragraphs */
        }
        strong {
            color: #333; /* Ensure strong text is darker */
        }
        form {
            margin-top: 20px;
            text-align: left; /* Align form text to the left */
        }
        label {
            display: block; /* Block-level labels for better alignment */
            margin: 10px 0 5px; /* Space out label from input */
            font-weight: 500; /* Slightly bolder labels */
        }
        input[type="number"],
        input[type="text"] {
            width: calc(100% - 20px); /* Full-width input with padding */
            padding: 10px; /* Padding for inputs */
            margin-bottom: 15px; /* Space out inputs */
            border: 1px solid #ccc; /* Light border */
            border-radius: 4px; /* Rounded corners */
            font-size: 16px; /* Increased font size for readability */
        }
        button {
            background-color: #002061; /* Primary theme color */
            color: white; /* White text */
            border: none; /* Remove default border */
            padding: 10px 15px; /* Padding for button */
            border-radius: 4px; /* Rounded corners */
            cursor: pointer; /* Cursor changes to pointer */
            font-size: 16px; /* Consistent font size */
            width: 100%; /* Full-width button */
        }
        button:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }
        a {
            color: #007bff; /* Link color */
        }
        a:hover {
            text-decoration: underline; /* Underline links on hover */
        }
		/* Responsive styles */
		@media (max-width: 768px) {
			main {
				margin: auto;
			}
		}
    </style>
</head>
<body>
    <main>
        <h1>Participate in Fundraising Game</h1>
        <h2>Game: <?php echo htmlspecialchars($fundraisingGame['name']); ?></h2>
        <p><strong>Club:</strong> <?php echo htmlspecialchars($fundraisingGame['club']); ?></p>
        <p><strong>Goal:</strong> €<?php echo htmlspecialchars(number_format($fundraisingGame['goal'], 2)); ?></p>
        <p><strong>Current Amount Raised:</strong> €<?php echo htmlspecialchars(number_format($fundraisingGame['current_amount_raised'], 2)); ?></p>
        <p><strong>Number of Donations:</strong> <?php echo htmlspecialchars($fundraisingGame['doners']); ?></p>

        <h2>Join the Game</h2>
        <?php
        if ($gameData->num_rows == 0): // If there are no rows in the result, show the donation form
        ?>
            <form method="POST" action="ai-games-donation_checkout.php?gameId=<?php echo $fundraisingId; ?>">
                <input type="hidden" name="fundraising_id" value="<?php echo $fundraisingId; ?>">
                <label for="amount">Optional Extra Donation Amount (in Euro):</label>
                <input type="number" name="amount" min="1" step='.5'>
                <label for="name">Your Name:</label>
                <input type="text" name="name" required>
                <button type="submit">Participate</button>
            </form>
        <?php
        else: // If there are rows, display the "Play" button
            while ($game = $gameData->fetch_assoc()):
                // Set location based on the game name
                if ($game['name'] === 'TicTacToe') {
                    $location = 'ticTacToe';
                } elseif ($game['name'] === 'Connect4') {
                    $location = 'connect4';
                } elseif ($game['name'] === 'SnakeDuel') {
                    $location = 'snake_duel';
                }
        ?>
                <!-- Buttons for playing or viewing leaderboard -->
                <button style="background-color: #00FF00;" onclick="window.location.href = '<?php echo $location; ?>.php?gameId=<?php echo $game['game_id']; ?>';">Play</button>
                <br>
                <button onclick="window.location.href = 'leaderboard.php?gameId=<?php echo $game['game_id']; ?>';">
                    Leaderboard
                </button>
        <?php
            endwhile;
        endif;
        ?>

        <p><a href="fundraising.php">Back to Fundraising Games</a></p>
    </main>
</body>
</html>
