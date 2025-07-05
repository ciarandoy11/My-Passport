<?php
include __DIR__ . '/db.php';

// Check if meet_id is provided
if (!isset($_GET['meet_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Meet ID is required']);
    exit;
}

$meetId = (int)$_GET['meet_id'];

// Fetch events for the meet
$stmt = $conn->prepare("
    SELECT id, event_number, event_name, age_group, gender, distance, stroke 
    FROM meet_events 
    WHERE meet_id = ? 
    ORDER BY event_number
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $meetId);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    // Format the event name for display
    $displayName = sprintf(
        "Event %s: %dm %s %s %s",
        $row['event_number'],
        $row['distance'],
        ucfirst($row['stroke']),
        $row['age_group'] ? "(" . $row['age_group'] . ")" : "",
        ucfirst($row['gender'])
    );
    
    $events[] = [
        'id' => $row['id'],
        'display_name' => $displayName,
        'event_number' => $row['event_number'],
        'event_name' => $row['event_name'],
        'age_group' => $row['age_group'],
        'gender' => $row['gender'],
        'distance' => $row['distance'],
        'stroke' => $row['stroke']
    ];
}

// Return events as JSON
header('Content-Type: application/json');
echo json_encode(['events' => $events]); 
?>