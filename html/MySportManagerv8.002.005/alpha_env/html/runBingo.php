<?php
// runBingo.php
include __DIR__ . '/db.php';

$gameID = isset($_GET['gameId']) ? (int)$_GET['gameId'] : '';

function getPreviousNumbers($conn, $gameID) {
        // Fetch current numbers from the fundraising table
        $sql = "SELECT numbers FROM fundraising WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $gameID);
        $stmt->execute();
        $result = $stmt->get_result();
        $formattedNumbers = ""; // Initialize formatted numbers

        if ($result->num_rows > 0) {
            // Fetch the fundraising record
            $fundraising = $result->fetch_assoc();
            $currentNumbers = $fundraising['numbers'];
            $numberArray = $currentNumbers ? explode(",", $currentNumbers) : [];

            // Formatting numbers
            foreach ($numberArray as $index => $number) {
                if ($index > 0 && $index % 10 === 0 || $index == 0) {
                    $formattedNumbers .= "<br>"; // Line break after every 10th number
                }
                $formattedNumbers .= $number;

                if ($index < count($numberArray) - 1) {
                    $formattedNumbers .= ","; // Comma between numbers but not after the last one
                }
            }
        }

        return [$currentNumbers, $numberArray, $formattedNumbers];
    }

// Random number generation function
$randomNumber = '';
if (isset($_POST['generate'])) {

    list($currentNumbers, $numberArray, $formattedNumbers) = getPreviousNumbers($conn, $gameID);//dont show on the ui

    // Ensure all numbers are unique
    if (count($numberArray) >= 75) {
        echo "All numbers have already been generated.";
        exit(); // Exit the script if all numbers are generated
    }

    // Generate a unique Bingo number
    do {
        $randomNumber = rand(1, 75);
    } while (in_array($randomNumber, $numberArray));

    // Append the new number to the existing CSV string
    $newNumbers = $currentNumbers ? $currentNumbers . "," . $randomNumber : $randomNumber;

    // Update the fundraising table with the new number string
    $updateSql = "UPDATE fundraising SET numbers = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $newNumbers, $gameID);
    if ($updateStmt->execute()) {
    } else {
        echo "Error updating the numbers in the database: " . htmlspecialchars($updateStmt->error);
    }
    $updateStmt->close();
}


// Query to fetch all winners with their associated donation and user info
$sql = "
    SELECT g.*, d.donor_name, d.amount, u.email, u.phone, u.firstname, u.lastname
    FROM games g
    LEFT JOIN donations d ON g.user_id = d.user_id AND g.gameId = d.fundraising_id AND g.id = d.id
    LEFT JOIN users u ON g.user_id = u.id
    WHERE g.bingo = '1' AND gameID = ?"; // Adjust the condition as per your requirement

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gameID);
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

list($currentNumbers, $numberArray, $formattedNumbers) = getPreviousNumbers($conn, $gameID);//dont show on the ui

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Run Bingo</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
	<style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            background-color: #f4f4f4;
            padding: 20px;
			text-align: center;
        }
        table {
            border-collapse: collapse;
            width: 300px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            background-color: white;
            margin: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            height: 60px;
            text-align: center;
            font-size: 24px;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        td:hover {
            background-color: #f1f1f1;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        .selected {
            background-color: blue;
            color: white;
        }
        .selected:hover {
            background-color: blue;
            color: white;
        }
        .numbers-display {
            margin: 20px 0;
            font-size: 20px;
            color: #333;
        }
		/* Shine Effect CSS */
		.bingo-winner,
		.bingo-winner th,
		.bingo-winner .selected {
			position: relative;
			background-color: gold !important;
			color: white;
			overflow: hidden;
		}

		.bingo-winner::after {
			content: '';
			position: absolute;
			top: -50%;
			left: -50%;
			width: 200%; /* Wider than the element */
			height: 200%; /* Taller than the element */
			background: linear-gradient(45deg, rgba(255,255,255,0.5) 25%, rgba(255,255,255,0) 50%, rgba(255,255,255,0.5) 75%);
			animation: shine 2s ease-in-out infinite;
			transform: rotate(30deg); /* Adjust the angle of the shine */
			opacity: 0.5; /* Adjust opacity for a softer shine */
		}

		@keyframes shine {
			0% {
				transform: translate(-100%, -100%) rotate(30deg);
			}
			100% {
				transform: translate(100%, 100%) rotate(30deg);
			}
		}
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            background-color: #f4f4f4;
            padding: 20px;
        }
        table {
            border-collapse: collapse;
            width: 300px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            background-color: white;
            margin: 10px; /* Spacing between tables */
        }
        th, td {
            border: 1px solid #ddd;
            height: 60px;
            text-align: center;
            font-size: 24px;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        td:hover {
            background-color: #f1f1f1;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        .selected {
            background-color: blue;
            color: white;
        }
        .numbers-display {
            margin: 20px 0;
            font-size: 20px;
            color: #333;
        }
    </style>
</head>
<body>
    <h1>Welcome to Bingo</h1>
    <h2>Hello, Admin: <?php echo htmlspecialchars($userName); ?></h2>
	<a href="clubFundraising.php">Back to Fundraising management page</a> 

    <div class="numbers-display">
    	<p><strong>Previously Generated Numbers:</strong><?php print_r($formattedNumbers); ?></p>
        <p><strong>Generated Bingo Number:</strong>
        <?php
        if ($randomNumber) {
            echo $randomNumber;
        } else {
            echo "Press the button below to generate a number.";
        }
        ?></p>
    </div>

    <!-- Form to generate a random number -->
    <form method="POST">
        <button type="submit" name="generate">Generate Bingo Number</button>
    </form>

	<div class="winner-display">
		<p><strong>Bingo winners:</strong></p><br>
		<?php
			// Check if there are any winners and display the information
			if (!empty($winners)) {
				foreach ($winners as $winner) {
					$card = json_decode($winner['card']);
					echo '<hr>';
					echo 'Game ID: ' . htmlspecialchars($winner['gameID']) . '<br>';
					echo 'Card ID: ' . htmlspecialchars($winner['id']) . '<br>';
					echo 'Winner User ID: ' . htmlspecialchars($winner['user_id']) . '<br>';
					echo "Winner's Name: " . htmlspecialchars($winner['donor_name']) . '<br>';
					echo "User's Name: " . htmlspecialchars($winner['firstname']) . ' ' . htmlspecialchars($winner['lastname']) . '<br>';
					echo 'Donation Amount: ' . htmlspecialchars($winner['amount']) . '<br>';

					// Start the table for the Bingo card
					echo '<table>
						<thead>
							<tr>
								<th>B</th>
								<th>I</th>
								<th>N</th>
								<th>G</th>
								<th>O</th>
							</tr>
						</thead>
						<tbody>';

					// Display the Bingo card
					for ($col = 0; $col < 5; $col++) {
						echo '<tr>';
						for ($row = 0; $row < 5; $row++) {
							$class = ""; // Initialize class variable

							$selectedNumbers = explode(',', $winner['selectedNumbers']);

							// Check if the number is selected
							if (in_array($card[$row][$col], $selectedNumbers) || $card[$row][$col] == 'FREE') {
								$class = "selected";
							}

							echo '<td class="' . $class . '">' . htmlspecialchars($card[$row][$col]) . '</td>';
						}
						echo '</tr>';
					}

					echo '</tbody>
						</table>';
				}
			} else {
				echo 'No winners found.';
			}
		?>
	</div>

</body>
</html>
