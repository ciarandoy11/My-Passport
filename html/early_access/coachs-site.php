<?php
//coachs-site.php
session_start();
include './db.php';

function respondWithError($message) {
    header('Content-Type: application/json');
    die(json_encode(["error" => $message]));
}

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
            padding: 0;
            position: relative;
            height: 100%;
        }
        .session {
            background-color: white;
            padding: 10px;
            border-radius: 0;
            box-shadow: none;
            margin: 0;
            height: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            border: 3px solid black;
        }
        .session input[type="text"],
        .session input[type="time"] {
            width: 100%;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .session label {
            display: block;
            margin-top: 8px;
            color: #002061;
            font-weight: bold;
        }
        .fa-close {
            position: absolute;
            top: 5px;
            right: 5px;
            margin: 0;
            cursor: pointer;
            z-index: 1;
        }
        .submit {
            text-align: center;
            margin: 20px 0;
        }
        #message-container {
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 65%;
            padding: 15px;
            text-align: center;
            display: none;
            border-radius: 5px;
            z-index: 1000;
        }
        #message-container.success {
            background-color: #4CAF50;
            color: white;
        }
        #message-container.error {
            background-color: #f44336;
            color: white;
        }
        .activity-type {
            margin: 25px 0 10px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .activity-type label {
            display: inline-block;
            margin-right: 15px;
            cursor: pointer;
            font-weight: normal;
        }
        .activity-type input[type="radio"] {
            margin-right: 5px;
            cursor: pointer;
        }
        .activity-type input[type="radio"] + label {
            color: #002061;
            transition: color 0.3s ease;
        }
        .activity-type input[type="radio"]:checked + label {
            color: #002061;
            font-weight: bold;
        }
        .activity-type input[type="radio"]:hover + label {
            color: #003380;
        }
        .droppable {
            min-height: 100px;
            transition: background-color 0.3s ease;
        }
        .droppable:hover {
            background-color: #e6e6e6;
        }
        .session-type-container {
            display: flex;
            align-items: center;
            margin: 5px 0;
            gap: 10px;
        }
        .session-type-radio {
            display: none;
        }
        .session-type-label {
            padding: 8px 16px;
            border: 2px solid #002061;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
            color: #002061;
            font-weight: bold;
        }
        .session-type-radio:checked + .session-type-label {
            background-color: #002061;
            color: white;
        }
        .session-type-label:hover {
            background-color: #e6e6e6;
        }
        .session-type-radio:checked + .session-type-label:hover {
            background-color: #002061;
        }
    </style>
    <script>
            let sessions = [];
            let autoUpdateInterval;
            let lastUpdateTime = 0;
    
            function allowDrop(e) { e.preventDefault(); }
    
            function drag(e) {
                e.dataTransfer.setData("text", e.target.dataset.id);
            }
    
            function drop(e) {
                e.preventDefault();
                const sessionId = e.dataTransfer.getData("text");
                const targetCell = e.target.closest('td');
                const sessionElement = document.querySelector(`div[data-id="${sessionId}"]`);
                if (!targetCell || !sessionElement) return;
    
                // Get the current session data before moving
                const [oldDay, oldRawTime] = sessionElement.parentElement.id.split('-');
                const oldTime = `${oldRawTime.slice(0, 2)}:${oldRawTime.slice(2)}`;
    
                // Move the element
                targetCell.appendChild(sessionElement);
    
                // Get new cell data
                const [newDay, newRawTime] = targetCell.id.split('-');
                const newTime = `${newRawTime.slice(0, 2)}:${newRawTime.slice(2)}`;
    
                // Update session data
                const session = {
                    sessionId: sessionId,
                    day: newDay,
                    time: newTime,
                    group: sessionElement.querySelector('input[name="group"]')?.value || '',
                    location: sessionElement.querySelector('input[name="location"]')?.value || '',
                    stime: sessionElement.querySelector('input[name="stime"]')?.value || '',
                    etime: sessionElement.querySelector('input[name="etime"]')?.value || '',
                    coach: sessionElement.querySelector('input[name="coach"]')?.value || '',
                    otherActivity: parseInt(sessionElement.querySelector(`input[name="otherActivity-${sessionId}"]:checked`)?.value || '0')
                };
    
                // Update sessions array
                const index = sessions.findIndex(s => s.sessionId === sessionId);
                if (index >= 0) {
                    sessions[index] = session;
                } else {
                    sessions.push(session);
                }
    
                // Save to server
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "save_sessions.php", true);
                xhr.setRequestHeader("Content-Type", "application/json");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    showMessage('Session moved successfully', 'success');
                                } else {
                                    showMessage('Error moving session: ' + response.message, 'error');
                                }
                            } catch (e) {
                                showMessage('Error processing server response', 'error');
                            }
                        } else {
                            showMessage('Error moving session', 'error');
                        }
                    }
                };
                xhr.send(JSON.stringify([session]));
            }
    
            function createSession(e) {
                const targetCell = e.target.closest('td');
                if (!targetCell || targetCell.querySelector('.session')) return;
    
                const [day, time] = targetCell.id.split('-');
                const sessionTime = `${time.slice(0, 2)}:${time.slice(2)}`;
                const sessionId = `${Date.now()}`;
    
                const sessionElement = document.createElement('div');
                sessionElement.className = 'session';
                sessionElement.dataset.id = sessionId;
                sessionElement.draggable = true;
                sessionElement.innerHTML = buildSessionHTML(sessionId, '', '', sessionTime, getEndTime(sessionTime), 'N/a', '0');
                sessionElement.addEventListener('dragstart', drag);
    
                targetCell.appendChild(sessionElement);
                sessions.push({ sessionId, day, time: sessionTime, group: '', location: '', stime: sessionTime, etime: getEndTime(sessionTime), coach: 'N/a', otherActivity: '0' });
                saveDetails();
            }
    
            function getEndTime(start) {
                const [h, m] = start.split(":").map(Number);
                const date = new Date();
                date.setHours(h, m + 60);
                return date.toTimeString().substring(0, 5);
            }
    
            function buildSessionHTML(id, group, location, stime, etime, coach, otherActivity) {
                // Ensure otherActivity is an integer
                otherActivity = parseInt(otherActivity) || 0;
                return `
                    <a class='fa fa-close' style='color:red' onclick='deleteSession(event)'></a>
                    <div class="activity-type">
                        <label>Activity Type:</label><br>
                        <label>
                            <input type="radio" name="otherActivity-${id}" value="0" ${otherActivity === 0 ? 'checked' : ''}>
                            Training
                        </label><br>
                        <label>
                            <input type="radio" name="otherActivity-${id}" value="1" ${otherActivity === 1 ? 'checked' : ''}>
                            Other
                        </label>
                    </div>
                    <label>Group(s):</label><input type='text' name='group' value='${group}'><br>
                    <label>Location:</label><input type='text' name='location' value='${location}'><br>
                    <label>Start Time:</label><input type='time' name='stime' value='${stime}'><br>
                    <label>End Time:</label><input type='time' name='etime' value='${etime}'>
                    <label>Coach:</label><input type='text' name='coach' value='${coach}'>
                `;
            }
    
            function updateSessionData(el, cellId) {
                const [day, rawTime] = cellId.split('-');
                const time = `${rawTime.slice(0, 2)}:${rawTime.slice(2)}`;
    
                const inputs = el.querySelectorAll('input, select');
                const otherActivityValue = parseInt(el.querySelector(`input[name="otherActivity-${el.dataset.id}"]:checked`)?.value || '0');
    
                const session = {
                    sessionId: el.dataset.id,
                    day,
                    time,
                    group: inputs.namedItem('group')?.value || '',
                    location: inputs.namedItem('location')?.value || '',
                    stime: inputs.namedItem('stime')?.value || '',
                    etime: inputs.namedItem('etime')?.value || '',
                    coach: inputs.namedItem('coach')?.value || '',
                    otherActivity: otherActivityValue
                };
    
                const index = sessions.findIndex(s => s.sessionId === session.sessionId);
                if (index >= 0) {
                    sessions[index] = session;
                } else {
                    sessions.push(session);
                }
            }
    
            function updateTimetable() {
                const xhr = new XMLHttpRequest();
                xhr.open("GET", `get_timetable.php?lastUpdate=${lastUpdateTime}`, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (res.status === 'success' && res.sessions?.length) {
                                renderSessions(res.sessions);
                                lastUpdateTime = res.timestamp || Date.now();
                            }
                        } catch (e) {
                            console.error('JSON error:', e);
                        }
                    }
                };
                xhr.send();
            }
    
            function renderSessions(sessionList) {
                document.querySelectorAll('.session').forEach(s => s.remove());
                sessionList.forEach(s => {
                    const cell = document.getElementById(`${s._day}-${s._time.replace(':', '')}`);
                    if (!cell) return;
    
                    const el = document.createElement('div');
                    el.className = 'session';
                    el.dataset.id = s.id;
                    el.draggable = true;
                    // Convert otherActivity to integer for comparison
                    const otherActivity = parseInt(s.otherActivity) || 0;
                    el.innerHTML = buildSessionHTML(s.id, s._group, s._location, s.stime, s.etime, s.coach, otherActivity);
                    el.addEventListener('dragstart', drag);
                    cell.appendChild(el);
                });
    
                document.querySelectorAll('input, select').forEach(input => {
                    input.addEventListener('input', saveDetails);
                });
            }
    
            function saveDetails() {
                const sessionData = Array.from(document.querySelectorAll('.session')).map(el => {
                    const cellId = el.parentElement.id;
                    const [day, rawTime] = cellId.split('-');
                    const time = `${rawTime.slice(0, 2)}:${rawTime.slice(2)}`;
    
                    return {
                        sessionId: el.dataset.id,
                        day,
                        time,
                        group: el.querySelector('input[name="group"]')?.value || '',
                        location: el.querySelector('input[name="location"]')?.value || '',
                        stime: el.querySelector('input[name="stime"]')?.value || '',
                        etime: el.querySelector('input[name="etime"]')?.value || '',
                        coach: el.querySelector('input[name="coach"]')?.value || '',
                        otherActivity: parseInt(el.querySelector(`input[name="otherActivity-${el.dataset.id}"]:checked`)?.value || '0')
                    };
                });
    
                // Load existing sessions from localStorage to avoid undoing deletes
                const existingSessions = JSON.parse(localStorage.getItem('sessions')) || [];
                const updatedSessions = sessionData.map(session => {
                    const existingSession = existingSessions.find(es => es.sessionId === session.sessionId);
                    return existingSession ? { ...existingSession, ...session } : session;
                });
    
                localStorage.setItem('sessions', JSON.stringify(updatedSessions));
    
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "save_sessions.php", true);
                xhr.setRequestHeader("Content-Type", "application/json");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    showMessage('Changes saved successfully', 'success');
                                } else {
                                    showMessage('Error saving changes: ' + response.message, 'error');
                                }
                            } catch (e) {
                                showMessage('Error processing server response', 'error');
                            }
                        } else {
                            showMessage('Error saving changes', 'error');
                        }
                    }
                };
                xhr.send(JSON.stringify(updatedSessions));
            }
    
            function deleteSession(e) {
                const el = e.target.closest('.session');
                if (el) {
                    // Mark the session for deletion
                    const sessionData = JSON.parse(localStorage.getItem('sessions'));
                    const sessionId = el.dataset.id;
                    sessionData.forEach(session => {
                        if (session.sessionId === sessionId) {
                            session.delete = true;
                        }
                    });
                    localStorage.setItem('sessions', JSON.stringify(sessionData));
                    console.log(sessionData);
                    saveDetails();
                    el.remove();
                }
            }
    
            function startAutoUpdate() {
                if (autoUpdateInterval) clearInterval(autoUpdateInterval);
                autoUpdateInterval = setInterval(updateTimetable, 5000);
            }
    
            window.onload = () => {
                document.querySelectorAll('input, select').forEach(input => {
                    input.addEventListener('input', saveDetails);
                });
                startAutoUpdate();
                updateTimetable();
            };
        </script>
<?php

// Ensure userId is available
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    die(json_encode(["error" => "User not logged in or invalid user ID"]));
}

$userId = $_SESSION['user_id'];

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
}

// Extract user data from result
$userName = $user['username'];
$club = $user['club'];
$typeCoach = (int)$user['type-coach']; // Cast to integer
$stmt->close(); // Close the statement

// Check if the user is a coach
if ($typeCoach !== 1) {
    header("Location: login"); // Redirect to login if not logged in
    exit();
}

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
<button style="background-color: red; color: white;" onclick="document.location='login'">Sign Out</button>

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
        <button type="button" onclick="document.location='sessionPlans'" style="margin-top: 10px;background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Session Plans</button>
        <button type="button" onclick="document.location='coachAttendance'" style="margin-top: 10px;background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Attendance Reports</button>
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
            $otherActivity = $session['otherActivity'];
            $group = htmlspecialchars($session['_group']);
            $location = htmlspecialchars($session['_location']);
            $stime = htmlspecialchars($session['stime']);
            $etime = htmlspecialchars($session['etime']);
            $coach = htmlspecialchars($session['coach']);
            echo "<td style='background-color: #f2f2f2;' id='$cellId' class='droppable' onclick='createSession(event)' ondrop='drop(event)' ondragover='allowDrop(event)'>
            <div class='session' draggable='true' data-id='$sessionId' ondragstart='drag(event)'>
                <a class='fa fa-close' style='color:red' data-id='$sessionId' onclick='deleteSession(event)'></a>
                <div class='activity-type'>
                    <label>Activity Type:</label><br>
                    <label>
                        <input type='radio' name='otherActivity-$sessionId' value='0' " . (intval($otherActivity) === 1 ? '' : 'checked') . ">
                        Training
                    </label><br>
                    <label>
                        <input type='radio' name='otherActivity-$sessionId' value='1' " . (intval($otherActivity) === 1 ? 'checked' : '') . ">
                        Other
                    </label>
                </div>
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
