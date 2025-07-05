<?php
// Simple security: only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$desc = trim($_POST['desc'] ?? '');
$title = trim($_POST['title'] ?? '');

if (!$desc) {
    echo json_encode(['success' => false, 'message' => 'Feature request description is required.']);
    exit;
}

// Format the feature request entry
$date = date('d-m-Y');
$featureRequestEntry = "\n\n- $title" .
    ($name ? "\n  - Requested by: $name" : "") .
    ($email ? "([$email](mailto:$email))" : "") .
    "\n  - Request Date: $date" .
    "\n  - Description: " . str_replace("\n", ' ', $desc);

// Read roadmap.md
$mdPath = __DIR__ . '/roadmap.md';
$md = file_get_contents($mdPath);
if ($md === false) {
    echo json_encode(['success' => false, 'message' => 'Could not read roadmap.md']);
    exit;
}

// Insert feature request under the correct section
$pattern = '/## Requests/i';
if (preg_match($pattern, $md, $matches, PREG_OFFSET_CAPTURE)) {
    $pos = $matches[0][1] + strlen($matches[0][0]);
    $md = substr($md, 0, $pos) . $featureRequestEntry . substr($md, $pos);
    if (file_put_contents($mdPath, $md) !== false) {
        echo json_encode(['success' => true, 'message' => 'Feature request submitted! Thank you.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not write to roadmap.md']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Could not find section in roadmap.md']);
}
?>

