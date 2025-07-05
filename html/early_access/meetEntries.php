<?php
session_start();

include __DIR__ . '/db.php';

// Basic auth and data retrieval like before:
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
$stmt->close();

if (!$meet) {
    header('Location: competitions');
    exit;
}

// Get entries (simplified, no event filter here)
$stmt = $conn->prepare("
    SELECT 
        me.*,
        e.event_number,
        e.event_name,
        e.distance,
        e.stroke,
        e.age_group,
        e.gender,
        g.item_name AS athlete_name,
        g.club
    FROM meet_entries me
    JOIN meet_events e ON me.event_id = e.id
    JOIN meets m ON e.meet_id = m.id
    JOIN groups g ON me.athlete_id = g.id
    WHERE me.meet_id = ?
    ORDER BY e.event_number ASC, g.item_name ASC
");
$stmt->bind_param("i", $meetId);
$stmt->execute();
$entries = $stmt->get_result();
$stmt->close();

// Get logged-in user club
$club = '';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT club FROM groups WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $club = $row['club'];
    }
    $stmt->close();
}

// Get athletes in user club
$athletes = [];
if ($club) {
    $stmt = $conn->prepare("SELECT id, item_name FROM groups WHERE club = ? ORDER BY item_name ASC");
    $stmt->bind_param("s", $club);
    $stmt->execute();
    $athletes = $stmt->get_result();
    $stmt->close();
}

// Get events for meet
$stmt = $conn->prepare("SELECT * FROM meet_events WHERE meet_id = ? ORDER BY event_number ASC");
$stmt->bind_param("i", $meetId);
$stmt->execute();
$events = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Entries - <?= htmlspecialchars($meet['name']) ?></title>
<link rel="stylesheet" href="style.css?v=8.002.004" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css" />
<style>
  /* Basic modal styles */
  #entryModal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#f5f5f5; padding:20px; border-radius:10px; z-index:1000; }
  #entryModal .close { cursor:pointer; float:right; font-size:1.5em; }
  .upload-section { margin:20px 0; padding:20px; background:#f8f9fa; border-radius:5px; }
  .file-types { margin-top:10px; font-family: monospace; background:#fff; padding:10px; border:1px solid #dee2e6; border-radius:3px; }
</style>
</head>
<body>
<?php if ($typeAdmin == 1): ?>
        <?php include 'includes/admin_navigation'; ?>
    <?php elseif ($typeCoach == 1): ?>
    <?php else: ?>
        <?php include 'includes/navigation'; ?>
    <?php endif; ?>

<main>
<h1>Entries - <?= htmlspecialchars($meet['name']) ?></h1>
<p><strong>Dates:</strong> <?= htmlspecialchars($meet['start_date'] . ' to ' . $meet['end_date']) ?></p>
<p><strong>Venue:</strong> <?= htmlspecialchars($meet['venue']) ?></p>
<p><strong>Entry Closing Date:</strong> <?= htmlspecialchars($meet['closing_date']) ?></p>

<section class="active">
<?php if ($entries && $entries->num_rows > 0): ?>

<?php if ($meet['closing_date'] > date('Y-m-d')): ?>
<button onclick="showEntryForm()" class="button">Add Entry</button>
<?php else: ?>
<button onclick="compileInvoices()" class="button">Compile Invoices</button>
<?php endif; ?>

<table style="opacity: 1;">
<thead>
  <tr>
    <th>Event</th><th>Athlete</th><th>Entry Time</th><th>Club</th>
  </tr>
</thead>
<tbody>
  <?php while ($entry = $entries->fetch_assoc()): 
    if ($entry['event_id'] === $eventId || $eventId === 0) :?>
  <tr>
    <td><?= htmlspecialchars($entry['event_number'] . ': ' . $entry['event_name']) ?></td>
    <td><?= htmlspecialchars($entry['athlete_name']) ?></td>
    <td><?= htmlspecialchars(substr($entry['entry_time'], 0, 3) === '00:' ? substr($entry['entry_time'], 3) : $entry['entry_time']) ?></td>
    <td><?= htmlspecialchars($entry['club']) ?></td>
  </tr>
  <?php endif; ?>
  <?php endwhile; ?>
</tbody>
</table>
<?php else: ?>
<p>No entries found for this meet.</p>
<?php endif; ?>
</section>

<!-- Entry Form Modal -->
<div id="entryModal">
  <span class="close" onclick="closeEntryForm()">&times;</span>
  <h2>Add Entry</h2>
  <form id="entryForm" method="POST">
    <input type="hidden" name="action" value="submit_entry" />
    <div>
      <label for="athlete">Select Athlete:</label>
      <select name="athlete_id" id="athlete" required>
      <?php if ($athletes): 
        $athletes->data_seek(0);
        while ($athlete = $athletes->fetch_assoc()): ?>
        <option value="<?= $athlete['id'] ?>"><?= htmlspecialchars($athlete['item_name']) ?></option>
      <?php endwhile; endif; ?>
      </select>
    </div>
    <div>
      <label for="event">Select Event:</label>
      <select name="event_id" id="event" required>
      <?php if ($events):
        $events->data_seek(0);
        while ($event = $events->fetch_assoc()): ?>
        <option value="<?= $event['id'] ?>"><?= htmlspecialchars($event['event_number'] . ': ' . $event['distance'] . 'm ' . $event['stroke'] . " ({$event['age_group']} {$event['gender']})") ?></option>
      <?php endwhile; endif; ?>
      </select>
    </div>
    <div>
      <label for="entry_time">Entry Time (mm:ss.xx or ss.xx):</label>
      <input type="text" name="entry_time" id="entry_time" pattern="^\d{1,2}:\d{2}(\.\d{1,2})?$|^\d+(\.\d{1,2})?$" required />
    </div>
    <button type="submit" class="button">Submit Entry</button>
  </form>
  <div id="entryFormMessage"></div>
</div>

<!-- Upload Entries File Section -->
<section class="active">
  <h3>Upload Entries File (Hytek):</h3>
  <input type="file" id="entriesFile" accept=".hy3" />
  <button id="uploadBtn" class="button">Upload</button>
  <div id="uploadMessage"></div>
  <div class="file-types">
    Acceptable file formats:<br>
    - Hytek Meet Entries (.hy3)<br>
  </div>
</section>

<!-- Add this modal for missing emails -->
<div id="noEmailModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border-radius:10px; z-index:2000; box-shadow:0 2px 10px #0002;">
  <span class="close" onclick="closeNoEmailModal()" style="float:right; cursor:pointer; font-size:1.5em;">&times;</span>
  <h2>Missing Emails</h2>
  <form id="noEmailForm">
    <div id="noEmailInputs"></div>
    <button type="submit" class="button">Send Invoices</button>
  </form>
</div>
</main>

<script>
let lastNoEmailContext = null; // { meetId, athletesToRetry: [{athleteId: ID, eventIds: 'comma,separated,ids'}] }
let globalAthleteFees = {}; // To store the full athleteFees data from compileInvoices

// On DOM ready: loading screen fade out & scroll reveal
document.addEventListener('DOMContentLoaded', function() {
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

  // Enable upload button only when file selected
  const fileInput = document.getElementById('entriesFile');
  const uploadBtn = document.getElementById('uploadBtn');
  uploadBtn.disabled = true;

  fileInput.addEventListener('change', () => {
    uploadBtn.disabled = !fileInput.files.length;
    // Reset upload message on new file select
    document.getElementById('uploadMessage').textContent = '';
  });
});

// Modal controls
function showEntryForm() {
  document.getElementById('entryModal').style.display = 'block';
}
function closeEntryForm() {
  document.getElementById('entryModal').style.display = 'none';
}

// Manual entry AJAX submit
document.getElementById('entryForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('submit_entry_ajax', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    const msg = document.getElementById('entryFormMessage');
    if (data.success) {
      msg.textContent = "Entry submitted successfully!";
      msg.style.color = 'green';
      this.reset();
    } else {
      msg.textContent = data.error || "Error submitting entry.";
      msg.style.color = 'red';
    }
  })
  .catch(() => {
    document.getElementById('entryFormMessage').textContent = 'Network error.';
  });
});

// Upload and parse entries file
document.getElementById('uploadBtn').addEventListener('click', () => {
  const fileInput = document.getElementById('entriesFile');
  const file = fileInput.files[0];
  if (!file) return;

  const uploadMessage = document.getElementById('uploadMessage');
  uploadMessage.textContent = 'Parsing file...';
  uploadMessage.style.color = 'black';

  const reader = new FileReader();
  reader.onload = function(e) {
    const content = e.target.result;
    const ext = file.name.split('.').pop().toLowerCase();

    try {
      let parsedData;
      if (ext === 'hy3') {
        parsedData = parseEntriesHy3(content);
      } else {
        throw new Error('Unsupported file format.');
      }
      sendParsedData(parsedData);
    } catch (ex) {
      uploadMessage.textContent = 'Error parsing file: ' + ex.message;
      uploadMessage.style.color = 'red';
    }
  };
  reader.readAsText(file);
});

// Basic HY3 parser placeholder — replace with your actual parsing logic
function parseEntriesHy3(text) {
  const lines = text.split(/\r?\n/);

  const athletes = [];
  const entries = [];
  let meetName = '';
  let currentAthleteId = null;
  let currentAthleteGender = null;

  for (const line of lines) {
    if (!line.trim()) continue;

    const recordType = line.charAt(0);

    if (recordType === 'B') {
      meetName = line.substring(2).trim();

    } else if (recordType === 'D') {
      const parts = [
        line.substring(0, 4),
        line.substring(5, 27),
        line.substring(28, 68),
        line.substring(69, 87),
        line.substring(88, 96),
        line.substring(97, 104),
        line.substring(105, 127)
      ];

      const gender = parts[0].charAt(2);
      const athleteId = parts[1].replace(/\D/g, '').trim();
      const lastName = parts[1].replace(/^\d+/, '').trim();
      const firstName = parts[2].trim();
      const swimIrelandId = parts[3].trim();
      const dobRaw = parts[4].trim(); // 08252010
      const age = parts[5].trim();
      const group = parts[6].trim();

      let dob = null;
      if (dobRaw.length === 8) {
        const mm = dobRaw.substring(0, 2);
        const dd = dobRaw.substring(2, 4);
        const yyyy = dobRaw.substring(4);
        dob = `${yyyy}-${mm}-${dd}`;
      }

      athletes.push({
        id: athleteId,
        gender,
        firstName,
        lastName,
        dob,
        swimIrelandId,
        group
      });

      currentAthleteId = athleteId;
      currentAthleteGender = gender;

    } else if (recordType === 'E') {
        // Example line:
        // E1F  116Mc CoFF   100A 12 13      7.00  2        0S   95.21S                                                                    06
        const parts = [
        line.substring(0, 4), // 0
        line.substring(5, 14), // 1
        line.substring(15, 21), // 2
        line.substring(23, 24), // 3
        line.substring(26, 27), // 4
        line.substring(30, 37), // 5
        line.substring(38, 48), // 6
        line.substring(49, 50), // 7
        line.substring(51, 59) // 8
      ];

      const gender = parts[0].charAt(2);
      const athleteId = currentAthleteId;
      const entryFee = (parts[5].trim() * 100);
      const eventNumber = parts[6].trim();
      const rawSeconds = parseFloat(parts[8].trim());

    const hours = Math.floor(rawSeconds / 3600);
    const minutes = Math.floor((rawSeconds % 3600) / 60);
    const seconds = Math.floor(rawSeconds % 60);

    // Get fractional part (2 decimal places), formatted cleanly
    const fraction = Math.round((rawSeconds % 1) * 100).toString().padStart(2, '0');

    const entryTime = 
    `${hours.toString().padStart(2, '0')}:` +
    `${minutes.toString().padStart(2, '0')}:` +
    `${seconds.toString().padStart(2, '0')}.` +
    `${fraction}`;

      entries.push({
        athlete_id: athleteId,
        gender,
        entry_fee: entryFee,
        event_number: eventNumber,
        entry_time: entryTime
      });
    }
  }

  if (entries.length === 0) {
    throw new Error('No entries found in .hy3 file.');
  }

  return {
    meetName,
    club: <?= json_encode($club) ?>,
    athletes,
    entries
  };
}

// Send parsed data to backend via AJAX
function sendParsedData(data) {
  const uploadMessage = document.getElementById('uploadMessage');
  if (!data.entries || data.entries.length === 0) {
    uploadMessage.textContent = 'No entries found in file.';
    uploadMessage.style.color = 'red';
    return;
  }

  fetch('upload_entries_ajax', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      meet_id: <?= json_encode($meetId) ?>,
      club: data.club,
      athletes: data.athletes,
      entries: data.entries
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      uploadMessage.textContent = res.message || 'Entries uploaded successfully!';
      uploadMessage.style.color = 'green';
      setTimeout(() => window.location.reload(), 1500);
    } else {
      uploadMessage.textContent = res.error || 'Error uploading entries.';
      uploadMessage.style.color = 'red';
    }
  })
  .catch(() => {
    uploadMessage.textContent = 'Network or server error.';
    uploadMessage.style.color = 'red';
  });
}

// --- GLOBAL INVOICE FUNCTIONS ---
function editInvoice(meetId, athleteId, eventIds) {
  // Implement edit invoice functionality
  const editModal = document.createElement('div');
  editModal.id = 'editModal';
  editModal.style.display = 'none';
  editModal.style.position = 'fixed';
  editModal.style.top = '0';
  editModal.style.left = '0';
  editModal.style.height = '100%';
  editModal.style.overflowY = 'scroll';
  editModal.style.backgroundColor = '#f5f5f5';
  editModal.style.padding = '20px';
  editModal.style.borderRadius = '10px';
  editModal.style.zIndex = '1000';

  const editModalContent = document.createElement('div');
  editModalContent.innerHTML = `
    <span class="close" style="color: red;" onclick="closeEditModal()">&times;</span>
    <h2>Edit Invoice</h2>
    <form id="editForm">
      <label for="editAmount">New Amount (per event):</label>
      <input type="number" id="editAmount" name="editAmount" required>
      <button type="submit">Save</button>
    </form>
  `;
  editModal.appendChild(editModalContent);

  document.body.appendChild(editModal);

  document.getElementById('editForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const newAmount = document.getElementById('editAmount').value;
    if (!newAmount) {
      alert('Please enter a new amount.');
      return;
    }

    try {
      const response = await fetch('meet_actions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ 'action': 'edit_fee', 'meet_id': meetId, 'athlete_id': athleteId, 'new_amount': newAmount })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const result = await response.json();
      if (result.success) {
        alert('Invoice updated successfully.');
        window.location.reload();
      } else {
        alert('Failed to update invoice.');
      }
    } catch (error) {
      console.error('Error updating invoice:', error);
      alert('Network or server error.');
    }
  });

  editModal.style.display = 'block';
}

function closeEditModal() {
  const modal = document.getElementById('editModal');
  if (modal) modal.style.display = 'none';
}

function showNoEmailModal(athletesToRetry) {
  const modal = document.getElementById('noEmailModal');
  const inputsDiv = document.getElementById('noEmailInputs');
  inputsDiv.innerHTML = '';
  athletesToRetry.forEach(athlete => {
    inputsDiv.innerHTML += `<div style="margin-bottom:10px;">
      <label>Enter email for ${athlete.name}: <input type="email" name="email_${athlete.athleteId}" data-athlete-id="${athlete.athleteId}" data-event-ids="${athlete.eventIds}" required></label>
    </div>`;
  });
  modal.style.display = 'block';
}

function closeNoEmailModal() {
  document.getElementById('noEmailModal').style.display = 'none';
}

async function sendInvoice(meetId, athleteId, eventIds, overrideEmails = null) {
  try {
    const body = new URLSearchParams({ 'action': 'send_invoice', 'meet_id': meetId, 'athlete_id': athleteId, 'event_ids': eventIds });
    if (overrideEmails) {
      body.append('override_emails', JSON.stringify(overrideEmails));
    }
    const response = await fetch('meet_actions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });
    if (!response.ok) {
      const errorText = await response.text(); // Get raw response text for debugging
      console.error('HTTP Error Response:', response.status, response.statusText, errorText);
      throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}. Response: ${errorText.substring(0, 200)}...`);
    }
    const result = await response.json();
    console.log('Stripe API response:', result); // Added for debugging amount issue

    // Prioritize displaying issues over success
    if (result.no_email && result.no_email.length > 0) {
        lastNoEmailContext = {
            meetId: meetId,
            athletesToRetry: result.no_email.map(athleteObject => {
                console.log('Mapping no_email object in sendInvoice:', athleteObject); // Debugging log
                return {
                    athleteId: athleteObject.athlete_id,
                    name: athleteObject.name,
                    eventIds: globalAthleteFees[athleteObject.athlete_id] ? globalAthleteFees[athleteObject.athlete_id].eventIds.join(',') : ''
                };
            })
        };
        showNoEmailModal(lastNoEmailContext.athletesToRetry); // Pass rich context to modal
        return result; // Return for consolidated handling if called from sendAllInvoices or noEmailForm
    } else if (result.failedInvoices && result.failedInvoices.length > 0) {
        alert('Invoice failed for Athlete ' + result.failedInvoices[0].athlete + ': ' + result.failedInvoices[0].error);
        return result; // Return for consolidated handling
    } else if (result.success && result.success.length > 0) {
        // Only show success if no other issues
        alert('Invoice sent successfully for Athlete ' + result.success[0]);
        //compileInvoices(); // Refresh the invoice summary
        return result; // Return for consolidated handling
    } else {
        alert('Failed to send invoice for Athlete ' + athleteId + ' (unknown reason).');
        return result;
    }
  } catch (error) {
    console.error('Error sending invoice:', error);
    alert('Network or server error for Athlete ' + athleteId + ': ' + error.message);
    return { success: [], failedInvoices: [{ athlete: athleteId, error: error.message }], no_email: [] }; // Return structured error
  }
}

document.getElementById('noEmailForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  closeNoEmailModal();

  const finalResults = { success: [], failedInvoices: [], no_email: [] };
  let allEmailsProvided = true;

  if (lastNoEmailContext && lastNoEmailContext.athletesToRetry) {
    for (const athlete of lastNoEmailContext.athletesToRetry) {
      const overrideEmail = formData.get('email_' + athlete.athleteId);
      if (!overrideEmail || overrideEmail.trim() === '') {
        allEmailsProvided = false;
        finalResults.no_email.push(athlete); // Push the original athlete object back
        continue; // Skip trying to send for this athlete
      }

      const result = await sendInvoice(lastNoEmailContext.meetId, athlete.athleteId, athlete.eventIds, { [athlete.athleteId]: overrideEmail });
      // Consolidate results from individual sendInvoice calls
      finalResults.success = finalResults.success.concat(result.success || []);
      finalResults.failedInvoices = finalResults.failedInvoices.concat(result.failedInvoices || []);
      // For no_email, we need to push the full athlete object if not processed
      if (result.no_email && result.no_email.length > 0) {
        finalResults.no_email = finalResults.no_email.concat(result.no_email.map(id => {
            console.log('Mapping no_email object in noEmailForm retry:', id); // Debugging log
            return {
                athleteId: id.athlete_id,
                name: id.name,
                eventIds: globalAthleteFees[id.athlete_id] ? globalAthleteFees[id.athlete_id].eventIds.join(',') : ''
            };
        }));
      }
    }
  }

  // After all retries, show a consolidated message
  let finalMessage = '';
  if (finalResults.success.length > 0) {
    finalMessage += `Successfully sent invoices for ${finalResults.success.length} athletes.\n`;
  }
  if (finalResults.no_email.length > 0) {
    finalMessage += `Could not send invoices for ${finalResults.no_email.length} athletes due to missing emails.\n`;
  }
  if (finalResults.failedInvoices.length > 0) {
    finalMessage += `Failed to send invoices for ${finalResults.failedInvoices.length} athletes: ${finalResults.failedInvoices.map(f => `Athlete ${f.athlete}: ${f.error}`).join(', ')}\n`;
  }

  if (finalMessage) {
    alert(finalMessage);
    compileInvoices(); // Refresh after consolidated message
  } else {
    alert('No invoices were processed during retry.');
  }

  // If some emails are still missing, show the modal again with remaining athletes
  if (finalResults.no_email.length > 0) {
    lastNoEmailContext = {
      meetId: lastNoEmailContext.meetId,
      athletesToRetry: finalResults.no_email.map(athleteObject => {
          console.log('Mapping no_email object before showing modal again:', athleteObject); // Debugging log
          return {
              athleteId: athleteObject.athleteId, // Already correctly mapped in previous step
              name: athleteObject.name, // Already correctly mapped
              eventIds: athleteObject.eventIds // Already correctly mapped
          };
      })
    };
    showNoEmailModal(lastNoEmailContext.athletesToRetry);
  }
});

// For sendAll, collect all no_email and prompt for all at once
async function sendAllInvoices(meetId, athleteFees) {
  let allNoEmail = [];
  let allFailed = [];
  let allSuccess = [];
  
  for (const athleteId of Object.keys(athleteFees)) {
    const eventIds = athleteFees[athleteId].eventIds.join(',');
    const result = await sendInvoice(meetId, athleteId, eventIds);

    // Consolidate results from individual sendInvoice calls
    allSuccess = allSuccess.concat(result.success || []);
    allFailed = allFailed.failedInvoices.concat(result.failedInvoices || []);
    // Ensure no_email elements are properly formatted when consolidating from individual calls
    if (result.no_email && result.no_email.length > 0) {
      allNoEmail = allNoEmail.concat(result.no_email.map(id => {
          console.log('Mapping no_email object in sendAllInvoices:', id); // Debugging log
          return {
              athleteId: id.athlete_id,
              name: id.name,
              eventIds: globalAthleteFees[id.athlete_id] ? globalAthleteFees[id.athlete_id].eventIds.join(',') : ''
          };
      }));
    }
  }

  let finalMessage = '';
  if (allSuccess.length > 0) {
    finalMessage += `Successfully sent invoices for ${allSuccess.length} athletes.\n`;
  }
  if (allNoEmail.length > 0) {
    finalMessage += `Could not send invoices for ${allNoEmail.length} athletes due to missing emails.\n`;
  }
  if (allFailed.length > 0) {
    finalMessage += `Failed to send invoices for ${allFailed.length} athletes: ${allFailed.map(f => `Athlete ${f.athlete}: ${f.error}`).join(', ')}\n`;
  }

  if (finalMessage) {
    alert(finalMessage);
    compileInvoices(); // Refresh after consolidated message
  } else {
    alert('No invoices were processed.');
  }

  // If some emails are still missing, show the modal again with remaining athletes
  if (allNoEmail.length > 0) {
    lastNoEmailContext = {
      meetId: meetId,
      athletesToRetry: allNoEmail.map(athleteObject => {
          console.log('Mapping no_email object before showing modal again (sendAll):', athleteObject); // Debugging log
          return {
              athleteId: athleteObject.athleteId, // Already correctly mapped in previous step
              name: athleteObject.name, // Already correctly mapped
              eventIds: athleteObject.eventIds // Already correctly mapped
          };
      })
    };
    showNoEmailModal(lastNoEmailContext.athletesToRetry);
  }
}

async function compileInvoices() {
  const meetId = <?= json_encode($meetId) ?>;

  function closeSummaryModal() {
    document.getElementById('summaryModal').style.display = 'none';
  }

  function closeBulkEditModal() {
    document.getElementById('bulkEditModal').style.display = 'none';
  }

  console.log('Fetching Entry Fees');

  const entryFeesResponse = await fetch('meet_actions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({ 'action': 'get_entry_fees', 'meet_id': meetId })
  });

  if (!entryFeesResponse.ok) {
    throw new Error(`HTTP error! Status: ${entryFeesResponse.status}`);
  }

  const entryFees = await entryFeesResponse.json();
  console.log('Entry Fees:', entryFees);

  const summaryModal = document.createElement('div');
  summaryModal.id = 'summaryModal';
  summaryModal.style.display = 'none';
  summaryModal.style.position = 'fixed';
  summaryModal.style.top = '0';
  summaryModal.style.left = '0';
  summaryModal.style.height = '100%';
  summaryModal.style.overflowY = 'scroll';
  summaryModal.style.backgroundColor = '#f5f5f5';
  summaryModal.style.padding = '20px';
  summaryModal.style.borderRadius = '10px';
  summaryModal.style.zIndex = '1000';

  const summaryModalContent = document.createElement('div');
  summaryModalContent.innerHTML = `
    <span class="close" onclick="closeSummaryModal()">&times;</span>
    <h2>Invoice Summary</h2>
    <div id="summaryContent"></div>
    <button id="sendAllButton">Send All</button>
    <button id="bulkEditButton">Bulk Edit</button>
  `;
  summaryModal.appendChild(summaryModalContent);
  document.body.appendChild(summaryModal);

  const summaryContent = document.getElementById('summaryContent');
  let totalAmount = 0;
  const entryFeesData = entryFees.entryFees;
  globalAthleteFees = entryFeesData.reduce((acc, fee) => {
    if (!acc[fee.athlete_id]) {
      acc[fee.athlete_id] = { totalFee: 0, eventIds: [] };
    }
    acc[fee.athlete_id].totalFee += fee.entry_fee;
    acc[fee.athlete_id].eventIds.push(fee.event_id);
    return acc;
  }, {});

  Object.entries(globalAthleteFees).forEach(([athleteId, { totalFee, eventIds }]) => {
    totalAmount += totalFee;
    summaryContent.innerHTML += `
      <div>
        <span>Athlete ID: ${athleteId}</span>
        <span>Event IDs: ${eventIds.join(', ')}</span>
        <span>Total Entry Fee: ${totalFee}</span>
        <button onclick="editInvoice(${meetId}, ${athleteId}, '${eventIds.join(', ')}')">Edit</button>
        <button onclick="sendInvoice(${meetId}, ${athleteId}, '${eventIds.join(', ')}')">Send</button>
      </div>
    `;
  });

  summaryContent.innerHTML += `<div>Total Amount: ${totalAmount}</div>`;
  summaryModal.style.display = 'block';

  document.getElementById('sendAllButton').addEventListener('click', () => {
    sendAllInvoices(meetId, globalAthleteFees);
  });

  document.getElementById('bulkEditButton').addEventListener('click', () => {
    const bulkEditModal = document.createElement('div');
    bulkEditModal.id = 'bulkEditModal';
    bulkEditModal.style.display = 'none';
    bulkEditModal.style.position = 'fixed';
    bulkEditModal.style.top = '0';
    bulkEditModal.style.left = '0';
    bulkEditModal.style.height = '100%';
    bulkEditModal.style.overflowY = 'scroll';
    bulkEditModal.style.backgroundColor = '#f5f5f5';
    bulkEditModal.style.padding = '20px';
    bulkEditModal.style.borderRadius = '10px';
    bulkEditModal.style.zIndex = '1000';

    const bulkEditModalContent = document.createElement('div');
    bulkEditModalContent.innerHTML = `
      <span class="close" onclick="closeBulkEditModal()">&times;</span>
      <h2>Bulk Edit</h2>
      <form id="bulkEditForm">
        <label for="bulkEditAmount">Adjust Amount (Per Event):</label>
        <input type="number" id="bulkEditAmount" name="bulkEditAmount" required>
        <button type="submit">Apply</button>
      </form>
    `;
    bulkEditModal.appendChild(bulkEditModalContent);
    document.body.appendChild(bulkEditModal);

    document.getElementById('bulkEditForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const adjustmentAmount = document.getElementById('bulkEditAmount').value;
      if (!adjustmentAmount) {
        alert('Please enter an adjustment amount.');
        return;
      }

      try {
        const response = await fetch('meet_actions', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            'action': 'bulk_edit_fees',
            'meet_id': meetId,
            'adjustment_amount': adjustmentAmount
          })
        });

        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const result = await response.json();
        if (result.success) {
          alert('Fees adjusted successfully.');
          closeBulkEditModal();
          compileInvoices();
        } else {
          alert('Failed to adjust fees.');
        }
      } catch (error) {
        console.error('Error adjusting fees:', error);
        alert('Network or server error.');
      }
    });

    bulkEditModal.style.display = 'block';
  });
}
</script>
</body>
</html>
