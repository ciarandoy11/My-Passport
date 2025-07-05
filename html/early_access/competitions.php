<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/db.php';

// Get user's athletes
$stmt = $conn->prepare("SELECT id, item_name FROM groups WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$athletes = $stmt->get_result();

// Get upcoming meets
$stmt = $conn->prepare("SELECT * FROM meets WHERE closing_date >= CURDATE() AND club = ? ORDER BY start_date ASC");
$stmt->bind_param("s", $club);
$stmt->execute();
$meets = $stmt->get_result();

// Handle meet entry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_entry') {
    $athleteId = $_POST['athlete_id'];
    $meetId = $_POST['meet_id'];
    $eventId = $_POST['event_id'];
    $entryTime = $_POST['entry_time'];
    
    // Verify athlete belongs to user
    $stmt = $conn->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $athleteId, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $error = "Invalid athlete selection.";
    } else {
        // Check if meet is still open
        $stmt = $conn->prepare("SELECT closing_date FROM meets WHERE id = ? AND closing_date >= CURDATE()");
        $stmt->bind_param("i", $meetId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $error = "This meet is no longer accepting entries.";
        } else {
            // Submit entry
            $stmt = $conn->prepare("INSERT INTO meet_entries (meet_id, athlete_id, event_id, entry_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $meetId, $athleteId, $eventId, $entryTime);
            
            if ($stmt->execute()) {
                $success = "Entry submitted successfully!";
            } else {
                $error = "Error submitting entry: " . $conn->error;
            }
        }
    }
}

// Get user's athlete entries
$stmt = $conn->prepare("
    SELECT me.*, m.name as meet_name, m.start_date, m.end_date, m.venue, 
           e.event_name, e.distance, e.stroke, a.item_name as athlete_name
    FROM meet_entries me
    JOIN meets m ON me.meet_id = m.id
    JOIN meet_events e ON me.event_id = e.id
    JOIN groups a ON me.athlete_id = a.id
    WHERE a.user_id = ?
    ORDER BY m.start_date DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$entries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitions</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h2>Loading...</h2>
        </div>
    </div>

    <?php include 'includes/navigation.php'; ?>
    <main>
        <h1>Competitions</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <section class="upcoming-meets">
            <h2>Upcoming Meets</h2>
            <?php if ($meets->num_rows > 0): ?>
                <table style="text-align: center; margin: 0 auto; vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Dates</th>
                            <th>Venue</th>
                            <th>Closing Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($meet = $meets->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align: center; vertical-align: middle;"><?php echo htmlspecialchars($meet['name']); ?></td>
                                <td style="text-align: center; vertical-align: middle;"><?php echo htmlspecialchars($meet['start_date'] . ' to ' . $meet['end_date']); ?></td>
                                <td style="text-align: center; vertical-align: middle;"><?php echo htmlspecialchars($meet['venue']); ?></td>
                                <td style="text-align: center; vertical-align: middle;"><?php echo htmlspecialchars($meet['closing_date']); ?></td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <button onclick="location.href='viewMeet?id=<?php echo $meet['id']; ?>'" class="button" style="background-color: blue; color: white;">View Details</button>
                                    <button onclick="showEntryForm(<?php echo $meet['id']; ?>)" class="button" style="background-color: blue; color: white;">Enter Events</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No upcoming meets available.</p>
            <?php endif; ?>
        </section>
        
        <section class="current-entries">
            <h2>Your Entries</h2>
            <?php if ($entries->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Athlete</th>
                            <th>Meet</th>
                            <th>Event</th>
                            <th>Entry Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($entry = $entries->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['athlete_name']); ?></td>
                                <td><?php echo htmlspecialchars($entry['meet_name']); ?></td>
                                <td><?php echo htmlspecialchars($entry['distance'] . 'm ' . $entry['stroke']); ?></td>
                                <td><?php echo htmlspecialchars($entry['entry_time']); ?></td>
                                <td><?php echo htmlspecialchars($entry['status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No entries found.</p>
            <?php endif; ?>
        </section>
        
        <!-- Entry Form Modal -->
        <div id="entryModal" class="modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background-color: #f5f5f5; padding: 20px; border-radius: 10px;">
            <div class="modal-content">
                <span onmouseover="this.style.cursor='pointer';" class="close">&times;</span>
                <h2>Add Entry</h2>
                <form method="POST" class="entry-form">
                    <input type="hidden" name="action" value="submit_entry">
                    <input type="hidden" name="meet_id" id="meet_id">
                    
                    <div class="form-group">
                        <label for="athlete">Select Athlete:</label>
                        <select name="athlete_id" id="athlete" required>
                            <?php
                            $athletes->data_seek(0);
                            while ($athlete = $athletes->fetch_assoc()) {
                                echo "<option value='" . $athlete['id'] . "'>" . htmlspecialchars($athlete['item_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="event">Select Event:</label>
                        <select name="event_id" id="event" required>
                            <!-- Will be populated via AJAX -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="entry_time">Entry Time (seconds):</label>
                        <input type="number" step="0.01" name="entry_time" id="entry_time" required>
                    </div>
                    
                    <button type="submit" class="button">Submit Entry</button>
                </form>
            </div>
        </div>
        
        <script>
        function showEntryForm(meetId) {
            document.getElementById('meet_id').value = meetId;
            document.getElementById('entryModal').style.display = 'block';
            
            // Fetch events for this meet
            fetch(`getMeetEvents.php?meet_id=${meetId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        eventSelect.innerHTML = `<option value="">Error: ${data.error}</option>`;
                        return;
                    }
                    
                    const eventSelect = document.getElementById('event');
                    // Clear loading message
                    eventSelect.innerHTML = '<option value="">Select an event</option>';
                    
                    // Add events to dropdown
                    data.events.forEach(event => {
                        const option = document.createElement('option');
                        option.value = event.id;
                        option.textContent = event.display_name;
                        eventSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    eventSelect.innerHTML = '<option value="">Error loading events</option>';
                });
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
        </script>
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