<?php
// Include the database connection parameters
include 'db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get and validate input data
$location = $_POST['location'];
$temp = (float)$_POST['temp'];
$wind_speed = (float)$_POST['wind_speed'];
$wind_direction = $_POST['wind_direction']; // Change to (float) if stored as a number
$date = $_POST['date'];
$time = $_POST['time'];

// Prepare the SQL statement
$sql = "INSERT INTO weather_data (location, temp, wind_speed, wind_direction, date, time) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL prepare failed: " . $conn->error);
}

// Bind parameters correctly
$stmt->bind_param("ssddss", $location, $temp, $wind_speed, $wind_direction, $date, $time);

// Execute the query
if ($stmt->execute()) {
    echo "New record created successfully.<br>Redirecting shortly...";
    sleep(5);
    header('Location: index.html');
} else {
    echo "Error: " . $stmt->error;
}

// Close the database connection
$stmt->close();
$conn->close();

