<?php
include __DIR__ . '/db.php';

$fundraisingId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;
if ($fundraisingId <= 0) {
    echo json_encode(["error" => "Invalid game ID"]);
    exit();
}

$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

// Ensure the required fields are set and valid
if ($fundraisingId && !empty($name) && $amount >= 0) {
    // Begin a transaction
    $conn->begin_transaction();

    try {
        // Retrieve the game name from the fundraising table
        $stmt = $conn->prepare("SELECT name FROM aigamesFundraising WHERE id = ?");
        $stmt->bind_param("i", $fundraisingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $gameData = $result->fetch_assoc();
        $stmt->close();

        if (!$gameData) {
            throw new Exception("Game name not found.");
        }

        $gameName = $gameData['name'];

        // Update the fundraising game in the database
        $stmt = $conn->prepare("UPDATE aigamesFundraising SET current_amount_raised = current_amount_raised + ?, doners = doners + 1 WHERE id = ?");
        $stmt->bind_param("di", $amount, $fundraisingId);
        if (!$stmt->execute()) {
            throw new Exception("Update Failed: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();

        // Record the donation in the donations table
        $stmt = $conn->prepare("INSERT INTO aiGamesDonations (club, user_id, fundraising_id, amount, donor_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siids", $club, $userId, $fundraisingId, $amount, $name);
        if (!$stmt->execute()) {
            throw new Exception("Insert Failed: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();

        // Commit the transaction
        $conn->commit();

        // Generate game cards
        generate_cards($conn, $gameName, $club, $userId, $fundraisingId);
    } catch (Exception $e) {
        $conn->rollback();
        die("Transaction failed: " . htmlspecialchars($e->getMessage()));
    }
} else {
    die("Invalid amount, name, or missing required fields.");
}

$conn->close();

function generate_cards($conn, $gameName, $club, $userId, $fundraisingId) {
    $location = "";
    
    if ($gameName === 'TicTacToe') {
        $location = 'ticTacToe.php?gameId=' . $fundraisingId;
    } elseif ($gameName === 'Connect4') {
        $location = 'connect4.php?gameId=' . $fundraisingId;
    } elseif ($gameName === 'SnakeDuel') {
        $location = 'snake_duel.php?gameId=' . $fundraisingId;
    } else {
        die("Unknown game type.");
    }

    $stmt = $conn->prepare("INSERT INTO aigames (userId, club, game_id, ai_high_score, player_high_score) VALUES (?, ?, ?, 0, 0)");
    if ($stmt) {
        $stmt->bind_param("isi", $userId, $club, $fundraisingId);
        if (!$stmt->execute()) {
            die("Error inserting game: " . htmlspecialchars($stmt->error));
        }
        $stmt->close();
    } else {
        die("Error preparing statement: " . htmlspecialchars($conn->error));
    }

    header("Location: $location");
    exit();
}
?>
