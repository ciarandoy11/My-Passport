<?php
include __DIR__ . '/db.php';

$gameId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;

// Verify admin status and game ownership
$sql = "SELECT id, club, games, goal, current_amount_raised, doners, price, numbers, last_updated 
        FROM fundraising 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameId);
if (!$stmt->execute()) {
    error_log("Failed to execute query: " . $stmt->error);
    header("HTTP/1.1 500 Internal Server Error");
    exit();
}
$result = $stmt->get_result();
$gameData = $result->fetch_assoc();

if (!$gameData) {
    error_log("Unauthorized access or game not found");
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Handle form submission for drawing numbers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw_numbers'])) {
    // Generate 6 random unique numbers between 1 and 49
    $numbers = array();
    while (count($numbers) < 6) {
        $num = rand(1, 49);
        if (!in_array($num, $numbers)) {
            $numbers[] = $num;
        }
    }
    sort($numbers);
    $drawnNumbers = implode(',', $numbers);
    
    // Update the game with drawn numbers
    $updateSql = "UPDATE fundraising SET numbers = ?, last_updated = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $drawnNumbers, $gameId);
    
    // Add error handling for the update
    if (!$updateStmt->execute()) {
        error_log("Failed to update game: " . $updateStmt->error);
        header("HTTP/1.1 500 Internal Server Error");
        exit();
    }
    
    // Redirect with success parameter
    header("Location: ".$_SERVER['PHP_SELF']."?gameId=".$gameId);
    exit();
}

// Fetch current game status
$drawnNumbers = $gameData['numbers'] ?? null;

// Query to fetch all winners with their associated donation and user info
$sql = "
    SELECT g.*, u.email, u.phone, u.firstname, u.lastname
    FROM games g
    LEFT JOIN users u ON g.user_id = u.id
    WHERE g.bingo = 1 AND g.gameID = ? 
    ORDER BY g.matches DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameId);
$stmt->execute();
$result = $stmt->get_result();

// Initialize an array to store winners
$winners = [];

// Fetch all winners data
while ($row = $result->fetch_assoc()) {
    $winners[] = $row; // Collecting all winners into the $winners array
}

// Close the statement
$stmt->close();

// Raffle game interface
$sql = "SELECT * FROM games WHERE gameID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameId);
if (!$stmt->execute()) {
    $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
    error_log($error);
    exit();
}
$result = $stmt->get_result();
$cardData = array();
while ($row = $result->fetch_assoc()) {
    $cardData[] = $row;
}
$stmt->close();

// Check if the user is a winner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gameId'])) {
    $cardId = $_POST['gameId'];
    // Fetch current game status
    $sql = "SELECT * FROM games WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cardId);
    if (!$stmt->execute()) {
        $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
        error_log($error);
        exit();
    }
    $result = $stmt->get_result();
    $cardData = array();
    while ($row = $result->fetch_assoc()) {
        $cardData[] = $row;
    }
    // Check if $cardData is an array with a single element and if 'selectedNumbers' is set
    if (empty($cardData) || !isset($cardData[0]['card'])) {
        echo json_encode(array("error" => "Game data not found or invalid."));
        exit();
    }
    $selectedNumbers = explode(',', $cardData[0]['card']); // Corrected to access the first element of $cardData

    $sql = "SELECT * FROM fundraising WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cardData[0]['gameID']); // Corrected to access the first element of $cardData
    if (!$stmt->execute()) {
        $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
        error_log($error);
        exit();
    }
    $result = $stmt->get_result();
    $fundraisingData = $result->fetch_assoc();

    // Check if $fundraisingData is a single element array and if 'numbers' is set
    if (empty($fundraisingData) || !isset($fundraisingData['numbers'])) {
        echo json_encode(array("error" => "Fundraising data not found or invalid."));
        exit();
    }
    $winningNumbers = explode(',', $fundraisingData['numbers']); // Corrected to access the first element of $fundraisingData
    $matches = array_intersect($selectedNumbers, $winningNumbers);
    $isWinner = count($matches) >= 2; // Changed to >= 6 to recognize a winner with at least 6 matches
    // Include cardData in the response for debugging purposes
    echo json_encode(array("winner" => $isWinner, 'matches' => $matches, 'cardData' => $cardData));

    if ($isWinner) {
        $sql = "UPDATE `games` SET bingo = 1, matches = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", count($matches), $cardId);

        if ($stmt->execute()) {
            //echo "Card updated successfully for ID: {$cardId}.";
        } else {
            echo "Error updating the card in the database: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $sql = "UPDATE `games` SET bingo = 0, matches = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", count($matches), $cardId);

        if ($stmt->execute()) {
            //echo "Card updated successfully for ID: {$cardId}.";
        } else {
            echo "Error updating the card in the database: " . $stmt->error;
        }

        $stmt->close();
    }
    exit();
}

try {
    $sql = "SELECT card FROM games WHERE gameID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $gameId);
    $stmt->execute();
    $result = $stmt->get_result();
    $cards = [];
    while ($row = $result->fetch_assoc()) {
        $cards[] = $row['card'];
    }
    error_log("Cards fetched");
} catch (Exception $e) {
    $error = json_encode(array("error" => "Failed to execute query: " . $e->getMessage()));
    error_log($error);
    header("HTTP/1.1 500 Internal Server Error");
    exit();
} finally {
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Run Lotto Game</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background-color: #f0f2f5; /* Light background */
            font-family: Arial, sans-serif; /* Modern font family */
            color: #333; /* Darker text for readability */
            margin: 0; /* Remove default margin */
            padding: 0; /* Remove default padding */
        }

        h1, h2 {
            color: #002061; /* Primary color for headers */
            margin-bottom: 10px; /* Space below headers */
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .lotto-ball {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f0db4f;
            color: #000;
            text-align: center;
            line-height: 40px;
            margin: 5px;
            font-weight: bold;
        }

        .number-display {
            margin: 20px 0;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .number-input {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f0db4f;
            color: #000;
            text-align: center;
            line-height: 40px;
            margin: 5px;
            font-weight: bold;
            border: 2px solid #fff; /* Add a border for better visibility */
        }

        .selectedNumber-input {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: green;
            color: #000;
            text-align: center;
            line-height: 40px;
            margin: 5px;
            font-weight: bold;
            border: 2px solid #fff; /* Add a border for better visibility */
        }

        .number-display {
            margin: 20px 0;
            text-align: center;
            font-size: 18px; /* Increase font size for better readability */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Lotto Game Administration - new</h1>
        <h2>Game ID: <?php echo $gameId; ?></h2>
        <h2>Club: <?php echo htmlspecialchars($gameData['club']); ?></h2>
        <h2>Amount of Lotto Cards in Play: <strong><?php echo count($cards); ?></strong></h2>
        
        <?php if ($drawnNumbers === null): ?>
            <form method="POST" onsubmit="return confirm('Are you sure you want to draw the numbers? This cannot be undone.');">
                <button type="submit" name="draw_numbers" class="btn btn-primary">Draw Numbers</button>
            </form>
        <?php else: ?>
            <div class="results">
                <h3>Drawn Numbers:</h3>
                <div class="number-display">
                    <?php 
                    $numbers = explode(',', $drawnNumbers);
                    foreach ($numbers as $number) {
                        echo "<span class='lotto-ball'>$number</span>";
                    }
                    ?>
                </div>
                <p>Last Updated: <?php echo $gameData['last_updated']; ?></p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to draw the numbers AGAIN? This CANNOT be undone.');">
                    <button type="submit" name="draw_numbers" class="btn btn-primary">Draw Numbers Again?</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="clubFundraising" class="btn">Back to Dashboard</a>
        </div>
        <div class="winner-display">
		<p><strong>Lotto winners:</strong></p>
        <button onclick="location.reload();" class="btn btn-secondary">Update Winners</button>
        <br>
		<?php
			// Check if there are any winners and display the information
			if (!empty($winners)) {
                $drawnNumbers = explode(',', $drawnNumbers);
				foreach ($winners as $index => $winner) {
					$card = json_decode($winner['card']);
					echo '<hr>';
					echo 'Place: ' . htmlspecialchars('#' . ($index + 1)) . '<br>';
					echo 'Card ID: ' . htmlspecialchars($winner['id']) . '<br>';
                    echo 'Matches: ' . htmlspecialchars($winner['matches']) . '<br>';
					echo 'Winner User ID: ' . htmlspecialchars($winner['user_id']) . '<br>';
					echo "User's Name: " . htmlspecialchars($winner['firstname']) . ' ' . htmlspecialchars($winner['lastname']) . '<br>';
                    echo "<div id='" . htmlspecialchars($winner['id']) . "' class='number-display'>
                        <h3>Selected Numbers for Card " . htmlspecialchars($winner['id']) . ":</h3>";
                    $numbers = explode(',', $winner['selectedNumbers']);
                    foreach ($numbers as $number) {
                          $selectedClass = in_array($number, $drawnNumbers) ? 'selectedNumber-input' : 'number-input';
                          echo "<span class='$selectedClass'>" . htmlspecialchars($number) . "</span>";
                    }
                    echo "</div>";
                }
			} else {
				echo 'No winners found.';
			}
		?>
	</div>
    </div>
</body>
<script>
    fetchSelectedNumbers();

    function findWinner(cardId) {
        fetch('lotto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `gameId=${cardId}`,
        })
        .then(response => response.json())
        .then(data => {
            if (data.winner) {
                console.log('Congratulations! You are the winner!');
            } else {
                console.log('Sorry, you are not the winner. Better luck next time!');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
    }
    // Poll every 5 seconds (5000 ms)
    setInterval(fetchSelectedNumbers, 10000);

    function fetchSelectedNumbers() {
        <?php foreach ($cardData as $game): ?>
            findWinner(<?php echo $game['id']; ?>);
            console.log('running check winner');
        <?php endforeach; ?>
    }
</script>
</html>