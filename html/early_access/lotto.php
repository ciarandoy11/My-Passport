<?php
include __DIR__ . '/db.php';

$gameId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;

// Fetch current game status
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

// Handle form submission for selecting numbers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_numbers'])) {
    $selectedNumbers = array();
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST['number_' . $i])) {
            $selectedNumbers[] = $_POST['number_' . $i];
        }
    }
    $selectedNumbers = implode(',', $selectedNumbers);

    // Update the game with selected numbers
    $updateSql = "UPDATE games SET selectedNumbers = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $selectedNumbers, $_POST['game_id']);
    if (!$updateStmt->execute()) {
        $error = json_encode(array("error" => "Failed to update game: " . $updateStmt->error));
        error_log($error);
        exit();
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?gameId=" . $gameId);
}

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
    if (empty($gameData) || !isset($gameData[0]['selectedNumbers'])) {
        echo json_encode(array("error" => "Game data not found or invalid."));
        exit();
    }
    $selectedNumbers = explode(',', $gameData[0]['selectedNumbers']); // Corrected to access the first element of $gameData

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
    $isWinner = count($matches) >= 2; // Changed to >= 6 to recognize a winner with at least 6 matches
    // Include gameData in the response for debugging purposes
    echo json_encode(array("winner" => $isWinner, 'matches' => $matches, 'gameData' => $gameData));

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

?>

<!DOCTYPE html>
<html>
<head>
    <title>Lotto Game</title>
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
            font-weight: bold; /* Make headers bold */
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Add a subtle shadow for depth */
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

        .progress-bar {
            width: 100%;
            background-color: #007bff;
            border-radius: 5px;
            margin: 10px 0;
            text-align: center;
            height: 20px;
            color: white;
        }

        .progress p {
            margin-left: 10px;
            text-align: center;
        }

        .progress {
            height: 20px;
            background-color: green;
            border-radius: 5px;
        }

        /* Added styles for winning card animation */
        .lotto-winner,
        .lotto-winner th,
        .lotto-winner .selected {
            position: relative;
            background-color: gold;
            color: white;
            overflow: hidden;
            animation: pulse 1.5s infinite;
        }
        .lotto-winner::after {
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
        .lotto-winner-button {
            display: none; /* Hides the element completely */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Lotto Game</h1>
        <div id='game-info'>
            <h2>Game Information</h2>
            <p>Club: <?php echo $fundraisingData['club']; ?></p>
            <p>Goal: <?php echo $fundraisingData['goal']; ?></p>
            <p>Current Amount Raised: <?php echo $fundraisingData['current_amount_raised']; ?></p>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo max(min(($fundraisingData['current_amount_raised'] / $fundraisingData['goal']) * 100, 100), 0); ?>%"><p><?php echo max(min(round(($fundraisingData['current_amount_raised'] / $fundraisingData['goal']) * 100), 100), 0); ?>%</p></div>
            </div>
            <p id='numbers-display'>Winning Numbers: <?php echo $fundraisingData['numbers']; ?></p>
            <p>Last Updated: <?php echo $fundraisingData['last_updated']; ?></p>
        </div>
        <div class="actions">
            <a href="fundraising" class="btn">Back to Dashboard</a>
        </div>
        <h2>Select Your Numbers: </h2>
        <?php foreach ($gameData as $game): ?>
            <div class="card-section">
                <hr>
                <?php if (empty($fundraisingData['numbers'])): ?>
                    <form method="POST" class="number-selection-form">
                        <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                        <div class="number-inputs">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="number" name="number_<?php echo $i; ?>" 
                                       value="<?php echo isset(explode(',', $game['selectedNumbers'])[$i-1]) ? explode(',', $game['selectedNumbers'])[$i-1] : ''; ?>" 
                                       class="number-input" min="1" max="49" required>
                            <?php endfor; ?>
                        </div>
                        <button type="submit" name="select_numbers" class="btn select-btn">
                            Select Numbers for Card <?php echo $game['id']; ?>
                        </button>
                    </form>
                <?php elseif (!empty($fundraisingData['numbers']) && is_null($game['selectedNumbers'])): ?>
                    <p style="color: red; text-align: center;" class="info-message">You are too late to select Your Numbers. The numbers have already been selected by the admin.</p>
                <?php else: ?>
                    <button id='call-lotto-<?php echo $game['id']; ?>' 
                            onclick='findWinner(<?php echo $game['id']; ?>)' 
                            class='btn check-winner-btn'>
                        Check for Winner on Card <?php echo $game['id']; ?>
                    </button>
                <?php endif; ?>
                <div id='<?php echo $game['id']; ?>' class="number-display">
                    <h3>Selected Numbers for Card <?php echo $game['id']; ?>:</h3>
                    <div class="selected-numbers">
                        <?php $numbers = explode(',', $game['selectedNumbers']); ?>
                        <?php foreach ($numbers as $number): ?>
                            <span class="number-input"><?php echo $number; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
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
            body: 'gameId=' + cardId,
        })
        .then(response => response.json())
        .then(data => {
            if (data.winner) {
                alert('Congratulations! You are the winner!');
                const card = document.getElementById(cardId);
                const callButton = document.getElementById('call-lotto-' + cardId); // Use + for concatenation
                console.log(callButton);
                if (card) {
                    card.classList.add('lotto-winner'); // Add the lotto-winner class
                    callButton.style.display = 'none'; // Set the callButton display to none
                }
            } else {
                alert('Sorry, you are not the winner. Better luck next time!');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
    }
function fetchSelectedNumbers() {
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
            numbersDisplay.innerHTML = `<strong>Selected Numbers:</strong> ${data.numbers ? data.numbers : 'None'}`;
            // Check if Lotto was called
            if (data.bingo) {
                const cardIds = data.cardID.split(', '); // Store cardIDs for validation
                cardIds.forEach(cardId => {
                    const card = document.getElementById(cardId); // Corrected to use cardId
                    const callButton = document.getElementById(`call-lotto-${cardId}`); // Corrected selector
                    console.log(`test ${cardId}`);
                    if (card && callButton) {
                        card.classList.add('lotto-winner'); // Add the lotto-winner class
                        callButton.style.display = 'none'; // Set the callButton display to none
                    } else {
                        console.warn(`Card or call button with ID ${cardId} not found.`);
                    }
                });
            }
        })
        .catch(error => console.error('Error fetching selected numbers:', error));
}

// Poll every 5 seconds (5000 ms)
setInterval(fetchSelectedNumbers, 5000);
</script>
</html>
