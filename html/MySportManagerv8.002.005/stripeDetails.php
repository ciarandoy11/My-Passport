<?php
include __DIR__ . '/db.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capture form data
    $club = trim($_POST['club']);
    $stripeApi = trim($_POST['stripeApi']);

    // Validate input
    if (!empty($club) && !empty($stripeApi)) {
        $stmt = $conn->prepare("UPDATE clubSecrets SET stripeApi = ? WHERE club = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $stripeApi, $club);
            if ($stmt->execute()) {
                echo "Info updated successfully";
                header("Location: clubMembership.php");
                exit;
            } else {
                echo "Error updating record: " . $stmt->error;
            }
        } else {
            echo "Error preparing update statement: " . htmlspecialchars($conn->error);
        }
        $stmt->close(); // Close the statement
    } else {
        echo "Please fill in all fields";
    }
}

$conn->close();
?>