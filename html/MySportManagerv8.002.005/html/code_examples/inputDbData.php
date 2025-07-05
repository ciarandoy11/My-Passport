<!DOCTYPE html>
<html>
<head>
    <title>Input Weather Data</title>
    <script src="input.js"></script>
</head>
<body>
    <h1>Input Weather Data</h1>
    <form action="processInputData.php" method="post" onsubmit="return showConfirmation()">
        <label for="location">Location:</label><br>
        <input type="text" id="location" name="location"><br>
        <label for="temp">Temperature:</label><br>
        <input type="number" id="temp" name="temp" step="0.01"><br>
        <label for="wind_speed">Wind Speed:</label><br>
        <input type="number" id="wind_speed" name="wind_speed" step="0.01"><br>
        <label for="wind_direction">Wind Direction:</label><br>
        <input type="number" id="wind_direction" name="wind_direction" step="0.01"><br>
        <label for="date">Date:</label><br>
        <input type="date" id="date" name="date"><br>
        <label for="time">Time:</label><br>
        <input type="time" id="time" name="time"><br>
        <input type="submit" value="Submit">
    </form>
</body>
</html>

