<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/db.php';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['meetFile'])) {
    try {
        $allowedTypes = ['hyv', 'ev3'];
        $file = $_FILES['meetFile'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowedTypes));
        }
        
        $uploadDir = __DIR__ . '/uploads/meets/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        $fileName = uniqid() . '.' . $fileType;
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception("Error uploading file. PHP Error: " . error_get_last()['message']);
        }
        
        // Parse meet file based on type
        $meetData = parseMeetFile($filePath, $fileType);
        
        // Debug output
        error_log("Parsed meet data: " . print_r($meetData, true));
        
        // Insert meet data into database
        $stmt = $conn->prepare("INSERT INTO meets (club, name, start_date, end_date, venue, course, closing_date, meet_file_path, meet_file_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssss", 
            $club,
            $meetData['name'],
            $meetData['start_date'],
            $meetData['end_date'],
            $meetData['venue'],
            $meetData['course'],
            $meetData['closing_date'],
            $fileName,
            $fileType
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error saving meet data: " . $stmt->error);
        }
        
        $meetId = $conn->insert_id;
        
        // Insert events
        foreach ($meetData['events'] as $event) {
            $stmt = $conn->prepare("INSERT INTO meet_events (meet_id, event_number, event_name, age_group, gender, distance, stroke) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement for events: " . $conn->error);
            }
            
            $stmt->bind_param("iisssiis", 
                $meetId,
                $event['number'],
                $event['name'],
                $event['age_group'],
                $event['gender'],
                $event['distance'],
                $event['entry_fee'],
                $event['stroke']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error inserting event data: " . $stmt->error);
            }
        }
        
        $success = "Meet file uploaded and processed successfully!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Meet upload error: " . $e->getMessage());
    }
}

// Function to parse different meet file types
function parseMeetFile($filePath, $fileType) {
    if (!file_exists($filePath)) {
        throw new Exception("Meet file not found at: " . $filePath);
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Failed to read meet file");
    }
    
    $meetData = [
        'name' => '',
        'start_date' => '',
        'end_date' => '',
        'venue' => '',
        'closing_date' => '',
        'entry_fee' => '',
        'events' => []
    ];
    
    switch ($fileType) {
        case 'hyv':
            // Parse Hytek file
            $lines = explode("\n", $content);
            
            // First line contains meet information
            if (isset($lines[0])) {
                $meetInfo = explode(';', $lines[0]);
                if (count($meetInfo) >= 4) {
                    $meetData['name'] = trim($meetInfo[0]);
                    
                    // Convert dates from MM/DD/YYYY to YYYY-MM-DD
                    $startDate = DateTime::createFromFormat('m/d/Y', trim($meetInfo[1]));
                    $endDate = DateTime::createFromFormat('m/d/Y', trim($meetInfo[2]));
                    $closingDate = DateTime::createFromFormat('m/d/Y', trim($meetInfo[3]));
                    
                    if ($startDate) {
                        $meetData['start_date'] = $startDate->format('Y-m-d');
                    }
                    if ($endDate) {
                        $meetData['end_date'] = $endDate->format('Y-m-d');
                    }
                    if ($closingDate) {
                        $meetData['closing_date'] = $closingDate->format('Y-m-d');
                    }
                    
                    // Venue is in the 5th field
                    if (isset($meetInfo[4])) {
                        $meetData['course'] = trim($meetInfo[4]);
                    }

                    // Venue is in the 5th field
                    if (isset($meetInfo[5])) {
                        $meetData['venue'] = trim($meetInfo[5]);
                    }
                }
            }
            
            // Parse events (remaining lines)
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                
                $eventInfo = array_map('trim', explode(';', $line));
                if (count($eventInfo) >= 8) {
                    $event = [
                        'number' => $eventInfo[0],
                        'name' => '', // Will be constructed below
                        'age_group' => '',
                        'gender' => 'mixed',
                        'distance' => (int)$eventInfo[6],
                        'entry_fee' => (int)$eventInfo[11],
                        'stroke' => ''
                    ];

                    // Gender
                    if (isset($eventInfo[2])) {
                        switch (trim($eventInfo[2])) {
                            case 'M': $event['gender'] = 'male'; break;
                            case 'F': $event['gender'] = 'female'; break;
                            case 'X': $event['gender'] = 'mixed'; break;
                        }
                    }

                    // Stroke
                    if (isset($eventInfo[7])) {
                        switch ((int)$eventInfo[7]) {
                            case 1: $event['stroke'] = 'free'; break;
                            case 2: $event['stroke'] = 'back'; break;
                            case 3: $event['stroke'] = 'breast'; break;
                            case 4: $event['stroke'] = 'fly'; break;
                            case 5: $event['stroke'] = 'im'; break;
                        }
                    }

                    // Age group
                    if (isset($eventInfo[4]) && isset($eventInfo[5])) {
                        $minAge = trim($eventInfo[4]);
                        $maxAge = trim($eventInfo[5]);
                        if ($minAge && $maxAge) {
                            $event['age_group'] = $minAge . '-' . $maxAge;
                        } elseif ($minAge) {
                            $event['age_group'] = $minAge . '+';
                        }
                    }

                    // Construct event name
                    $event['name'] = $event['distance'] . 'm ' . $event['stroke'] . ' (' . $event['age_group'] . ' ' . $event['gender'] . ')';

                    $meetData['events'][] = $event;
                }
            }
            break;
            
        case 'ev3':
            throw new Exception("File type $fileType is not yet supported");
            break;
    }
    
    // Validate required fields
    if (empty($meetData['name']) || empty($meetData['start_date']) || 
        empty($meetData['end_date']) || empty($meetData['venue']) || 
        empty($meetData['closing_date']) || empty($meetData['course'])) {
        throw new Exception("Required meet information is missing from the file. Found: " . print_r($meetData, true));
    }
    
    return $meetData;
}

// Get all meets
$stmt = $conn->prepare("SELECT * FROM meets WHERE club = ? ORDER BY start_date ASC");
$stmt->bind_param("s", $club);
$stmt->execute();
$meets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitions Management - Admin</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        body {
            background-color: #ffffff;
            color: #333333;
        }
        main {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .upload-section, .meets-section {
            background-color: #ffffff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #dddddd;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dddddd;
        }
        th {
            background-color: #f5f5f5;
        }
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 2px;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .error {
            color: #dc3545;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #dc3545;
            border-radius: 4px;
            background-color: #f8d7da;
        }
        .success {
            color: #28a745;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #28a745;
            border-radius: 4px;
            background-color: #d4edda;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="file"] {
            padding: 10px;
            border: 1px solid #dddddd;
            border-radius: 4px;
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h2>Loading...</h2>
        </div>
    </div>

    <?php include 'includes/admin_navigation.php'; ?>
    <main>
        <h1>Competitions Management</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <section class="upload-section">
            <h2>Upload Meet File</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="meetFile">Select Meet File (.hyv, .ev3):</label>
                    <input type="file" id="meetFile" name="meetFile" accept=".hyv,.ev3" required>
                </div>
                <button type="submit" class="button">Upload</button>
            </form>
        </section>
        
        <section class="meets-section">
            <h2>Current Meets</h2>
            <?php if ($meets && $meets->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Dates</th>
                            <th>Venue</th>
                            <th>Closing Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($meet = $meets->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($meet['name']); ?></td>
                                <td><?php echo htmlspecialchars($meet['start_date'] . ' to ' . $meet['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($meet['venue']); ?></td>
                                <td><?php echo htmlspecialchars($meet['closing_date']); ?></td>
                                <td><?php echo htmlspecialchars($meet['status']); ?></td>
                                <td>
                                    <a href="viewMeet?id=<?php echo $meet['id']; ?>" class="button">View</a>
                                    <!--<a href="meetResults?id=<?php echo $meet['id']; ?>" class="button">Results</a>-->
                                    <a href="meetEntries?id=<?php echo $meet['id']; ?>" class="button">Entries</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No meets found.</p>
            <?php endif; ?>
        </section>
    </main>
    <script>
        // Add this at the beginning of your script section
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen when page is fully loaded
            const loadingScreen = document.querySelector('.loading-screen');
            if (loadingScreen) {
                // Add fade-out class
                loadingScreen.classList.add('fade-out');
                // Remove from DOM after animation
                setTimeout(() => {
                    loadingScreen.remove();
                }, 500);
            }

            // Scroll reveal effect
            const sections = document.querySelectorAll('section');
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    } else {
                        entry.target.classList.remove('active');
                    }
                });
            }, { threshold: 0.1 });

            sections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html> 