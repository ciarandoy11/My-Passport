<?php
// comingSoon.php
include __DIR__ . '/db.php';

// Fetch user admin type
$sql = "SELECT `type-admin` FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$typeAdmin = (int)$row['type-admin']; // Cast to integer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            text-align: center;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 600px; /* Limit the maximum width */
            width: 90%; /* Full width on small devices */
        }

        .title {
            font-size: 2.5em;
            margin-bottom: 0.5em;
        }

        .description {
            font-size: 1.2em;
            margin-bottom: 1em;
        }

        .countdown {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            font-size: 1.5em;
            color: #333;
            flex-wrap: wrap; /* Wrap items on smaller screens */
        }

        .countdown-item {
            text-align: center;
            flex: 1 1 100px; /* Allow flexibility */
            min-width: 80px; /* Minimum width for each item */
        }

        .label {
            display: block;
            font-size: 0.5em;
            color: #888;
        }

        .pwd {
            margin-top: 20px;
        }

        #pwd {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 80%; /* Responsive width */
            max-width: 200px; /* Limit the maximum width */
        }

        #access {
            padding: 10px;
            border: none;
            border-radius: 5px;
            background-color: #28a745;
            color: white;
            cursor: pointer;
            margin-top: 10px;
            width: 100px; /* Fixed width for button */
        }

        #access:hover {
            background-color: #218838;
        }

        .response {
            margin-top: 20px;
            color: #d9534f; /* Red for error */
        }

        @media (max-width: 768px) { /* For tablets and smaller devices */
            .title {
                font-size: 2em;
            }

            .description {
                font-size: 1em;
            }

            .countdown {
                font-size: 1.2em; /* Smaller font size for countdown */
            }
        }

        @media (max-width: 480px) { /* For mobile devices */
            .title {
                font-size: 1.8em;
            }

            .description {
                font-size: 0.9em;
            }

            .countdown {
                flex-direction: column; /* Stack countdown items */
            }

            .countdown-item {
                width: 100%; /* Full width for countdown items */
                margin: 10px 0; /* Margin for spacing */
            }

            #pwd, #access {
                width: 90%; /* More responsive input/button width */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h1 class="title">Coming Soon</h1>
            <p class="description">We're working hard to bring something great to you. Stay tuned!</p>
            <div class="countdown" id="countdown">
                <div class="countdown-item">
                    <span id="days">00</span>
                    <span class="label">Days</span>
                </div>
                <div class="countdown-item">
                    <span id="hours">00</span>
                    <span class="label">Hours</span>
                </div>
                <div class="countdown-item">
                    <span id="minutes">00</span>
                    <span class="label">Minutes</span>
                </div>
                <div class="countdown-item">
                    <span id="seconds">00</span>
                    <span class="label">Seconds</span>
                </div>
            </div>
            <div class="pwd">
                <input type="password" id="pwd" placeholder="Developer password" required>
                <button id="access">Access</button>
            </div>
            <p id="response" class="response"></p>
        </div>
    </div>
    <script>
        // Set the date we're counting down to
        let countDownDate = new Date("Apr 1, 2025 10:00:00").getTime();

        // Update the countdown every 1 second
        let countdownInterval = setInterval(function() {
            let now = new Date().getTime();
            let distance = countDownDate - now;

            // Time calculations for days, hours, minutes, and seconds
            let days = Math.floor(distance / (1000 * 60 * 60 * 24));
            let hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Display the result
            document.getElementById("days").innerText = days;
            document.getElementById("hours").innerText = hours;
            document.getElementById("minutes").innerText = minutes;
            document.getElementById("seconds").innerText = seconds;

            // If the countdown is over, display a message and redirect
            if (distance < 0) {
                clearInterval(countdownInterval);
                document.querySelector(".content").innerHTML = "<h1>We're Live!</h1><p>Thank you for your patience!</p>";

                // Redirect based on admin type
                <?php if ($typeAdmin !== 1): ?>
                    document.location.href = 'fundraising.php';
                <?php else: ?>
                    document.location.href = 'clubFundraising.php';
                <?php endif; ?>
            }
        }, 1000);

        // Handle password input for access
        document.getElementById("access").addEventListener("click", function() {
            const passwordInput = document.getElementById("pwd").value;
            const correctPassword = 'Cn18012009!'; // The correct password

            if (passwordInput === correctPassword) {
                // Redirect based on admin type
                <?php if ($typeAdmin !== 1): ?>
                    document.location.href = 'fundraising.php';
                <?php else: ?>
                    document.location.href = 'clubFundraising.php';
                <?php endif; ?>
            } else {
                document.getElementById("response").innerText = "Please enter a valid password.";
            }
        });
    </script>
</body>
</html>
