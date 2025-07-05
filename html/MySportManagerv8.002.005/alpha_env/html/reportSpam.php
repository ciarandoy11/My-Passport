<?php
// reportSpam.php
include __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailId = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($emailId > 0) {
        $stmt = $conn->prepare("UPDATE emails SET status='spam' WHERE id = ?");
        $stmt->bind_param("i", $emailId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Email reported as spam.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to report email.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email ID.']);
    }
}

$conn->close();
?>
