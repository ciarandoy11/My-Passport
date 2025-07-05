<?php
// Simple security: only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$title = trim($_POST['title'] ?? '');
$desc = trim($_POST['desc'] ?? '');
$severity = $_POST['severity'] ?? 'Medium';

if (!$desc) {
    echo json_encode(['success' => false, 'message' => 'Bug description is required.']);
    exit;
}

// Map severity to section header
$sectionMap = [
    'Critical' => '### Critical Priority 🔴',
    'High' => '### High Priority 🟡',
    'Medium' => '### Medium Priority 🟢',
    'Low' => '### Low Priority ⚪'
];
$sectionHeader = $sectionMap[$severity] ?? $sectionMap['Medium'];

// Format the bug entry
$date = date('d-m-Y');
$bugEntry = "- $title" .
    ($name ? "\n  - Reported by: $name" : "") .
    ($email ? "([$email](mailto:$email))" : "") .
    "\n  - Description: " . str_replace("\n", ' ', $desc) .
    "\n  - Status: 🚫 Open\n\n";

// Read bugs.md
$mdPath = __DIR__ . '/bugs.md';
$md = file_get_contents($mdPath);
if ($md === false) {
    echo json_encode(['success' => false, 'message' => 'Could not read bugs.md']);
    exit;
}

// Insert bug under the correct section
$pattern = '/(' . preg_quote($sectionHeader, '/') . '\s*)/i';
if (preg_match($pattern, $md, $matches, PREG_OFFSET_CAPTURE)) {
    $pos = $matches[0][1] + strlen($matches[0][0]);
    $md = substr($md, 0, $pos) . $bugEntry . substr($md, $pos);
    if (file_put_contents($mdPath, $md) !== false) {
        echo json_encode(['success' => true, 'message' => 'Bug reported! Thank you.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not write to bugs.md']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Could not find section in bugs.md']);
} 