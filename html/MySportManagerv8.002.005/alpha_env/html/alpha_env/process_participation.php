<?php
include __DIR__ . '/db.php';

$fundraisingId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;

if ($fundraisingId <= 0) {
    echo json_encode(["error" => "Invalid game ID"]);
    exit();
}

$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$cards = isset($_GET['cards']) ? (int)$_GET['cards'] : 0;
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

// Ensure the required fields are set and valid
if ($fundraisingId && !empty($name) && ($cards > 0 || $amount > 0)) {
    // Begin a transaction
    $conn->begin_transaction();

    try {
         // Retrieve the game name from the fundraising table
         $stmt = $conn->prepare("SELECT * FROM `fundraising` WHERE `id` = ?");
         $stmt->bind_param("i", $fundraisingId);
         $stmt->execute();
         $result = $stmt->get_result();
         $gameData = $result->fetch_assoc();
         $stmt->close();

         if ($gameData['games'] !== 'JustDonate') {
             $amount += $gameData['prices'] * $cards;
         }

        // Update the fundraising game in the database
        $stmt = $conn->prepare("UPDATE fundraising SET current_amount_raised = current_amount_raised + ?, doners = doners + 1 WHERE id = ?");
        $stmt->bind_param("di", $amount, $fundraisingId);

        if (!$stmt->execute()) {
            throw new Exception("Update Failed: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();

        // Record the donation in the donations table
        $stmt = $conn->prepare("INSERT INTO donations (club, user_id, fundraising_id, amount, donor_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siids", $club, $userId, $fundraisingId, $amount, $name);

        if (!$stmt->execute()) {
            throw new Exception("Insert Failed: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();

        // Commit the transaction
        $conn->commit();

        if ($gameData && isset($gameData['games'])) {
            $gameName = $gameData['games'];
            if ($gameName !== 'JustDonate') {
                // Generate cards based on the number specified
                generate_cards($conn, $cards, $gameName, $club, $userId, $fundraisingId, $name);
            } else {
                // Redirect to a confirmation page for JustDonate
                header("Location: fundraising.php?");
                exit();
            }
        } else {
            die("Game name not found.");
        }
    } catch (Exception $e) {
        // Rollback the transaction in case of failure
        $conn->rollback();
        die("Transaction failed: " . htmlspecialchars($e->getMessage()));
    }
} else {
    die("Invalid amount, name, or missing required fields.");
}

$conn->close();

// Function to generate a Bingo card
function generateBingoCard() {
    $card = [];
    for ($col = 0; $col < 5; $col++) {
        $values = [];
        switch ($col) {
            case 0: $range = range(1, 15); break; // B
            case 1: $range = range(16, 30); break; // I
            case 2: $range = range(31, 45); break; // N
            case 3: $range = range(46, 60); break; // G
            case 4: $range = range(61, 75); break; // O
        }
        shuffle($range);
        $card[$col] = array_slice($range, 0, 5);
        if ($col === 2) { // Center space free
            $card[$col][2] = "FREE";
        }
    }
    return $card; // Return the generated card
}

function generate_cards($conn, $cards, $gameName, $club, $userId, $fundraisingId, $name) {
    // Loop through the number of cards to create
    for ($i = 0; $i < $cards; $i++) {
        // Generate a Bingo card if the game is Bingo
        if ($gameName === 'Bingo') {
            $bingoCard = json_encode(generateBingoCard()); // Generate a new Bingo card
            echo "Generated Bingo Card: " . htmlspecialchars($bingoCard) . "<br />"; // For debugging output

            // Insert the Bingo card into the database
            $stmt = $conn->prepare("INSERT INTO games (user_id, card, club, gameID) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issi", $userId, $bingoCard, $club, $fundraisingId);
                if (!$stmt->execute()) {
                    die("Error inserting Bingo card: " . htmlspecialchars($stmt->error));
                }
                $stmt->close();
            } else {
                die("Error preparing statement: " . htmlspecialchars($conn->error));
            }
			$location = 'bingo.php?gameId=' . $fundraisingId;
        } elseif ($gameName === 'Lotto') {
            $lottoCard = 'blank'; // Generate a new Lotto card

            // Insert the Lotto card into the database
            $stmt = $conn->prepare("INSERT INTO games (user_id, card, club, gameID) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issi", $userId, $lottoCard, $club, $fundraisingId);
                if (!$stmt->execute()) {
                    die("Error inserting Lotto card: " . htmlspecialchars($stmt->error));
                }
                $stmt->close();
            } else {
                die("Error preparing statement: " . htmlspecialchars($conn->error));
            }
            $location = 'lotto.php?gameId=' . $fundraisingId;
        } elseif ($gameName === 'Raffle') {
            // Retrieve the highest card number for the current gameID
            $stmt = $conn->prepare("SELECT MAX(card) as highestCardNumber FROM games WHERE gameID = ?");
            $stmt->bind_param("i", $fundraisingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $highestCardNumber = $row['highestCardNumber'] ?? 0; // Default to 0 if no cards exist
            $stmt->close();

            // Generate a new Raffle card number
            $raffleCard = $highestCardNumber + 1;

            // Insert the Raffle card into the database
            $stmt = $conn->prepare("INSERT INTO games (user_id, card, club, gameID) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $userId, $raffleCard, $club, $fundraisingId);
            if (!$stmt->execute()) {
                die("Error inserting Raffle card: " . htmlspecialchars($stmt->error));
            }
            $stmt->close();

            $location = 'raffle.php?gameId=' . $fundraisingId;
        } elseif ($gameName === 'silent_auction') {
            // Handle Silent Auction card generation (if applicable)
            $location = 'silent_auction.php?gameId=' . $fundraisingId;
            exit();
        } elseif ($gameName === 'JustDonate') {
            // Handle Silent Auction card generation (if applicable)
            $location = 'fundraising.php';
            exit();
        } else {
            die("Unknown game type.");
        }
    }

    // If all cards are processed, you can redirect to a final page
    header("Location: {$location}"); // Adjust this to your confirmation page
    exit();
}
?>
