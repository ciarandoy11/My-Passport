<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/db.php';

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$meetId = isset($input['meet_id']) ? (int)$input['meet_id'] : 0;
$club = $input['club'] ?? '';
$athletes = $input['athletes'] ?? [];
$entries = $input['entries'] ?? [];

if ($meetId === 0 || empty($entries)) {
    echo json_encode(['success' => false, 'error' => 'Missing required data.']);
    exit;
}

$conn->begin_transaction();

try {
    // Insert athletes if needed (optional, depends on your app logic)
    // Here, let's just ignore athlete insertions and assume athletes exist or skip

    $inserted = 0;
    foreach ($entries as $entry) {
        // Find matching athlete object
        $matchingAthlete = null;
        foreach ($athletes as $a) {
            if ((string)$a['id'] === (string)$entry['athlete_id']) {
                $matchingAthlete = $a;
                break;
            }
        }
    
        if (!$matchingAthlete) {
            throw new Exception("Athlete not found for athlete_id: " . $entry['athlete_id']);
        }
    
        // Lookup event_id
        $sql = "SELECT id FROM meet_events WHERE event_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $entry['event_number']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
    
        if (!$row) {
            throw new Exception("Event not found for event_number: " . $entry['event_number']);
        }
        $event_id = (int)$row['id'];
    
        // Lookup athlete_id in DB using firstname + lastname
        $nameKey = $matchingAthlete['firstName'] . ' ' . $matchingAthlete['lastName'];
        $sql = "SELECT id FROM groups WHERE item_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nameKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
    
        if (!$row) {
            // throw new Exception("Athlete not found in DB for name: " . $nameKey);
            // Insert the new item into the `groups` table
            $stmt = $conn->prepare("INSERT INTO groups (list_name, item_name, pod_index, club) VALUES (?, ?, '0', ?)");
            $stmt->bind_param('sss', $matchingAthlete['group'], $nameKey, $club);
            if (!$stmt->execute()) {
                echo json_encode(["success" => false, "message" => "Error creating item: " . $stmt->error]);
                return; // Stop on error
            }
            $athlete_id = $conn->insert_id; // Get the new athlete's ID
            $stmt->close();
        } else {
            $athlete_id = (int)$row['id'];
        }
    
        // Validate entry_time
        $entry_time = trim($entry['entry_time']);
        if (!preg_match('/^\d{2}:\d{2}:\d{2}\.\d{2}$/', $entry_time)) {
            throw new Exception("Invalid entry_time format: " . $entry_time);
        }
    
        // Insert entry
        $stmt = $conn->prepare("INSERT INTO meet_entries (meet_id, athlete_id, event_id, entry_time, entry_fee) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    
        $stmt->bind_param('iiisi', $meetId, $athlete_id, $event_id, $entry_time, $entry['entry_fee']);
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $stmt->close();

        // Get pod_date_exemption
        $sql = "SELECT start_date, end_date FROM meets WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $meetId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $start_date = $row['start_date'];
        $end_date = $row['end_date'];

        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day') // include end date and start date
        );

        $dateArray = [];
        foreach ($period as $date) {
            $dateArray[] = $date->format('d/m/y');
        }
        $dates = implode(',', $dateArray);

        // Fetch current pod_date_exemption value
        $stmt = $conn->prepare("SELECT pod_date_exemption FROM groups WHERE id = ?");
        $stmt->bind_param('i', $athlete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $current_exemption = $row['pod_date_exemption'] ?? '';
        if (!empty($current_exemption)) {
            // Merge and remove duplicates
            $existing_dates = explode(',', $current_exemption);
            $new_dates = explode(',', $dates);
            $all_dates = array_unique(array_merge($existing_dates, $new_dates));
            $dates = implode(',', $all_dates);
        }

         // Insert pod_date_exemption
         $stmt = $conn->prepare("UPDATE groups SET pod_date_exemption = ? WHERE id = ?");
         if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
     
         $stmt->bind_param('si', $dates, $athlete_id);
         if (!$stmt->execute()) {
             throw new Exception("Update failed: " . $stmt->error);
         }
         $stmt->close();

        $inserted++;
    }
    
    $stmt->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => "Inserted $inserted entries."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>