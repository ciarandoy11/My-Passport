<?php
//coachs-site.php
include './db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Coach's Site</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            color: #002061;
        }
        h1, h2 {
            color: #002061;
        }
        button {
            background-color: #002061;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #003380;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #002061;
            color: #ffff00;
        }
        td {
            background-color: #f2f2f2;
        }
        .session input {
            margin: 5px;
        }
        .fa-close {
            margin-right: 10px;
        }
        .submit {
            text-align: center;
            margin: 20px 0;
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
    let sessions = [];
    let autoUpdateInterval;
    let lastUpdateTime = 0;

    function allowDrop(event) {
        event.preventDefault();
    }

    function drag(event) {
        event.dataTransfer.setData("text", event.target.dataset.id);
    }

    function drop(event) {
        event.preventDefault();
        const sessionId = event.dataTransfer.getData("text");
        const targetCell = event.target.closest('td');

        const sessionElement = document.querySelector(`div[data-id="${sessionId}"]`);
        if (targetCell && sessionElement) {
            targetCell.appendChild(sessionElement);

            const [day, time] = targetCell.id.split('-');
            const sessionTime = time.slice(0, 2) + ':' + time.slice(2);

            const groupInput = sessionElement.querySelector('input[name="group"]');
            const locationInput = sessionElement.querySelector('input[name="location"]');
            const stimeInput = sessionElement.querySelector('input[name="stime"]');
            const etimeInput = sessionElement.querySelector('input[name="etime"]');
            const coachInput = sessionElement.querySelector('input[name="coach"]');

            const group = groupInput ? groupInput.value : 'default_group';
            const location = locationInput ? locationInput.value : 'default_location';
            const stime = stimeInput ? stimeInput.value : '00:00';
            const etime = etimeInput ? etimeInput.value : '00:00';
            const coach = coachInput ? coachInput.value : 'N/a';

            const existingSessionIndex = sessions.findIndex(session => session.sessionId === sessionId);
            if (existingSessionIndex !== -1) {
                sessions[existingSessionIndex] = {
                    sessionId,
                    day,
                    time: sessionTime,
                    group,
                    location,
                    stime,
                    etime,
                    coach
                };
            } else {
                sessions.push({
                    sessionId,
                    day,
                    time: sessionTime,
                    group,
                    location,
                    stime,
                    etime,
                    coach
                });
            }

            saveDetails();
        }
    }

    function createSession(event) {
        const targetCell = event.target.closest('td');
        if (targetCell) {
            const existingSession = targetCell.querySelector('.session');
            if (!existingSession) {
                const [day, time] = targetCell.id.split('-');
                const sessionTime = time.slice(0, 2) + ':' + time.slice(2);

                const sessionElement = document.createElement('div');
                sessionElement.className = 'session';
                sessionElement.draggable = true;
                sessionElement.innerHTML = `
                    <a class='fa fa-close' style='color:red' onclick='deleteSession(event)'></a>
                    <label for='group'>Group:</label>
                    <input type='text' name='group' value=''><br>
                    <label for='location'>Location:</label>
                    <input type='text' name='location' value=''><br>
                    <label for='stime'>Start Time:</label>
                    <input type='time' name='stime' value=''><br>
                    <label for='etime'>End Time:</label>
                    <input type='time' name='etime' value=''>
                    <label for='coach'>Coach:</label>
                    <input type='text' name='coach' value=''>
                `;
                sessionElement.addEventListener('dragstart', drag);

                targetCell.appendChild(sessionElement);

                const newSession = {
                    sessionId: `session-${Date.now()}`,
                    day,
                    time: sessionTime,
                    group: '',
                    location: '',
                    stime: sessionTime,
                    etime: new Date(new Date('1970-01-01T' + sessionTime + 'Z').getTime() + 60 * 60 * 1000).toISOString().substr(11, 5),
                    coach: 'N/a'
                };

                sessions.push(newSession);
                saveDetails();
            }
        }
    }

    // Add input change listeners when the page loads
    window.onload = function() {
        // Add listeners for all input changes
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                saveDetails();
            });
        });

        // Start auto-update interval
        startAutoUpdate();
        
        // Initial load of sessions
        updateTimetable();
    };

    function startAutoUpdate() {
        // Clear any existing interval
        if (autoUpdateInterval) {
            clearInterval(autoUpdateInterval);
        }
        
        // Set new interval to update every 5 seconds
        autoUpdateInterval = setInterval(updateTimetable, 5000);
    }

    function updateTimetable() {
        const xhr = new XMLHttpRequest();
        xhr.open("GET", "get_timetable.php?lastUpdate=" + lastUpdateTime, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === "success") {
                            // Only update if we have new data
                            if (response.sessions && response.sessions.length > 0) {
                                updateTimetableDisplay(response.sessions);
                                lastUpdateTime = response.timestamp || Date.now();
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing timetable update:', e);
                    }
                }
            }
        };
        xhr.send();
    }

    function updateTimetableDisplay(newSessions) {
        // Store current input values
        const currentValues = {};
        document.querySelectorAll('.session').forEach(session => {
            const sessionId = session.dataset.id;
            if (sessionId) {
                currentValues[sessionId] = {
                    group: session.querySelector('input[name="group"]')?.value,
                    location: session.querySelector('input[name="location"]')?.value,
                    stime: session.querySelector('input[name="stime"]')?.value,
                    etime: session.querySelector('input[name="etime"]')?.value,
                    coach: session.querySelector('input[name="coach"]')?.value
                };
            }
        });

        // Clear existing sessions
        document.querySelectorAll('.session').forEach(session => {
            session.remove();
        });

        // Add new sessions
        newSessions.forEach(session => {
            const cellId = `${session._day}-${session._time.replace(':', '')}`;
            const cell = document.getElementById(cellId);
            if (cell) {
                const sessionElement = document.createElement('div');
                sessionElement.className = 'session';
                sessionElement.draggable = true;
                sessionElement.dataset.id = session.id;
                
                // Always use server values for updates
                const values = {
                    group: session._group || '',
                    location: session._location || '',
                    stime: session.stime || '',
                    etime: session.etime || '',
                    coach: session.coach || ''
                };

                sessionElement.innerHTML = `
                    <a class='fa fa-close' style='color:red' data-id='${session.id}' onclick='deleteSession(event)'></a>
                    <label for='group-${session.id}'>Group:</label>
                    <input type='text' id='group-${session.id}' name='group' value='${values.group}'><br>
                    <label for='location-${session.id}'>Location:</label>
                    <input type='text' id='location-${session.id}' name='location' value='${values.location}'><br>
                    <label for='stime-${session.id}'>Start Time:</label>
                    <input type='time' id='stime-${session.id}' name='stime' value='${values.stime}'><br>
                    <label for='etime-${session.id}'>End Time:</label>
                    <input type='time' id='etime-${session.id}' name='etime' value='${values.etime}'>
                    <label for='coach-${session.id}'>Coach:</label>
                    <input type='text' id='coach-${session.id}' name='coach' value='${values.coach}'>
                `;
                sessionElement.addEventListener('dragstart', drag);
                cell.appendChild(sessionElement);
            }
        });

        // Reattach input listeners
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                saveDetails();
            });
        });
    }

    function saveDetails() {
        const sessions = Array.from(document.querySelectorAll('.session')).map(sessionElement => {
            const sessionId = sessionElement.dataset.id;
            const targetCell = sessionElement.parentElement;
            const [day, time] = targetCell.id.split('-');

            const match = time.match(/(\d{2})(\d{2})/);
            const sessionTime = match ? `${match[1]}:${match[2]}` : '00:00';

            const groupInput = sessionElement.querySelector('input[name="group"]');
            const locationInput = sessionElement.querySelector('input[name="location"]');
            const stimeInput = sessionElement.querySelector('input[name="stime"]');
            const etimeInput = sessionElement.querySelector('input[name="etime"]');
            const coachInput = sessionElement.querySelector('input[name="coach"]');

            return {
                sessionId,
                day,
                time: sessionTime,
                group: groupInput ? groupInput.value : 'default_group',
                location: locationInput ? locationInput.value : 'default_location',
                stime: stimeInput ? stimeInput.value : '00:00',
                etime: etimeInput ? etimeInput.value : '00:00',
                coach: coachInput ? coachInput.value : 'N/a'
            };
        });

        localStorage.setItem('sessions', JSON.stringify(sessions));

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "save_sessions.php", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                const response = JSON.parse(xhr.responseText);
                if (xhr.status === 200 && response.status === "success") {
                    showMessage("Timetable updated successfully!", 'success');
                } else {
                    showMessage("Error updating timetable: " + response.message, 'error');
                }
            }
        };
        xhr.send(JSON.stringify(sessions));
    }

    function deleteSession(event) {
        event.stopPropagation();
        const sessionId = event.target.dataset.id;
        const sessionElement = document.querySelector(`div[data-id="${sessionId}"]`);
        if (sessionElement) {
            sessionElement.remove();

            const sessionIndex = sessions.findIndex(session => session.sessionId === sessionId);
            if (sessionIndex !== -1) {
                sessions[sessionIndex].delete = true;
            } else {
                sessions.push({ sessionId, delete: true });
            }

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "save_sessions.php", true);
            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        showMessage("Session deleted successfully!", 'success');
                    } else {
                        showMessage("Error deleting session!", 'error');
                    }
                }
            };
            xhr.send(JSON.stringify(sessions));
        }
    }

    function showMessage(message, type) {
        const messageContainer = document.getElementById('message-container');
        messageContainer.textContent = message;
        messageContainer.className = type;
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 3000);
    }
    </script>

<?php

// Fetch user data
$sql = "SELECT username, club, `type-coach` FROM users WHERE id = ?";
 $stmt = $conn->prepare($sql);

  if ($stmt === false) {
   die(json_encode(["error" => "Error preparing the statement: " . $conn->error]));
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        die(json_encode(["error" => "User not found"]));
    } // Extract user data from result

    $userName = $user['username'];
    $club = $user['club'];
    $typeCoach = (int)$user['type-coach']; // Cast to integer
    $stmt->close(); // Close the statement

    // Check if the user is a coach
    if ($typeCoach !== 1) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
    }

// Ensure userId is available
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    die(json_encode(["error" => "User not logged in or invalid user ID"]));
}

$userId = $_SESSION['user_id'];

// Fetch user data
$sql = "SELECT username, club FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die(json_encode(["error" => "User not found"]));
}

$userName = $user['username'];
$club = $user['club'];
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

// Fetch timetable sessions from the database
$sql = "SELECT * FROM timetable WHERE club = ?";
$stmt = $conn->prepare($sql); // Prepare the SQL statement
   $stmt->bind_param("s", $club); // Bind the parameter to the placeholder
   $stmt->execute(); // Execute the prepared statement
   $result = $stmt->get_result(); // Get the result set from the executed statement

$sessions = array();

while ($row = mysqli_fetch_assoc($result)) {
    $day = $row['_day'];
    $time = $row['_time'];
    $sessions[$day][$time] = $row;
}

// Fetch pre-saved sessions where the sessions column is not empty
$sql = "SELECT id, name FROM presave WHERE sessions IS NOT NULL AND sessions != '' AND club = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
respondWithError("Failed to prepare statement for fetching pre-saved sessions.");
}

$stmt->bind_param('s', $club);
if (!$stmt->execute()) {
respondWithError("Failed to execute statement for fetching pre-saved sessions.");
}

// Get the result from the executed statement
$result = $stmt->get_result();

// Store the pre-saves in an array
$presaves = [];
while ($row = $result->fetch_assoc()) {
$presaves[] = $row;
}

$conn->close();

$times = ['04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
?>

</head>
<body>
<main>
<div id="message-container" style="display:none;"></div> <!-- Message container -->
    <h1>Coach's Site</h1>

<?php echo("<h2>Hello " . $userName . "</h2>"); ?>
<button style="background-color: red; color: white;" onclick="document.location='login.php'">Sign Out</button>

    <div class='week-navigation' style='text-align: center; margin: 20px 0;'>
        <button type='button' onclick='changeWeek(-1)' style='background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px;'>Previous Week</button>
        <button type='button' onclick='changeWeek(1)' style='background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>Next Week</button>
        <select id='pre-save-select' onchange='preSaves()' style='background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: auto;'>
            <option value='' type='default'>Pre-Saves:</option>
            <?php
            foreach ($presaves as $presave) {
                echo "<option value='" . htmlspecialchars($presave['name']) . "'>" . htmlspecialchars($presave['name']) . "</option>";
            }
            ?>
            <option value='add-new'>Add new</option>
        </select><br>
        <button type="button" onclick="document.location='sessionPlans.php'" style="margin-top: 10px;background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Session Plans</button>
        <button type="button" onclick="document.location='coachAttendance.php'" style="margin-top: 10px;background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Attendance Reports</button>
    </div>
    <?php
// Output the timetable with sessions
echo "<h1>Timetable:</h1>";
echo "<table id='timetable'>";
echo "<tr><th style='background-color: #002061; color: #ffff00;'>Time</th>";

foreach ($days as $day) {
    echo "<th style='background-color: #ffff00; color: #002061;'>$day</th>";
}

echo "</tr>";

for ($hour = 4; $hour <= 21; $hour++) {
    $time = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
    echo "<tr>";
    echo "<td style='background-color: #ffff00; color: #002061;'>$time</td>";

    // Output sessions for each day
    foreach ([
        'Monday ' . $weekDates[0],
        'Tuesday ' . $weekDates[1],
        'Wednesday ' . $weekDates[2],
        'Thursday ' . $weekDates[3],
        'Friday ' . $weekDates[4],
        'Saturday ' . $weekDates[5],
        'Sunday ' . $weekDates[6]
    ] as $day) {
        $cellId = $day . '-' . str_replace(':', '', $time);
        if (isset($sessions[$day][$time])) {
            $session = $sessions[$day][$time];
            $sessionId = $session['id'];
            $group = htmlspecialchars($session['_group']);
            $location = htmlspecialchars($session['_location']);
            $stime = htmlspecialchars($session['stime']);
            $etime = htmlspecialchars($session['etime']);
            $coach = htmlspecialchars($session['coach']);
            echo "<td style='background-color: #f2f2f2; border: 3px solid black;' id='$cellId' class='droppable' onclick='createSession(event)' ondrop='drop(event)' ondragover='allowDrop(event)'>
            <div class='session' draggable='true' data-id='$sessionId' ondragstart='drag(event)'>
                <a class='fa fa-close' style='color:red' data-id='$sessionId' onclick='deleteSession(event)'></a>
                <label for='group-$sessionId'>Group(s):</label>
                <input type='text' id='group-$sessionId' name='group' value='$group'><br>
                <label for='location-$sessionId'>Location:</label>
                <input type='text' id='location-$sessionId' name='location' value='$location'><br>
                <label for='stime-$sessionId'>Start Time:</label>
                <input type='time' id='stime-$sessionId' name='stime' value='$stime'><br>
                <label for='etime-$sessionId'>End Time:</label>
                <input type='time' id='etime-$sessionId' name='etime' value='$etime'>
                <label for='coach-$sessionId'>Coach:</label>
                <input type='text' id='coach-$sessionId' name='coach' value='$coach'>
            </div>";
        } else {
            echo "<td id='$cellId' class='droppable' onclick='createSession(event)' ondrop='drop(event)' ondragover='allowDrop(event)'></td>";
        }
    }

    echo "</tr>";
}

echo "</table>";
?>
<script>
function changeWeek(offset) {
    const params = new URLSearchParams(window.location.search);
    let weekOffset = parseInt(params.get('weekOffset') || '0');
    weekOffset += offset;
    params.set('weekOffset', weekOffset);
    window.location.search = params.toString();
}

function preSaves() {
    // Get the selected option (pre-save name) from the dropdown
    const selectedOption = document.querySelector('select').value;

    // Get the current session data from localStorage or an empty array if not found
    let sessionData = JSON.parse(localStorage.getItem('sessions')) || [];

    console.log('Session Data:', sessionData);  // Log the session data for debugging

    // Get the weekOffset from the URL
    const params = new URLSearchParams(window.location.search);
    let weekOffset = parseInt(params.get('weekOffset') || '0');  // Default to 0 if weekOffset is not present

    // Check if the user selected "add-new" to create a new pre-save
    if (selectedOption === 'add-new') {
        // Prompt the user for a name for the new pre-save
        const preSaveName = prompt("Enter a name for this pre-save:");
        if (preSaveName) {
            // Send the new pre-save data to save_presave.php via POST
            fetch('save_presave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name: preSaveName, sessions: sessionData })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    //alert("Pre-save saved successfully!");
					showMessage(data.message, 'success');
                } else {
                    //alert("Pre-save not saved successfully!");
					showMessage(data.message, 'error');
                    location.reload();  // Refresh the page to update the timetable
                }
            })
            .catch(error => console.error('Error:', error));
        }
    } else {
        // Load the selected pre-save and send weekOffset to load_presave.php via POST
        fetch('load_presave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name: selectedOption, weekOffset: weekOffset })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Session Data:', sessionData);  // Log the session data for debugging
            if (data.success) {
				showMessage(data.message, 'success');
                location.reload();  // Refresh the page to update the timetable
            } else {
				showMessage(data.message, 'error');
                location.reload();  // Refresh the page to update the timetable

            }
        })
        .catch(error => console.error('Error:', error));
    }
}

function showMessage(message, type) {
        const messageContainer = document.getElementById('message-container');
        messageContainer.textContent = message;
        messageContainer.className = type; // Add a class for styling
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 3000); // Hide after 3 seconds
    }
</script>
    </main>
    <div class="submit">
        <button type="button" onclick="saveDetails()">Save</button>
    </div>
    <p id="save-message" style="display:none;">Changes have been saved.</p>
</body>
</html>
