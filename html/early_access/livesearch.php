<?php
include __DIR__ . '/db.php';

// Get the search term from the URL
$q = isset($_GET['q']) ? $_GET['q'] : '';

if ($q !== '') {
    $sql = "SELECT item_name FROM groups WHERE item_name LIKE ? AND club = ?";
    $stmt = $conn->prepare($sql);
    $searchTerm = "%" . $q . "%";
    $stmt->bind_param("ss", $searchTerm, $club);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div onclick=\"selectSuggestion('" . htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8') . "')\">" . htmlspecialchars($row['item_name']) . "</div>";
        }
    } else {
        echo "<div>No results</div>";
    }

    $stmt->close();
} else {
    echo "<div>No search term provided</div>";
}

$conn->close();
?>
