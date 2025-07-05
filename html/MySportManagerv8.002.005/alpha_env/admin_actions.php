<?php
//admin.php
include './db.php';

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
        echo json_encode(["success" => true, "message" => "Item updated successfully.", "no-reload" => true]);
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
    $sql = "SELECT * FROM timetable WHERE club = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $club);
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

$times = ['04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
?>