<?php
include __DIR__ . '/db.php';

// Check if notifications have already been sent in this session
if (!isset($_SESSION['notifications_sent'])) {

    // Calculate the date 7 days from now
    $dateNow = new DateTime();
    $dayOne = (clone $dateNow)->modify('+1 days');
    $dayTwo = (clone $dateNow)->modify('+2 days');
    $dayThree = (clone $dateNow)->modify('+3 days');
    $dayfour = (clone $dateNow)->modify('+4 days');
    $dayFive = (clone $dateNow)->modify('+5 days');
    $daySix = (clone $dateNow)->modify('+6 days');
    $dateNextWeek = (clone $dateNow)->modify('+7 days');

    // Calculate start and end date for next week in correct format
    $startDate = $dateNow->format('l d/m/y H:i:s');
    $dayOne = $dayOne->format('l d/m/y');
    $dayTwo = $dayTwo->format('l d/m/y');
    $dayThree = $dayThree->format('l d/m/y');
    $dayfour = $dayfour->format('l d/m/y');
    $dayFive = $dayFive->format('l d/m/y');
    $daySix = $daySix->format('l d/m/y');
    $endDate = $dateNextWeek->format('l d/m/y');

	echo "+--------------------------+ \n|                          | \n| " . $startDate . " | \n|                          | \n+--------------------------+ \n \n";

    // Fetching all user IDs associated with pods
    // Prepare the SQL statement
    $sql = "SELECT u.id
            FROM timetable t
            JOIN users u ON FIND_IN_SET(t.pod, u.swimmer) > 0
            WHERE _day LIKE ? OR _day LIKE ? OR _day LIKE ? OR _day LIKE ? OR _day LIKE ? OR _day LIKE ? OR _day LIKE ? OR _day LIKE ?;";

    // Prepare the statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind the start and end date as parameters
        $stmt->bind_param("ssssssss", $startDate, $dayOne, $dayTwo, $dayThree, $dayfour, $dayFive, $daySix, $endDate);

        // Execute the prepared statement
        if ($stmt->execute()) {
            // Bind result variable
            $stmt->bind_result($userId);

            // Initialize an array to store all user IDs
            $userIds = [];
            while ($stmt->fetch()) {
                $userIds[] = $userId;
            }

            // Close the statement
            $stmt->close();

            // If no users are found, terminate with an error message
            if (empty($userIds)) {
                die("No users associated with any pods.");
            }

            // Retrieve all timetable data
            $timetableSql = "SELECT * FROM timetable";
            $timetableResult = $conn->query($timetableSql);

            // Iterate over each userId and send a notification for sessions only within the next week
            foreach ($userIds as $userId) {
                // Retrieve user data
                $userSql = "SELECT * FROM users WHERE id = ?";
                $userStmt = $conn->prepare($userSql);
                $userStmt->bind_param("i", $userId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();

                if ($userResult && $user = $userResult->fetch_assoc()) {
                    $timetableResult->data_seek(0); // Reset pointer for timetable records
                    while ($row = $timetableResult->fetch_assoc()) {
                        // Ensure sessionDate is valid and in correct format
                        $sessionDate = DateTime::createFromFormat('l d/m/y', $row['_day']); // Aligning to the 'Day dd/mm/yy' format
                        if ($sessionDate === false) {
                            echo "Invalid date format for session: {$row['_day']}<br>";
                            continue; // Skip invalid dates
                        }

                        // Compare the session date with the future date
                        if ($sessionDate >= $dateNow && $sessionDate <= $dateNextWeek) {
                            $swimmers = explode(",", $user['swimmer']);

                            // Check if the user is in the 'pod'
                            if (in_array($row['pod'], $swimmers)) {
								$timeNow = new DateTime();
								$time = $timeNow->format('H:i:s');
                                // Send notification
                                $message = 'You are pod on ' . $row['_day'] . ' at ' . $row['stime'] . '.';
                                if (sendPodNotification($userId, $message)) {
                                    echo "Notification sent successfully for user $userId at $time \n \n ----------------------------------------------------- \n \n";
                                } else {
                                    echo "Failed to send notification for user $userId<br>";
                                }
                            }
                        }
                    }
                } else {
                    echo "User not found for ID $userId<br>";
                }
            }

            // Set session variable to indicate notifications have been sent
            $_SESSION['notifications_sent'] = true;

        } else {
            echo "Execute failed: " . $stmt->error;
        }
    } else {
        echo "Prepare failed: " . $conn->error;
    }

} else {
    echo "Notifications have already been sent for this session.<br>";
}

// Display the session notification status for debugging
//echo "Notifications sent status: " . ($_SESSION['notifications_sent'] ? 'Yes' : 'No') . "<br>";

// Function to send Pod Notification
function sendPodNotification($userId, $message) {
    $instanceId = "c4b9ff1a-d210-44b4-b02a-43d0dd4da8fd";
    $secretKey = "0651D9F597A3BF415812A5EE987F910AC3DD969EA169DE05BFAFD3D162A9E7F1";

    $url = "https://{$instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$instanceId}/publishes/interests";

    $data = [
        "interests" => ["user_{$userId}"],
        "web" => [
            "notification" => [
                "title" => "Pod Notification",
                "body" => $message,
            ]
        ]
    ];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$secretKey}"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo "Failed to send notification: " . $error;
        return false;
    }

    $responseDecoded = json_decode($response, true);
    if (isset($responseDecoded['error'])) {
        echo "Error from Pusher Beams: " . $responseDecoded['error'];
        return false;
    }

    return true;
}
?>
