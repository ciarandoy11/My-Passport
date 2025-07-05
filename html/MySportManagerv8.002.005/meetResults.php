<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/db';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_results') {
        if (isset($_FILES['results_file']) && $_FILES['results_file']['error'] === UPLOAD_ERR_OK) {
            $fileExtension = strtolower(pathinfo($_FILES['results_file']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['hyv', 'hy3', 'ev3', 'cl2'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $error = "Invalid file type. Please upload a Hy-Tek meet file (.hyv, .hy3, .ev3, or .cl2)";
            } else {
                $file = fopen($_FILES['results_file']['tmp_name'], 'r');
                if ($file) {
                    $successCount = 0;
                    $errorCount = 0;
                    $errors = [];
                    $currentEvent = null;
                    $currentHeat = null;
                    
                    while (($line = fgets($file)) !== FALSE) {
                        $line = trim($line);
                        
                        // Skip empty lines
                        if (empty($line)) continue;
                        
                        // Parse different file types
                        switch ($fileExtension) {
                            case 'hyv':
                            case 'hy3':
                                // Event line
                                if (strpos($line, 'E0') === 0) {
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 4) {
                                        $currentEvent = [
                                            'number' => $parts[1],
                                            'distance' => $parts[2],
                                            'stroke' => $parts[3],
                                            'age_group' => $parts[4] ?? '',
                                            'gender' => $parts[5] ?? ''
                                        ];
                                    }
                                }
                                // Heat line
                                else if (strpos($line, 'H0') === 0) {
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 2) {
                                        $currentHeat = $parts[1];
                                    }
                                }
                                // Result line
                                else if (strpos($line, 'R0') === 0) {
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 6 && $currentEvent) {
                                        $entryId = $parts[1];
                                        $resultTime = $parts[2];
                                        $position = $parts[3];
                                        $disqualified = strtolower($parts[4]) === 'dq' ? 1 : 0;
                                        $disqualificationReason = $parts[5] ?? null;
                                        
                                        // Convert time format if needed
                                        if (strpos($resultTime, ':') === false) {
                                            $minutes = floor($resultTime / 60);
                                            $seconds = $resultTime % 60;
                                            $resultTime = sprintf("%d:%05.2f", $minutes, $seconds);
                                        }
                                        
                                        $resultType = $currentHeat ? 'heat' : 'final';
                                        
                                        $stmt = $conn->prepare("
                                            INSERT INTO meet_results 
                                            (entry_id, result_time, position, disqualified, disqualification_reason, result_type)
                                            VALUES (?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        if ($stmt === false) {
                                            $errors[] = "Error preparing statement for entry $entryId: " . $conn->error;
                                            $errorCount++;
                                            continue;
                                        }
                                        
                                        $stmt->bind_param("isiss", $entryId, $resultTime, $position, $disqualified, $disqualificationReason, $resultType);
                                        
                                        if ($stmt->execute()) {
                                            $successCount++;
                                        } else {
                                            $errors[] = "Error adding result for entry $entryId: " . $conn->error;
                                            $errorCount++;
                                        }
                                    }
                                }
                                break;
                                
                            case 'ev3':
                                // Event results
                                if (strpos($line, 'E') === 0) {
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 6) {
                                        $entryId = $parts[1];
                                        $resultTime = $parts[2];
                                        $position = $parts[3];
                                        $disqualified = strtolower($parts[4]) === 'dq' ? 1 : 0;
                                        $disqualificationReason = $parts[5] ?? null;
                                        $resultType = 'final';
                                        
                                        $stmt = $conn->prepare("
                                            INSERT INTO meet_results 
                                            (entry_id, result_time, position, disqualified, disqualification_reason, result_type)
                                            VALUES (?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        if ($stmt === false) {
                                            $errors[] = "Error preparing statement for entry $entryId: " . $conn->error;
                                            $errorCount++;
                                            continue;
                                        }
                                        
                                        $stmt->bind_param("isiss", $entryId, $resultTime, $position, $disqualified, $disqualificationReason, $resultType);
                                        
                                        if ($stmt->execute()) {
                                            $successCount++;
                                        } else {
                                            $errors[] = "Error adding result for entry $entryId: " . $conn->error;
                                            $errorCount++;
                                        }
                                    }
                                }
                                break;
                                
                            case 'cl2':
                                // Heat results
                                if (strpos($line, 'H') === 0) {
                                    $parts = explode(',', $line);
                                    if (count($parts) >= 6) {
                                        $entryId = $parts[1];
                                        $resultTime = $parts[2];
                                        $position = $parts[3];
                                        $disqualified = strtolower($parts[4]) === 'dq' ? 1 : 0;
                                        $disqualificationReason = $parts[5] ?? null;
                                        $resultType = 'heat';
                                        
                                        $stmt = $conn->prepare("
                                            INSERT INTO meet_results 
                                            (entry_id, result_time, position, disqualified, disqualification_reason, result_type)
                                            VALUES (?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        if ($stmt === false) {
                                            $errors[] = "Error preparing statement for entry $entryId: " . $conn->error;
                                            $errorCount++;
                                            continue;
                                        }
                                        
                                        $stmt->bind_param("isiss", $entryId, $resultTime, $position, $disqualified, $disqualificationReason, $resultType);
                                        
                                        if ($stmt->execute()) {
                                            $successCount++;
                                        } else {
                                            $errors[] = "Error adding result for entry $entryId: " . $conn->error;
                                            $errorCount++;
                                        }
                                    }
                                }
                                break;
                        }
                    }
                    fclose($file);
                    
                    if ($successCount > 0) {
                        $success = "Successfully imported $successCount results.";
                    }
                    if ($errorCount > 0) {
                        $error = "Failed to import $errorCount results. " . implode("<br>", $errors);
                    }
                } else {
                    $error = "Error opening uploaded file.";
                }
            }
        } else {
            $error = "Please select a valid Hy-Tek meet file to upload.";
        }
    }
    // Handle result submission
    if ($_POST['action'] === 'submit_result') {
        $entryId = $_POST['entry_id'];
        $resultTime = $_POST['result_time'];
        $position = $_POST['position'];
        $disqualified = isset($_POST['disqualified']) ? 1 : 0;
        $disqualificationReason = $_POST['disqualification_reason'] ?? null;
        $resultType = $_POST['result_type'];
        
        // Validate time format
        if (!preg_match('/^\d+:[0-5][0-9]\.[0-9]{2}$/', $resultTime)) {
            $error = "Invalid time format. Please use MM:SS.mm format (e.g., 1:27.34)";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO meet_results 
                (entry_id, result_time, position, disqualified, disqualification_reason, result_type)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt === false) {
                $error = "Error preparing statement: " . $conn->error;
            } else {
                $stmt->bind_param("isiss", $entryId, $resultTime, $position, $disqualified, $disqualificationReason, $resultType);
                
                if ($stmt->execute()) {
                    $success = "Result added successfully!";
                } else {
                    $error = "Error adding result: " . $conn->error;
                }
            }
        }
    }
}

// Get all meets with entries
$stmt = $conn->prepare("
    SELECT DISTINCT m.* 
    FROM meets m
    JOIN meet_entries me ON m.id = me.meet_id
    ORDER BY m.start_date DESC
");
$stmt->execute();
$meets = $stmt->get_result();

// Get meet ID from URL or default to most recent
$selectedMeetId = isset($_GET['meet_id']) ? (int)$_GET['meet_id'] : 0;
if ($selectedMeetId === 0 && $meets->num_rows > 0) {
    $selectedMeetId = $meets->fetch_assoc()['id'];
    $meets->data_seek(0);
}

// Get entries and their results for selected meet
if ($selectedMeetId > 0) {
    $stmt = $conn->prepare("
        SELECT 
            me.*,
            e.event_number,
            e.distance,
            e.stroke,
            e.age_group,
            e.gender,
            g.item_name as athlete_name,
            g.club,
            GROUP_CONCAT(
                CONCAT(
                    mr.result_type, '|',
                    mr.result_time, '|',
                    mr.position, '|',
                    mr.disqualified, '|',
                    mr.disqualification_reason, '|',
                    mr.created_at
                )
                ORDER BY mr.created_at DESC
                SEPARATOR '||'
            ) as results
        FROM meet_entries me
        JOIN meet_events e ON me.event_id = e.id
        JOIN groups g ON me.athlete_id = g.id
        LEFT JOIN meet_results mr ON me.id = mr.entry_id
        WHERE me.meet_id = ?
        GROUP BY me.id
        ORDER BY e.event_number ASC, me.id ASC
    ");
    $stmt->bind_param("i", $selectedMeetId);
    $stmt->execute();
    $entries = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet Results</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        .result-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .result-row td {
            padding: 8px;
        }
        .disqualified {
            color: #dc3545;
            font-weight: bold;
        }
        .completed {
            color: #28a745;
        }
        .result-type {
            font-weight: bold;
            color: #007bff;
        }
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
        <h1>Meet Results</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="meet-selector">
            <form method="GET" class="inline-form">
                <label for="meet_id">Select Meet:</label>
                <select name="meet_id" id="meet_id" onchange="this.form.submit()">
                    <?php while ($meet = $meets->fetch_assoc()): ?>
                        <option value="<?php echo $meet['id']; ?>" <?php echo $meet['id'] == $selectedMeetId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($meet['name'] . ' (' . $meet['start_date'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedMeetId > 0): ?>
            <div class="upload-section">
                <h3>Import Results from Hy-Tek Meet File</h3>
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="action" value="upload_results">
                    <div class="form-group">
                        <label for="results_file">Select Hy-Tek Meet File:</label>
                        <input type="file" name="results_file" id="results_file" accept=".hyv,.hy3,.ev3,.cl2" required>
                    </div>
                    <button type="submit" class="button">Upload Results</button>
                </form>
                
                <div class="file-types">
                    <strong>Supported File Types:</strong>
                    - .hyv (Hy-Tek Meet Manager)
                    - .hy3 (Hy-Tek Meet Manager)
                    - .ev3 (Hy-Tek Event File)
                    - .cl2 (Hy-Tek Heat Sheet)
                </div>
                <p><small>Note: The file must be exported from Hy-Tek Meet Manager or compatible software.</small></p>
            </div>
        <?php endif; ?>

        <?php if (isset($entries) && $entries->num_rows > 0): ?>
            <section class="meet-results">
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Athlete</th>
                            <th>Club</th>
                            <th>Entry Time</th>
                            <th>Results</th>
                            <th>Actions</th>
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
                                <td><?php echo htmlspecialchars($entry['club']); ?></td>
                                <td><?php echo htmlspecialchars($entry['entry_time']); ?></td>
                                <td>
                                    <?php if ($entry['results']): ?>
                                        <?php 
                                        $results = explode('||', $entry['results']);
                                        foreach ($results as $result):
                                            list($type, $time, $pos, $dq, $reason, $date) = explode('|', $result);
                                        ?>
                                            <div class="result-row">
                                                <span class="result-type"><?php echo htmlspecialchars($type); ?></span>
                                                Time: <?php echo htmlspecialchars($time); ?> |
                                                Position: <?php echo htmlspecialchars($pos); ?> |
                                                Status: <?php echo $dq ? '<span class="disqualified">DQ</span>' : '<span class="completed">Completed</span>'; ?>
                                                <?php if ($dq && $reason): ?>
                                                    (<?php echo htmlspecialchars($reason); ?>)
                                                <?php endif; ?>
                                                <br>
                                                <small>Recorded: <?php echo date('Y-m-d H:i', strtotime($date)); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        No results recorded
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="showResultForm(<?php echo $entry['id']; ?>)" class="button">Add Result</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>
        <?php else: ?>
            <p>No entries found for this meet.</p>
        <?php endif; ?>

        <!-- Result Form Modal -->
        <div id="resultModal" class="modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background-color: #f5f5f5; padding: 20px; border-radius: 10px;">
            <div class="modal-content">
                <span onmouseover="this.style.cursor='pointer';" class="close">&times;</span>
                <h2>Add Result</h2>
                <form method="POST" class="result-form">
                    <input type="hidden" name="action" value="submit_result">
                    <input type="hidden" name="entry_id" id="entry_id">
                    
                    <div class="form-group">
                        <label for="result_type">Result Type:</label>
                        <select name="result_type" id="result_type" required>
                            <option value="heat">Heat</option>
                            <option value="semi-final">Semi-Final</option>
                            <option value="final" selected>Final</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="result_time">Result Time (MM:SS.mm):</label>
                        <input type="text" pattern="[0-9]+:[0-5][0-9]\.[0-9]{2}" name="result_time" id="result_time" required placeholder="e.g: 1:27.34">
                        <small>Format: Minutes:Seconds.Centiseconds (e.g., 1:27.34)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position:</label>
                        <input type="number" name="position" id="position" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="disqualified" id="disqualified" onchange="toggleDisqualificationReason()">
                            Disqualified
                        </label>
                    </div>
                    
                    <div class="form-group" id="disqualification_reason_group" style="display: none;">
                        <label for="disqualification_reason">Disqualification Reason:</label>
                        <textarea name="disqualification_reason" id="disqualification_reason"></textarea>
                    </div>
                    
                    <button type="submit" class="button">Add Result</button>
                </form>
            </div>
        </div>

        <script>
        function showResultForm(entryId) {
            document.getElementById('entry_id').value = entryId;
            document.getElementById('resultModal').style.display = 'block';
        }
        
        function toggleDisqualificationReason() {
            const checkbox = document.getElementById('disqualified');
            const reasonGroup = document.getElementById('disqualification_reason_group');
            reasonGroup.style.display = checkbox.checked ? 'block' : 'none';
        }
        
        // Close modal when clicking the X
        document.querySelector('.close').onclick = function() {
            document.getElementById('resultModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('resultModal')) {
                document.getElementById('resultModal').style.display = 'none';
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