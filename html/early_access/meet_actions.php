<?php
//meet_actions
include './db.php';

// Check the action being requested
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'get_entry_fees':
        $meetId = isset($_POST['meet_id']) ? $_POST['meet_id'] : '';
        getEntryFees($meetId, $conn);
        break;
    
    case 'bulk_edit_fees':
        $meetId = isset($_POST['meet_id']) ? $_POST['meet_id'] : '';
        $adjustmentAmount = isset($_POST['adjustment_amount']) ? $_POST['adjustment_amount'] : '';
        bulkEdit($meetId, $adjustmentAmount, $conn);
        break;

    case 'edit_fee':
        $meetId = isset($_POST['meet_id']) ? $_POST['meet_id'] : '';
        $athleteId = isset($_POST['athlete_id']) ? $_POST['athlete_id'] : '';
        $newAmount = isset($_POST['new_amount']) ? $_POST['new_amount'] : '';
        editFee($meetId, $athleteId, $newAmount, $conn);
        break;

    case 'send_invoice':
        $meetId = isset($_POST['meet_id']) ? $_POST['meet_id'] : '';
        $athleteId = isset($_POST['athlete_id']) ? $_POST['athlete_id'] : '';
        sendInvoice($meetId, $athleteId, $conn);
        break;
}

function getEntryFees($meetId, $conn) {
    $sql = "SELECT athlete_id, event_id, entry_fee FROM meet_entries WHERE meet_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meetId);
    $stmt->execute();
    $result = $stmt->get_result();
    $entryFees = [];
    while ($row = $result->fetch_assoc()) {
        $entryFees[] = $row;
    }
    echo json_encode(['entryFees' => $entryFees]);
}

function bulkEdit($meetId, $adjustmentAmount, $conn) {
    $sql = "UPDATE meet_entries SET entry_fee = entry_fee + ? WHERE meet_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $adjustmentAmount, $meetId);
    $stmt->execute();
    
    echo json_encode(['success' => 'true']);
}

function editFee($meetId, $athleteId, $newAmount, $conn) {
    $sql = "UPDATE meet_entries SET entry_fee = ? WHERE meet_id = ? AND athlete_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $newAmount, $meetId, $athleteId);
    $stmt->execute();
    
    echo json_encode(['success' => 'true']);
}

function sendInvoice($meetId, $athleteId, $conn) {
    include_once __DIR__ . '/stripe_invoice_helper.php';
    
    $response = [
        'no_email' => [],
        'failedInvoices' => [],
        'success' => []
    ];

    // Get override_emails from POST if present
    $overrideEmails = [];
    if (isset($_POST['override_emails'])) {
        $overrideEmails = json_decode($_POST['override_emails'], true);
    }

    // Get meet name
    $meetName = '';
    $stmt = $conn->prepare("SELECT name, club FROM meets WHERE id = ?");
    $stmt->bind_param("i", $meetId);
    $stmt->execute();
    $meetResult = $stmt->get_result();
    if ($meetRow = $meetResult->fetch_assoc()) {
        $meetName = $meetRow['name'];
        $club = $meetRow['club'];
    } else {
        echo json_encode(['error' => 'Meet not found']);
        return;
    }

    // Fetch club secrets (Stripe key)
    $stmt = $conn->prepare("SELECT * FROM clubSecrets WHERE club = ?");
    $stmt->bind_param("s", $club);
    $stmt->execute();
    $secrets = $stmt->get_result();
    $stripeSecret = '';
    if ($secrets->num_rows > 0) {
        while ($row = $secrets->fetch_assoc()) {
            if (!empty($row['stripeAPI'])) {
                $stripeSecret = $row['stripeAPI'];
                break;
            }
        }
    }
    if (!$stripeSecret) {
        echo json_encode(['error' => 'Stripe secret not found for club']);
        return;
    }

    // Get all athletes for the meet if $athleteId is empty, else just that athlete
    $athleteIds = [];
    if ($athleteId) {
        $athleteIds[] = $athleteId;
    } else {
        $stmt = $conn->prepare("SELECT DISTINCT athlete_id FROM meet_entries WHERE meet_id = ?");
        $stmt->bind_param("i", $meetId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $athleteIds[] = $row['athlete_id'];
        }
    }

    foreach ($athleteIds as $athleteId) {
        // Get user_id and athlete name
        $stmt = $conn->prepare("SELECT user_id, item_name FROM groups WHERE id = ?");
        $stmt->bind_param("i", $athleteId);
        $stmt->execute();
        $groupResult = $stmt->get_result();
        if (!$groupRow = $groupResult->fetch_assoc()) {
            $response['failedInvoices'][] = ['athlete' => (int)$athleteId, 'error' => 'Athlete not found in groups'];
            continue;
        }
        $userId = $groupRow['user_id'];
        $athleteName = $groupRow['item_name'];

        // Get email, using override if provided
        $email = '';
        if (isset($overrideEmails[$athleteId]) && filter_var($overrideEmails[$athleteId], FILTER_VALIDATE_EMAIL)) {
            $email = $overrideEmails[$athleteId];
        } elseif ($userId) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userResult = $stmt->get_result();
            if ($userRow = $userResult->fetch_assoc()) {
                $email = $userRow['email'];
            }
        }
        if (!$email) {
            $response['no_email'][] = ['athlete_id' => $athleteId, 'name' => $athleteName];
            continue;
        }

        // Get entry fees and event ids for this athlete in this meet
        $stmt = $conn->prepare("SELECT event_id, entry_fee FROM meet_entries WHERE meet_id = ? AND athlete_id = ?");
        $stmt->bind_param("ii", $meetId, $athleteId);
        $stmt->execute();
        $entriesResult = $stmt->get_result();
        $total = 0;
        $eventIdsArr = [];
        while ($entryRow = $entriesResult->fetch_assoc()) {
            $total += $entryRow['entry_fee'];
            $eventIdsArr[] = $entryRow['event_id'];
        }
        
        // Round the total to 2 decimal places to prevent floating point precision issues
        $total = round($total, 2);

        if ($total == 0) {
            $response['failedInvoices'][] = ['athlete' => (int)$athleteId, 'error' => 'No entry fees found or total is zero'];
            continue;
        }

        // Get event names
        $eventNames = [];
        if (count($eventIdsArr) > 0) {
            $in = str_repeat('?,', count($eventIdsArr) - 1) . '?';
            $types = str_repeat('i', count($eventIdsArr));
            $stmt = $conn->prepare("SELECT event_name FROM meet_events WHERE id IN ($in)");
            $stmt->bind_param($types, ...$eventIdsArr);
            $stmt->execute();
            $eventResult = $stmt->get_result();
            while ($eventRow = $eventResult->fetch_assoc()) {
                $eventNames[] = $eventRow['event_name'];
            }
        }

        $description = "Meet: $meetName\nEvents: " . implode(', ', $eventNames) . "\nAthlete: $athleteName";
        $customerData = [
            'email' => $email,
            'name' => $athleteName
        ];
        $metadata = [
            'athlete_id' => $athleteId,
            'meet_id' => $meetId,
            'club' => $club
        ];
        // Debug: echo "Total: $total, Event IDs: " . implode(', ', $eventIdsArr) . PHP_EOL;
        $result = createStripeInvoice($stripeSecret, $customerData, $total, 'eur', $description, $metadata);
        if ($result['success']) {
            $response['success'][] = $result;
        } else {
            $response['failedInvoices'][] = ['athlete' => (int)$athleteId, 'error' => $result['error']];
        }
    }

    echo json_encode($response);
}
?>