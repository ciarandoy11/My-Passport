<?php
//runGame
include __DIR__ . '/db';

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
                        header("Location: runBingo?gameId={$gameID}");
                        break;
                    case 'Lotto':
                        header("Location: runLotto?gameId={$gameID}");
                        break;
                    case 'Raffle':
                        header("Location: runRaffle?gameId={$gameID}");
                        break;
                    case 'silent_auction':
                        header("Location: runSilent_auction?gameId={$gameID}");
                        break;
                    default:
                        die("Unknown game type.");
                }
            } else {
                die("Game name not found.");
            }
            exit();
?>
