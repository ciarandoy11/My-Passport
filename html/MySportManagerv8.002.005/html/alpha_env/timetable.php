<?php
include __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['right'])) {
					// Get the 'right' value sent from JavaScript
					$right = htmlspecialchars($_POST['right']);
					// Now you can use the $right value as needed in your PHP logic.
					// For example, saving it to a database or processing it further.
				}

// Retrieve user's swimmer data
$sql = "SELECT swimmer FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Retrieve all group names for the user's swimmers
$swimmerGroups = [];
if (!empty($user['swimmer'])) {
    $swimmers = array_map('trim', explode(',', $user['swimmer']));
    foreach ($swimmers as $swimmer) {
        $sql = "SELECT list_name FROM groups WHERE item_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $swimmer);
        $stmt->execute();
        $stmt->bind_result($groupName);
        $stmt->fetch();
        $stmt->close();

        if ($groupName) {
            $swimmerGroups[] = $groupName; // Collect all group names
        }
    }
}

// Convert swimmerGroups array to a set for efficient lookup
$swimmerGroupsSet = array_flip($swimmerGroups);

// Retrieve the club from the users table
$clubSql = "SELECT club FROM users WHERE id = ?";
$stmt = $conn->prepare($clubSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($club);
$stmt->fetch();
$stmt->close();

$sessions = prepareSessions(fetchTimetable($conn, $club));

function prepareSessions($result) {
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $time = $row['_time'];
        $day = $row['_day'];
        $sessions[$day][$time] = $row;
    }
    return $sessions;
}

function fetchTimetable($conn, $club) {
    $sql = "SELECT * FROM timetable WHERE club = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $club);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

//print_r($sessions);

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

// Output the timetable with sessions
echo "<h1>Timetable</h1>";
echo "<div class='week-navigation'>
    <button type='button' onclick='changeWeek(-1)' class='button'>Previous Week</button>
    <button type='button' onclick='changeWeek(1)' class='button'>Next Week</button>
</div>";

$times = ['04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Timetable - Swimming Club Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            margin-top: 20px;
        }

        .week-navigation {
            text-align: center;
            margin: 20px 0;
        }

        .button {
            background-color: #3241FF;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #002061;
        }

        /* Table Styles */
    #timetable {
        width: 100%; /* Full width */
        border-collapse: collapse; /* Collapse borders */
        margin-top: 20px; /* Space above the table */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Light shadow for depth */
        font-family: Arial, sans-serif; /* Font for the table */
    }

    th, td {
        padding: 10px; /* Padding for table cells */
        text-align: left; /* Align text to the left */
        border-top: 0px solid white; /* Bottom border for cells */
        border-bottom: 0px solid white; /* Bottom border for cells */
    }

    td {
        height: 10%;
    }

    th {
        background-color: #0073e6; /* Header background color */
        color: white; /* Header text color */
        font-weight: bold; /* Bold font for headers */
        position: sticky; /* Make the header sticky */
        top: 0; /* Position it at the bottom of the nav bar */
        z-index: 12; /* Ensure it stays above other content */    }

    td, tr {
        height: auto;  /* Each row will take up 20% of the parent element's 10%eight */
        position: relative; /* Maintain relative positioning for absolute ch
ildren */
        vertical-align: top; /* Align content to the top */
    }

    .timetable {
        width: 100%; /* Set table width to 100% */
        table-layout: fixed; /* Use fixed table layout algorithm */
        border-collapse: collapse; /* Optional, to avoid double borders */
    }

    .timetable th,
    .timetable td {
        padding: 10px; /* Add some padding */
        border: 1px solid #ddd; /* Optional border */
        overflow: visible !important;
    }

    /* Set the width of the first (time) column */
    .timetable th:first-child,
    .timetable td:first-child {
        width: 5%; /* Adjust percentage as needed (e.g., 5% for time column) */
    }

    /* Set equal width for the rest of the columns (7 columns) */
    .timetable th:not(:first-child),
    .timetable td:not(:first-child) {
        width: 13.57142857%; /* Remaining space divided equally by 7 columns */
    }

    /* Optional - Add "word-wrap:" property to prevent overflow */
    .timetable th,
    .timetable td {
        word-wrap: break-word;
        border-top: 0px solid white; /* Bottom border for cells */
        border-bottom: 0px solid white; /* Bottom border for cells */
        pointer-events: none;
    }

    button, .button-66 {
        background-color: #0a6bff; /* Primary button color */
        color: white;
        border: none;
        padding: 16px 20px;
        font-size: 18px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s cubic-bezier(.22, .61, .36, 1), transform 0.2s cubic-bezier(.22, .61, .36, 1);
        font-family: "Space Grotesk", -apple-system, system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, Moderustic;
        font-weight: 700;
        line-height: 24px;
        min-height: 56px;
        min-width: 120px;
    }

    .button-66:hover {
        background-color: #002061; /* Hover background color */
        color: #ffff00; /* Hover text color */
        transform: translateY(-2px);
    }

        .session {
            background-color: #ffffff; /* Background for session details */
            border: 1px solid #ddd; /* Border around session */
            padding: 10px; /* Padding inside the session */
            border-radius: 4px; /* Rounded corners */
            margin: 4px 0; /* Space between sessions */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); /* Light shadow for depth */
            transition: background-color 0.2s; /* Smooth background transition */
            pointer-events: auto; /* Ensures interaction */
        }

        .session:hover {
            background-color: #e0e0e0; /* Darker background on hover */
            z-index: 9;
        }

        .highlight {
            border: 4px solid red !important; /* Highlight style */
            background-color: #ffffff; /* Background for session details */
            border: 1px solid #ddd; /* Border around session */
            padding: 10px; /* Padding inside the session */
            border-radius: 4px; /* Rounded corners */
            margin: 4px 0; /* Space between sessions */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); /* Light shadow for depth */
            transition: background-color 0.2s; /* Smooth background transition */
            pointer-events: auto; /* Ensures interaction */
        }

        .highlight:hover {
            background-color: #e0e0e0; /* Darker background on hover */
            z-index: 10;
        }

        /* Responsive Navigation Styles */
        .sideNav {
            list-style-type: none;
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #000;
            position: fixed;
            top: 0;
            z-index: 13; /* Higher than the table header */
            display: flex;
            align-items: center; /* Vertical centering */
            transition: top 0.3s; /* Smooth transition for hiding and showing the nav */
            height: 60px; /* Fixed height for the nav bar */
        }

        li a {
            display: block;
            color: white;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }

        li a:hover:not(.active) {
            background-color: #565656; /* Hover background color */
        }

        .active {
            background-color: #3241FF; /* Active link background color */
        }

        @media (max-width: 600px) {
            body {
                margin: 0;
            }

            .sideNav {
                height: auto; /* Allow height to adjust as needed */
                flex-direction: column; /* Stack nav items vertically */
                align-items: stretch; /* Stretch nav items to full width */
            }

            .sideNav li {
                width: 100%; /* Full width for nav items */
            }

            li a {
                padding: 10px; /* Adjust padding for mobile */
                font-size: 14px; /* Smaller font size for mobile links */
            }

            th, td {
                font-size: 12px; /* Decrease font size on small screens */
            }

            .week-navigation {
                flex-direction: column; /* Stack buttons vertically */
                margin-top: 190px;
            }

            .button {
                width: 100%; /* Make buttons full width */
                margin: 5px 0; /* Small margin for stacked buttons */
            }

            th {
                top: 0%; /* Adjust top for smaller screens */
            }

            /* Wrapper for the table to allow scrolling */
            .table-wrapper {
                overflow-x: auto; /* Enable horizontal scrolling */
                width: 100%; /* Full width of the parent */
                margin-top: 60px; /* Adjust as needed to create space for other page elements */
                overflow: auto; /* Allows scrolling */
                height: calc(100vh - 60px); /* Adjust the height to fit the layout */
            }

            .timetable {
                width: auto; /* Use auto width for mobile */
                min-width: 1600px; /* Set a minimum width to prevent it from being too small */
            }
        }

        .table-wrapper {
            position: relative;
            margin-top: 60px;
        }

        #timetable thead {
            position: relative;
            z-index: 12;
        }

        #timetable thead th {
            background-color: #0073e6;
            color: white;
            font-weight: bold;
            padding: 10px;
            text-align: left;
        }
    </style>
</head>
<body onscroll="scrollFunction()">

<ul class='sideNav' id="mySidenav">
    <li><img onclick='document.location="dashboard.php"' src='/images/logo-rectangle.png' style='width: 39%'></li>
    <li><a href="dashboard.php">Home</a></li>
    <li><a class="active" href="timetable.php">Timetable</a></li>
    <li><a href="membership.php">Finances</a></li>
    <li><a href="comingSoon.php">Fundraising</a></li>
    <li><a href="emails.php">Emails Dashboard</a></li>
</ul>

<script>
function scrollFunction() {
  if (window.innerWidth <= 768) {
    if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
      document.getElementById("mySidenav").style.display = "none";
    } else {
      document.getElementById("mySidenav").style.display = "block";
    }
  }
}
</script>

<main>
<button style="background-color: red; color: white;" onclick="document.location='login.php'">Sign Out</button>
    <div class="table-wrapper">
        <div class="table-container">
            <table id="timetable" class="timetable">
                <thead>
                    <tr>
                        <th>Time</th>
                        <?php foreach (['Monday ' . $weekDates[0], 'Tuesday ' . $weekDates[1], 'Wednesday ' . $weekDates[2], 'Thursday ' . $weekDates[3], 'Friday ' . $weekDates[4], 'Saturday ' . $weekDates[5], 'Sunday ' . $weekDates[6]] as $day) {
                            echo "<th>$day</th>";
                        } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sessionsArray = [];
                    foreach ($times as $time) {
                        echo "<tr style='height: 200px;'><td>$time</td>";
                        foreach ($days as $day) {
                            $wordday = (explode(' ', $day)[1]);
                            $cellId = ($wordday) . '-' . str_replace(':', '', $time);
                            echo "<td style='position: relative; height: 300px; vertical-align: top;'>";

                            // Session details and dimensions
                            if (isset($sessions[$day][$time])) {
                                $session = $sessions[$day][$time];
                                $dayString = $day;
                                $day = explode(' ', $dayString)[0];

                                $sessionId = $session['id'];
                                $group = htmlspecialchars($session['_group']);
                                $groups = explode(',', $session['_group']);
                                $formattedGroups = implode(', ', array_map('trim', $groups));
                                $location = htmlspecialchars($session['_location']);
                                $coach = htmlspecialchars($session['coach']);
                                $pod = htmlspecialchars($session['pod']);
                                $stime = htmlspecialchars($session['stime']);
                                $etime = htmlspecialchars($session['etime']);

                                // Calculate the height based on the difference between start and end times
                                $startHour = intval(explode(':', $stime)[0]);
                                $startMinute = intval(explode(':', $stime)[1]);
                                $endHour = intval(explode(':', $etime)[0]);
                                $endMinute = intval(explode(':', $etime)[1]);

                                // Calculate the percentage for start and end positions
                                $hoursBase = 4; // Assuming the timetable starts at 4 AM
                                $totalHours = 17; // From 4 AM to 9 PM

                                $inputDiff = ($startHour - $time) * 100;

                                $startPosition = ((($startMinute / 60) * 100) + $inputDiff); // Start position as a percentage
                                $endPosition = (($endMinute / 60) * 100); // End position as a percentage

                                $hourDiff = ($endHour - $startHour);
                                $minuteDiff = ($endMinute - $startMinute);

                                // Calculate how much the card covers in pixels
                                $height = (5 * $minuteDiff) + (300 * $hourDiff); // Height represents the coverage of the session in pixels

                                // Optional: Ensure height is non-negative or less than clearly visible for rendering issues
                                if ($height < 300) $height = 300; // This ensures we handle bad input gracefully

                                //$rightPosition = 13.57142857;

                                // Store session data in sessionsArray
                                $sessionsArray[] = [
                                    'id' => $cellId,
                                    'day' => $day,
                                    'end' => str_replace(':', '', $etime),
                                    'start' => str_replace(':', '', $stime),
                                    'height' => $height,
                                    'group' => $formattedGroups,
                                    'location' => $location,
                                    'coach' => $coach,
                                    'pod' => $pod,
                                    'stime' => $stime,
                                    'etime' => $etime,
                                ];

                                // Optional: Check for highlighting logic
                                $highlight = false;
                                foreach ($groups as $group) {
                                    if (isset($swimmerGroupsSet[trim($group)])) {
                                        $highlight = true;
                                        break;
                                    }
                                }

                                $class = $highlight ? 'highlight' : 'session';

                                // Session card with inline styling
                                echo "<div id='$cellId' class='$class' onclick='openSessionInfo(`$cellId`)' style='position: absolute; left: 0; top: {$startPosition}%; height: {$height}px; width: 100%;'>
                                        <p type='text' id='group-$sessionId' name='group'>Groups: $formattedGroups</p>
                                        <p type='text' id='start-time-$sessionId' name='start-time'>Start Time: $stime</p>
                                        <p type='text' id='pod-$sessionId' name='pod'>POD: $pod</p>
                                        <b>Click here for more Sesion Info!</b>
                                      </div>";
                            }
                            echo "</td>"; // Close td for the day column
                        }
                        echo "</tr>"; // Close the row for this time slot
                    }

    // Check for overlapping sessions
    $overlaps = []; // Array to keep track of overlapping sessions
    foreach ($sessionsArray as $sessionInfo) {
        $overlaps[$sessionInfo['id']]['ids'] = []; // Initialize the id list
        foreach ($sessionsArray as $compareSession) {
            // Skip if it's the same session
            if ($sessionInfo['id'] === $compareSession['id']) continue;

            // Check for overlap
            if ($sessionInfo['day'] == $compareSession['day'] &&
                ($sessionInfo['start'] < $compareSession['end'] && $sessionInfo['end'] > $compareSession['start'])) {

                $overlaps[$sessionInfo['id']]['ids'][] = $compareSession['id'];
            }
        }
        // Ensure the session itself is in its overlap list
        if (!empty($overlaps[$sessionInfo['id']]['ids'])) {
            $overlaps[$sessionInfo['id']]['ids'][] = $sessionInfo['id'];
        }
    }

    // Sorting overlapped sessions by the number of ids in each sub-array
    uasort($overlaps, function ($a, $b) {
        return count($b['ids']) - count($a['ids']); // Sort in descending order
    });

// Normalize entries by keeping the largest set without overlapping IDs
$normalizedOverlaps = [];
foreach ($overlaps as $sessionId => $data) {
    // Sort ids to ensure consistency
    sort($data['ids']);

    // Convert array to a string to use as a unique key
    $key = $data['ids'][0];

    // Check for overlap with existing keys
    $overlapFound = false;
    foreach ($normalizedOverlaps as $existingData) {
        // Check if there is any overlapping ID
        if (array_intersect($data['ids'], $existingData['ids'])) {
            $overlapFound = true;
            break; // Exit loop on first overlap found to save time
        }
    }

    // If no overlap is found, proceed to add or replace
    if (!$overlapFound) {
        // Add the unique key to the normalized overlaps
        $normalizedOverlaps[$key] = ['ids' => $data['ids']];
    } else {
        // If overlap is found, check if we should update the existing entry
        if (isset($normalizedOverlaps[$key]) && count($data['ids']) > count($normalizedOverlaps[$key]['ids'])) {
            $normalizedOverlaps[$key] = ['ids' => $data['ids']];
        }
    }
}

// Use $normalizedOverlaps in the rest of your code
$overlaps = $normalizedOverlaps; // Replace old overlaps with normalized overlaps


    // Check if sessions and overlaps are populated
    if (empty($sessionsArray)) {
        echo "No sessions found.";
        //return; // Stop execution
    }

    foreach ($sessionsArray as $session) {
        $sessionId = $session['id'];
        if (isset($overlaps[$sessionId])) {
            $overlapCount = count($overlaps[$sessionId]['ids']);

            foreach ($overlaps[$sessionId]['ids'] as $index => $overlappedId) {
                $width = 100 / $overlapCount;
                $index = $index + 1;
                $right = $right + $width;
                if ($index === 1) {
                    $right = 0;
                }
                $font = 160;

                // Outputting debug information for style generation
                //echo "Generating style for $overlappedId ($index): width: {$width}%; right: {$right}%;<br>";
            }
        }
    }

    // Generate JavaScript for styling after DOM content loaded
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {';

    foreach ($sessionsArray as $session) {
        $sessionId = $session['id'];
        if (isset($overlaps[$sessionId])) {
            $overlapCount = count($overlaps[$sessionId]['ids']);

            foreach ($overlaps[$sessionId]['ids'] as $index => $overlappedId) {
                $width = 100 / $overlapCount;
                $index = $index + 1;
                $right = $right + $width;
                if ($index === 1) {
                    $right = 0;
                }
                $font = 12;

                // Set the styles using JavaScript
                echo "document.getElementById(\"$overlappedId\").style.width = \"$width%\";";
                echo "document.getElementById(\"$overlappedId\").style.left = \"$right%\";";
                echo "document.getElementById(\"$overlappedId\").style.fontSize = \"$font\";";
            }
        }
    }

    echo '});
    </script>';

    // Close the database connection
    $conn->close();
    ?>
                </tbody>
            </table>
        </div>


    </div>
</main>

<script>
    function changeWeek(offset) {
        const params = new URLSearchParams(window.location.search);
        let weekOffset = parseInt(params.get('weekOffset') || '0');
        weekOffset += offset;
        params.set('weekOffset', weekOffset);
        window.location.search = params.toString();
    }

    function openSessionInfo(sessionId) {
        console.log("Looking for session ID:", sessionId);
        const sessionInfo = <?php echo json_encode($sessionsArray); ?>.find(session => session.id === sessionId);
        if (sessionInfo) {
            console.log("Session found:", sessionInfo);
        } else {
            console.log("Session not found");
        }
        // Create a new div for the session info
        let sessionInfoDiv = document.createElement('div');
        sessionInfoDiv.style.position = 'fixed';
        sessionInfoDiv.style.top = '15%';
        sessionInfoDiv.style.left = '25%';
        sessionInfoDiv.style.width = '50%';
        sessionInfoDiv.style.height = '80%';
        sessionInfoDiv.style.backgroundColor = 'white';
        sessionInfoDiv.style.padding = '20px';
        sessionInfoDiv.style.zIndex = '13';
        sessionInfoDiv.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.1)';
        sessionInfoDiv.style.border = '1px solid #ddd';
        sessionInfoDiv.style.textAlign = 'center';

        // Display the full session info
        sessionInfoDiv.innerHTML = `
            <div style="position: relative;">
                <br>
                <button onclick="this.parentElement.parentElement.remove()" style="position: reletive; top: 10px; right: 10px; background: red; color: white; border: none; min-height: 15px; min-width: 15px; cursor: pointer;">X</button>
                <br>
                <h2>Session Info</h2>
                <p><strong>Day:</strong> ${sessionInfo.day}</p>
                <p><strong>Start Time:</strong> ${sessionInfo.stime}</p>
                <p><strong>End Time:</strong> ${sessionInfo.etime}</p>
                <p><strong>Groups:</strong> ${sessionInfo.group}</p>
                <p><strong>Location:</strong> ${sessionInfo.location}</p>
                <p><strong>Coach:</strong> ${sessionInfo.coach}</p>
                <p><strong>POD:</strong> ${sessionInfo.pod}</p>
            </div>
        `;

        // Append the div to the body
        document.body.appendChild(sessionInfoDiv);
    }
</script>
</body>
</html>
