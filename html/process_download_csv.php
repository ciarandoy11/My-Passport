<?php
include __DIR__ . '/db';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get selected fields from the form
    $fields = isset($_POST['fields']) ? $_POST['fields'] : [];

    // Prepare header for CSV
    $combinedItem = [];
    if (in_array('group', $fields)) {
        $combinedItem[] = "Group";
        $combinedItem[] = "Split";
    }
    if (in_array('name', $fields)) {
        $combinedItem[] = "Name";
    }
    if (in_array('exemption', $fields)) {
        $combinedItem[] = "Exemptions";
    }
    if (in_array('day_exemptions', $fields)) {
        $combinedItem[] = "Day Exemptions, Day Exemption Days";
    }

    // Query to get data based on available fields
    $dataSql = "SELECT list_name, split, item_name, pod_exemption, pod_day_exemption, day_exemption FROM groups WHERE club = ?";
    $dataStmt = $conn->prepare($dataSql);
    $dataStmt->bind_param("s", $club); // Bind the club parameter
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();

    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="download.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');
    // Write CSV header
    fputcsv($output, $combinedItem);

    // Fetch data and write to CSV
    if ($dataResult->num_rows > 0) {
        while ($row = $dataResult->fetch_assoc()) {
            $csvRow = [];
            if (in_array('group', $fields)) {
                $csvRow[] = $row['list_name'];
                $csvRow[] = $row['split'];
            }
            if (in_array('name', $fields)) {
                $csvRow[] = $row['item_name'];
            }
            if (in_array('exemption', $fields)) {
                $csvRow[] = $row['pod_exemption'];
            }
            if (in_array('day_exemptions', $fields)) {
                // Including both exemptions in separate fields
                $csvRow[] = $row['pod_day_exemption']; // first kind of exemption
                $csvRow[] = $row['day_exemption']; // second kind of exemption
            }
            fputcsv($output, $csvRow);
        }
    } else {
        // If no results found, write an empty row or handle this case as needed
        fputcsv($output, array_fill(0, count($combinedItem), 'No data found'));
    }

    fclose($output);
    exit; // Terminate the script
}
$conn->close();
?>
