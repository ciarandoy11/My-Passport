<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/db';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_entries') {
        // Check if file was uploaded
        if (!isset($_FILES['entries_file'])) {
            $error = "No file was uploaded.";
        }
        // Check for upload errors
        else if ($_FILES['entries_file']['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES['entries_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error = "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $error = "Unknown upload error occurred.";
            }
        }
        // Validate file type
        else {
            $fileExtension = strtolower(pathinfo($_FILES['entries_file']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['hy3', 'cl2'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $error = "Invalid file type. Please upload a Hy-Tek meet file (.hy3 or .cl2)";
            }
            // Validate file size (max 10MB)
            else if ($_FILES['entries_file']['size'] > 10 * 1024 * 1024) {
                $error = "File size exceeds the maximum limit of 10MB.";
            }
            else {
                $file = fopen($_FILES['entries_file']['tmp_name'], 'r');
                if ($file) {
                    $successCount = 0;
                    $errorCount = 0;
                    $errors = [];
                    $currentEvent = null;
                    $meetId = $_POST['meet_id'];
                    $lineNumber = 0;
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        $club = '';
                        $softwareVersion = '';
                        $meetName = '';
                        $athletes = []; // Store all athletes first
                        $entries = []; // Store all entries to process later
                        $relayTeams = [];
                        
                        // First pass: collect all athletes and entries
                        while (($line = fgets($file)) !== FALSE) {
                            $lineNumber++;
                            $line = trim($line);
                            
                            // Skip empty lines
                            if (empty($line)) continue;
                            
                            // Parse different record types
                            $recordType = substr($line, 0, 2);
                            
                            switch ($recordType) {
                                case 'A1': // Meet header
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 3) {
                                        $meetName = trim($parts[0]);
                                        $softwareVersion = trim($parts[2]);
                                    }
                                    break;
                                    
                                case 'B1': // Club information
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 2) {
                                        $club = trim($parts[0]);
                                    }
                                    break;
                                    
                                case 'D1': // Athlete information
                                	$athleteId = '';
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 7) {
                                        $nameParts = explode(' ', trim($parts[1]));
                                        $firstName = array_pop($nameParts); // Get last part as first name
                                        $surname = implode(' ', $nameParts); // Join remaining parts as surname
                                        
                                        $athletes[trim($parts[0])] = [
                                            'id' => trim($parts[0]),
                                            'name' => $firstName . ' ' . $surname,
                                            'dob' => trim($parts[4]),
                                            'age' => trim($parts[5]),
                                            'gender' => substr($parts[0], 0, 1),
                                            'group' => trim($parts[6])
                                        ];
                                        $athleteId = trim($parts[0]);
                                    }
                                    break;
                                    
                                case 'E1': // Event entry
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 8) {
                                        $entries[] = [
                                            'athlete_id' => $athleteId,
                                            'event_info' => [
                                                'gender' => substr($parts[0], 0, 1),
                                                'distance' => trim($parts[2]),
                                                'stroke' => trim($parts[3]),
                                                'age_group' => trim($parts[4]) . ' ' . trim($parts[5]),
                                                'entry_time' => trim($parts[7])
                                            ]
                                        ];
                                    }
                                    break;
                            }
                        }
                        
                        // Second pass: process athletes and create them in database
                        foreach ($athletes as $athleteId => $athlete) {
                        	echo $athlete;
                            $group = explode('-', $athlete['group'])[0];
                            $split = explode('-', $athlete['group'])[1];
                            
                            // Check if athlete exists
                            $stmt = $conn->prepare("
                                SELECT id FROM groups 
                                WHERE item_name = ? AND club = ?
                            ");
                            if ($stmt === false) {
                                $errors[] = "Error preparing athlete check statement: " . $conn->error;
                                $errorCount++;
                                continue;
                            }
                            
                            $stmt->bind_param("ss", $athlete['name'], $club);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows === 0) {
                                $stmt = $conn->prepare("
                                    INSERT INTO groups 
                                    (item_name, club, list_name, split)
                                    VALUES (?, ?, ?, ?)
                                ");
                                if ($stmt === false) {
                                    $errors[] = "Error preparing athlete insert statement: " . $conn->error;
                                    $errorCount++;
                                    continue;
                                }
                                
                                $stmt->bind_param("ssss", 
                                    $athlete['name'], 
                                    $club,
                                    $group,
                                    $split
                                );
                                if (!$stmt->execute()) {
                                    $errors[] = "Error creating athlete: " . $stmt->error;
                                    $errorCount++;
                                    continue;
                                }
                                $athletes[$athleteId]['db_id'] = $conn->insert_id;
                            } else {
                                $athletes[$athleteId]['db_id'] = $result->fetch_assoc()['id'];
                            }
                        }
                        
                        // Third pass: process entries
                        foreach ($entries as $entry) {
                        	echo $entry;
                            if (!isset($athletes[$entry['athlete_id']])) {
                                $errors[] = "Athlete ID {$entry['athlete_id']} not found";
                                $errorCount++;
                                continue;
                            }
                            
                            $eventInfo = $entry['event_info'];
                            
                            // Convert time format if needed
                            if (strpos($eventInfo['entry_time'], ':') === false) {
                                if (!is_numeric($eventInfo['entry_time'])) {
                                    $errors[] = "Invalid entry time format for athlete {$entry['athlete_id']}";
                                    $errorCount++;
                                    continue;
                                }
                                $minutes = floor($eventInfo['entry_time'] / 60);
                                $seconds = $eventInfo['entry_time'] % 60;
                                $eventInfo['entry_time'] = sprintf("%d:%05.2f", $minutes, $seconds);
                            }
                            
                            // Check if event exists
                            $stmt = $conn->prepare("
                                SELECT id FROM meet_events 
                                WHERE meet_id = ? AND distance = ? AND stroke = ? AND age_group = ? AND gender = ?
                            ");
                            if ($stmt === false) {
                                $errors[] = "Error preparing event check statement: " . $conn->error;
                                $errorCount++;
                                continue;
                            }
                            
                            $stmt->bind_param("issss", 
                                $meetId, 
                                $eventInfo['distance'],
                                $eventInfo['stroke'],
                                $eventInfo['age_group'],
                                $eventInfo['gender']
                            );
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows === 0) {
                                $stmt = $conn->prepare("
                                    INSERT INTO meet_events 
                                    (meet_id, distance, stroke, age_group, gender)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                if ($stmt === false) {
                                    $errors[] = "Error preparing event insert statement: " . $conn->error;
                                    $errorCount++;
                                    continue;
                                }
                                
                                $stmt->bind_param("issss", 
                                    $meetId,
                                    $eventInfo['distance'],
                                    $eventInfo['stroke'],
                                    $eventInfo['age_group'],
                                    $eventInfo['gender']
                                );
                                if (!$stmt->execute()) {
                                    $errors[] = "Error creating event: " . $stmt->error;
                                    $errorCount++;
                                    continue;
                                }
                                $eventId = $conn->insert_id;
                            } else {
                                $eventId = $result->fetch_assoc()['id'];
                            }
                            
                            // Check if entry exists
                            $stmt = $conn->prepare("
                                SELECT id FROM meet_entries 
                                WHERE meet_id = ? AND event_id = ? AND athlete_id = ?
                            ");
                            if ($stmt === false) {
                                $errors[] = "Error preparing entry check statement: " . $conn->error;
                                $errorCount++;
                                continue;
                            }
                            
                            $stmt->bind_param("iii", $meetId, $eventId, $athletes[$entry['athlete_id']]['db_id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows === 0) {
                                $stmt = $conn->prepare("
                                    INSERT INTO meet_entries 
                                    (meet_id, event_id, athlete_id, entry_time)
                                    VALUES (?, ?, ?, ?)
                                ");
                                if ($stmt === false) {
                                    $errors[] = "Error preparing entry insert statement: " . $conn->error;
                                    $errorCount++;
                                    continue;
                                }
                                
                                $stmt->bind_param("iiis", 
                                    $meetId, 
                                    $eventId, 
                                    $athletes[$entry['athlete_id']]['db_id'], 
                                    $eventInfo['entry_time']
                                );
                                if (!$stmt->execute()) {
                                    $errors[] = "Error creating entry: " . $stmt->error;
                                    $errorCount++;
                                    continue;
                                }
                                $successCount++;
                            }
                        }
                        
                        // Commit transaction if no errors
                        if ($errorCount === 0) {
                            $conn->commit();
                            if ($successCount > 0) {
                                $success = sprintf(
                                    "Successfully imported entries:<br>" .
                                    "• Total Entries: %d<br>" .
                                    "• File: %s<br>" .
                                    "• Meet: %s<br>" .
                                    "• Club: %s<br>" .
                                    "• Import Date: %s<br>" .
                                    "• File Format: Hy-Tek Meet Manager (.hy3)<br>" .
                                    "• Software Version: %s",
                                    $successCount,
                                    htmlspecialchars($_FILES['entries_file']['name']),
                                    htmlspecialchars($meetName),
                                    htmlspecialchars($club),
                                    date('Y-m-d H:i:s'),
                                    htmlspecialchars($softwareVersion ?? 'Unknown')
                                );
                            } else {
                                $success = sprintf(
                                    "No new entries were imported:<br>" .
                                    "• File: %s<br>" .
                                    "• Meet: %s<br>" .
                                    "• Club: %s<br>" .
                                    "• Import Date: %s<br>" .
                                    "• Reason: All entries already exist in the system",
                                    htmlspecialchars($_FILES['entries_file']['name']),
                                    htmlspecialchars($meetName),
                                    htmlspecialchars($club),
                                    date('Y-m-d H:i:s')
                                );
                            }
                        } else {
                            // Rollback transaction if there were errors
                            $conn->rollback();
                            $error = sprintf(
                                "Failed to import entries:<br>" .
                                "• File: %s<br>" .
                                "• Meet: %s<br>" .
                                "• Club: %s<br>" .
                                "• Import Date: %s<br>" .
                                "• Error Count: %d<br>" .
                                "• Errors:<br>%s",
                                htmlspecialchars($_FILES['entries_file']['name']),
                                htmlspecialchars($meetName),
                                htmlspecialchars($club),
                                date('Y-m-d H:i:s'),
                                $errorCount,
                                implode("<br>", $errors)
                            );
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $error = "Error importing entries: " . $e->getMessage();
                    }
                    
                    fclose($file);
                } else {
                    $error = "Error opening uploaded file.";
                }
            }
        }
    }
}

// Get meet ID from URL
$meetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($meetId === 0) {
    header('Location: competitions');
    exit;
}

// Get meet details
$stmt = $conn->prepare("SELECT * FROM meets WHERE id = ?");
$stmt->bind_param("i", $meetId);
$stmt->execute();
$meet = $stmt->get_result()->fetch_assoc();

if (!$meet) {
    header('Location: competitions');
    exit;
}

// Handle entry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_entry') {
    $athleteId = $_POST['athlete_id'];
    $eventId = $_POST['event_id'];
    $entryTime = $_POST['entry_time'];
    
    // Get club from the athlete's group
    $stmt = $conn->prepare("SELECT club FROM groups WHERE id = ?");
    $stmt->bind_param("i", $athleteId);
    $stmt->execute();
    $club = $stmt->get_result()->fetch_assoc()['club'];
    
    // Submit entry
    $stmt = $conn->prepare("INSERT INTO meet_entries (meet_id, club, athlete_id, event_id, entry_time) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error = "Error preparing statement: " . $conn->error;
    } else {
        $stmt->bind_param("issid", $meetId, $club, $athleteId, $eventId, $entryTime);
        
        if ($stmt->execute()) {
            $success = "Entry submitted successfully!";
        } else {
            $error = "Error submitting entry: " . $conn->error;
        }
    }
}

// Get all entries for this meet
if ($eventId === 0) {
    $stmt = $conn->prepare("
        SELECT 
            me.*,
        e.event_number,
        e.distance,
        e.stroke,
        e.age_group,
        e.gender,
        g.item_name as athlete_name,
        g.club
    FROM meet_entries me
    JOIN meet_events e ON me.event_id = e.id
    JOIN groups g ON me.athlete_id = g.id
    WHERE me.meet_id = ?
    ORDER BY e.event_number ASC, g.item_name ASC
");
    $stmt->bind_param("i", $meetId);
    $stmt->execute();
    $entries = $stmt->get_result();
} else {
    $stmt = $conn->prepare("
        SELECT 
            me.*,
            e.event_number,
            e.distance,
            e.stroke,
            e.age_group,
            e.gender,
            g.item_name as athlete_name,
            g.club
        FROM meet_entries me
        JOIN meet_events e ON me.event_id = e.id
        JOIN groups g ON me.athlete_id = g.id
        WHERE me.event_id = ?
        ORDER BY e.event_number ASC, g.item_name ASC
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $entries = $stmt->get_result();
}

// Get all athletes in the club
$stmt = $conn->prepare("
    SELECT id, item_name, list_name, split, club 
    FROM groups 
    WHERE club = (SELECT club FROM groups WHERE id = ?)
    ORDER BY item_name ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$athletes = $stmt->get_result();

// Get all events for this meet
$stmt = $conn->prepare("SELECT * FROM meet_events WHERE meet_id = ? ORDER BY event_number ASC");
$stmt->bind_param("i", $meetId);
$stmt->execute();
$events = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entries - <?php echo htmlspecialchars($meet['name']); ?></title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        /* ... existing styles ... */
        .upload-section {
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .upload-section h3 {
            margin-top: 0;
        }
        .file-types {
            margin-top: 10px;
            font-family: monospace;
            white-space: pre;
            background-color: #fff;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation'; ?>
    
    <main>
        <div class="meet-header">
            <h1>Entries - <?php echo htmlspecialchars($meet['name']); ?></h1>
            <div class="meet-info">
                <p><strong>Dates:</strong> <?php echo htmlspecialchars($meet['start_date'] . ' to ' . $meet['end_date']); ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($meet['venue']); ?></p>
                <?php if ($meet['closing_date'] >= date('Y-m-d')): ?>
                    <button onclick="showEntryForm()" class="button">Add Entry</button>
                <?php else: ?>
                    <p class="closed-notice">This meet is no longer accepting entries.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <section class="meet-entries">
            <?php if ($entries->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Athlete</th>
                            <th>Entry Time</th>
                            <th>Club</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($entry = $entries->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($entry['event_number'] . ': ' . 
                                        $entry['distance'] . 'm ' . $entry['stroke'] . 
                                        ' (' . $entry['age_group'] . ' ' . $entry['gender'] . ')'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['athlete_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($entry['entry_time'], 0, 3) == '00:' ? substr($entry['entry_time'], 3) : $entry['entry_time']); ?></td>
                                <td><?php echo htmlspecialchars($entry['club']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No entries found for this meet or event.</p>
            <?php endif; ?>
        </section>

        <!-- Entry Form Modal -->
        <div id="entryModal" class="modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background-color: #f5f5f5; padding: 20px; border-radius: 10px;">
            <div class="modal-content">
                <span onmouseover="this.style.cursor='pointer';" class="close">&times;</span>
                <h2>Add Entry</h2>
                <form method="POST" class="entry-form">
                    <input type="hidden" name="action" value="submit_entry">
                    
                    <div class="form-group">
                        <label for="athlete">Select Athlete:</label>
                        <select name="athlete_id" id="athlete" required>
                            <?php
                            $athletes->data_seek(0);
                            while ($athlete = $athletes->fetch_assoc()) {
                                echo "<option value='" . $athlete['id'] . "'>" . 
                                     htmlspecialchars($athlete['item_name'] . ' (' . $athlete['list_name'] . '-' . $athlete['split'] . ')') . 
                                     "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="event">Select Event:</label>
                        <select name="event_id" id="event" required>
                            <?php
                            $events->data_seek(0);
                            while ($event = $events->fetch_assoc()) {
                                echo "<option value='" . $event['id'] . "'>" . 
                                     htmlspecialchars($event['event_number'] . ': ' . $event['distance'] . 'm ' . $event['stroke'] . 
                                     ' (' . $event['age_group'] . ' ' . $event['gender'] . ')') . 
                                     "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="entry_time">Entry Time (minutes:seconds.100ths):</label>
                        <input type="text" pattern="[0-9]+:[0-9]{2}\.[0-9]{2}" name="entry_time" id="entry_time" required placeholder="e.g: 1:27.34">
                    </div>
                    
                    <button type="submit" class="button">Submit Entry</button>
                </form>
            </div>
        </div>

        <div class="upload-section">
            <h3>Import Entries from Hy-Tek Entries File</h3>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="action" value="upload_entries">
                <input type="hidden" name="meet_id" value="<?php echo $meetId; ?>">
                <div class="form-group">
                    <label for="entries_file">Select Hy-Tek Entries File:</label>
                    <input type="file" name="entries_file" id="entries_file" accept=".hyv,.hy3,.cl2" required>
                </div>
                <button type="submit" class="button">Upload Entries</button>
            </form>
            
            <div class="file-types">
                <strong>Supported File Types:</strong>
                - .hy3
                - .cl2
            </div>
            <p><small>Note: The file must be exported from Hy-Tek Meet Manager or compatible software.</small></p>
        </div>

        <script>
        function showEntryForm() {
            document.getElementById('entryModal').style.display = 'block';
        }
        
        // Close modal when clicking the X
        document.querySelector('.close').onclick = function() {
            document.getElementById('entryModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('entryModal')) {
                document.getElementById('entryModal').style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen when page is fully loaded
            const loadingScreen = document.querySelector('.loading-screen');
            if (loadingScreen) { 
                loadingScreen.classList.add('fade-out');
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
    </main>
</body>
</html> 
