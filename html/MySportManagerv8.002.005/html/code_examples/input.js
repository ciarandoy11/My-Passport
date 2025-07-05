// Function to validate the form data
function validateForm() {
    // Get the values of the form fields
    const location = document.getElementById('location').value.trim();
    const temp = document.getElementById('temp').value.trim();
    const windSpeed = document.getElementById('wind_speed').value.trim();
    const windDirection = document.getElementById('wind_direction').value.trim();

    // Check if any field is empty
    if (!location || !temp || !windSpeed || !windDirection) {
        alert('Please fill in all fields');
        return false;
    }

    // Validate temperature (should be a number between -50 and 50)
    const tempNum = parseFloat(temp);
    if (isNaN(tempNum) || tempNum < -50 || tempNum > 50) {
        alert('Temperature must be a number between -50 and 50');
        return false;
    }

    // Validate wind speed (should be a positive number)
    const windSpeedNum = parseFloat(windSpeed);
    if (isNaN(windSpeedNum) || windSpeedNum < 0) {
        alert('Wind speed must be a positive number');
        return false;
    }

    // Validate wind direction (should be between 0 and 360)
    const windDirectionNum = parseFloat(windDirection);
    if (isNaN(windDirectionNum) || windDirectionNum < 0 || windDirectionNum > 360) {
        alert('Wind direction must be a number between 0 and 360 degrees');
        return false;
    }

    // If all validations pass, return true
    return true;
}

// Function to show confirmation dialog
function showConfirmation() {
    // Get the values of the form fields
    const location = document.getElementById('location').value.trim();
    const temp = document.getElementById('temp').value.trim();
    const windSpeed = document.getElementById('wind_speed').value.trim();
    const windDirection = document.getElementById('wind_direction').value.trim();

    // Check if any field is empty
    if (!location || !temp || !windSpeed || !windDirection) {
        alert('Please fill in all fields');
        return false;
    }

    // Validate temperature (should be a number between -50 and 50)
    const tempNum = parseFloat(temp);
    const windSpeedNum = parseFloat(windSpeed);
    const windDirectionNum = parseFloat(windDirection);

    // If any of the validations fail, show an alert and return false
    if (isNaN(tempNum) || tempNum < -50 || tempNum > 50 || 
        isNaN(windSpeedNum) || windSpeedNum < 0 || 
        isNaN(windDirectionNum) || windDirectionNum < 0 || windDirectionNum > 360) {
        alert('Invalid input values');
        return false;
    }

    // If all validations pass, show a confirmation dialog
    return confirm(`Confirm weather data:\n\nLocation: ${location}\nTemperature: ${temp}°C\nWind Speed: ${windSpeed} m/s\nWind Direction: ${windDirection}°`);
}

// Add input validation as user types
document.addEventListener('DOMContentLoaded', () => {
    // Select all text input fields
    document.querySelectorAll('input[type="text"]').forEach(input => {
        // For each text input field, if it is for temperature, wind speed, or wind direction, add an event listener to validate the input
        if (['temp', 'wind_speed', 'wind_direction'].includes(input.id)) {
            input.addEventListener('input', () => input.value = input.value.replace(/[^0-9.-]/g, ''));
        }
    });
}); 