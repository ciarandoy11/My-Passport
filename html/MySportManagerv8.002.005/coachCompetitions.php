<?php
include __DIR__ . '/db

// Get all meets
$stmt = $conn->prepare("SELECT * FROM meets WHERE club = ? ORDER BY start_date ASC");
$stmt->bind_param("s", $club);
$stmt->execute();
$meets = $stmt->get_result();

// Handle manual time entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_time') {
    $athleteId = $_POST['athlete_id'];
    $eventId = $_POST['event_id'];
    $time = $_POST['time'];
    $date = $_POST['date'];
    $meetId = $_POST['meet_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO manual_times (athlete_id, event_id, time, meet_id, date_achieved, verified_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidsiis", $athleteId, $eventId, $time, $meetId, $date, $_SESSION['user_id'], $notes);
    
    if ($stmt->execute()) {
        $success = "Time recorded successfully!";
    } else {
        $error = "Error recording time: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitions - Coach</title>
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
    <main>
        <h1>Competitions Management</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <section class="meets-section">
            <h2>Current Meets</h2>
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
                                <a href="viewMeet<?php echo $meet['id']; ?>" class="button">View</a>
                                <a href="meetEntries<?php echo $meet['id']; ?>" class="button">Entries</a>
                                <a href="meetResults<?php echo $meet['id']; ?>" class="button">Results</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
        
        <section class="manual-times-section">
            <h2>Record Manual Times</h2>
            <form method="POST" class="manual-time-form">
                <input type="hidden" name="action" value="add_time">
                
                <div class="form-group">
                    <label for="athlete">Athlete:</label>
                    <select name="athlete_id" id="athlete" required>
                        <?php
                        $athletes = $conn->query("SELECT id, name FROM athletes ORDER BY name");
                        while ($athlete = $athletes->fetch_assoc()) {
                            echo "<option value='" . $athlete['id'] . "'>" . htmlspecialchars($athlete['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="event">Event:</label>
                    <select name="event_id" id="event" required>
                        <?php
                        $events = $conn->query("SELECT id, event_name, distance, stroke FROM meet_events ORDER BY distance, stroke");
                        while ($event = $events->fetch_assoc()) {
                            echo "<option value='" . $event['id'] . "'>" . 
                                 htmlspecialchars($event['distance'] . 'm ' . $event['stroke']) . 
                                 "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="time">Time (seconds):</label>
                    <input type="number" step="0.01" name="time" id="time" required>
                </div>
                
                <div class="form-group">
                    <label for="date">Date Achieved:</label>
                    <input type="date" name="date" id="date" required>
                </div>
                
                <div class="form-group">
                    <label for="meet">Meet (optional):</label>
                    <select name="meet_id" id="meet">
                        <option value="">None</option>
                        <?php
                        $meets->data_seek(0);
                        while ($meet = $meets->fetch_assoc()) {
                            echo "<option value='" . $meet['id'] . "'>" . htmlspecialchars($meet['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea name="notes" id="notes"></textarea>
                </div>
                
                <button type="submit" class="button">Record Time</button>
            </form>
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