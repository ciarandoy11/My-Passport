<?php
include __DIR__ . '/db.php';

// Retrieve user data and group name
$sql = "SELECT u.*, g.list_name
        FROM users u
        LEFT JOIN groups g ON FIND_IN_SET(g.item_name, u.swimmer)
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

//$club = $user['club'];

// Retrieve swimmer groups for display
$swimmerGroups = [];
if (!empty($user['swimmer'])) {
    $swimmers = array_map('trim', explode(',', $user['swimmer']));
    foreach ($swimmers as $swimmer) {
        $sql = "SELECT list_name, split FROM groups WHERE item_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $swimmer);
        $stmt->execute();
        $result = $stmt->get_result();
        $groupName = $result->fetch_assoc();
        $stmt->close();

        if ($groupName && isset($groupName['split']) && $groupName['split'] != '') {
            $swimmerGroups[$swimmer] = $groupName['list_name'] . '-' . $groupName['split'];
        } elseif ($groupName) {
            $swimmerGroups[$swimmer] = $groupName['list_name'];
        }

        $sql = "SELECT dob, inSchool FROM groups WHERE item_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $swimmer);
        $stmt->execute();
        $stmt->bind_result($swimmerDob, $swimmerInSchool);
        $stmt->fetch();
        $stmt->close();

        if ($swimmerDob) {
            $swimmerDobs[$swimmer] = $swimmerDob;
        }

        if ($swimmerInSchool) {
            $swimmerInSchools[$swimmer] = $swimmerInSchool;
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		// Process swap request
        if (isset($_POST['setSwimmersname'])) {
    // Get and sanitize the swimmer names from the POST request
    $swimmers = htmlspecialchars($_POST['swimmer']); // Trim whitespace and escape HTML characters

	//header('Location:?' . $swimmers . ' ' . $userId);
	//$_SESSION['message'] = "Athlete(s) name(s) submitted successfully.";
	//$_SESSION['message_type'] = "success";

    // Validate the inputs
    if (empty($swimmers)) {
        // Handle the case where the swimmer name is empty
        $_SESSION['message'] = "Athlete(s) name(s) cannot be empty.";
        $_SESSION['message_type'] = "error"; // Consider using an error type for visual feedback
        echo '<meta http-equiv="refresh" content="0;">';
        exit();
    }

    // Assuming $userID is properly defined and sanitized before this point
    // Prepare the SQL query
    $sql = "UPDATE users SET swimmer = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) { // Check if the statement was prepared successfully
        // Bind parameters (swimmer name and user ID - the ID should be an integer)
        $stmt->bind_param("si", $swimmers, $userID);

        // Execute the statement
        if ($stmt->execute()) {
            // Set a success message if the update is successful
            $_SESSION['message'] = "Athlete(s) name(s) submitted successfully to db." . $stmt['execute'];
            $_SESSION['message_type'] = "success";

            // --- Start: Update siblings in groups table ---
            // Retrieve the updated swimmer list for the current user
            $userSwimmersSql = "SELECT swimmer FROM users WHERE id = ?";
            $userSwimmersStmt = $conn->prepare($userSwimmersSql);
            $userSwimmersStmt->bind_param("i", $userID);
            $userSwimmersStmt->execute();
            $userSwimmersStmt->bind_result($userSwimmersString);
            $userSwimmersStmt->fetch();
            $userSwimmersStmt->close();

            if ($userSwimmersString) {
                $swimmerArray = array_map('trim', explode(',', $userSwimmersString));
                foreach ($swimmerArray as $currentSwimmer) {
                    // Build the siblings string for the current swimmer
                    $siblingsArray = array_diff($swimmerArray, array($currentSwimmer));
                    $siblingsString = implode(',', $siblingsArray);

                    // Update the siblings column in the groups table for the current swimmer
                    // Assuming item_name in groups table stores the swimmer's name
                    $updateGroupsSql = "UPDATE groups SET siblings = ? WHERE item_name = ?";
                    $updateGroupsStmt = $conn->prepare($updateGroupsSql);
                    $updateGroupsStmt->bind_param("ss", $siblingsString, $currentSwimmer);
                    $updateGroupsStmt->execute();
                    $updateGroupsStmt->close();
                }
            }
            // --- End: Update siblings in groups table ---

        } else {
            // Handle execution error
            $_SESSION['message'] = "Failed to update athlete(s) name(s).";
            $_SESSION['message_type'] = "error";
        }

        // Close the statement
        $stmt->close();
    } else {
        // Handle preparation error
        $_SESSION['message'] = "Failed to prepare SQL statement.";
        $_SESSION['message_type'] = "error";
    }

    // Reload to dashboard.php
    echo '<meta http-equiv="refresh" content="0;">';
    exit();
}

    // Update User info and swimmer names
    if (isset($_POST['info'])) {
        $Nusername = htmlspecialchars($_POST['Nusername']);
        $Nfirstname = htmlspecialchars($_POST['Nfirstname']);
        $Nlastname = htmlspecialchars($_POST['Nlastname']);
        $Nemail = htmlspecialchars($_POST['Nemail']);
        $Nphone = htmlspecialchars($_POST['Nphone']);
        $Nswimmer = htmlspecialchars($_POST['Nswimmer']);
        $inputPassword = $_POST['Npassword'] ?? '';

        // Hash password if provided
        $hashedPassword = !empty($inputPassword) ? password_hash($inputPassword, PASSWORD_BCRYPT) : NULL;


        $updateSql = "UPDATE users SET username = ?, ";
        if ($hashedPassword) {
            $updateSql .= "password = ?, ";
        }
        $updateSql .= "firstname = ?, lastname = ?, email = ?, phone = ?, swimmer = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);

        if ($hashedPassword) {
            $updateStmt->bind_param("sssssssi", $Nusername, $hashedPassword, $Nfirstname, $Nlastname, $Nemail, $Nphone, $Nswimmer, $userId);
        } else {
            $updateStmt->bind_param("sssssssi", $Nusername, $Nfirstname, $Nlastname, $Nemail, $Nphone, $Nswimmer, $userId);
        }
        $updateStmt->execute();
        $updateStmt->close();

        // Set siblings in groups table
        $swimmerArray = array_map('trim', explode(',', $Nswimmer));
        foreach ($swimmerArray as $currentSwimmer) {
            // Use full swimmer array instead of removing current swimmer
            $siblingsString = implode(',', $swimmerArray);
            
            // Calculate sibling index (0 to number of siblings - 1)
            $siblingIndex = array_search($currentSwimmer, $swimmerArray);
            
            $updateGroupsSql = "UPDATE groups SET siblings = ?, sibling_index = ?, user_id = ? WHERE item_name = ?";
            $updateGroupsStmt = $conn->prepare($updateGroupsSql);
            $updateGroupsStmt->bind_param("isis", $siblingsString, $siblingIndex, $userId, $currentSwimmer);
            $updateGroupsStmt->execute();
            $updateGroupsStmt->close();
        }

		echo '<meta http-equiv="refresh" content="0;">';
		exit();
    }

    // Handle swap requests
    if (isset($_POST['swap']) || isset($_POST['agreeToSwap'])) {
        // Combo SQL statement for both types of requests
        $sessionId = $_POST['session_id'];

        // Load session info
        $stmt = $conn->prepare("SELECT pod, _group, pod_swap FROM timetable WHERE id = ?");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $stmt->bind_result($pod, $sessionGroupList, $podSwap);
        $stmt->fetch();
        $stmt->close();

        // Process swap request
        if (isset($_POST['swap'])) {
            // Sending a swap request
            $sql = "UPDATE timetable SET pod_swap = 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $sessionId);
            $stmt->execute();
            $_SESSION['message'] = "Swap request has been sent.";
            $_SESSION['message_type'] = "success";
            header("Location: dashboard");
            exit();
        } elseif (isset($_POST['agreeToSwap'])) {
            // Agree to swap processing
            $swimmers = array_map('trim', explode(',', $user['swimmer']));

            // Check for matching swimmer groups
            foreach ($swimmers as $swimmer) {
                $stmt = $conn->prepare("SELECT list_name FROM groups WHERE item_name = ?");
                $stmt->bind_param("s", $swimmer);
                $stmt->execute();
                $stmt->bind_result($swimmerGroup);
                $stmt->fetch();
                $stmt->close();

                if ($podSwap == '1' && in_array($swimmerGroup, array_map('trim', explode(',', $sessionGroupList)))) {
                    // Update timetable with the matched swimmer
                    $sql = "UPDATE timetable SET pod = ?, pod_swap = 0 WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $swimmer, $sessionId);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Swap Accepted.";
                        $_SESSION['message_type'] = "success";
                    } else {
                        $_SESSION['message'] = "Error accepting swap: " . $conn->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt->close();
                    header("Location: dashboard");
                    exit();
                }
            }
            $_SESSION['message'] = "No suitable swimmer found for the swap.";
            $_SESSION['message_type'] = "error";
            header("Location: dashboard");
            exit();
        }
    }

    // Update User info and swimmer names
    if (isset($_POST['dob'])) {
        $dob = date('Y-m-d', strtotime($_POST['dob'])); // Ensure it's in 'YYYY-MM-DD' format
        $name = htmlspecialchars($_POST['name']);
        $group = htmlspecialchars($_POST['group']);
        $inSchool = $_POST['inSchool'];

        if ($inSchool) {
            $inSchool = 1;
        } else {
            $inSchool = 0;
        }
    
        $updateSql = "UPDATE groups SET dob = ?, inSchool = ? WHERE item_name = ? AND list_name = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("siss", $dob, $inSchool, $name, $group);
        $updateStmt->execute();
        $updateStmt->close();
    
        echo '<meta http-equiv="refresh" content="0;">';
        exit();
    }    
}

// Retrieve all timetable data for the user
$sql = "SELECT * FROM timetable WHERE club = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $club);
$stmt->execute();
$timetableResult = $stmt->get_result();
$stmt->close();

// Prepare date calculations
$currentDate = new DateTime();
$today = $currentDate->format('d/m/y');

$weekOffset = isset($_GET['weekOffset']) ? intval($_GET['weekOffset']) : 0;

// Calculate the Monday of the relevant week
$monday = clone $currentDate;
$monday->modify('-' . ($currentDate->format('N') - 1) . ' days');
$monday->modify('+' . ($weekOffset * 7) . ' days');

// Generate date strings for the whole week
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[$i] = $monday->format('d/m/y');
    $monday->modify('+1 day');
}

// Prepare day names
$days = [
    'Monday ' . $weekDates[0],
    'Tuesday ' . $weekDates[1],
    'Wednesday ' . $weekDates[2],
    'Thursday ' . $weekDates[3],
    'Friday ' . $weekDates[4],
    'Saturday ' . $weekDates[5],
    'Sunday ' . $weekDates[6]
];

if (isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    // Clear the message after displaying
    unset($_SESSION['message']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://js.pusher.com/beams/1.0/push-notifications-cdn.js"></script>
    <link rel="stylesheet" href="tutorial/tutorial-styles.css">
    <script src="tutorial/tutorial-config.js"></script>
    <script src="tutorial/tutorial-script.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('service-worker.js')
            .then(function(registration) {
                console.log('Service Worker registered with scope:', registration.scope);
            })
            .catch(function(error) {
                console.error('Service Worker registration failed:', error);
            });
        }

        const beamsClient = new PusherPushNotifications.Client({
            instanceId: 'c4b9ff1a-d210-44b4-b02a-43d0dd4da8fd',
        });

        beamsClient.start()
            .then(() => beamsClient.addDeviceInterest('hello'))
            .then(() => beamsClient.addDeviceInterest('user_<?php echo json_encode($userId); ?>'))
            .then(() => console.log('Successfully registered and subscribed!'))
            .catch(console.error);
    </script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const userId = <?php echo json_encode($userId); ?>;

        // Make a request to the PHP script
        const xhr = new XMLHttpRequest();
        xhr.open("GET", `dashboard.php?userId=${userId}`, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    console.log("Notification sent successfully");
                } else {
                    console.error("Failed to send notification", xhr.responseText);
                }
            }
        };
        xhr.send();
    });
</script>
    <script>
window.onload = function() {
    var ei = document.getElementById("editInfo");
    ei.style.display = "none";
    <?php
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        // Safely echo the PHP variables inside JavaScript
        $message = addslashes($_SESSION['message']);
        $messageType = addslashes($_SESSION['message_type']);
        echo "showMessage('$message', '$messageType');";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
};

function showMessage(message, type) {
    const messageContainer = document.getElementById('message-container');
    if (messageContainer) {  // Check if the element exists
        messageContainer.textContent = message;
        messageContainer.className = type; // Add a class for styling, e.g., "success" or "error"
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 3000); // Hide after 3 seconds
    }
}



        function hidePendingSwaps() {
            const ps = document.getElementById("pendingSwaps");
            const hp = document.getElementById("podSessions");

            // Check if there's at least one <tbody> row inside the table
            if (ps && ps.querySelector("tbody") && ps.querySelector("tbody").rows.length === 0) {
                ps.style.display = "none";
            }

            if (hp && hp.querySelector("tbody") && hp.querySelector("tbody").rows.length === 0) {
                hp.style.display = "none";
            }
        }

        function editInfo() {
            const infoDiv = document.getElementById('info');
            const editInfoDiv = document.getElementById('editInfo');
            
            if (infoDiv.style.display !== 'none') {
                infoDiv.style.display = 'none';
                editInfoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'block';
                editInfoDiv.style.display = 'none';
            }
        }

        function showResult(str) {
            const swimmers = str.split(',').map(s => s.trim());
            const lastSwimmer = swimmers[swimmers.length - 1];

            if (lastSwimmer.length == 0) {
                document.getElementById("livesearch").innerHTML = "";
                document.getElementById("livesearch").style.border = "0px";
                return;
            }
            const xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("livesearch").innerHTML = this.responseText;
                    document.getElementById("livesearch").style.border = "1px solid #A5ACB2";
                }
            };
            xmlhttp.open("GET", "livesearch.php?q=" + lastSwimmer, true);
            xmlhttp.send();
        }

        function selectSuggestion(value) {
            const input = document.getElementById("Nswimmer");
            const currentValue = input.value;
            const lastCommaIndex = currentValue.lastIndexOf(',');
            if (lastCommaIndex === -1) {
                input.value = value;
            } else {
                input.value = currentValue.substring(0, lastCommaIndex + 1) + ' ' + value;
            }
            document.getElementById("livesearch").innerHTML = "";
            document.getElementById("livesearch").style.border = "0px";
        }

        // Add smooth transitions for elements
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });

            // Hide loading screen when page is fully loaded
            const loadingScreen = document.querySelector('.loading-screen');
            if (loadingScreen) {
                loadingScreen.classList.add('fade-out');
                setTimeout(() => {
                    loadingScreen.style.display = 'none';
                }, 500);
            }

            // Scroll reveal effect
            const sections = document.querySelectorAll('section');
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    } else {
                        entry.target.classList.remove('active');
                    }
                });
            }, { threshold: 0.2 });

            sections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
    <style>
        /* Root colors */
        :root {
            --primary-blue: #007BFF;
            --dark-blue: #0056b3;
            --black: #000000;
            --light-grey: #f5f5f5;
            --white: #ffffff;
            --highlight-red: #cb0c1f;
            --highlight-green: #28a745;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
            overflow-x: hidden;
            animation: backgroundAnimation 10s infinite alternate ease-in-out;
        }

        /* Navigation Styles */
        .nav-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: var(--dark-blue);
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav-logo {
            height: 40px;
            cursor: pointer;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background-color: var(--primary-blue);
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        /* Mobile Navigation */
        @media (max-width: 768px) {
            .nav-container {
                padding: 0.5rem;
            }

            .nav-links {
            position: fixed;
                top: 0;
                left: -250px;
                height: 100vh;
                width: 250px;
                background: var(--dark-blue);
                flex-direction: column;
                padding: 2rem 1rem;
                transition: left 0.3s ease;
                z-index: 1001;
            }

            .nav-links.active {
                left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .nav-links a {
                width: 100%;
                text-align: left;
            }

            main {
                margin-top: 60px;
            }
        }

        /* Rest of your existing styles */
        @keyframes backgroundAnimation {
            0% { background-position: left; }
            100% { background-position: right; }
        }

        main {
            background: transparent;
            padding: 2rem;
            margin: 20px;
            animation: fadeInMain 1.5s ease;
        }

        @keyframes fadeInMain {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        h1, h2 {
            color: var(--primary-blue);
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }

        button {
            padding: 0.8rem 1.5rem;
            border-radius: 5px;
            border: none;
            background-color: var(--primary-blue);
            color: var(--white);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0.5rem 0;
        }

        button:hover {
            background-color: var(--dark-blue);
            transform: translateY(-2px);
        }

        input[type="text"],
        input[type="tel"],
        input[type="password"],
        input[type="date"] {
            width: 100%;
            padding: 0.8rem;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1rem;
            margin: 0.5rem 0;
            transition: border-color 0.3s ease;
        }

        input:focus {
            border-color: var(--primary-blue);
            outline: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--primary-blue);
            color: var(--white);
            font-weight: bold;
        }

        tr:hover {
            background-color: var(--light-grey);
        }

        .sideNav {
            background: var(--dark-blue);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .sideNav a {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            display: block;
            transition: background-color 0.3s ease;
            border-radius: 5px;
        }

        .sideNav a:hover {
            background-color: var(--primary-blue);
        }

        .sideNav a.active {
            background-color: var(--primary-blue);
            font-weight: bold;
        }

        #message-container {
                position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 5px;
            color: var(--white);
            z-index: 1000;
            text-align: center;
            animation: slideDown 0.5s ease;
        }

        #message-container.success {
            background-color: var(--highlight-green);
        }

        #message-container.error {
            background-color: var(--highlight-red);
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); }
            to { transform: translate(-50%, 0); }
        }

        @keyframes fadeInResult {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-result-item {
            padding: 10px;
            border-bottom: 1px solid #ccc;
            cursor: pointer;
            opacity: 0;
            animation: fadeInResult 0.3s ease-out forwards;
        }
        .search-result-item:hover {
            background-color: #f0f0f0;
        }

        #editInfo {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
        }

        .swap {
            background-color: var(--highlight-green);
            color: var(--white);
        }

        .swap:hover {
            background-color: #218838;
        }

        #livesearch {
      max-height: 200px;
      overflow-y: auto;
      background: white;
      position: absolute;
      width: 100%;
      max-width: 360px;
      z-index: 200;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      animation: fadeIn 0.5s ease;
    }

    #livesearch div {
      padding: 10px;
      cursor: pointer;
      transition: background 0.3s;
    }

    #livesearch div:hover {
      background: #f1f1f1;
    }

    label {
      margin-top: 1rem;
      font-size: 0.9rem;
      animation: floatLabel 3s infinite alternate ease-in-out;
    }

    @keyframes floatLabel {
      0% { transform: translateY(0); }
      100% { transform: translateY(-5px); }
    }

    /* Minor animations on scroll */
    @media (prefers-reduced-motion: no-preference) {
      input, button {
        will-change: transform;
      }
      input:focus, button:focus {
        animation: bounce 0.3s ease forwards;
      }
    }

    @keyframes bounce {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

        @media (max-width: 768px) {
            main {
                margin: 10px;
                padding: 1rem;
            }

            .sideNav {
                padding: 0.5rem;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/navigation.php'; ?>
<main>
    <div id="message-container" class="message-container" style="display:none;"></div>
    <div id="info" class="card" data-tutorial="step-1">
        <h1>Hi, <?php echo htmlspecialchars($user['firstname']); ?></h1>
        <button class="button button-danger" onclick="document.location='login'">Sign Out</button>
        <p>Username: <?php echo htmlspecialchars($user['username']); ?></p>
        <p>First Name: <?php echo htmlspecialchars($user['firstname']); ?></p>
        <p>Last Name: <?php echo htmlspecialchars($user['lastname']); ?></p>
        <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
        <p>Phone: <?php echo htmlspecialchars($user['phone']); ?></p>
        <p>Athlete(s): <?php echo htmlspecialchars(!empty($user['swimmer']) ? $user['swimmer'] : 'No Athlete assigned yet.'); ?></p>
        <p>Group(s):
            <?php
            if (!empty($user['swimmer'])) {
                // Retrieve swimmers and corresponding groups
                $swimmers = is_string($user['swimmer']) ? array_map('trim', explode(',', $user['swimmer'])) : [];
                $groups = !empty($swimmerGroups) ? array_map('trim', explode(', ', implode(', ', $swimmerGroups))) : [];

                echo '<ul>';
                foreach ($swimmers as $index => $swimmer) {
                    $group = isset($swimmerGroups[$swimmer]) ? htmlspecialchars($swimmerGroups[$swimmer]) : 'No group assigned';
                    $dob = isset($swimmerDobs[$swimmer]) ? htmlspecialchars($swimmerDobs[$swimmer]) : '';
                    $inSchool = isset($swimmerInSchools[$swimmer]) ? (int)$swimmerInSchools[$swimmer] : '';

                    echo '<form action="" method="POST" class="card">
                            <li style="list-style-type: none; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                ' . htmlspecialchars($swimmer) . ' - ' . $group . ' - 
                                <input type="date" name="dob" value="' . $dob . '" required>
                                <input type="hidden" name="name" value="' . htmlspecialchars($swimmer) . '">
                                <input type="hidden" name="group" value="' . $group . '">
                                <label for="inSchool">In School?</label>
                                <input type="checkbox" name="inSchool" id="inSchool"' . 
                                ($inSchool === 1 ? ' checked' : '') . '>
                                <button type="submit" class="button button-success">Save</button>
                            </li>
                        </form>';
                }
                echo '</ul>';
            } else {
                echo 'No groups assigned.';
            }
            ?>
        </p>
        <button id="editInfoBtn" class="button" onclick="editInfo()">Edit Info</button>
    </div>

    <div id="editInfo" class="card" style="display: none;" data-tutorial="step-2">
        <form method="POST" action="">
            <label for="Nusername">Username:</label>
            <input type="text" id="Nusername" name="Nusername" value="<?php echo htmlspecialchars($user['username']); ?>">
            <label for="Nfirstname">First Name:</label>
            <input type="text" id="Nfirstname" name="Nfirstname" value="<?php echo htmlspecialchars($user['firstname']); ?>">
            <label for="Nlastname">Last Name:</label>
            <input type="text" id="Nlastname" name="Nlastname" value="<?php echo htmlspecialchars($user['lastname']); ?>">
            <label for="Nemail">Email:</label>
            <input type="text" id="Nemail" name="Nemail" value="<?php echo htmlspecialchars($user['email']); ?>">
            <label for="Nphone">Phone:</label>
            <input type="tel" id="Nphone" name="Nphone" value="<?php echo htmlspecialchars($user['phone']); ?>">
            <label for="Nswimmer">Athlete(s) (comma separated):</label>
            <input type="text" size="30" onkeyup="showResult(this.value)" id="Nswimmer" name="Nswimmer" value="<?php echo htmlspecialchars($user['swimmer']) ?? 'Please put athlete(s) name here!!!'; ?>" required>
            <div id="livesearch"></div>
            <label for="Npassword">Change Password:</label>
            <input type="password" id="Npassword" name="Npassword" value="">
            <button type="submit" name="info" class="button button-success">Save Info</button>
            <button type="button" onclick="editInfo()" class="button button-secondary">Cancel</button>
        </form>
    </div>

    <div id="podSessions" class="card" git remote add origin https://github.com/ciaranddol/My-Passport.git
git branch -M main
git push -u origin main>
    <h2>Session Details</h2>
        <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Time</th>
                <th>Group(s)</th>
                <th>Location</th>
                <th>Pod</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($timetableResult->num_rows > 0) {
                    $timetableResult->data_seek(0);
                while ($row = $timetableResult->fetch_assoc()) { 
                    $sessionGroups = array_map('trim', explode(',', $row['_group']));
                    $swimmers = array_map('trim', explode(',', $user['swimmer']));

                    foreach ($swimmers as $swimmer) {
                        $sql = "SELECT list_name, split FROM groups WHERE item_name = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $swimmer);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $groupName = $result->fetch_assoc();
                        $stmt->close();

                            if (!$groupName) continue;

                        $swimmerGroup = $groupName['list_name'] . '-' . $groupName['split'];
                        $swimmerGroupNoSplit = $groupName['list_name'];

                        $isPodSession = (
                            (in_array($swimmerGroup, $sessionGroups) || in_array($swimmerGroupNoSplit, $sessionGroups)) &&
                            trim($swimmer) == $row['pod'] &&
                            $row['pod_swap'] == "0"
                        );

                        $isPendingSwap = (
                            in_array($swimmerGroup, $sessionGroups) &&
                            $row['pod_swap'] == "1"
                        );

                        if ($isPodSession || $isPendingSwap) {
                            $sessionDay = explode(' ', $row['_day'], 2);
                            $sessionDate = $sessionDay[1];
                            $sessionTime = $row['etime'];

                            $date1 = DateTime::createFromFormat('d/m/y', $sessionDate);
                            $date2 = DateTime::createFromFormat('d/m/y', $today);

                            if (!$date1 || !$date2) {
                                echo "<tr><td colspan='6'>Invalid date format in session data.</td></tr>";
                                continue;
                            }

                            if ($date1 >= $date2 || $isPendingSwap) {
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['_day']); ?></td>
                                    <td><?php echo htmlspecialchars($row['stime'] . " - " . $row['etime']); ?></td>
                                    <td><?php echo htmlspecialchars($row['_group']); ?></td>
                                    <td><?php echo htmlspecialchars($row['_location']); ?></td>
                                    <td><?php echo htmlspecialchars($row['pod']); ?></td>
                                    <td>
                                        <form method="POST" action="">
                                            <input type="hidden" name="session_id" value="<?php echo $row['id']; ?>">
                                            <?php if ($isPodSession && $date1 > $date2) { ?>
                                                    <button type="submit" class="button button-secondary" name="swap">Request a swap</button>
                                            <?php } elseif ($isPodSession) { ?>
                                                    <button type="button" class="button" onclick="window.location.href='attendance?id=<?php echo $row['id']; ?>'">Take attendance for this session</button>
                                            <?php } elseif ($isPendingSwap) { ?>
                                                    <button type="submit" class="button button-success" name="agreeToSwap">Agree to swap</button>
                                            <?php } ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                                    break;
                            }
                        }
                    }
                }
            } else {
                echo "<tr><td colspan='6'>No sessions available.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
</main>
<div id="tutorial-overlay" class="tutorial-overlay hidden">
    <div class="tutorial-backdrop"></div>
    <div class="tutorial-tooltip">
        <div class="tutorial-content">
            <h4 id="tutorial-title">Tutorial Step</h4>
            <p id="tutorial-description">Tutorial description goes here.</p>
            <div class="tutorial-controls">
                <button id="tutorial-prev" onclick="previousStep()">Previous</button>
                <span id="tutorial-progress">1 / 5</span>
                <button id="tutorial-next" onclick="nextStep()">Next</button>
                <button id="tutorial-skip" onclick="skipTutorial()">Skip Tutorial</button>
            </div>
        </div>
        <div class="tutorial-arrow"></div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        initializeTutorial();
        tutorialInstance.steps = TUTORIAL_CONFIG.tutorials.dashboard.steps; 
        startTutorial();
    });
</script>
</body>
</html>