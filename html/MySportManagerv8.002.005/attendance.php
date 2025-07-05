<?php
//attendance.php
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
    $sql = "SELECT item_name, list_name, split FROM groups WHERE list_name = ? AND club = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $group, $club);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $sessionAthletes[] = $row;
    }    
    $stmt->close();
}

$sql = "SELECT attendees FROM attendance WHERE sessionID = ?";
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

// If the attendees field exists and is not empty, process it
if ($attendees && !empty($attendees['attendees'])) {
    // Trim quotes and split the comma-separated list into an array
    $attendees = explode(',', trim($attendees['attendees'], '"'));
} else {
    $attendees = []; // Empty array if no attendees or NULL
}

//echo json_encode($sessionAthletes);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['swimmer'])) {
    $swimmers = implode(',', $_POST['swimmer']);
    $attendance = json_encode($swimmers);

    echo "<br>SQL Query Debug:<br>";
    echo "Attendance Data: " . $attendance . "<br>";
    echo "Session ID: " . $sessionID . "<br>";
    echo "Club: " . $club . "<br>";

    $sql = "INSERT INTO attendance (attendees, sessionID, club) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE attendees = ?";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("siss", $attendance, $sessionID, $club, $attendance);

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    } else {
        echo "Attendance recorded successfully!";
    }

    $stmt->close();
}

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
    </style>
</head>
<body>
    <h1>Attendance for <?php echo $session['_group']; echo ' ' . $session['_day'] . ' at ' . $session['stime']; ?> </h1>
    <button onclick="document.location='dashboard.php'">Back To Dashboard</button>
    <form method="POST" action="">
        <ol>
            <?php
            $totalSwimmers = count($sessionAthletes);
            $checkedSwimmers = 0;
            foreach ($sessionAthletes as $swimmer) {
                $isChecked = in_array($swimmer['item_name'], $attendees) ? 'checked' : '';
                echo "<li>";
                // Use 'name="swimmer[]"' so multiple values can be collected in an array
                echo "<input type='checkbox' name='swimmer[]' value='{$swimmer['item_name']}' id='{$swimmer['item_name']}' $isChecked>";
                echo "<label for='{$swimmer['item_name']}'>{$swimmer['item_name']} - {$swimmer['list_name']}-{$swimmer['split']}</label>";
                echo "</li>";
            }
            ?>
        </ol>
        <button name='attendance' type="submit">Submit</button>
        <p id="attendance-counter"><?php echo "Attendance: $checkedSwimmers/$totalSwimmers (" . round(($checkedSwimmers/$totalSwimmers)*100) . "%)"; ?></p>
    </form>
    <h2>Coach Sign Below</h2>
    <canvas id="signatureCanvas" style='background-color:rgba(196, 231, 247, 0.7); margin: 20px;' width="400" height="200"></canvas>
    <div class="btn-container">
        <button onclick="clearCanvas()">Clear</button>
        <button onclick="saveSignature()">Save</button>
    </div>
    <script>
    const canvas = document.getElementById("signatureCanvas");
    const ctx = canvas.getContext("2d");

    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.strokeStyle = "black";

    let drawing = false;

    function getPosition(event) {
        let x, y;
        if (event.touches) { // For touch devices
            let touch = event.touches[0];
            x = touch.clientX - canvas.getBoundingClientRect().left;
            y = touch.clientY - canvas.getBoundingClientRect().top;
        } else { // For mouse
            x = event.offsetX;
            y = event.offsetY;
        }
        return { x, y };
    }

    // Start drawing
    function startDrawing(event) {
        event.preventDefault();
        drawing = true;
        let pos = getPosition(event);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    // Draw
    function draw(event) {
        if (!drawing) return;
        event.preventDefault();
        let pos = getPosition(event);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    // Stop drawing
    function stopDrawing() {
        drawing = false;
    }

    // Clear the canvas
    function clearCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    // Save signature
    function saveSignature() {
        const dataURL = canvas.toDataURL("image/png");
        const session = "<?php echo $session['_day'] . '_' . $session['stime']; ?>";
        const link = document.createElement("a");
        link.href = dataURL;
        link.download = "signature_" + session + ".png";
        
        const formData = new URLSearchParams();
        formData.append('coach_signature', dataURL);
        formData.append('sessionID', "<?php echo htmlspecialchars($sessionID, ENT_QUOTES, 'UTF-8'); ?>");

        fetch('processCoachSignature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Signature saved successfully!");
            } else {
                alert("Error saving signature: " + data.error);
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            alert("Request failed. Please check console for details.");
        });
    }

    // Event Listeners for Mouse
    canvas.addEventListener("mousedown", startDrawing);
    canvas.addEventListener("mousemove", draw);
    canvas.addEventListener("mouseup", stopDrawing);
    canvas.addEventListener("mouseleave", stopDrawing);

    // Event Listeners for Touch
    canvas.addEventListener("touchstart", startDrawing);
    canvas.addEventListener("touchmove", draw);
    canvas.addEventListener("touchend", stopDrawing);

    </script>
    <script>
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
