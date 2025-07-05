<?php
include __DIR__ . '/db.php';

    // // Check if the user is a coach
    // if ($typeCoach !== 1) {
    //     header("Location: login"); // Redirect to login if not logged in
    //     exit();
    // }

    // Prepare and bind
    $stmt = $conn->prepare("SELECT * FROM timetable WHERE club = ?");
    $stmt->bind_param("s", $club);

    // Execute the statement
    $stmt->execute();

    // Get the result
    $result = $stmt->get_result();

    // Fetch the timetable
    $timetable = array();
    while ($row = $result->fetch_assoc()) {
        $day = $row['_day'];
        $time = $row['_time'];
        $timetable[$day][$time] = $row['_day'] . '-' . $row['stime'] . '-' . $row['etime'];
    }

    // Close the statement
    $stmt->close();

    // Determine the week offset (how many weeks ahead/behind we are)
$weekOffset = isset($_GET['weekOffset']) ? intval($_GET['weekOffset']) : 0;

// Get the current date
$currentDate = new DateTime();
$currentDayOfWeek = $currentDate->format('N'); // 1 (for Monday) through 7 (for Sunday)

// Calculate the Monday of the relevant week
$monday = clone $currentDate;
$monday->modify('-' . ($currentDayOfWeek - 1) . ' days');
$monday->modify('+' . ($weekOffset * 7) . ' days');

// Generate the dates for the entire week
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[$i] = $monday->format('d/m/y');
    $monday->modify('+1 day');
}

$days = [
    'Monday ' . $weekDates[0],
    'Tuesday ' . $weekDates[1],
    'Wednesday ' . $weekDates[2],
    'Thursday ' . $weekDates[3],
    'Friday ' . $weekDates[4],
    'Saturday ' . $weekDates[5],
    'Sunday ' . $weekDates[6]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Session Plans</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        main {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        h1, h2 {
            text-align: center;
        }
        button {
            margin: 10px 0;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .week-navigation {
            text-align: center;
            margin: 20px 0;
        }
        select, textarea {
            margin-top: 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
        }
        #message-container {
            position: fixed;
            top: 10;
            left: 50;
            width: 65%;
            padding: 10px;
            text-align: center;
            display: none;
        }
        #message-container.success {
            background-color: #4CAF50;
            color: white;
        }
        #message-container.error {
            background-color: #f44336;
            color: white;
        }
    </style>
    <script>
    let sessionPlans = [];

    function loadSessionPlans() {
        const selectedPlan = document.querySelector('select').value;

        fetch('load_session_plan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name: selectedPlan })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('session-plan').value = data.plan;
                showMessage("Session plan loaded successfully!", 'success');
            } else {
                console.error('Error:', data.message);
                showMessage('Error:', data.message, 'error');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function saveSessionPlan() {
        const plan = document.getElementById('session-plan').value;
        const name = document.getElementById('session-plan-select').value;

        console.log(plan);
        console.log(name);

        fetch('saveSessionPlans.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ plan: plan, name: name })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Session plan saved successfully!');
                showMessage("Session plan saved successfully!", 'success');
            } else {
                console.error('Error:', data.message);
                showMessage('Error:', data.message, 'error');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function changeWeek(offset) {
        const params = new URLSearchParams(window.location.search);
        let weekOffset = parseInt(params.get('weekOffset') || '0');
        weekOffset += offset;
        params.set('weekOffset', weekOffset);
        window.location.search = params.toString();
    }

    function showMessage(message, type) {
        const messageContainer = document.getElementById('message-container');
        messageContainer.textContent = message;
        messageContainer.className = type; // Add a class for styling, e.g., "success" or "error"
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 3000); // Hide after 3 seconds
    }
    </script>
</head>
<body>
<main>
<div id="message-container" style="display:none;"></div> <!-- Message container -->
    <h1>Session Plans</h1>

    <?php echo("<h2>Hello " . $userName . "</h2>"); ?>
    <button style="background-color: red; color: white;" onclick="document.location='login'">Sign Out</button>
    <button style="background-color: blue; color: white;" onclick="document.location='coachs-site'">Back</button>

    <div class='week-navigation'>
        <button type='button' onclick='changeWeek(-1)' style='background-color: #002061; color: #ffffff;'>Previous Week</button>
        <button type='button' onclick='changeWeek(1)' style='background-color: #002061; color: #ffffff;'>Next Week</button>
        <select id='session-plan-select' onchange='loadSessionPlans()' style='background-color: #002061; color: #ffffff;'>
            <option value='' type='default'>Session Plans:</option>
            <?php
            foreach ($timetable as $day => $times) {
                if (in_array($day, $days)) {
                    foreach ($times as $time => $sessionPlan) {
                        echo "<option value='" . htmlspecialchars($sessionPlan) . "'>" . htmlspecialchars($sessionPlan) . "</option>";
                    }
                }
            }
            ?>
        </select><br>
        <textarea id='session-plan'></textarea>
        <button type="button" onclick="if(document.getElementById('session-plan-select').value != '') { saveSessionPlan(); }" style="background-color: #002061; color: #ffffff;">Save Session Plan</button>
    </div>
</main>
</body>
</html>
