<?php
include __DIR__ . '/db.php';

// Fetch existing fundraising entries
$sql = "SELECT * FROM fundraising WHERE club = ? ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $club);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Fetch existing fundraising entries
$sql = "SELECT * FROM aigamesFundraising WHERE club = ? ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $club);
$stmt->execute();
$games = $stmt->get_result();
$stmt->close();

// Handle form submission for adding/editing fundraising
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fundraising_game'])) {
    $games = $_POST['games'];
    $goal = $_POST['goal'];
    $current_amount_raised = $_POST['current_amount_raised'];
    $doners = $_POST['doners'];
    $price = isset($_POST['prices']) && is_numeric($_POST['prices']) ? $_POST['prices'] : '0.00';

    // Add new entry to the database
    $stmt = $conn->prepare("INSERT INTO fundraising (club, games, goal, current_amount_raised, doners, price) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $bind = $stmt->bind_param("sssssd", $club, $games, $goal, $current_amount_raised, $doners, $price);
    if ($bind === false) {
        die("Bind failed: " . $stmt->error);
    }
    $exec = $stmt->execute();
    if ($exec === false) {
        die("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    // Redirect to refresh the page
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle fundraising game update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fundraising'])) {
    $gameId = $_POST['game_id'];
    $goal = $_POST['goal'];
    $price = isset($_POST['price']) ? $_POST['price'] : '0.00';

    // Update query
    $stmt = $conn->prepare("UPDATE aigamesFundraising SET goal = ?, price = ? WHERE id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("dsi", $goal, $price, $gameId);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    // Redirect to refresh the page
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Fundraising</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
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

        .sideNav {
            list-style-type: none;
            margin: 0;
            padding: 0;
            width: 15%;
            background-color: #111; /* Darker for a sleeker look */
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            overflow: auto;
            transition: width 0.3s ease-in-out;
        }

        main {
            flex: 1;
            padding: 20px;
            position: relative; /* Ensure it's positioned */
            left: 0; /* Explicit starting position */
            transition: left 0.3s ease-in-out; /* Smooth transition */
        }

        .sideNav.collapsed ~ main {
            left: 0; /* Moves left when sidebar collapses */
            margin-left: 10%;
        }

        .sideNav.collapsed {
            width: 50px; /* Slightly larger when collapsed for usability */
        }

        .sideNav.collapsed img,
        .sideNav.collapsed button,
        .sideNav.collapsed a {
            display: none;
            pointer-events: none;
        }
        
        /* Toggle button always visible */
        .toggle-btn {
            position: absolute;
            top: 10px;
            right: 0; /* Matches default sidebar width */
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: left 0.3s ease-in-out;
        }

        .sideNav.collapsed .toggle-btn {
            display: block; /* Ensure it's visible */
            pointer-events: auto; /* Allow interaction */
        }

		li a {
			display: block;
			color: #fff;
			padding: 8px 16px;
			text-decoration: none;
			transition: background-color 0.3s; /* Smooth transition on hover */
		}
		li a.active {
			background-color: #3241FF;
			color: white;
		}
		li a:hover:not(.active) {
			background-color: #565656;
			color: white;
		}

        ul {
            padding-left: 20px; /* Indent list items */
        }

        hr {
            margin: 20px 0; /* Space above and below horizontal rules */
        }

        /* Additional styling for fundraising games */
        .fundraising-game {
            background-color: #ffffff; /* White background */
            padding: 15px; /* Padding for game items */
            border: 1px solid #ccc; /* Light gray border */
            border-radius: 5px; /* Rounded corners */
            margin-bottom: 15px; /* Space between game items */
        }

        .fundraising-game a {
            color: #007BFF; /* Link color */
            text-decoration: none; /* Remove underline */
        }

        .fundraising-game a:hover {
            text-decoration: underline; /* Underline on hover */
        }

        .no-games {
            font-style: italic; /* Italics for no games message */
            color: #666; /* Lighter gray */
        }

        /* Adjusted styles for table and buttons */
        table {
            width: 100%; /* Full width for the table */
            border-collapse: collapse; /* Collapse borders for a cleaner look */
        }

        input {
            width: 100%;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        /* Adjusted styles for table and buttons */
        .styled-table {
        border-collapse: collapse;
        font-size: 16px;
        margin: 20px auto; /* Centered and add margin */
		width: auto; /* Full width */
		table-layout: fixed; /* Optional: Ensures the table respects the width set */
    }
    .styled-table thead tr {
        background-color: #3241FF;
        color: #fff;
        text-align: left;
    }
    .styled-table th, .styled-table td {
        padding: 12px 15px;
        border: 1px solid #000;
    }
    .styled-table tbody tr {
        background-color: #f9f9f9;
        color: #565656;
    }
    .styled-table tbody tr:nth-child(even) {
        background-color: #eaeaea;
    }
    .styled-table tbody tr:hover {
        background-color: #ddd;
    }
    .styled-table td {
        color: #000;
    }
    .styled-table th {
        font-weight: bold;
        color: #fff;
        background-color: #3241FF;
    }
    .styled-table tbody tr td:first-child {
        font-weight: bold; /* Bold first column */
    }

    .form-container {
        max-width: 500px;
        margin: 20px auto; /* Center form */
        padding: 15px; /* Internal spacing */
        background-color: white; /* White background for forms */
        border-radius: 8px; /* Rounded corners */
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Subtle shadow */
    }
    label, select, input {
        display: block;
        margin: 10px 0;
        width: 100%; /* Full width */
    }

	td, th {
			white-space: nowrap; /* Prevents text wrapping in table cells */
			padding: 8px; /* Add some padding for aesthetics */
			border: 1px solid #ccc; /* Optional: add border for table cells */
		}
        /* Media query for mobile responsiveness */
@media (max-width: 768px) {
            main {
				margin: auto;
                left: 0;
                top: 100%;
			}
			.sideNav {
				height: auto; /* Allow height to adjust as needed */
				flex-direction: column; /* Stack nav items vertically */
				align-items: stretch; /* Stretch nav items to full width */
				width: 100%; /* Full width for nav items */
				top: 0;
				text-align: center;
                z-index: 9;
			}

        .sideNav.collapsed ~ main {
            top: 0; /* Moves left when sidebar collapses */
            left: 0;
            margin: auto;
        }

        .sideNav.collapsed {
            height: 50px; /* Slightly larger when collapsed for usability */
        }

        /* Center content on smaller screens */
        .styled-table, .form-container {
            margin: 10px auto; /* Center with reduced margin */
            width: 90%; /* Width set to 90% of the viewport */
        }

        .styled-table {
            font-size: 12px; /* Slightly smaller font size for readability */
			width: auto; /* Full width */
			border-collapse: collapse; /* Collapse borders */
			table-layout: fixed; /* Optional: Ensures the table respects the width set */
        }

        li a {
            padding: 10px 15px; /* Increased padding for touch targets */
            text-align: center; /* Center links for better readability */
        }

		.table-wrapper {
            width: auto;
			overflow: auto; /* Allows scrolling */
		}

		td, th {
			white-space: nowrap; /* Prevents text wrapping in table cells */
			padding: 8px; /* Add some padding for aesthetics */
			border: 1px solid #ccc; /* Optional: add border for table cells */
		}
    }

    /* Loading Screen */
    .loading-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #89f7fe, #66a6ff);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.5s ease-out;
    }

    .loading-screen.fade-out {
        opacity: 0;
        pointer-events: none;
    }

    .loading-content {
        text-align: center;
        color: white;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid var(--primary-blue);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</head>
<body onscroll="scrollFunction()">
<!-- Loading Screen -->
<div class="loading-screen">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <h2>Loading...</h2>
    </div>
</div>

<?php include 'includes/admin_navigation.php'; ?>
    <main>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <h1>Fundraising Management</h1>

        <form method="POST" action=""> <!-- Specify action endpoint -->
			<label for="games">Game:</label>
			<select id="games" name="games" onchange="updatePriceLabel()" required>
                <option value="JustDonate">Just Donate</option>
				<option value="Bingo">Bingo</option>
				<option value="Lotto">Lotto</option>
				<option value="Raffle">Raffle</option>
				<!-- Add more games here -->
			</select><br>

			<label for="goal">Goal Amount (in euro):</label>
			<input type="number" name="goal" required><br>

			<label for="current_amount_raised">Current Amount Raised (in euro):</label>
			<input type="number" name="current_amount_raised" required><br>

			<label for="doners">Number of Donations:</label>
			<input type="number" name="doners" required><br>

			<label id="priceLabel" for="prices">Price per card (in euro):</label> <!-- Adjusted label -->
			<input type="number" min="0.50" step="0.50" name='prices' id="prices"><br>

			<button type="submit" name='add_fundraising_game'>Add Fundraising Game</button>
		</form>

		<script>
			function updatePriceLabel() {
				const gameSelect = document.getElementById('games');
				const selectedGame = gameSelect.value; // Capture selected game
				const priceLabel = document.getElementById('priceLabel');
				const priceInput = document.getElementById('prices');

				// Update the price label dynamically
				if (selectedGame === 'JustDonate') {
					priceLabel.style.display = 'none';
					priceInput.style.display = 'none';
					priceInput.removeAttribute('required');
				} else {
					priceLabel.style.display = 'block';
					priceInput.style.display = 'block';
					priceLabel.innerText = `Price per ${selectedGame} card (in euro):`;
					priceInput.setAttribute('required', 'required');
				}
			}

			// Optionally initialize the label on page load
			window.onload = updatePriceLabel;
		</script>

        <h2>Current Fundraising Games:</h2>
        <div class="table-wrapper">
		<table class="styled-table" border="1" cellpadding="10" cellspacing="0">
			<tr>
				<th>Game</th>
				<th>Goal</th>
				<th>Current Amount Raised</th>
				<th>Doners</th>
				<th>Price (per card)</th>
				<th>Delete</th>
				<th>Action</th>
			</tr>
			<?php if ($result->num_rows > 0): ?>
				<?php while ($row = $result->fetch_assoc()): ?>
					<tr>
						<td><?php echo htmlspecialchars($row['games']); ?></td>
						<td><?php echo '€' . htmlspecialchars($row['goal']); ?></td>
						<td><?php echo '€' . htmlspecialchars($row['current_amount_raised']); ?></td>
						<td><?php echo htmlspecialchars($row['doners']); ?></td>
						<td><?php echo $row['games'] === 'JustDonate' ? 'N/A' : '€' . htmlspecialchars($row['price']); ?></td>
						<td>
							<button onclick="if(confirm('Are you sure you want to delete this game?')) { window.location.href = 'deleteGame?gameId=<?php echo htmlspecialchars($row['id']); ?>'; }" style="background-color: #FF0000;">Delete</button>
						</td>
						<?php if ($row['games'] !== 'JustDonate'): ?>
							<td>
								<button onclick="window.location.href = 'runGame?gameId=<?php echo htmlspecialchars($row['id']); ?>';" style="background-color: #00FF00;">
									Run Game
								</button>
							</td>
						<?php endif; ?>
					</tr>
				<?php endwhile; ?>
                <?php endif; ?>
				<?php foreach ($games as $game): ?>
    <tr>
        <!-- Static Display -->
        <td class="game-name"><?php echo htmlspecialchars($game['name']); ?></td>
        <td class="game-goal"><?php echo '€' . htmlspecialchars($game['goal']); ?></td>
        <td class="game-raised"><?php echo '€' . htmlspecialchars($game['current_amount_raised']); ?></td>
        <td class="game-donors"><?php echo htmlspecialchars($game['doners']); ?></td>
        <td class="game-price"><?php echo '€' . htmlspecialchars($game['price']); ?></td>
        <td><button type="button" class="edit-button" style="background-color: rgb(72, 60, 208);">Edit Info</button></td>

            <!-- Edit Form (hidden by default) -->
            <form method="POST" class="edit-form" style="display: none;">
                <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game['id']); ?>">
                <td style="display: none;" class="edit-cell"><label>Goal:</label><input type="number" name="goal" value="<?php echo htmlspecialchars($game['goal']); ?>" required></td>
                <td style="display: none;" class="edit-cell"><label>Price:</label><input type="number" min="1.10" step="0.10" name="price" value="<?php echo htmlspecialchars($game['price']); ?>" required></td>
                <td style="display: none;" class="edit-cell"><button style="background-color: #00FF00;" type="submit" name="update_fundraising">Update</button></td>
            </form>


        <td>
            <button onclick="window.location.href = 'leaderboard?gameId=<?php echo $game['id']; ?>';" style="background-color: #00FF00;">
                Leaderboard
            </button>
        </td>
    </tr>
<?php endforeach; ?>
</table>
</div>
            
            <script>
            document.querySelectorAll('.edit-button').forEach(button => {
    button.addEventListener('click', function() {
        const row = button.closest('tr'); // Get the closest table row
        const editForm = row.querySelector('.edit-form');
        const staticData = row.querySelectorAll('.game-goal, .game-raised, .game-donors, .game-price');
        const editCells = row.querySelectorAll('.edit-cell');

        // Toggle the visibility of static data and form
        if (editForm.style.display === '' || editForm.style.display === 'none') {
            staticData.forEach(el => el.style.display = 'none'); // Hide static data
            editCells.forEach(el => el.style.display = 'table-cell'); // Show edit cells
            editForm.style.display = 'table-cell'; // Show form inputs in their respective cells
            button.innerText = 'Cancel Edit';
            button.style.backgroundColor = 'red';
            button.style.color = 'white';
        } else {
            staticData.forEach(el => el.style.display = 'table-cell'); // Show static data
            editCells.forEach(el => el.style.display = 'none'); // Hide edit cells
            editForm.style.display = 'none'; // Hide form
            button.innerText = 'Edit Info';
            button.style.backgroundColor = 'rgb(72, 60, 208)';
            button.style.color = '';
        }
    });
});

// Cancel button functionality
document.querySelectorAll('.cancel-button').forEach(button => {
    button.addEventListener('click', function() {
        const row = button.closest('tr');
        const editForm = row.querySelector('.edit-form');
        const staticData = row.querySelectorAll('.game-goal, .game-raised, .game-donors, .game-price');
        const editCells = row.querySelectorAll('.edit-cell');

        staticData.forEach(el => el.style.display = 'table-cell'); // Show static data
        editCells.forEach(el => el.style.display = 'none'); // Hide edit cells
        editForm.style.display = 'none'; // Hide form
        row.querySelector('.edit-button').innerText = 'Change Info';
        row.querySelector('.edit-button').style.backgroundColor = 'rgb(72, 60, 208)';
        row.querySelector('.edit-button').style.color = '';
    });
});
            </script>
    </main>

<script>
// Add this at the beginning of your script section
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading screen when page is fully loaded
    const loadingScreen = document.querySelector('.loading-screen');
    if (loadingScreen) {
        loadingScreen.classList.add('fade-out');
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }
});
</script>
</body>
</html>
