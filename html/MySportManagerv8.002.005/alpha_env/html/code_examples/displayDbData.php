<?php

// Include the database connection file
include 'db.php';

// Check if the location is set in the POST request
if(isset($_POST['location'])) {

    // Get the location from the POST request
    $location = $_POST['location'];

    // Prepare the SQL statement to select weather data for the given location
    $stmt = $conn->prepare("SELECT temp, wind_speed, wind_direction, time, date FROM weather_data WHERE location = ?");

    // Bind the location parameter to the SQL statement
    $stmt->bind_param("s", $location);

    // Execute the SQL statement
    $stmt->execute();

    // Get the result of the SQL statement
    $result = $stmt->get_result();

    // Initialize an array to store the weather data
    $weatherData = array();

    // Check if there is no data
    if($result->num_rows < 1) {
        $weatherData[] = array(
            'Temperature' => "None",
            'Wind Speed' => "None",
            'Wind Direction' => "None",
            'Time' => "None",
            'Date' => "None"
        );
    } else {
        // Loop through each row in the result and add the weather data to the array
        while($row = $result->fetch_assoc()) {
            $weatherData[] = array(
                'Temperature' => $row['temp'] . "°C",
                'Wind Speed' => $row['wind_speed'] . "m/s",
                'Wind Direction' => $row['wind_direction'],
                'Time' => $row['time'],
                'Date' => $row['date']
            );
        }
    }

    // Redirect to the viewWeather.php page with the weather data included
    header('Location: viewWeather.php?weatherData=' . json_encode($weatherData) . '&&location=' . $location);
    exit();

    // Close the SQL statement
    $stmt->close();
}

// Close the database connection
$conn->close();

?>
