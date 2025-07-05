<?php
//admin.php
include './db.php';

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

// Check the action being requested
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'get_unacceptable_pods':
        $group = isset($_POST['group']) ? $_POST['group'] : '';
        $day = isset($_POST['day']) ? $_POST['day'] : '';
        getUnacceptablePods($conn, $group, $day, $club);
        break;

    case 'delete_item':
        deleteItem($conn, $club);
        break;

    case 'set_pod_indexes':
		$selectedPod = isset($POST['selected_pod']) ? $_POST['selected_pod'] : '';
		setPodIndexes($conn, $club, $selectedPod);
		break;
}

$sql = "SELECT `type-admin` FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$stmt->close();  // Correctly closes the statement

$typeAdmin = (int)$row['type-admin'];  // Cast to integer

// Check if the user is an admin or has a valid session
if ($typeAdmin !== 1) {  // Correct comparison with integer
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

function setPodIndexes($conn, $club) {
    // Use HTTP method check to avoid direct access
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(["success" => false, "message" => "Invalid request method."]);
        return; // Stop on invalid request
    }

    // Assuming selectedPod is coming from POST
    if (!isset($_POST['selected_pod'])) {
        echo json_encode(["success" => false, "message" => "selected_pod is missing."]);
        return; // Stop if selected_pod is not set
    }

    $selectedPod = $_POST['selected_pod'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update pod_index for the club
        $stmt = $conn->prepare("UPDATE `groups` SET `pod_index` = `pod_index` + 1 WHERE `club` = ?");
        $stmt->bind_param('s', $club);

        if (!$stmt->execute()) {
            throw new Exception("Error updating pod indexes: " . $stmt->error);
        }
        $stmt->close();

        // Set the pod_index of the selected item to 0
        $stmt = $conn->prepare("UPDATE `groups` SET `pod_index` = 0 WHERE `item_name` = ?");
        $stmt->bind_param('s', $selectedPod);

        if (!$stmt->execute()) {
            throw new Exception("Error resetting pod index for selected item: " . $stmt->error);
        }
        $stmt->close();

        // Get all items in the club
        $stmt = $conn->prepare("SELECT siblings FROM `groups` WHERE `item_name` = ? AND club = ?");
        $stmt->bind_param('ss', $selectedPod, $club);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $siblings = $row['siblings'];
        } else {
            $siblings = '';
        }
        $stmt->close();

        $siblingNames = explode(', ', $siblings);
        $siblingCount = count($siblingNames) - 1;

        // Update sibling info
        foreach ($siblingNames as $sibling) {
            $stmt = $conn->prepare("UPDATE `groups` SET `sibling_index` = `sibling_index` - 1, `pod_index` = 0 WHERE `item_name` = ?");
            $stmt->bind_param('s', $sibling);

            if (!$stmt->execute()) {
                throw new Exception("Error updating sibling indexes: " . $stmt->error);
            }
            $stmt->close();
        }

        // Set the sibling index of the selected item to the calculated value
        $stmt = $conn->prepare("UPDATE `groups` SET `sibling_index` = ? WHERE `item_name` = ?");
        $stmt->bind_param('is', $siblingCount, $selectedPod);

        if (!$stmt->execute()) {
            throw new Exception("Error resetting sibling index for selected item: " . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        echo json_encode(["success" => true, "message" => "Pod indexes updated successfully. Sib Count: " . $siblingCount]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }

    exit(); // Ensure no additional output is sent
}

function updatePodInTimetable($conn, $club) {
    $sessionId = $_POST['session_id'];
    $selectedPod = $_POST['selected_pod'];

    //if (empty($sessionId) || empty($selectedPod) || empty($club)) {
  //      respondWithError("Session ID, selected pod, or club is missing.");
//    }

    // Update timetable with the selected pod
    $stmt = $conn->prepare("UPDATE timetable SET pod = ? WHERE id = ?");
    if (!$stmt) {
        respondWithError("Failed to prepare statement for timetable update.");
    }
    $stmt->bind_param('si', $selectedPod, $sessionId);
    if (!$stmt->execute()) {
        respondWithError("Failed to execute statement for timetable update.");
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(["success" => true, "message" => "Pod updated successfully"]);
    exit;
}

// Function to handle errors and respond with a JSON message
function respondWithError($message) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => $message]);
    exit;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn, $club);
    $conn->close();  // Close connection after handling POST
    exit;  // Exit after handling POST request
}

// Ensure userId is available and valid
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    die(json_encode(["error" => "User not logged in or invalid user ID"]));
}

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


// Fetch timetable data
$sessions = prepareSessions(fetchTimetable($conn, $club));
$lists = fetchGroups($conn, $club);
$conn->close();  // Close connection after fetching data

function handlePostRequest($conn, $club) {
    error_log(print_r($_POST, true));
    if (isset($_POST['action']) && $_POST['action'] === 'delete_item') {
        deleteItem($conn, $club);
    } elseif (isset($_POST['new_list_name'])) {
        addNewList($conn, $club);
    } elseif (isset($_POST['new_item_name'])) {
        addNewItemToList($conn, $club);
    } elseif (isset($_POST['group-change'])) {
        updateItemName($conn, $club);
    } elseif (isset($_POST['session_id']) && isset($_POST['selected_pod'])) {
        updatePodInTimetable($conn, $club);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'get_unacceptable_pods') {
        getUnacceptablePods($conn, $group, $day, $club);
    } elseif (isset($_POST['csvFile'])) {
	} else {
        echo json_encode(["success" => false, "message" => "Invalid request"]);
    }
}

function addNewList($conn, $club) {
    $listName = $_POST['new_list_name'];
    if (!empty($listName)) {
        $stmt = $conn->prepare("INSERT INTO groups (list_name, item_name, club) VALUES (?, '', ?)");
        $stmt->bind_param('ss', $listName, $club);
        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "message" => "Error creating list: " . $stmt->error]);
        } else {
            echo json_encode(["success" => true, "message" => "List created successfully."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "List name is empty"]);
    }
}

function addNewItemToList($conn, $club) {
// Fetch input data for list name and item name
$listName = $_POST['list_name'] ?? '';
$itemName = $_POST['new_item_name'] ?? '';

// Ensure both list name and item name are provided
if (!empty($listName) && !empty($itemName)) {

// Insert the new item into the `groups` table
$stmt = $conn->prepare("INSERT INTO groups (list_name, item_name, pod_index, club) VALUES (?, ?, '0', ?)");
$stmt->bind_param('sss', $listName, $itemName, $club);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Error creating item: " . $stmt->error]);
    return; // Stop on error

}
echo json_encode(["success" => true, "message" => "Item(s) created successfully."]);
$stmt->close();
} else {
 //Return error if required fields are missing
echo json_encode(["success" => false, "message" => "List name or item name is missing."]);
return; // Stop on error
}
header("Refresh:0");
}

 function updateItemName($conn, $club) {
 // Sanitize POST data
$id = isset($_POST['item_id']) ? $_POST['item_id'] : null;
$UpdatedItemName = $_POST['update_item_name'];
$podExemption = isset($_POST['pod_exemption']) ? 1 : 0;
$podDayExemption = isset($_POST['pod_day_exemption']) ? 1 : 0;
$dayExemption = isset($_POST['day_exemption']) ? implode(',', $_POST['day_exemption']) : '';
$overide = isset($_POST['overide']) ? 1 : 0;
$originalListName = filter_input(INPUT_POST, 'list_name', FILTER_SANITIZE_STRING);
$UpdatedListName = $_POST['group-change'] ?? $originalListName;
$UpdatedListName = isset($_POST['group-change']) ? $_POST['group-change'] : $originalListName;
$groupSplit = isset($_POST['split']) ? $_POST['split'] : NULL;

// Database update if both item names are not empty
if (!empty($originalListName) && !empty($id)) {
    $stmt = $conn->prepare("UPDATE groups SET item_name = ?, list_name = ?, split = ?, pod_exemption = ?, pod_day_exemption = ?, day_exemption = ?, overide = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Failed to prepare update statement."]);
        exit;
    }

	$stmt->bind_param('sssiisii', $UpdatedItemName, $UpdatedListName, $groupSplit, $podExemption, $podDayExemption, $dayExemption, $overide, $id);

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Error updating item: " . $stmt->error]);
    } else {
        echo json_encode(["success" => true, "message" => "Item updated successfully.", "no_reload" => true]);
    } $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Item name is empty"]);
}
}

function prepareSessions($result) {
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $day = ($row['_day']);
        $time = $row['_time'];
        $sessions[$day][$time] = $row;
    }
    return $sessions;
}

function fetchTimetable($conn, $club) {
    global $days; // Add this if $days is defined outside the function
    $placeholders = implode(',', array_fill(0, count($days), '?'));
    $sql = "SELECT * FROM timetable WHERE `_day` IN ($placeholders) AND club = ?";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($days)) . 's';
    $params = array_merge($days, [$club]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

function fetchGroups($conn, $club) {
    // First, fetch all required data from groups
    $sql = "SELECT id, list_name, item_name, dob, inSchool, pod_exemption, pod_day_exemption, day_exemption, overide, split
            FROM groups WHERE club = ?
            ORDER BY list_name, split";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die('Error preparing the statement: ' . $conn->error);
    }

    $stmt->bind_param("s", $club);
    $stmt->execute();
    $result = $stmt->get_result();

    $lists = [];
    $updates = []; // Array to store IDs that need updating

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id = (int)$row['id'];
            $listName = htmlspecialchars($row['list_name']);
            $itemName = htmlspecialchars($row['item_name']);
            $swimmerDob = $row['dob'];
            $swimmerInSchool = (int)$row['inSchool'];
            $podExemption = (int)$row['pod_exemption'];
            $podDayExemption = (int)$row['pod_day_exemption'];
            $dayExemption = htmlspecialchars($row['day_exemption']);
            $overide = (int)$row['overide'];
            $split = $row['split'];

            // Check if the swimmer should be granted pod_exemption
            if (!empty($swimmerDob) && strtotime($swimmerDob) < strtotime('-18 years') && $swimmerInSchool === 0 && $overide === 0) {
                $updates[] = $id; // Store ID for batch update
            }

            // Organize data into lists
            if (!isset($lists[$listName])) {
                $lists[$listName] = [];
            }
            if (!empty($itemName)) {
                $lists[$listName][] = [
                    'id' => $id,
                    'item_name' => $itemName,
                    'pod_exemption' => $podExemption,
                    'pod_day_exemption' => $podDayExemption,
                    'day_exemption' => $dayExemption,
                    'overide' => $overide,
                    'split' => $split
                ];
            }
        }
    }

    $stmt->close(); // Close the first statement

    // Batch update pod_exemption for eligible swimmers
    if (!empty($updates)) {
        $placeholders = implode(',', array_fill(0, count($updates), '?'));
        $updateSql = "UPDATE groups SET pod_exemption = 1 WHERE id IN ($placeholders)";
        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt === false) {
            die('Error preparing batch update statement: ' . $conn->error);
        }

        // Bind parameters dynamically
        $types = str_repeat('i', count($updates));
        $updateStmt->bind_param($types, ...$updates);
        $updateStmt->execute();
        $updateStmt->close();
    }

    return $lists;
}

function deleteItem($conn, $club) {
// Fetch POST data and handle default values
$deletedItemRaw = isset($_POST['deleted_item']) ? $_POST['deleted_item'] : '';

// Convert deletedItemRaw to an array if it is a string and trim spaces
$deletedItem = is_array($deletedItemRaw) ? array_map('trim', $deletedItemRaw) : array_map('trim', explode(',', $deletedItemRaw));

// Check if the 'id' is set in the POST data and is a numeric value
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $itemId = (int)$_POST['id'];

    // Prepare the SQL statement to delete the item with the specified ID
    $stmt = $conn->prepare('DELETE FROM groups WHERE id = ?');
    $stmt->bind_param('i', $itemId);

    // Execute the statement and check if the deletion was successful
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
    }

    // Close the statement
    $stmt->close();
} else {
    // Handle cases where the 'id' is missing or not numeric
    echo json_encode(['success' => false, 'message' => 'Invalid request, item ID missing or not an integer']);
}
}

function getUnacceptablePods($conn, $group, $day, $club) {
    // Convert the input strings into arrays
    $groupsArray = explode(', ', $group);

    // Prepare and execute the SQL statement to get exempted pods
    $stmt = $conn->prepare("SELECT item_name FROM groups WHERE (day_exemption LIKE ? OR pod_exemption = '1') AND club = ? ORDER BY pod_index DESC");
    $dayParam = "%$day%"; // Adjust for LIKE clause
    $stmt->bind_param("ss", $dayParam, $club);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch all results into an array
    $exemptPods = [];
    while ($row = $result->fetch_assoc()) {
        $exemptPods[] = $row['item_name'];
    }

    // Prepare and execute statement to get swimmers in the specified group
    $swimmerConditions = [];
    $params = [];
    foreach ($groupsArray as $groupItem) {
        // Check if the group has a split (e.g., Group1-A)
        if (strpos($groupItem, '-') !== false) {
            // Split the group into list_name and split (e.g., Group1-A -> Group1, A)
            list($list_name, $split) = explode('-', $groupItem);
            $swimmerConditions[] = "(list_name = ? AND split = ?)";
            array_push($params, $list_name, $split); // Add list_name and split to the params
        } else {
            // If there's no split (e.g., Group1), only filter by list_name
            $swimmerConditions[] = "list_name = ?";
            array_push($params, $groupItem); // Add list_name to the params
        }
    }

    // Join all conditions with OR
    $placeholders = implode(' OR ', $swimmerConditions);

    $sql = "SELECT item_name FROM `groups` WHERE `sibling_index` = '0' AND ($placeholders) AND club = ? ORDER BY pod_index DESC";
    $stmt = $conn->prepare($sql);

    // Add the club to params
    array_push($params, $club);
    // Prepare the parameter binding
    $types = str_repeat('s', count($params)); // 's' for each parameter
    $stmt->bind_param($types, ...$params); // Dynamically bind the parameters

    // Execute the statement
    $stmt->execute();

    // Get the result
    $result = $stmt->get_result();

    // Initialize an array to hold swimmer names
    $swimmers = [];
    while ($row = $result->fetch_assoc()) {
        $swimmers[] = $row['item_name'];
    }

    // Initialize an array to hold acceptable swimmers
    $acceptableSwimmers = [];

    // Iterate over the swimmer data
    foreach ($swimmers as $swimmerName) {
        // Check if the swimmer's pod is not in exemptPods
        if (!in_array($swimmerName, $exemptPods)) {
            $acceptableSwimmers[] = $swimmerName; // Add to acceptable swimmers
        }
    }

    // Close the statement
    $stmt->close();

    // Check for empty result set, just for debugging
    if (empty($acceptableSwimmers)) {
        // Uncomment the following line for debugging purposes.
        // echo json_encode(["message" => "No acceptable swimmers found."]);
    }

    // Return the result as a JSON response
    header('Content-Type: application/json');
    // Make sure nothing else is echoed after this
    echo json_encode($acceptableSwimmers);
    exit;
}

$times = ['04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - My Sport Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /* Root colors */
        :root {
            --primary-blue: #007BFF;
            --dark-blue: #0056b3;
            --black: #000000;
            --light-grey: #f5f5f5;
            --white: #ffffff;
            --highlight-red: #cb0c1f;
            --highlight-green: #28a745;
        }

        /* Reset and base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
            background-size: 100% 100%;
            background-position: left;
            overflow-x: hidden;
            animation: backgroundAnimation 10s infinite alternate ease-in-out;
        }

        @keyframes backgroundAnimation {
            0% { background-position: left; }
            100% { background-position: right; }
        }

        /* Header */
        header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: var(--white);
            padding: 30px 20px;
            text-align: center;
            animation: slideDown 1s ease-out forwards;
        }

        header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            animation: floatText 3s ease-in-out infinite alternate;
        }

        header i {
            font-size: 1.3rem;
            opacity: 0.9;
        }

        /* Table Styles */
        .table-wrapper {
            margin-top: 20px;
        }

        #timetable {
            border-collapse: collapse;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-family: Arial, sans-serif;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-top: 0px solid white;
            border-bottom: 0px solid white;
        }

        #timetable thead {
            width: auto;
            background-color: #0073e6;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .session {
            background-color: #ffffff;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            margin: 4px 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            transition: background-color 0.2s;
        }

        .session:hover {
            background-color: #e0e0e0;
        }

        .session button {
            min-height: 30px;
            min-width: 80px;
            background-color: #0a6bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .session button:hover {
            background-color: #005cbf;
        }

        button, .button-66 {
            background-color: #0a6bff;
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
            background-color: #002061;
            color: #ffff00;
            transform: translateY(-2px);
        }

        .h1 {
            position: relative;
            text-align: center;
            margin-bottom: 20px;
            color: var(--dark-blue);
        }

        /* Footer */
        footer {
            background-color: var(--black);
            color: var(--white);
            text-align: center;
            padding: 20px;
            font-size: 0.9rem;
            margin-top: 40px;
        }

        /* Animations */
        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }

        @keyframes floatText {
            from { transform: translateY(0); }
            to { transform: translateY(8px); }
        }

        @keyframes subtlePulse {
            0% { transform: scale(1); }
            100% { transform: scale(1.02); }
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            header {
                padding: 20px 10px;
            }

            header h1 {
                font-size: 1.8rem;
            }

            header i {
                font-size: 1rem;
            }

            section {
                padding: 30px 10px;
                margin: 20px 10px;
            }

            section h2 {
                font-size: 1.5rem;
            }

            .table-wrapper {
                margin-top: 60px;
            }

            #timetable th {
                font-size: 14px;
                top: 0;
            }

            #timetable td {
                font-size: 13px;
            }

            .session {
                padding: 8px;
                font-size: 14px;
            }

            .session button {
                font-size: 14px;
                padding: 6px 10px;
                min-width: 60px;
            }

            button, .button-66 {
                padding: 10px 14px;
                font-size: 14px;
                min-height: 44px;
                min-width: 90px;
            }

            .h1 {
                font-size: 18px;
            }

            footer {
                font-size: 0.8rem;
                padding: 15px 10px;
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
<body>
<!-- Loading Screen -->
<div class="loading-screen">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <h2>Loading...</h2>
    </div>
</div>

<?php include 'includes/admin_navigation.php'; ?>
<main>
    <h1 class="h1">Admin Dashboard</h1>
    <div id="message-container" style="display:none;"></div> <!-- Message container -->
    <section>
        <div class='week-navigation' style='text-align: center;'>
            <h2>Action Buttons</h2>
            <button class="signOut" style="background-color: red; color: white; margin-top: 1%; margin-bottom: 10px;" onclick="document.location='login.php'">Sign Out</button>
            <button type="button" onclick="document.location='clubAttendance.php'" style="margin-top: 10px;background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Attendance Reports & Coach's Signature</button>
        </div>
    </section>

<!-- Container div for potential further styling -->
<section>
    <h2>POD Managment</h2>
    <button type='button' onclick='changeWeek(-1)' style='background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px;'>Previous Week</button>
    <button type='button' onclick='findAllSessionPods()' style='background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px;'>Find All Pods</button>
    <button type='button' onclick='changeWeek(1)' style='background-color: #002061; color: #ffffff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>Next Week</button><br><br>
<div class="table-wrapper">
    <table id="timetable" class="timetable">
        <thead>
            <tr>
                <th>Time</th>
                <?php foreach ([
                    'Monday ' . $weekDates[0],
                    'Tuesday ' . $weekDates[1],
                    'Wednesday ' . $weekDates[2],
                    'Thursday ' . $weekDates[3],
                    'Friday ' . $weekDates[4],
                    'Saturday ' . $weekDates[5],
                    'Sunday ' . $weekDates[6]
                ] as $day) {
                    echo "<th>$day</th>";
                } ?>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($times as $time) {
                echo "<tr><td>$time</td>";
                foreach ($days as $day) {
                    $cellId = ($day) . '-' . str_replace(':', '', $time);
                    echo "<td id='$cellId'>";
                    if (isset($sessions[$day][$time])) {
                        $session = $sessions[$day][$time];
                        $dayString = $day;
                        $day = explode(' ', $dayString)[0];

                        // Session details
                        $sessionId = $session['id'];
                        $group = htmlspecialchars($session['_group']);
                        $location = htmlspecialchars($session['_location']);
                        $coach = htmlspecialchars($session['coach']);
                        $pod = htmlspecialchars($session['pod']);

                        // Session card
                        echo "<div class='session' draggable='true' data-id='$sessionId' ondragstart='drag(event)'>
                                <p type='text' id='group-$sessionId' name='group'>Group: $group</p>
								<input type='hidden' id='group-$sessionId' value='$group' name='group'></input>
                                <p type='text' id='location-$sessionId' name='location'>Location: $location</p>
                                <p>POD: $pod</p>
                                <p>Coach: $coach</p>
                                <button style='min-height: 6px; min-width: 50px;' onclick='findPod($sessionId, \"$group\", \"$day\")'>Find Pod</button>
                              </div>";
                    }
                    echo "</td>";
                }
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<p id="save-message" style="display:none;">Changes have been saved.</p>
</section>

<section>
<h2>Download Sessions to excel</h2>

<p><i>This will download an excel file with the week's sessions with all the data in it</i></p>

<form id="downloadSessionsForm" name="downloadSessionsForm" action="process_download_excel.php" method="POST">
    <input type="hidden" name="timetableData" id="timetableData">
    <button style="margin: 10px; background-color: green;" name='downloadSessionsForm' type="submit">Download to Excel file</button>
</form>

<script>
document.getElementById("downloadSessionsForm").addEventListener("submit", function(event) {
    let table = document.getElementById("timetable");
    let data = [];

    let headers = [];
    table.querySelectorAll("thead th").forEach(th => headers.push(th.innerText.trim()));

    table.querySelectorAll("tbody tr").forEach(row => {
        let rowData = {};
        let cells = row.querySelectorAll("td");

        rowData["Time"] = cells[0].innerText.trim(); // First column is Time

        headers.slice(1).forEach((header, index) => {
            let cell = cells[index + 1];
            rowData[header] = cell ? cell.innerText.trim() : "";
        });

        data.push(rowData);
    });

    document.getElementById("timetableData").value = JSON.stringify(data);
});
</script>

</section>

<section>
    <h2>Upload Athlete CSV File</h2>
    <h5><i><b>Hint:</b> Download the 'Athlete CSV File' below for the template to follow</i></h5><br>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
        <button name='uploadForm' type="submit">Upload</button>
    </form>

    <div id="result"></div>

</section>

<section>
    <h2>Download Athlete CSV File</h2>
    <form id="downloadForm" name="downloadCsv" action="process_download_csv.php" method="POST">
		<label>
			<input type="checkbox" name="fields[]" value="group" checked> Group
		</label>
		<label>
			<input type="checkbox" name="fields[]" value="name" checked> Name
		</label>
		<label>
			<input type="checkbox" name="fields[]" value="exemption"> Exemptions
		</label>
		<label>
			<input type="checkbox" name="fields[]" value="day_exemptions"> Day Exemptions
		</label>
		<br>
		<button type="submit" style="margin: 10px; background-color: green;">Download</button>
	</form>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent the form from submitting the traditional way

            var formData = new FormData();
            var fileInput = document.getElementById('csvFile');
            formData.append('csvFile', fileInput.files[0]);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'process_csv.php', true); // Replace 'process_csv.php' with the path to your PHP file

            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('result').innerHTML = '<pre>' + xhr.responseText + '</pre>';
                } else {
                    document.getElementById('result').innerHTML = 'An error occurred while uploading the file.';
                }
            };

            xhr.send(formData);

			document.location.reload();
        });
    </script>

</section>

<section>
<h2>Manage Lists</h2>
<!-- Add a container for the entire section -->
<div class="admin-container" style="padding: 20px; max-width: 800px; margin: auto; font-family: Arial, sans-serif;">

    <!-- Form for adding a new list -->
    <form id="new-list-form" method="POST" class="form-inline" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <label for="new_list_name">New List Name:</label>
        <input type="text" id="new_list_name" name="new_list_name" required style="padding: 8px; flex: 1; border: 1px solid #ccc; border-radius: 5px;">
        <button type="submit" class="btn-primary" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Add New List</button>
    </form>

    <?php foreach ($lists as $listName => $items): ?>
        <div class="list-container" style="border: 1px solid #ccc; margin-bottom: 15px; padding: 15px; border-radius: 5px;">
            <h3 style="width: 100%; text-align: center;  background-color: #f4f4f4; padding: 10px; margin: 0; cursor: pointer; border-radius: 5px;" class="fa fa-caret-down" onclick="toggleList(this)">	<?= htmlspecialchars($listName) ?></h3>

            <div class="item-list" style="display: none; padding: 10px;">
                <ul style="list-style-type: none; padding: 0;">

                    <?php foreach ($items as $item): ?>
                        <form method="POST" class="item-form" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
                            <input type="hidden" name="list_name" value="<?= htmlspecialchars($listName) ?>">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?? '' ?>">

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label for="update_item_name" style="flex: 1;">Item Name:</label>
                                <input type="text" name="update_item_name" value="<?= htmlspecialchars($item['item_name']) ?>" style="padding: 5px; flex: 2; border: 1px solid #ccc; border-radius: 5px;">
                                <button class="fa fa-close" style="background-color: red; color: white; padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer;" onclick="deleteItem(event)"></button>
                            </div>

                            <div style="margin-top: 10px;">
                                <label for="pod_exemption">POD Exemption:</label>
                                <input type="checkbox" name="pod_exemption" <?= !empty($item['pod_exemption']) ? 'checked' : '' ?>>
                            </div>

                            <div style="margin-top: 10px;">
                                <label for="overide">Override POD Exemption Automation:</label>
                                <input type="checkbox" name="overide" <?= !empty($item['overide']) ? 'checked' : '' ?>>
                            </div>

                            <div style="margin-top: 10px;">
                                <label>POD Day Exemption(s):</label>
                                <input type="checkbox" name="pod_day_exemption" id="pod_day_exemption"
                                    <?= !empty($item['pod_day_exemption']) ? 'checked' : '' ?>
                                    onclick="this.nextElementSibling.style.display = this.checked ? 'block' : 'none';">

                                <div id="day_exemption_container-<?= $item['id'] ?? '' ?>" class="day-exemption"
                                    style="display: <?= !empty($item['pod_day_exemption']) ? 'block' : 'none' ?>; margin-top: 10px;">
                                    <label>Day Exemption(s):</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                        <?php
                                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                        $dayExemption = isset($item['day_exemption']) ? explode(',', $item['day_exemption']) : [];
                                        foreach ($days as $day):
                                            $isChecked = in_array($day, $dayExemption) ? 'checked' : '';
                                        ?>
                                            <label><input type="checkbox" name="day_exemption[]" value="<?= $day ?>" <?= $isChecked ?>> <?= $day ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 10px;">
                                <label for="group-change">Change/Move Group:</label>
                                <select name="group-change" style="padding: 5px; width: 100%; border: 1px solid #ccc; border-radius: 5px;">
                                    <?php foreach ($lists as $listNameOption => $items): ?>
                                        <option value="<?= $listNameOption ?>" <?= $listNameOption === $listName ? 'selected' : '' ?>>
                                            <?= $listNameOption ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-top: 10px;">
                                <label for="group-split">Group Split (Leave blank for no split):</label>
                                <input type="text" name="split" value="<?= htmlspecialchars($item['split'] ?? '') ?>"
                                    style="padding: 5px; width: 100%; border: 1px solid #ccc; border-radius: 5px;">
                            </div>

                            <button type="submit" class="btn-update"
                                style="padding: 8px 15px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;">
                                Update
                            </button>
                        </form>
                    <?php endforeach; ?>
                </ul>

                <form method="POST" class="form-inline" style="margin-top: 20px; display: flex; align-items: center; gap: 10px;">
                    <input type="hidden" name="list_name" value="<?= htmlspecialchars($listName) ?>">
                    <label for="new_item_name">New Item Name:</label>
                    <input type="text" name="new_item_name" required
                        style="padding: 8px; flex: 1; border: 1px solid #ccc; border-radius: 5px;">
                    <button type="submit" class="btn-primary"
                        style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Add New Item
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</section>
</main>
<script>
function toggleList(element) {
    var itemList = element.nextElementSibling;
    if (itemList.style.display === "none") {
        itemList.style.display = "block";
    } else {
        itemList.style.display = "none";
    }
}
function toggleDayExemption(id) {
    var podDayExemptionCheckbox = document.getElementById('pod_day_exemption');
    var dayExemptionContainer = document.getElementById('day_exemption_container-${id}');

    if (podDayExemptionCheckbox.checked) {
        dayExemptionContainer.style.display = 'block';
    } else {
        dayExemptionContainer.style.display = 'none';
    }
}

// Ensure that the function is called on page load to set the initial state
document.addEventListener('DOMContentLoaded', function() {
    toggleDayExemption();
});


function changeWeek(offset) {
    const params = new URLSearchParams(window.location.search);
    let weekOffset = parseInt(params.get('weekOffset') || '0');
    weekOffset += offset;
    params.set('weekOffset', weekOffset);
    window.location.search = params.toString();
}
</script>
<script>
	var findAllPods = 'false';

    async function deleteItem(event) {
        event.preventDefault();
        event.stopPropagation();

        const itemElement = event.target.closest('form');
        const formData = new FormData(itemElement);
        const itemId = formData.get('item_id');
        const listName = formData.get('list_name');
        const itemName = formData.get('original_item_name');

        console.log('Item ID:', itemId);
        console.log('Item Name:', itemName);
        console.log('List Name:', listName);

        // Ensure itemId is an integer
        const parsedItemId = parseInt(itemId, 10);

        try {
            // Proceed with delete operation
            const deleteResponse = await fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'delete_item',
                    id: parsedItemId,
                    deleted_item: itemName
                })
            });

            if (deleteResponse.ok) {
                const deleteResult = await deleteResponse.text();
                console.log('Server response:', deleteResult);
                location.reload(); // Reload page after successful delete
            } else {
                console.error('Failed to delete the item.');
            }
        } catch (error) {
            console.error('An error occurred while deleting the item:', error);
        }
    }

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            // Check if the form is for CSV download
            if (event.target.id === 'downloadForm' || event.target.id === 'csvFile') {
                return; // Allow normal submission for CSV forms
            } else {
                event.preventDefault();

                let formData = new FormData(event.target);
                const url = 'admin_actions.php'; // Updated URL

                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        if (data.no_reload === undefined || data.no_reload === false) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });

    let allSessions = [];

   function findAllSessionPods() {
        if (confirm("Are you sure? This is NOT reversible.")) {
            findAllPods = 'true';
            console.log('Fetching pods for all sessions...');

            const sessions = Array.from(document.querySelectorAll('.session')).map(sessionElement => {
                const sessionId = sessionElement.dataset.id;
                const targetCell = sessionElement.parentElement;
                const [day, time] = targetCell.id.split('-');

                const match = time.match(/(\d{2})(\d{2})/);
                const sessionTime = match ? `${match[1]}:${match[2]}` : '00:00';

                const groupInput = sessionElement.querySelector('input[name="group"]');
                const group = groupInput ? groupInput.value : 'default_group';
                // Extract just the day name without the date
                const dayName = day.split(' ')[0];

                return {
                    sessionId,
                    day: dayName,
                    time: sessionTime,
                    group: group
                };
            });

            console.log('Sessions to process:', sessions);

            async function processSessions(sessions) {
                for (const session of sessions) {
                    try {
                        console.log(`Processing session ${session.sessionId}:`, session);
                        const response = await findPod(session.sessionId, session.group, session.day);
                        
                        if (response === 'find pod done successfully') {
                            console.log(`Pod found for session: ${session.sessionId}`);
                        } else {
                            console.error(`Failed to find pod for session: ${session.sessionId}`);
                        }
                        // Add a small delay between requests to prevent overwhelming the server
                        await new Promise(resolve => setTimeout(resolve, 100));
                    } catch (error) {
                        console.error(`Error processing session ${session.sessionId}:`, error);
                    }
                }
                allSessionsProcessed();
            }

            function allSessionsProcessed() {
                console.log("All sessions have been processed.");
                showMessage("All sessions have been processed.", 'success');
                document.location.reload();
            }

            processSessions(sessions);
        }
    }

    let selectedPods = [];

    function showMessage(message, type) {
        const messageContainer = document.getElementById('message-container');
        messageContainer.textContent = message;
        messageContainer.className = type; // Add a class for styling
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 3000); // Hide after 3 seconds
    }

    async function findPod(sessionId, group, day) {
        try {
            console.log("Session ID:", sessionId);
            console.log("Group:", group);
            console.log("Day:", day);

            if (!group) {
                showMessage("Group input is empty.", 'error');
                return;
            }

            console.log('Fetching Acceptable pods');

            const unacceptablePodsResponse = await fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ 'action': 'get_unacceptable_pods', 'group': group, 'day': day })
            });

            if (!unacceptablePodsResponse.ok) {
                throw new Error(`HTTP error! Status: ${unacceptablePodsResponse.status}`);
            }

            const acceptablePods = await unacceptablePodsResponse.json();
            console.log(acceptablePods);

            const finalAvailablePods = acceptablePods;

            if (finalAvailablePods.length > 0) {
                const selectedPod = finalAvailablePods[0];
                console.log('Selected pod:', selectedPod);
		showMessage("Selected Pod: " + selectedPod, 'success');

                const updatePodResponse = await fetch('admin_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({ 'session_id': sessionId, 'selected_pod': selectedPod })
                });

                if (!updatePodResponse.ok) {
                    throw new Error(`HTTP error! Status: ${updatePodResponse.status}`);
                }

                const updateData = await updatePodResponse.json();

                if (updateData.success) {
                    showMessage("Pod updated successfully!", 'success');
					console.log('Setting Pod indexes');

					const setPodIndexResponse = await fetch('admin_actions.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: new URLSearchParams({ 'action': 'set_pod_indexes', 'selected_pod': selectedPod })
					});

					if (!setPodIndexResponse.ok) {
						throw new Error(`HTTP error! Status: ${setPodIndexResponse.status}`);
					}

					const podIndexes = await setPodIndexResponse.json();
					console.log(podIndexes);
                } else {
                    showMessage("Error updating pod: " + updateData.message, 'error');
                }
            } else {
                showMessage("No available pods after filtering.", 'error');
            }
        } catch (error) {
            console.error('Error in findPod:', error);
            showMessage("An error occurred while finding a pod: " + error.message, 'error');
        }

		if (findAllPods === 'true') {
			return 'find pod done successfully';
		} else if (findAllPods === 'false') {
			document.location.reload();
		}
    }
</script>
<script>
// Add this at the beginning of your script section
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading screen when page is fully loaded
    const loadingScreen = document.querySelector('.loading-screen');
    if (loadingScreen) {
        // Add fade-out class
        loadingScreen.classList.add('fade-out');
        // Remove from DOM after animation
        setTimeout(() => {
            loadingScreen.remove();
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
    }, { threshold: 0.1 });

    sections.forEach(section => {
        observer.observe(section);
    });
});
</script>
</body>
</html>
