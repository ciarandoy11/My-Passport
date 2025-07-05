<?php
include __DIR__ . '/db

// Check if gameId is provided and is numeric
if (!isset($_GET['gameId']) || !is_numeric($_GET['gameId'])) {
    header("Location: clubFundraising
    exit();
}

$gameId = $_GET['gameId'];
$userId = $_SESSION['user_id'];

// First, verify that the user has permission to delete this game (belongs to their club)
$sql = "SELECT f.* FROM fundraising f 
        INNER JOIN users u ON f.club = u.club 
        WHERE f.id = ? AND u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $gameId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Either the game doesn't exist or user doesn't have permission
    $_SESSION['error'] = "You don't have permission to delete this game.";
    header("Location: clubFundraising
    exit();
}

// If we get here, user has permission to delete the game
$sql = "DELETE FROM fundraising WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameId);

if ($stmt->execute()) {
    $_SESSION['success'] = "Game successfully deleted.";
} else {
    $_SESSION['error'] = "Error deleting game.";
}

$stmt->close();
$conn->close();

header("Location: clubFundraising
exit();
?>