<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/db.php';

// Define user role flags
$typeAdmin = $_SESSION['typeAdmin'] ?? 0;
$typeCoach = $_SESSION['typeCoach'] ?? 0;

// Fetch all meets with at least one entry
$stmt = $conn->prepare("
    SELECT DISTINCT m.*
    FROM meets m
    JOIN meet_entries me ON m.id = me.meet_id
    ORDER BY m.start_date DESC
");
$stmt->execute();
$meetsResult = $stmt->get_result();
$meets = $meetsResult->fetch_all(MYSQLI_ASSOC);

// Determine selected meet ID
$selectedMeetId = isset($_GET['meet_id']) ? (int) $_GET['meet_id'] : 0;
if ($selectedMeetId === 0 && count($meets) > 0) {
    $selectedMeetId = (int) $meets[0]['id'];
}

// Fetch entries + concatenated results if a valid meet is selected
$entries = [];
if ($selectedMeetId > 0) {
    $stmt = $conn->prepare("
        SELECT
            me.id AS entry_id,
            e.event_number,
            e.distance,
            e.stroke,
            e.age_group,
            e.gender,
            g.item_name AS athlete_name,
            g.club,
            GROUP_CONCAT(
                CONCAT_WS('|',
                    mr.result_type,
                    mr.result_time,
                    mr.position,
                    mr.disqualified,
                    mr.disqualification_reason,
                    mr.created_at
                ) SEPARATOR '||'
            ) AS results
        FROM meet_entries me
        JOIN meet_events e ON me.event_id = e.id
        JOIN `groups` g ON me.athlete_id = g.id
        LEFT JOIN meet_results mr ON me.id = mr.entry_id
        WHERE me.meet_id = ?
        GROUP BY me.id
        ORDER BY e.event_number ASC, me.id ASC
    ");
    $stmt->bind_param('i', $selectedMeetId);
    $stmt->execute();
    $entriesRes = $stmt->get_result();
    $entries = $entriesRes->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Meet Results</title>
<link rel="stylesheet" href="style.css?v=8.002.004" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css" />
<style>
    .result-row { background-color: #f8f9fa; border-top: 1px solid #dee2e6; padding: 8px; }
    .disqualified { color: #dc3545; font-weight: bold; }
    .completed { color: #28a745; }
    .result-type { font-weight: bold; color: #007bff; }
    .upload-section { margin: 20px 0; padding: 20px; background-color: #f8f9fa; border-radius: 5px; }
    .file-types { margin-top: 10px; font-family: monospace; white-space: pre; background-color: #fff; padding: 10px; border: 1px solid #dee2e6; border-radius: 3px; }
    .modal { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background-color: #f5f5f5; padding: 20px; border-radius: 10px; }
    .close { float: right; cursor: pointer; }
    .error { color: red; }
    .success { color: green; }
</style>
</head>
<body>

<?php if ($typeAdmin === 1): ?>
    <?php include 'includes/admin_navigation.php'; ?>
<?php elseif ($typeCoach === 1): ?>
    <!-- Coach nav can go here -->
<?php else: ?>
    <?php include 'includes/navigation.php'; ?>
<?php endif; ?>

<main>
    <h1>Meet Results</h1>

    <div class="meet-selector">
        <form method="GET" class="inline-form">
            <label for="meet_id">Select Meet:</label>
            <select name="meet_id" id="meet_id" onchange="this.form.submit()">
                <?php foreach ($meets as $meet): ?>
                    <option value="<?= htmlspecialchars($meet['id']) ?>" <?= $meet['id'] == $selectedMeetId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($meet['name'] . ' (' . $meet['start_date'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selectedMeetId > 0): ?>
        <section class="active">
            <h3>Upload Results File (Hytek)</h3>
            <input type="file" id="resultsFile" accept=".hy3" />
            <button id="uploadBtn" disabled>Upload</button>
            <div id="uploadMessage"></div>
            <div class="file-types">
                Acceptable file formats:<br>
                - Hytek Meet Results (.hy3)
            </div>
        </section>
    <?php endif; ?>

    <?php if (count($entries) > 0): ?>
        <section class="active">
            <table>
                <thead>
                    <tr><th>Event</th><th>Athlete</th><th>Club</th><th>Entry Time</th><th>Results</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars("{$entry['event_number']}: {$entry['distance']}m {$entry['stroke']} ({$entry['age_group']} {$entry['gender']})") ?></td>
                            <td><?= htmlspecialchars($entry['athlete_name']) ?></td>
                            <td><?= htmlspecialchars($entry['club']) ?></td>
                            <td><?= htmlspecialchars($entry['entry_time'] ?? '') ?></td>
                            <td>
                                <?php if ($entry['results']): ?>
                                    <?php foreach (explode('||', $entry['results']) as $res): 
                                        list($type, $time, $pos, $dq, $reason, $date) = explode('|', $res); ?>
                                        <div class="result-row">
                                            <span class="result-type"><?= htmlspecialchars($type) ?></span>
                                            Time: <?= htmlspecialchars($time) ?> |
                                            Pos: <?= htmlspecialchars($pos) ?> |
                                            Status: <?= $dq ? '<span class="disqualified">DQ</span>' : '<span class="completed">Completed</span>' ?>
                                            <?= $dq && $reason ? '(' . htmlspecialchars($reason) . ')' : '' ?><br>
                                            <small>Recorded: <?= date('Y-m-d H:i', strtotime($date)) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    No results recorded
                                <?php endif; ?>
                            </td>
                            <td><button onclick="showResultsForm(<?= $entry['entry_id'] ?>)">Add Result</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No entries found for this meet.</p>
            <?php endif; ?>
        </section>
    </main>

    <div id="resultsModal" class="modal">
        <div>
            <span class="close" onclick="closeResultsForm()">&times;</span>
            <h2>Add Result</h2>
            <div id="resultsFormMessage"></div>
            <form id="resultsForm">
                <input type="hidden" name="action" value="submit_result">
                <input type="hidden" name="entry_id" id="modal_entry_id">

                <div><label>Type:
                    <select name="result_type" required>
                        <option value="heat">Heat</option>
                        <option value="semi-final">Semi-Final</option>
                        <option value="final" selected>Final</option>
                    </select>
                </label></div>

                <div><label>Time (MM:SS.cc):
                    <input type="text" name="result_time" id="result_time" pattern="^[0-9]+:[0-9]{2}\.[0-9]{2}$" required placeholder="e.g. 01:27.34">
                </label></div>

                <div><label>Position:
                    <input type="number" name="position" min="1" required>
                </label></div>

                <div><label>
                    <input type="checkbox" name="disqualified" id="disqualified" onchange="toggleReason()"> Disqualified
                </label></div>

                <div id="dqReason" style="display:none">
                    <label>Reason: <textarea name="disqualification_reason"></textarea></label>
                </div>

                <button type="submit">Add Result</button>
            </form>
        </div>
    </div>

    <script>
    let activeEntryId = null;

    document.addEventListener('DOMContentLoaded', () => {
        const loadingScreen = document.querySelector('.loading-screen');
        if (loadingScreen) {
            loadingScreen.classList.add('fade-out');
            setTimeout(() => loadingScreen.style.display = 'none', 500);
        }

        const sections = document.querySelectorAll('section');
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('active');
            });
        }, { threshold: 0.2 });
        sections.forEach(section => observer.observe(section));

        const fileInput = document.getElementById('resultsFile');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadMsg = document.getElementById('uploadMessage');

        uploadBtn.disabled = true;
        fileInput.addEventListener('change', () => {
            uploadBtn.disabled = !fileInput.files.length;
            uploadMsg.textContent = '';
        });

        uploadBtn.addEventListener('click', () => {
            const file = fileInput.files[0];
            if (!file) return;

            uploadMsg.style.color = 'black';
            uploadMsg.textContent = 'Parsing file...';

            const reader = new FileReader();
            reader.onload = e => {
                try {
                    if (!file.name.toLowerCase().endsWith('.hy3')) throw new Error('Unsupported format');
                    const data = parseResultsHy3(e.target.result);
                    sendParsedData(data);
                } catch (err) {
                    uploadMsg.style.color = 'red';
                    uploadMsg.textContent = `Parsing error: ${err}`;
                }
            };
            reader.readAsText(file);
        });

        document.getElementById('resultsForm')?.addEventListener('submit', e => {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            fetch('submit_results_ajax.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    const msgEl = document.getElementById('resultsFormMessage');
                    msgEl.textContent = data.success ? 'Result added!' : data.error || 'Error';
                    msgEl.style.color = data.success ? 'green' : 'red';
                    if (data.success) form.reset();
                })
                .catch(() => alert('Network error'));
        });
    });

    function showResultsForm(entryId) {
        activeEntryId = entryId;
        document.getElementById('modal_entry_id').value = entryId;
        document.getElementById('resultsFormMessage').textContent = '';
        document.getElementById('resultsModal').style.display = 'block';
    }

    function closeResultsForm() {
        document.getElementById('resultsModal').style.display = 'none';
    }

    function toggleReason() {
        const cb = document.getElementById('disqualified');
        document.getElementById('dqReason').style.display = cb.checked ? 'block' : 'none';
    }

    function parseResultsHy3(text) {
        const lines = text.split(/\r?\n/);

        const athletes = [];
        const athleteMap = {};
        const eventMap = {};
        const results = [];
        let lastAthleteId = null;

        for (const line of lines) {
            if (!line.trim()) continue;

            if (line.startsWith('D')) {
                // Example: D1M  281Barrett             Charlie                                  30086973        11207052014  9     0                   N   60
                const id = line.slice(5, 8).trim();
                const lastName = line.slice(8, 24).trim();
                const firstName = line.slice(24, 44).trim();
                // You can extract more fields as needed
                const athlete = {
                    id,
                    firstName,
                    lastName,
                    group: "", // Not in sample, set as needed
                };
                athletes.push(athlete);
                athleteMap[id] = athlete;
                lastAthleteId = id;
            }
            // 2. Parse E1 lines (event info)
            else if (line.startsWith('E1')) {
                // Example: E1M  281BarreMB    50A  9  9  0S  7.00 11     0.00S    0.00    16.00    0.00   NN               N                               98
                const id = line.slice(5, 8).trim();
                const eventName = line.slice(14, 19).trim(); // e.g. "50A"
                // You may want to build a more descriptive event name using other fields
                eventMap[id] = {
                    event_name: eventName
                };
                lastAthleteId = id;
            }
            // 3. Parse E2 lines (results)
            else if (line.startsWith('E2')) {
                // Example: E2F   46.24S       0  1  2  3   3  0    0.00   46.24    0.00        46.59     0.00     06102023A                          0     26
                // We'll use lastAthleteId to associate this result
                const result_time = line.slice(5, 12).replace('S', '').trim(); // e.g. "46.24"
                const heat = parseInt(line.slice(20, 22).trim(), 10) || 1;
                const lane = parseInt(line.slice(22, 24).trim(), 10) || 1;
                const place = parseInt(line.slice(24, 26).trim(), 10) || 1;
                const type = "final"; // or parse from somewhere if available

                if (lastAthleteId && eventMap[lastAthleteId]) {
                    results.push({
                        athlete_id: lastAthleteId,
                        event_name: eventMap[lastAthleteId].event_name,
                        result_time: result_time.length < 6 ? `00:${result_time.padStart(5, '0')}` : result_time, // format as MM:SS.cc
                        heat,
                        lane,
                        place,
                        type
                    });
                }
            }
        }

        if (!results.length) throw new Error('No valid results found');

        // Set club (prompt user or set default)
        const club = "Unknown Club"; // You can prompt or set dynamically

        return {
            meet_id: <?= json_encode($selectedMeetId) ?>,
            club,
            athletes,
            results
        };
    }

    function sendParsedData(data) {
        fetch('upload_results_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resp => {
            const msgEl = document.getElementById('uploadMessage');
            msgEl.style.color = resp.success ? 'green' : 'red';
            msgEl.textContent = resp.success ? resp.message || 'Uploaded!' : resp.error || 'Upload failed';
            if (resp.success) setTimeout(() => location.reload(), 1500);
        })
        .catch(() => {
            const msgEl = document.getElementById('uploadMessage');
            msgEl.style.color = 'red';
            msgEl.textContent = 'Network error uploading.';
        });
    }
    </script>
</body>
</html>
