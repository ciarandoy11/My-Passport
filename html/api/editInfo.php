<?php
    include __DIR__ . '/db.php';

    $error = ""; // Initialize error message variable

    $username = $data['username'] ?? '';
    $firstname = $data['fname'] ?? '';
    $lastname = $data['lname'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $swimmer = $data['swimmers'] ?? '';
    $inputPassword = $data['password'] ?? '';
    $userId = $data['id'] ?? 0; // Ensure userId is retrieved from input data

    // Validate userId
    if ($userId <= 0) {
        echo json_encode(["error" => "Invalid user ID"]);
        exit();
    }

    // Hash password if provided
    $hashedPassword = !empty($inputPassword) ? password_hash($inputPassword, PASSWORD_BCRYPT) : NULL;

    $updateSql = "UPDATE users SET username = ?, ";
    if ($hashedPassword) {
        $updateSql .= "password = ?, ";
    }
    $updateSql .= "firstname = ?, lastname = ?, email = ?, phone = ?, swimmer = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);

    if (!$updateStmt) {
        $error = "Failed to prepare statement: " . $conn->error;
        echo json_encode(["error" => $error]);
        exit();
    }

    if ($hashedPassword) {
        $bindResult = $updateStmt->bind_param("sssssssi", $username, $hashedPassword, $firstname, $lastname, $email, $phone, $swimmer, $userId);
    } else {
        $bindResult = $updateStmt->bind_param("ssssssi", $username, $firstname, $lastname, $email, $phone, $swimmer, $userId);
    }

    if (!$bindResult) {
        $error = "Failed to bind parameters: " . $updateStmt->error;
        echo json_encode(["error" => $error]);
        exit();
    }

    if (!$updateStmt->execute()) {
        $error = "Failed to execute statement: " . $updateStmt->error;
        echo json_encode(["error" => $error]);
        exit();
    }

    $updateStmt->close();
    
    echo json_encode(["error" => "false"]);
    exit();
?>