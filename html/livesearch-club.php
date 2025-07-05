<?php
include __DIR__ . '/db.php';

// Get the search term from the URL
$q = isset($_GET['q']) ? $_GET['q'] : '';

if ($q !== '') {
    $sql = "SELECT DISTINCT club FROM users WHERE club LIKE ?";
    $stmt = $conn->prepare($sql);
    $searchTerm = "%" . $q . "%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div onclick=\"selectSuggestion('" . htmlspecialchars($row['club'], ENT_QUOTES, 'UTF-8') . "')\">" . htmlspecialchars($row['club']) . "</div>";
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

