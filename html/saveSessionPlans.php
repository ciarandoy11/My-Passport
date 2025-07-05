<?php
include __DIR__ . '/db';

// Fetch user data
$sql = "SELECT username, club, `type-coach` FROM users WHERE id = ?";
 $stmt = $conn->prepare($sql);

  if ($stmt === false) {
   die(json_encode(["error" => "Error preparing the statement: " . $conn->error]));
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        die(json_encode(["error" => "User not found"]));
    } // Extract user data from result

    $userName = $user['username'];
    $club = $user['club'];
    $typeCoach = (int)$user['type-coach']; // Cast to integer
    $stmt->close(); // Close the statement

    // Check if the user is a coach
    if ($typeCoach !== 1) {
    header("Location: login"); // Redirect to login if not logged in
    exit();
    }

    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    // Ensure plan and name are received
    if (!isset($data['plan']) || !isset($data['name'])) {
        die(json_encode(["error" => "Plan and name not posted"]));
    }

    $plan = $data['plan'];
    $name = $data['name'];

    if ($plan === '') {
        $plan = 'No Plan made/Saved';
    }


    $sql = "SELECT * FROM sessionPlans WHERE name = ? AND club = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $club);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessionPlans = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($sessionPlans) {
        $sql = "UPDATE sessionPlans SET plan = ? WHERE name = ? AND club = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $plan, $name, $club);
    } else {
        $sql = "INSERT INTO sessionPlans (name, club, plan) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $club, $plan);
    }
    if (!$stmt->execute()) {
        die(json_encode(["error" => "Error executing the statement: " . $conn->error]));
    }
    $stmt->close();
    echo json_encode(["success" => "Data saved successfully"]);
    ?>