<?php
include __DIR__ . '/db.php';

$gameId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;

// Raffle game interface
$sql = "SELECT * FROM games WHERE user_id = ? AND gameID = ?";
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

$sql = "SELECT * FROM fundraising WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameId);
if (!$stmt->execute()) {
    $error = json_encode(array("error" => "Failed to execute query: " . $stmt->error));
    error_log($error);
    exit();
}
$result = $stmt->get_result();
$fundraisingData = $result->fetch_assoc();

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
    $gameData = array();
    while ($row = $result->fetch_assoc()) {
        $gameData[] = $row;
    }
    // Check if $gameData is an array with a single element and if 'selectedNumbers' is set
    if (empty($gameData) || !isset($gameData[0]['card'])) {
        echo json_encode(array("error" => "Game data not found or invalid."));
        exit();
    }
    $selectedNumbers = explode(',', $gameData[0]['card']); // Corrected to access the first element of $gameData

    $sql = "SELECT * FROM fundraising WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $gameData[0]['gameID']); // Corrected to access the first element of $gameData
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
    $isWinner = count($matches) == 1; // Changed to >= 6 to recognize a winner with at least 6 matches
    // Include gameData in the response for debugging purposes
    echo json_encode(array("winner" => $isWinner, 'matches' => $matches, 'gameData' => $gameData));

    if ($isWinner) {
        $sql = "UPDATE `games` SET bingo = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cardId);

        if ($stmt->execute()) {
            //echo "Card updated successfully for ID: {$cardId}.";
        } else {
            echo "Error updating the card in the database: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $sql = "UPDATE `games` SET bingo = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cardId);

        if ($stmt->execute()) {
            //echo "Card updated successfully for ID: {$cardId}.";
        } else {
            echo "Error updating the card in the database: " . $stmt->error;
        }

        $stmt->close();
    }
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Raffle Game</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background-color: #f0f2f5; /* Light background */
            font-family: Arial, sans-serif; /* Modern font family */
            color: #333; /* Darker text for readability */
            margin: 0; /* Remove default margin */
            padding: 0; /* Remove default padding */
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Add a subtle shadow for depth */
        }

        .number-input {
            display: inline-block;
            width: auto;
            height: auto;
            border-radius: 50%;
            background-color: #f0db4f;
            color: #000;
            text-align: center;
            line-height: auto;
            margin: 5px;
            font-weight: bold;
            border: 2px solid #fff; /* Add a border for better visibility */
            padding: 10px; /* Adjust padding to allow for dynamic size based on text */
        }

        .number-display {
            margin: 20px 0;
            text-align: center;
            font-size: 18px; /* Increase font size for better readability */
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
            font-weight: bold; /* Make button text bold */
            transition: background-color 0.3s ease; /* Add transition for a smooth hover effect */
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .raffle-winner,
        .raffle-winner th,
        .raffle-winner .selected {
            position: relative;
            background-color: gold;
            color: white;
            overflow: hidden;
            animation: pulse 1.5s infinite;
        }
        .raffle-winner::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%; /* Wider than the element */
            height: 200%; /* Taller than the element */
            background: linear-gradient(45deg, rgba(255,255,255,0.5) 25%, rgba(255,255,255,0) 50%, rgba(255,255,255,0.5) 75%);
            animation: shine 3s ease-in-out infinite;
            transform: rotate(30deg); /* Adjust the angle of the shine */
            opacity: 0.5; /* Adjust opacity for a softer shine */
        }
        @keyframes pulse {
            0% {
                transform: scale(0.999);
            }
            50% {
                transform: scale(1.055);
            }
            100% {
                transform: scale(1);
            }
        }
        @keyframes shine {
            0% {
                transform: translate(-100%, -100%) rotate(30deg);
            }
            100% {
                transform: translate(100%, 100%) rotate(30deg);
            }
        }
        .raffle-winner-button {
            display: none; /* Hides the element completely */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Raffle Game</h1>
        <div id='game-info'>
            <h2>Game Information</h2>
            <p>Club: <?php echo $fundraisingData['club']; ?></p>
            <p>Goal: <?php echo '€' . $fundraisingData['goal']; ?></p>
            <p>Current Amount Raised: <?php echo '€' . $fundraisingData['current_amount_raised']; ?></p>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo max(min(($fundraisingData['current_amount_raised'] / $fundraisingData['goal']) * 100, 100), 0); ?>%"><p><?php echo max(min(round(($fundraisingData['current_amount_raised'] / $fundraisingData['goal']) * 100), 100), 0); ?>%</p></div>
            </div>
            <div id='numbers-display'>Winning Numbers: <span class="numbers-display"></span></div>
            <p>Last Updated: <?php echo $fundraisingData['last_updated']; ?></p>
        </div>
        <div class="actions">
            <a href="fundraising" class="btn">Back to Dashboard</a>
        </div>
        <h2>Raffle Cards: </h2>
        <?php foreach ($gameData as $game): ?>
            <hr>
            <div id='<?php echo $game['id']; ?>' class="number-display">
                <h3>Raffle Number for Card <?php echo $game['id']; ?>:</h3>
                <?php foreach (explode(',', $game['card']) as $number): ?>
                    <span class="number-input">#<?php echo $number; ?></span>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
<script>
    fetchSelectedNumbers();

    function findWinner(cardId) {
        fetch('raffle.php', {
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
                const card = document.getElementById(cardId);
                if (card) {
                    card.classList.add('raffle-winner'); // Add the raffle-winner class
                }
            } else {
                console.log('Sorry, you are not the winner. Better luck next time!');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
    }

    function fetchSelectedNumbers() {
        <?php foreach ($gameData as $game): ?>
            findWinner(<?php echo $game['id']; ?>);
            console.log('running check winner');
        <?php endforeach; ?>
        const gameId = <?php echo json_encode($gameId); ?>; // Pass gameID from PHP to JS safely
        fetch(`fetch_selected_numbers.php?gameId=${gameId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const numbersDisplay = document.querySelector('.numbers-display');
                if (numbersDisplay) {
                    numbersDisplay.innerHTML = data.numbers ? data.numbers : 'Please Check again later!';
                } else {
                    console.warn('Numbers display not found.');
                }
                
                // Check if Raffle was called
                if (data.bingo) {
                    const cardIds = data.cardID.split(', '); // Store cardIDs for validation
                    cardIds.forEach(cardId => {
                        const card = document.getElementById(cardId); // Corrected to use cardId
                        if (card) {
                            card.classList.add('raffle-winner'); // Add the raffle-winner class
                        } else {
                            console.warn(`Card with ID ${cardId} not found.`);
                        }
                    });
                }
            })
            .catch(error => console.error('Error fetching selected numbers:', error));
    }

    // Poll every 5 seconds (5000 ms)
    setInterval(fetchSelectedNumbers, 5000);

    <?php foreach ($gameData as $game): ?>
        findWinner(<?php echo $game['id']; ?>);
        console.log('running check winner');
    <?php endforeach; ?>
</script>
</html>
