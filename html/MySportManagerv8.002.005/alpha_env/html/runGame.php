<?php
//runGame.php
include __DIR__ . '/db.php';

$gameID = isset($_GET['gameId']) ? (int)$_GET['gameId'] : '';

// Retrieve the game name from the fundraising table
            $sql = "SELECT `games` FROM `fundraising` WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $gameID);
            $stmt->execute();
            $result = $stmt->get_result();
            $gameData = $result->fetch_assoc();
            $stmt->close();

            // Check if the game name was fetched successfully
            if ($gameData && isset($gameData['games'])) {
                $gameName = $gameData['games'];  // Use the game name properly

                // Redirect to the corresponding game page
                switch ($gameName) {
                    case 'Bingo':
                        header("Location: runBingo.php?gameId={$gameID}");
                        break;
                    case 'Lotto':
                        header("Location: runLotto.php?gameId={$gameID}");
                        break;
                    case 'Raffle':
                        header("Location: runRaffle.php?gameId={$gameID}");
                        break;
                    case 'silent_auction':
                        header("Location: runSilent_auction.php?gameId={$gameID}");
                        break;
                    default:
                        die("Unknown game type.");
                }
            } else {
                die("Game name not found.");
            }
            exit();
?>
