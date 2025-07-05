<?php
// moveToTrash.php
include __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailId = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($emailId > 0) {
        $stmt = $conn->prepare("UPDATE emails SET status='trash' WHERE id = ?");
        $stmt->bind_param("i", $emailId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Email sent to trash.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move email.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email ID.']);
    }
}

$conn->close();
?>
