<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/db.php';

// Get meet ID from URL
$meetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($meetId === 0) {
    header('Location: competitions.php');
    exit;
}

// Get meet details
$stmt = $conn->prepare("SELECT * FROM meets WHERE id = ?");
$stmt->bind_param("i", $meetId);
$stmt->execute();
$meet = $stmt->get_result()->fetch_assoc();

if (!$meet) {
    header('Location: competitions.php');
    exit;
}

// Get meet events
$stmt = $conn->prepare("
    SELECT e.*, 
           COUNT(me.id) as entry_count
    FROM meet_events e
    LEFT JOIN meet_entries me ON e.id = me.event_id
    WHERE e.meet_id = ?
    GROUP BY e.id
    ORDER BY e.event_number ASC
");
$stmt->bind_param("i", $meetId);
$stmt->execute();
$events = $stmt->get_result();

// Get user's entries for this meet
$stmt = $conn->prepare("
    SELECT me.*, e.event_name, e.distance, e.stroke, g.item_name as athlete_name
    FROM meet_entries me
    JOIN meet_events e ON me.event_id = e.id
    JOIN groups g ON me.athlete_id = g.id
    WHERE me.meet_id = ? AND g.user_id = ?
    ORDER BY e.event_number ASC
");
$stmt->bind_param("ii", $meetId, $_SESSION['user_id']);
$stmt->execute();
$userEntries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meet['name']); ?> - Meet Details</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <main>
        <div class="meet-header">
            <h1><?php echo htmlspecialchars($meet['name']); ?></h1>
            <div class="meet-info">
                <p><strong>Dates:</strong> <?php echo htmlspecialchars($meet['start_date'] . ' to ' . $meet['end_date']); ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($meet['venue']); ?></p>
                <p><strong>Closing Date:</strong> <?php echo htmlspecialchars($meet['closing_date']); ?></p>
                <?php if ($meet['closing_date'] >= date('Y-m-d')): ?>
                    <button onclick="showEntryForm()" class="button">Enter Events</button>
                <?php else: ?>
                    <p class="closed-notice">This meet is no longer accepting entries.</p>
                <?php endif; ?>
            </div>
        </div>

        <section class="meet-events">
            <h2>Events</h2>
            <?php if ($events->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Age Group</th>
                            <th>Gender</th>
                            <th>Entries</th>
                            <?php if ($typeAdmin == 0 && $typeCoach == 0): ?>
                                <th>Your Entries</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($event = $events->fetch_assoc()): ?>
                            <tr onclick="window.location.href='meetEntries.php?id=<?php echo $meetId; ?>&event_id=<?php echo $event['id']; ?>'">
                                <td>
                                    <?php echo htmlspecialchars($event['event_number']); ?>: 
                                    <?php echo htmlspecialchars($event['distance'] . 'm ' . $event['stroke']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($event['age_group']); ?></td>
                                <td><?php echo htmlspecialchars($event['gender']); ?></td>
                                <td><?php echo $event['entry_count']; ?></td>
                                <?php if ($typeAdmin == 0 && $typeCoach == 0): ?>
                                <td>
                                    <?php
                                    $userEntries->data_seek(0);
                                    $found = false;
                                    while ($entry = $userEntries->fetch_assoc()) {
                                        if ($entry['event_id'] === $event['id']) {
                                            echo htmlspecialchars($entry['athlete_name'] . ' - ' . $entry['entry_time'] . 's');
                                            $found = true;
                                            break;
                                        }
                                    }
                                    if (!$found) {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No events available for this meet.</p>
            <?php endif; ?>
        </section>

        <!-- Entry Form Modal -->
        <div id="entryModal" class="modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background-color: #f5f5f5; padding: 20px; border-radius: 10px;">
            <div class="modal-content">
                <span onmouseover="this.style.cursor='pointer';" class="close">&times;</span>
                <h2>Add Entry</h2>
                <form method="POST" action="competitions.php" class="entry-form">
                    <input type="hidden" name="action" value="submit_entry">
                    <input type="hidden" name="meet_id" value="<?php echo $meetId; ?>">
                    
                    <div class="form-group">
                        <label for="athlete">Select Athlete:</label>
                        <select name="athlete_id" id="athlete" required>
                            <?php
                            $stmt = $conn->prepare("SELECT id, item_name FROM groups WHERE user_id = ?");
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $athletes = $stmt->get_result();
                            while ($athlete = $athletes->fetch_assoc()) {
                                echo "<option value='" . $athlete['id'] . "'>" . htmlspecialchars($athlete['item_name']) . "</option>";
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
                        <label for="entry_time">Entry Time (seconds):</label>
                        <input type="number" step="0.01" name="entry_time" id="entry_time" required>
                    </div>
                    
                    <button type="submit" class="button">Submit Entry</button>
                </form>
            </div>
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
        </script>
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
                }, { threshold: 0.01 });
                
                sections.forEach(section => {
                    observer.observe(section);
                });
            });
        </script>
    </main>
</body>
</html> 