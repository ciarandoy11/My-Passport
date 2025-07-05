<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather Data Display</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #1a73e8;
            text-align: center;
        }
        .weather-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .weather-table th, .weather-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .weather-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .weather-table tr:hover {
            background-color: #f5f5f5;
        }
        .no-data {
            text-align: center;
            color: #666;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        $location = $_GET['location'];
        echo '<h1>Weather Data for ' . $location . '</h1>';
        echo '<div id="weatherData">';
            
            // Check if the weather data is set in the GET request
            if(isset($_GET['weatherData'])) {
                // Decode the JSON weather data
                $weatherData = json_decode($_GET['weatherData'], true);

                // Check if the weather data is not empty
                if (!empty($weatherData)) {
                    // Start of the weather data table
                    echo '<table class="weather-table">';
                    echo '<tr>';
                    // Loop through the keys of the first weather data entry to create the table headers
                    foreach (array_keys($weatherData[0]) as $header) {
                        echo '<th>' . htmlspecialchars($header) . '</th>';
                    }
                    echo '</tr>';

                    // Loop through each weather data entry to create the table rows
                    foreach ($weatherData as $data) {
                        echo '<tr>';
                        // Loop through the values of each weather data entry to create the table cells
                        foreach ($data as $value) {
                            echo '<td>' . htmlspecialchars($value) . '</td>';
                        }
                        echo '</tr>';
                    }
                    // End of the weather data table
                    echo '</table>';
                } else {
                    // If the weather data is empty, display a message
                    echo '<div class="no-data">No weather data available</div>';
                }
            } else {
                // If the weather data is not set in the GET request, display a message
                echo '<div class="no-data">No weather data available</div>';
            }
            ?>
        </div>
    </div>
</body>
</html>