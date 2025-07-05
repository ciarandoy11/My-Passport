<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; // Load dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header("Content-Type: application/json");

$validKey = "1RFxhYueYjebjUxz5z60nMSNB0R-j4b_ldleu_T2X_I";

$rawData = file_get_contents("php://input");
file_put_contents("debug.log", "RAW INPUT: " . $rawData . PHP_EOL, FILE_APPEND); // For Debug

// Decode JSON
$data = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(["error" => "Invalid JSON format"]));
}

// Check for API key
if (!isset($data['api_key']) || $data['api_key'] !== $validKey) {
    http_response_code(403);
    die(json_encode(["error" => "Unauthorized", "data" => $data['api_key'], "api" => $validKey]));
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
