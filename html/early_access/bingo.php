<?php
include __DIR__ . '/db.php';

$gameID = isset($_GET['gameId']) ? (int)$_GET['gameId'] : '';

// Fetch all cards associated with the user
$sql = "SELECT card, id, selectedNumbers FROM `games` WHERE `user_id` = ? AND gameID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $gameID);
$stmt->execute();
$result = $stmt->get_result();

// Store all retrieved cards in an array
$savedCards = [];
$savedCardsIDs = [];
$savedCardsSelectedNumbers = [];
while ($row = $result->fetch_assoc()) {
    $savedCards[] = json_decode($row['card']); // Decode JSON cards
    $savedCardsIDs[] = $row['id'];
    $savedCardsSelectedNumbers[] = explode(',', $row['selectedNumbers']);
}

$stmt->close();

// Check the action being requested
$action = isset($_POST['action']) ? $_POST['action'] : 'none';

if ($action === 'selectNumber' || $action === 'deselectNumber') {
    $number = trim($_POST['number']);
    $cardID = (int) $_POST['id'];
    updateNumber($conn, $number, $cardID, $action); // Unified function to handle both actions
} elseif ($action === 'call_bingo') {
    $cardID = (int) $_POST['id'];
    checkBingo($conn, $cardID);
} elseif ($action === 'none') {
    // DO NOTHING
} else {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
}

function updateNumber($conn, $number, $cardID, $action) {
    // Fetch all selectedNumbers associated with the game ID
    $sql = "SELECT selectedNumbers FROM `games` WHERE `id` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cardID);
    $stmt->execute();
    $result = $stmt->get_result();

    $numbers = [];
    if ($row = $result->fetch_assoc()) {
        $numbers = explode(',', $row['selectedNumbers']); // Assuming 'selectedNumbers' can be empty
    }

    if ($action === 'selectNumber') {
        // Only add the number if it does not already exist in the array
        if (!in_array($number, $numbers)) {
            $numbers[] = $number; // Add the number
        }
    } elseif ($action === 'deselectNumber') {
        // Remove the number if it exists
        $index = array_search($number, $numbers);
        if ($index !== false) {
            unset($numbers[$index]); // Remove the number
            $numbers = array_values($numbers); // Re-index the array
        }
    }

    // Convert updated array back to a string
    $numbersString = implode(',', $numbers);

    // Update the database with the new number string
    $sql = "UPDATE `games` SET selectedNumbers = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $numbersString, $cardID);

    if ($stmt->execute()) {
        echo "Card updated successfully for ID: {$cardID}. Updated numbers: " . $numbersString;
    } else {
        echo "Error updating the card in the database: " . $stmt->error;
    }

    $stmt->close();
    exit();
}

function checkBingo($conn, $cardID) {
    // Fetch all selected numbers associated with the game ID
    $sql = "SELECT card, selectedNumbers, gameID FROM `games` WHERE `id` = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo "Failed to prepare statement: " . $conn->error;
        return;
    }

    $stmt->bind_param("i", $cardID);
    $stmt->execute();
    $result = $stmt->get_result();

    $numbers = [];
    $selectedNumbers = [];

    if ($row = $result->fetch_assoc()) {
        $gameID = $row['gameID'];

        if (!empty($row['selectedNumbers'])) {
            $selectedNumbers = array_filter(array_map('trim', explode(',', $row['selectedNumbers'])));
        }

        // Decode the card assuming it's stored as JSON format
        $numbers = json_decode($row['card'], true);

        if (!is_array($numbers)) {
            echo 'Card data is not in a valid format.';
            return;
        }
    }

    // Query to fetch the selected bingo numbers
    $sql = "SELECT numbers FROM fundraising WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $gameID);
    $stmt->execute();
    $result = $stmt->get_result();

    $bingoNumbers = [];
    if ($row = $result->fetch_assoc()) {
        $bingoNumbers = explode(',', $row['numbers']);
    } else {
        echo "No bingo numbers found for Game ID: " . $gameID . "\n";
    }

    $stmt->close();

    $trueNumbers = [];
    foreach ($selectedNumbers as $selectedNumber) {
        if (in_array($selectedNumber, $bingoNumbers)) {
            $trueNumbers[] = $selectedNumber; // Append directly to the array
        }
    }

    if (checkForBingo($numbers, $trueNumbers)) {
        $sql = "UPDATE `games` SET bingo = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cardID);

        if ($stmt->execute()) {
            echo "Card updated successfully for ID: {$cardID}.";
        } else {
            echo "Error updating the card in the database: " . $stmt->error;
        }

        $stmt->close();
        echo json_encode(['bingo' => true, 'cardID' => $cardID]);
    } else {
        echo json_encode(['bingo' => false]);
    }

    exit();
}

function checkForBingo($card, $trueNumbers) {
    $neededToWin = 5;

    // Check rows for Bingo
    foreach ($card as $row) {
        $matches = array_intersect($row, $trueNumbers);
        $freeCount = in_array('FREE', $row) ? 1 : 0;
        if (count($matches) + $freeCount >= $neededToWin) {
            return true; // Found Bingo in this row
        }
    }

    // Check columns for Bingo
    for ($col = 0; $col < count($card[0]); $col++) {
        $columnNumbers = array_column($card, $col);
        $matches = array_intersect($columnNumbers, $trueNumbers);
        $freeCount = in_array('FREE', $columnNumbers) ? 1 : 0;

        if (count($matches) + $freeCount >= $neededToWin) {
            return true; // Found Bingo in this column
        }
    }

    // Check diagonals for Bingo
    $diagonal1 = []; // Top-left to bottom-right
    $diagonal2 = []; // Top-right to bottom-left

    for ($i = 0; $i < count($card); $i++) {
        $diagonal1[] = $card[$i][$i];
        $diagonal2[] = $card[$i][count($card) - $i - 1];
    }

    if (count(array_intersect($diagonal1, $trueNumbers)) + (in_array('FREE', $diagonal1) ? 1 : 0) >= $neededToWin) {
        return true; // Found Bingo in diagonal
    }

    if (count(array_intersect($diagonal2, $trueNumbers)) + (in_array('FREE', $diagonal2) ? 1 : 0) >= $neededToWin) {
        return true; // Found Bingo in diagonal
    }

    return false; // No Bingo found
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bingo Game</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #333;
            text-align: center;
        }
        table {
            border-collapse: collapse;
            width: 300px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.2);
            margin: 15px 0;
            background-color: #ffffff;
        }
        th, td {
            border: 1px solid #ccc;
            height: 60px;
            text-align: center;
            font-size: 24px;
            transition: background-color 0.3s;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        td:hover {
            background-color: #e0f7fa;
            cursor: pointer;
        }
        .selected {
            background-color: #2196F3;
            color: white;
        }
        .selected:hover {
            background-color: #1976D2;
        }
        .numbers-display {
            position: sticky;
            top: 5%;
            margin: 20px 0;
            font-size: 20px;
            color: #333;
            z-index: 10;
            border: 2px solid #ccc; /* Add border */
            background-color: #fff; /* Add background color */
        }
        a {
            text-decoration: none;
            color: #2196F3;
            margin: 10px 0;
        }
        a:hover {
            text-decoration: underline;
        }
        button {
            padding: 10px 15px;
            font-size: 16px;
            color: white;
            background-color: #4CAF50;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
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
        .bingo-winner,
        .bingo-winner th,
        .bingo-winner .selected {
            position: relative;
            background-color: gold;
            color: white;
            overflow: hidden;
            animation: pulse 1.5s infinite;
        }
		.bingo-winner::after {
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
		.bingo-winner-button {
			display: none; /* Hides the element completely */
		}
    </style>
    <script>
        async function toggleCell(cell) {
            cell.classList.toggle('selected');
            const value = cell.dataset.value;
            const className = cell.classList.contains('selected');

            const [number, id] = value.split(',');

            let action = className ? 'selectNumber' : 'deselectNumber';

            // Proceed with operation
            const response = await fetch('bingo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    number: number,
                    id: id,
                    action: action
                })
            });

            if (response.ok) {
                location.reload(); // Reload page after the successful update
            } else {
                console.error(`Failed to ${action === 'selectNumber' ? 'select' : 'deselect'} the number.`);
            }
        }

        async function bingo(id) {
            const response = await fetch('bingo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    id: id,
                    action: 'call_bingo'
                })
            });

            if (response.ok) {
                const result = await response.json();

                if (result.bingo) {
					const card = document.getElementById(result.cardID);
					const callButton = document.getElementById('call-bingo-' + result.cardID); // Use + for concatenation
					console.log(callButon);
					if (card) {
						card.classList.add('bingo-winner'); // Add the bingo-winner class
						callButton.classList.add('bingo-winner-button'); // Add the bingo-winner-button class only if it exists
					}
					alert("Bingo! You have 5 in a row.");
				} else {
                    alert("No Bingo yet.");
                }
            } else {
                console.error(`Failed to call bingo.`);
            }
        }

        function fetchSelectedNumbers() {
            const gameId = <?php echo json_encode($gameID); ?>; // Pass gameID from PHP to JS safely
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

                    // Check if Bingo was called
                    if (data.bingo) {
                        const cardIds = data.cardID.split(', '); // Store cardIDs for validation
                        cardIds.forEach(cardId => {
                            const card = document.getElementById(cardId);
							const callButton = document.getElementById('call-bingo-' + cardId);
                            if (card) {
								callButton.classList.add('bingo-winner-button');
                                card.classList.add('bingo-winner'); // Add the bingo-winner class
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
    </script>
</head>
<body onload="fetchSelectedNumbers()">
    <div class='numbers-display'>
        <strong>Selected Numbers:</strong>
    </div>
    <div>
    <div class="actions">
            <a href="fundraising" class="btn">Back to Dashboard</a>
        </div>
        <h2>Your Bingo Cards</h2>
        <button onclick="document.location='participate?fundraising_id=<?php echo htmlspecialchars($gameID); ?>'">Generate a new card</button>

        <?php if (count($savedCards) > 0): ?>
            <?php foreach ($savedCards as $index => $storedCard): ?>
                <?php
                    $cardID = $savedCardsIDs[$index];
                    $selectedNumbers = $savedCardsSelectedNumbers[$index];
                ?>
                <h3>Card <?php echo $index + 1; ?></h3>
                <button id='call-bingo-<?php echo $cardID; ?>' onclick="bingo(<?php echo $cardID; ?>)">Call Bingo</button>
                <table id="<?php echo $cardID; ?>">
                    <thead>
                        <tr>
                            <th>B</th>
                            <th>I</th>
                            <th>N</th>
                            <th>G</th>
                            <th>O</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($row = 0; $row < 5; $row++): ?>
                        <tr>
                            <?php for ($col = 0; $col < 5; $col++): ?>
                                <?php
                                    $class = ''; // Initialize class variable
                                    foreach ($selectedNumbers as $selectedNumber) {
                                        if (($selectedNumber) == ($storedCard[$col][$row]) || $storedCard[$col][$row] == 'FREE') {
                                            $class = 'selected';
                                            break; // No need to check further if class is set
                                        }
                                    }
                                ?>
                                <td onclick="toggleCell(this)" class="<?php echo $class; ?>" data-value="<?php echo htmlspecialchars($storedCard[$col][$row] . ',' . $cardID); ?>">
                                    <?php echo htmlspecialchars($storedCard[$col][$row]); ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No saved cards found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
