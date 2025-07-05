<?php
//clubAttendance.php
include './db.php';

$sessionID = $_GET['id'];

$sql = "SELECT * FROM timetable WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sessionID);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

$sessionGroups = array_map('trim', explode(',', $session['_group']));
//print_r($sessionGroups);

$sessionAthletes = array();

foreach ($sessionGroups as $group) {
    $group = explode('-', $group, 2);
    $sql = "SELECT item_name, list_name, split FROM groups WHERE list_name = ? AND club = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $group[0], $club);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $sessionAthletes[] = $row;
    }    
    $stmt->close();
}

$sql = "SELECT attendees, coachSignature FROM attendance WHERE sessionID = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(json_encode(["error" => "SQL prepare failed: " . $conn->error]));
}
$stmt->bind_param("i", $sessionID);
$stmt->execute();
$result = $stmt->get_result();

// Fetch the row
$attendees = $result->fetch_assoc();
$stmt->close();

$coachSignature = isset($attendees['coachSignature']) ? $attendees['coachSignature'] : null;

// If the attendees field exists and is not empty, process it
if ($attendees && !empty($attendees['attendees'])) {
    // Trim quotes and split the comma-separated list into an array
    $attendees = explode(',', trim($attendees['attendees'], '"'));
} else {
    $attendees = []; // Empty array if no attendees or NULL
}

//echo json_encode($attendees);

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
        $sessionIDs[$day][$time] = $row['id'];
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
<html>
<head>
    <title>Attendance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            margin-top: 20px;
        }
        form {
            width: 30%;
            margin-left: 17.5%;
            margin-right: 47.5%;
            border: 1px solid black;
        }
        input[type='checkbox'] {
            margin-right: 10px;
        }
        label {
            display: block;
            margin-bottom: 15px;
        }
        button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #002061;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        li {
            width: 90%;
            background-color:rgb(255, 255, 255);
        }
        .attendee {
            width: 90%;
            background-color:rgba(103, 255, 128, 0.77);
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
    </style>
</head>
<body>
<?php echo("<h2>Hello " . $userName . "</h2>"); ?>
    <div class='week-navigation'>
        <button style="background-color: red; color: white;" onclick="document.location='login'">Sign Out</button>
        <button style="background-color: blue; color: white;" onclick="document.location='admin'">Back</button>
        <button type='button' onclick='changeWeek(-1)' style='background-color: #002061; color: #ffffff;'>Previous Week</button>
        <button type='button' onclick='changeWeek(1)' style='background-color: #002061; color: #ffffff;'>Next Week</button>
        <select id='session-select' onchange='loadSessionAttendance()' style='background-color: #002061; color: #ffffff;'>
            <option value='' type='default'>Sessions:</option>
            <?php
            foreach ($timetable as $day => $times) {
                if (in_array($day, $days)) {
                    foreach ($times as $time => $sessionPlan) {
                        echo "<option value='" . htmlspecialchars($sessionIDs[$day][$time]) . "'>" . htmlspecialchars($sessionPlan) . "</option>";
                    }
                }
            }
            ?>
        </select><br>
    <h1>Attendance for <?php echo $session['_group']; echo ' ' . $session['_day'] . ' at ' . $session['stime'] . ' - ' . $session['etime']; ?> </h1>
    <ol>
            <?php
            $totalSwimmers = count($sessionAthletes);
            $checkedSwimmers = 0;
            foreach ($sessionAthletes as $swimmer) {
                $isChecked = in_array($swimmer['item_name'], $attendees) ? 'checked' : '';
                $attendee = $isChecked ? 'attendee' : '';
                echo "<li class='{$attendee}'>";
                // Use 'name="swimmer[]"' so multiple values can be collected in an array
                echo "<input type='checkbox' name='swimmer[]' value='{$swimmer['item_name']}' id='{$swimmer['item_name']}' $isChecked hidden>";
                echo "<label for='{$swimmer['item_name']}'>{$swimmer['item_name']} - {$swimmer['list_name']}-{$swimmer['split']}</label>";
                echo "</li>";
            }
            ?>
        </ol>
        <p id="attendance-counter"><?php echo "Attendance: $checkedSwimmers/$totalSwimmers (" . round(($checkedSwimmers/$totalSwimmers)*100) . "%)"; ?></p>
        
        <?php echo '<img src="' . $coachSignature . '" alt="Coach Signature" />'; ?>


    <script>
        function loadSessionAttendance() {
            const sessionID = document.getElementById('session-select').value;
            const params = new URLSearchParams(window.location.search);
            let weekOffset = parseInt(params.get('weekOffset') || '0');
            if (sessionID != '') {
                document.location = 'clubAttendance?id=' + sessionID + '&weekOffset=' + weekOffset;
            }
        }
        function changeWeek(offset) {
            const params = new URLSearchParams(window.location.search);
            let weekOffset = parseInt(params.get('weekOffset') || '0');
            weekOffset += offset;
            params.set('weekOffset', weekOffset);
            window.location.search = params.toString();
        }
        document.addEventListener('DOMContentLoaded', function() {
            var totalSwimmers = <?php echo $totalSwimmers; ?>;
            var checkedSwimmers = 0;
            var checkboxes = document.querySelectorAll('input[type="checkbox"]');

            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked) {
                    checkedSwimmers++;
                }
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        checkedSwimmers++;
                    } else {
                        checkedSwimmers--;
                    }
                    document.getElementById("attendance-counter").innerHTML = "Attendance: " + checkedSwimmers + "/" + totalSwimmers + " (" + Math.round((checkedSwimmers/totalSwimmers)*100) + "%)";
                });
            });
            document.getElementById("attendance-counter").innerHTML = "Attendance: " + checkedSwimmers + "/" + totalSwimmers + " (" + Math.round((checkedSwimmers/totalSwimmers)*100) + "%)";
        });
    </script>
</body>
</html>
