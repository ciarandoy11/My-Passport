<?php
// Define the database connection parameters
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "weather_app";

// Create a new MySQLi object for the database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    // If the connection failed, print an error message and terminate the script
    die("Connection failed: " . $conn->connect_error);
}
?>
