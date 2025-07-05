<?php
include __DIR__ . '/db';

// Get the input data
$data = json_decode(file_get_contents('php://input'), true);

// Get the weekOffset from the URL
$weekOffset = isset($data['weekOffset']) ? intval($data['weekOffset']) : 0;

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
    $weekDates[$i] = $monday->format('d/m/y'); // Use full year format for consistency in DB
    $monday->modify('+1 day');
}

// Map days of the week to the generated dates
$days = [
    'Monday' => $weekDates[0],
    'Tuesday' => $weekDates[1],
    'Wednesday' => $weekDates[2],
    'Thursday' => $weekDates[3],
    'Friday' => $weekDates[4],
    'Saturday' => $weekDates[5],
    'Sunday' => $weekDates[6]
];

// Validate input
if (is_array($data) && isset($data['name'])) {
    $name = $data['name'];

    // Prepare and execute query to fetch pre-save data
    $stmt = $conn->prepare("SELECT sessions FROM presave WHERE name = ?");
    if ($stmt === false) {
        die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
    }
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $sessionDataJson = $row['sessions'];

        // Decode the session data JSON
        $sessionDataArray = json_decode($sessionDataJson, true);

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

$club = $user['club'];

        if (json_last_error() === JSON_ERROR_NONE) {
            // Iterate over session data and update the 'day' field
            foreach ($sessionDataArray as &$session) {
                list($day, $date) = explode(' ', $session['day']);
                $newdate = isset($days[$day]) ? $days[$day] : $date;
                $dayWithNewDate = $day . " " . $newdate;

                // Prepare SQL statement for inserting into the timetable
                $stmt = $conn->prepare("INSERT INTO timetable (_day, _time, stime, etime, _group, _location, club) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
                }

                // Bind parameters and execute the insert statement
                $time = $session['time'];
                $stime = $session['stime'];
                $etime = $session['etime'];
                $group = $session['group'];
                $location = $session['location'];
                $club = $club;
                

                $stmt->bind_param("sssssss", $dayWithNewDate, $time, $stime, $etime, $group, $location, $club);
                if (!$stmt->execute()) {
                    echo json_encode(["status" => "error", "message" => "Failed to insert session: " . $stmt->error]);
                    exit;
                }
            }

            echo json_encode(["status" => "success", "message" => "Sessions successfully inserted."]);
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to decode session data JSON: " . json_last_error_msg()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Pre-save not found."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}

$conn->close();
?>
