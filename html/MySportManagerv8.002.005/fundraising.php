<?php
include __DIR__ . '/db

// Fetch fundraising games associated with the user's club
$sql = "SELECT * FROM fundraising WHERE club = ? ORDER BY last_updated DESC";
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Fundraising - Swimming Club Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
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
            left: 25%; /* Explicit starting position */
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
		/* Responsive styles */
		@media (max-width: 768px) {
			main {
				margin: auto;
				margin-top: 85%;
                left: 0;
                top: 0;
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
            margin-top: 10%;
        }

        .sideNav.collapsed {
            height: 50px; /* Slightly larger when collapsed for usability */
        }

			.sideNav li {
				width: 100%; /* Full width for nav items */
			}

			img {
				height: 100px;
			}
			li a {
				padding: 10px; /* Adjust padding for mobile */
				font-size: 14px; /* Smaller font size for mobile links */
			}
		}
    </style>
</head>
<body>
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

    // Scroll reveal effect
    const sections = document.querySelectorAll('section');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            } else {
                entry.target.classList.remove('active');
            }
        });
    }, { threshold: 0.2 });

    sections.forEach(section => {
        observer.observe(section);
    });
});
</script>
<?php include 'includes/navigation>
<main>
    <h1>Welcome, <?php echo $userName; ?>!</h1>
    <h2>Your Club: <?php echo $club; ?></h2>

    <button onclick="window.location.href = 'collectionYour Game Collection</button><br>

    <h2>Available Fundraising Games:</h2>
    <ul>
    <?php foreach ($games as $game): 
                $lastUpdatedStr = $game['last_updated'];

                // Check if the date string is set
                if (isset($lastUpdatedStr) && !empty($lastUpdatedStr)) {
                    // Convert to timestamp
                    $timestamp = strtotime($lastUpdatedStr);
        
                    // Format the date into 'DD/MM/YYYY'
                    $formattedDate = date('d/m/Y H:i:s', $timestamp);
                }
                ?>

                <li class="fundraising-game">
                    <strong>Game: <?php echo htmlspecialchars($game['name']); ?></strong><br>
                    Goal: <?php echo '€' . htmlspecialchars($game['goal']); ?> <br>
                    Current Amount Raised: <?php echo '€' . htmlspecialchars($game['current_amount_raised']); ?> <br>
                    Donations: <?php echo htmlspecialchars($game['doners']); ?> <br>
                    Last Updated: <?php echo htmlspecialchars($formattedDate); ?> <br>
                    <a href="ai-games-participatedraising_id=<?php echo $game['id']; ?>">Participate</a>
                    <br>
                    <button onclick="window.location.href = 'leaderboardeId=<?php echo $game['id']; ?>';" style="background-color: #00FF00;">
                        Leaderboard
                    </button>
                </li>

<?php endforeach; ?>
    <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
			$lastUpdatedStr = $row['last_updated'];

			// Check if the date string is set
			if (isset($lastUpdatedStr) && !empty($lastUpdatedStr)) {
				// Convert to timestamp
				$timestamp = strtotime($lastUpdatedStr);

				// Format the date into 'DD/MM/YYYY'
				$formattedDate = date('d/m/Y H:i:s', $timestamp);
			}
			?>
                <li class="fundraising-game">
                    <strong>Game: <?php echo htmlspecialchars($row['games']); ?></strong><br>
                    Goal: <?php echo '€' . htmlspecialchars($row['goal']); ?> <br>
                    Current Amount Raised: <?php echo '€' . htmlspecialchars($row['current_amount_raised']); ?> <br>
                    Donations: <?php echo htmlspecialchars($row['doners']); ?> <br>
                    Last Updated: <?php echo htmlspecialchars($formattedDate); ?> <br>
                    <a href="participatedraising_id=<?php echo $row['id']; ?>">Participate</a>
                </li>
            <?php endwhile; ?>
        <?php endif; ?>
</ul>
</main>
</body>
</html>
