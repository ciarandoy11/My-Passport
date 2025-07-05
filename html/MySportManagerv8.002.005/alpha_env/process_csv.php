<?php
include __DIR__ . '/db.php';

if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
    // Get the uploaded file's temporary location
    $csvFile = $_FILES['csvFile']['tmp_name'];

    // Open the CSV file
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            // Skip empty rows
            if (count(array_filter($data)) == 0) {
                continue;
            }

            // Assign variables based on the columns in the CSV
            $squad = isset($data[0]) ? trim($data[0]) : '';
            $split = isset($data[1]) ? trim($data[1]) : 'NULL';
            $swimmerName = isset($data[2]) ? trim($data[2]) : '';
			$exemptions = isset($data[3]) ? trim($data[3]) : '0';
			$dayExemptions = isset($data[4]) ? trim($data[4]) : '0';
			$dayExemptionDays = isset($data[5]) ? trim($data[5]) : '';

            // Replace apostrophes with spaces in $swimmerName
            $swimmerName = str_replace("'", " ", $swimmerName);

			if (!empty($squad) && !empty($swimmerName) && $squad !== 'Group' && $swimmerName !== 'Name') {
				// Check if the swimmer already exists
                $checkStmt = $conn->prepare("SELECT id FROM groups WHERE item_name = ?");
                $checkStmt->bind_param('s', $swimmerName);
                $checkStmt->execute();
                $checkStmt->store_result(); // Store result to check num_rows

                $parts = explode('-', $squad);
                
                if (count($parts) == 1) {
                    // If there is no "-", keep split and sqaud as it is
                } else {
                    // If there is a "-", split into two parts
                    $squad = $parts[0];
                    $split = $parts[1];
                }
                
                if ($checkStmt->num_rows > 0) {
                    // Update existing record
                    $checkStmt->bind_result($id);
                    $checkStmt->fetch();
                    $updateStmt = $conn->prepare("UPDATE groups SET list_name = ?, split= ?, item_name = ?, pod_exemption = ?, pod_day_exemption = ?, day_exemption = ?, club = ? WHERE id = ?");
                    $updateStmt->bind_param('sssiissi', $squad, $split, $swimmerName, $exemptions, $dayExemptions, $dayExemptionDays, $club, $id);
                    if (!$updateStmt->execute()) {
                        die(json_encode(["success" => false, "message" => "Error updating item: " . $updateStmt->error]));
                    } else {
                        echo json_encode(["success" => true, "message" => "Item updated successfully."]);
                    }
                    $updateStmt->close();
                } else {
                    // Insert new record
                    $insertStmt = $conn->prepare("INSERT INTO groups (list_name, split, item_name, pod_exemption, pod_day_exemption, day_exemption, club, pod_index) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $insertStmt->bind_param('sssiiss', $squad, $split, $swimmerName, $exemptions, $dayExemptions, $dayExemptionDays, $club);
                    if (!$insertStmt->execute()) {
                        die(json_encode(["success" => false, "message" => "Error adding item: " . $insertStmt->error]));
                    } else {
                        echo json_encode(["success" => true, "message" => "Item added successfully."]);
                    }
                    $insertStmt->close();
                }

                // Close the check statement
                $checkStmt->close();

            } else {
                echo json_encode(["success" => false, "message" => "Squad or item name is invalid"]);
            }
        }

        fclose($handle);
    } else {
        die(json_encode(["success" => false, "message" => "Unable to open the file."]));
    }
} else {
    die(json_encode(["success" => false, "message" => "No file was uploaded or there was an error uploading the file."]));
}

$conn->close(); // Close the database connection
?>
