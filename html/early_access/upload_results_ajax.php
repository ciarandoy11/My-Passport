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
$results = $input['results'] ?? [];

if ($meetId === 0 || empty($results)) {
    echo json_encode(['success' => false, 'error' => 'Missing required data.']);
    exit;
}

$conn->begin_transaction();

try {
    // Insert athletes if needed (optional, depends on your app logic)
    // Here, let's just ignore athlete insertions and assume athletes exist or skip

    $inserted = 0;
    foreach ($results as $result) {
        // Find matching athlete object
        $matchingAthlete = null;
        foreach ($athletes as $a) {
            if ((string)$a['id'] === (string)$result['athlete_id']) {
                $matchingAthlete = $a;
                break;
            }
        }
    
        if (!$matchingAthlete) {
            throw new Exception("Athlete not found for athlete_id: " . $result['athlete_id']);
        }

        // Lookup event_id
        $sql = "SELECT id FROM meet_events WHERE event_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $result['event_name']);
        $stmt->execute();
        $eResult = $stmt->get_result();
        $row = $eResult->fetch_assoc();
        $stmt->close();
    
        if (!$row) {
            throw new Exception("Event not found for event_name: " . $eventName);
        }
        $event_id = (int)$row['id'];

        // Lookup entry_id
        $sql = "SELECT id FROM meet_entries WHERE event_id = ? AND athlete_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $event_id, $athlete_id);
        $stmt->execute();
        $EIResult = $stmt->get_result();
        $row = $EIResult->fetch_assoc();
        $stmt->close();
    
        if (!$row) {
            throw new Exception("Entry not found for event_id: " . $event_id);
        }
        $entry_id = (int)$row['id'];

        if ($entry_id) { // If there is an entry for the results save the result else dismiss
        
            // Lookup athlete_id in DB using firstname + lastname
            $nameKey = $matchingAthlete['firstName'] . ' ' . $matchingAthlete['lastName'];
            $sql = "SELECT id FROM groups WHERE item_name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $nameKey);
            $stmt->execute();
            $gResult = $stmt->get_result();
            $row = $gResult->fetch_assoc();
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
                echo json_encode(["success" => true, "message" => "Item(s) created successfully."]);
                $stmt->close();

                // Lookup athlete_id in DB using firstname + lastname
                $nameKey = $matchingAthlete['firstName'] . ' ' . $matchingAthlete['lastName'];
                $sql = "SELECT id FROM groups WHERE item_name = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $nameKey);
                $stmt->execute();
                $gResult = $stmt->get_result();
                $row = $gResult->fetch_assoc();
                $stmt->close();
            }
        
            $athlete_id = (int)$row['id'];
        
            // Validate result_time
            $result_time = trim($result['result_time']);
            if (!preg_match('/^\d{2}:\d{2}:\d{2}\.\d{2}$/', $result_time)) {
                throw new Exception("Invalid result_time format: " . $result_time);
            }
        
            // Insert result
            $stmt = $conn->prepare("INSERT INTO meet_results (heat_number, lane_number, placement, result_type, entry_id, final_time) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        
            $stmt->bind_param('iiisis', $result['heat'], $result['lane'], $result['place'], $result['type'], $entry_id, $result_time);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();
            $inserted++;
        }
    }
    
    $stmt->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => "Inserted $inserted results."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>