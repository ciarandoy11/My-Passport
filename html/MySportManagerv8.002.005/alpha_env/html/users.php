<?php
session_start();
include __DIR__ . '/db.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "No user_id in session. Redirecting to login...";
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch user admin type and club
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $userId);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("No user found with ID: " . $userId);
}

$typeAdmin = (int)$user['type-admin']; // Cast to integer
$club = $user['club']; // Get club from user data

if ($typeAdmin !== 1) {
    echo "User is not an admin. Redirecting to login...";
    header("Location: login.php");
    exit();
}

// Fetch all users for the club
$sql = "SELECT * FROM users WHERE club = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed for member list: " . $conn->error);
}

$stmt->bind_param("s", $club);
if (!$stmt->execute()) {
    die("Execute failed for member list: " . $stmt->error);
}

$memberList = $stmt->get_result();
$stmt->close();

// Initialize arrays to hold members based on their user types
$coaches = [];
$admins = [];
$users = [];

// Sort members into the respective arrays based on user types
if ($memberList && $memberList->num_rows > 0) {
    while ($member = $memberList->fetch_assoc()) {
        if ($member['type-coach'] === 1) {
            $coaches[] = $member;
        } elseif ($member['type-admin'] === 1) {
            $admins[] = $member;
        } else {
            $users[] = $member;
        }
    }
}

// Update user information
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $updateId = intval($_POST['user_id']);

    // Handle name vs firstname/lastname split
    if (isset($_POST['name']) && !empty(trim($_POST['name']))) {
        $name = htmlspecialchars(trim($_POST['name']));
        $nameParts = explode(' ', $name, 2);
        $newFirstname = $nameParts[0];
        $newLastname = isset($nameParts[1]) ? $nameParts[1] : '';
    } else {
        $newFirstname = htmlspecialchars($_POST['firstname']);
        $newLastname = htmlspecialchars($_POST['lastname']);
    }

    $newEmail = htmlspecialchars($_POST['email']);
    $newPhone = htmlspecialchars($_POST['phone']);

    // Check if swimmer field is present
    if (isset($_POST['swimmer'])) {
        $swimmer = htmlspecialchars($_POST['swimmer']);
        $updateSql = "UPDATE users SET firstname = ?, lastname = ?, email = ?, phone = ?, swimmer = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sssssi", $newFirstname, $newLastname, $newEmail, $newPhone, $swimmer, $updateId);
    } else {
        $updateSql = "UPDATE users SET firstname = ?, lastname = ?, email = ?, phone = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ssssi", $newFirstname, $newLastname, $newEmail, $newPhone, $updateId);
    }

    if ($updateStmt->execute()) {
        echo "<script>setTimeout(function() { window.location.href = 'users.php'; }, 1000);</script>";
        exit();
    } else {
        echo "<script>alert('Error updating user details: " . htmlspecialchars($updateStmt->error) . "');</script>";
    }

    $updateStmt->close();
}

// Handle new user signup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    // Get inputs and sanitize
    $name = htmlspecialchars(trim($_POST['name']));
    list($firstname, $lastname) = explode(' ', $name);
    $username = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $password = 'VerySecretPW1234!';
    $userType = htmlspecialchars(trim($_POST['user_type']));

    // Validation patterns
    $namePattern = "/^[A-Za-z._\-<>0-9]+$/";
    $passwordPattern = "/^(?=.*[!@#$%^&*(),.?\":{}|<>])[A-Za-z\d!@#$%^&*(),.?\":{}|<>]{8,}$/";

    // Validate input
    $errors = [];

    if (!preg_match($namePattern, $firstname)) {
        $errors[] = "First name can only contain English alphabet characters and the symbols . _ - < >";
    }

    if (!preg_match($namePattern, $lastname)) {
        $errors[] = "Last name can only contain English alphabet characters and the symbols . _ - < >";
    }

    if (!preg_match($namePattern, $username)) {
        $errors[] = "Username can only contain English alphabet characters and the symbols . _ - < >";
    }

    if (!preg_match($passwordPattern, $password)) {
        $errors[] = "Password must be at least 8 characters long and include at least one special character.";
    }

    // Check if there are any validation errors
    if (!empty($errors)) {
        // Return the errors to the user
        echo "<script>alert('" . implode("\\n", $errors) . "');</script>";
    } else {
        // Validate unique username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "<script>alert('Username already exists!');</script>";
        } else {
            // Determine user type
            $coach = ($userType === "Coach") ? 1 : 0;
            $admin = ($userType === "Admin") ? 1 : 0;

            // Insert new user
            $sql = "INSERT INTO users (firstname, lastname, username, email, phone, password, club, `type-coach`, `type-admin`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT); // Hash the password
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssii", $firstname, $lastname, $username, $email, $phone, $hashedPassword, $club, $coach, $admin);

            if ($stmt->execute()) {
                echo "<script>alert('User created successfully\\nPassword: VerySecretPW1234!');</script>";
                echo "<script>setTimeout(function() { window.location.href = 'users.php'; }, 1000);</script>";
            } else {
                echo "<script>alert('Error creating user: " . htmlspecialchars($stmt->error) . "');</script>";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Management - My Sport Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
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

        /* Reset and base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
            background-size: 100% 100%;
            background-position: left;
            overflow-x: hidden;
            animation: backgroundAnimation 10s infinite alternate ease-in-out;
            padding-top: 8%;
            color: #333; /* Ensure text is visible */
        }

        @keyframes backgroundAnimation {
            0% { background-position: left; }
            100% { background-position: right; }
        }

        /* Header */
        header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: var(--white);
            padding: 30px 20px;
            text-align: center;
            animation: slideDown 1s ease-out forwards;
        }

        header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            animation: floatText 3s ease-in-out infinite alternate;
        }

        header i {
            font-size: 1.3rem;
            opacity: 0.9;
        }

        /* Sections */
        section {
            background-color: var(--white);
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: #333;
            transform: translateY(50px);
            opacity: 0;
            transition: all 0.8s ease-out;
        }

        section.active {
            transform: translateY(0);
            opacity: 1;
        }

        section h2 {
            color: var(--dark-blue);
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        /* Table Styles */
        .table-wrapper {
            margin-top: 20px;
            overflow-x: auto;
            background-color: var(--white);
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 0.9em;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
            background-color: var(--white);
        }

        .styled-table thead tr {
            background-color: var(--dark-blue);
            color: var(--white);
            text-align: left;
        }

        .styled-table th,
        .styled-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            color: #333; /* Ensure text is visible */
        }

        .styled-table tbody tr {
            border-bottom: 1px solid #dddddd;
            background-color: var(--white);
        }

        .styled-table tbody tr:nth-child(even) {
            background-color: #f3f3f3;
        }

        .styled-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .styled-table input[type="text"],
        .styled-table input[type="email"],
        .styled-table input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: var(--white);
            color: #333;
        }

        .styled-table button {
            background-color: var(--primary-blue);
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .styled-table button:hover {
            background-color: var(--dark-blue);
        }

        /* Form Styles */
        #info, #editInfo {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
            color: #333;
        }

        #info h1 {
            color: var(--dark-blue);
            margin-bottom: 20px;
        }

        #info p {
            margin: 10px 0;
            color: #333;
        }

        #editInfo form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        #editInfo label {
            color: #333;
            font-weight: bold;
        }

        #editInfo input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: var(--white);
            color: #333;
        }

        /* Main content area */
        main {
            margin-left: 15%;
            padding: 20px;
            background-color: transparent;
        }

        /* Loading Screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }

        .loading-screen.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .loading-content {
            text-align: center;
            color: white;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 10px;
            }

            section {
                margin: 10px;
                padding: 15px;
            }

            .styled-table {
                font-size: 0.8em;
            }

            .styled-table th,
            .styled-table td {
                padding: 8px;
            }
        }

        /* Edit Button Styles */
        .edit-button {
            background-color: var(--primary-blue) !important;
            color: white !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            font-size: 0.9em !important;
            font-weight: 500 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            display: inline-block !important;
            text-align: center !important;
            text-decoration: none !important;
            line-height: 1.5 !important;
            vertical-align: middle !important;
            user-select: none !important;
        }

        .edit-button:hover {
            background-color: var(--dark-blue) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
        }

        .edit-button:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }

        /* Edit Form Styles */
        .edit-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .edit-form form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .edit-form input {
            flex: 1;
            min-width: 200px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        .edit-form button {
            background-color: var(--highlight-green);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-form button:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
<!-- Loading Screen -->
<div class="loading-screen">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <h2>Loading...</h2>
    </div>
</div>

<?php include 'includes/admin_navigation.php'; ?>
<main>
    <section>
        <h2>My Account</h2>
        <div id="info">
            <h1>Hello, <?php echo htmlspecialchars($user['firstname']); ?></h1>
            <button style="background-color: red; color: white;" onclick="document.location='login.php'">Sign Out</button>
            <p>Username: <?php echo htmlspecialchars($user['username']); ?></p>
            <p>First Name: <?php echo htmlspecialchars($user['firstname']); ?></p>
            <p>Last Name: <?php echo htmlspecialchars($user['lastname']); ?></p>
            <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
            <p>Phone: <?php echo htmlspecialchars($user['phone']); ?></p>
            <button onclick="editInfo()">Edit Info</button>
        </div>

        <div id="editInfo" style="display: none;">
            <form method="POST" action="">
                <?php
                echo '<input type="hidden" name="user_id" value="' . htmlspecialchars($user['id']) . '">';
                ?>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                <label for="firstname">First Name:</label>
                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>">
                <label for="lastname">Last Name:</label>
                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>">
                <label for="email">Email:</label>
                <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                <label for="password">Change Password:</label>
                <input type="password" id="password" name="password" value="">
                <button type="submit" name="update_user">Save Info</button>
            </form>
        </div>
    </section>

    <?php
    // Function to display a table for given members
    function displayTable($members, $userType) {
        echo '<section>';
        echo '<h2>' . htmlspecialchars($userType) . 's</h2>';
        echo '<div class="table-wrapper">';
        echo '<table class="styled-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Name</th>';
        echo '<th>Username</th>';
        echo '<th>Email</th>';
        echo '<th>Phone</th>';
        if ($userType === 'User') {
            echo '<th>Swimmer(s)</th>';
        }
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // Add User Form Row
        echo '<tr>';
        echo '<form method="POST" action="">';
        echo '<td><input type="text" name="name" placeholder="Name" required></td>';
        echo '<td><input type="text" name="username" placeholder="Username" required></td>';
        echo '<td><input type="email" name="email" placeholder="Email" required></td>';
        echo '<td><input type="text" name="phone" placeholder="Phone"></td>';
        echo '<input type="hidden" name="user_type" value="' . htmlspecialchars($userType) . '">';
        if ($userType === 'User') {
            echo '<td><input type="text" name="swimmer" placeholder="Swimmer(s)"></td>';
        }
        echo '<td><button type="submit" name="signup">Add User</button></td>';
        echo '</form>';
        echo '</tr>';

        // Display existing users
        foreach ($members as $member) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($member['firstname'] . ' ' . $member['lastname']) . '</td>';
            echo '<td>' . htmlspecialchars($member['username']) . '</td>';
            echo '<td>' . htmlspecialchars($member['email']) . '</td>';
            echo '<td>' . htmlspecialchars($member['phone']) . '</td>';
            if ($userType === 'User') {
                echo '<td>' . htmlspecialchars($member['swimmer'] ?? '') . '</td>';
            }
            echo '<td>';
            echo '<button type="button" class="edit-button" data-userid="' . htmlspecialchars($member['id']) . '">Edit</button>';
            echo '</td>';
            echo '</tr>';

            // Edit Form Row
            echo '<tr class="edit-form" style="display: none;">';
            echo '<td colspan="' . ($userType === 'User' ? '6' : '5') . '">';
            echo '<form method="POST" action="">';
            echo '<input type="hidden" name="user_id" value="' . htmlspecialchars($member['id']) . '">';
            echo '<input type="text" name="name" value="' . htmlspecialchars($member['firstname'] . ' ' . $member['lastname']) . '" placeholder="Name">';
            echo '<input type="text" name="username" value="' . htmlspecialchars($member['username']) . '" placeholder="Username">';
            echo '<input type="email" name="email" value="' . htmlspecialchars($member['email']) . '" placeholder="Email">';
            echo '<input type="text" name="phone" value="' . htmlspecialchars($member['phone']) . '" placeholder="Phone">';
            if ($userType === 'User') {
                echo '<input type="text" name="swimmer" value="' . htmlspecialchars($member['swimmer'] ?? '') . '" placeholder="Swimmer(s)">';
            }
            echo '<button type="submit" name="update_user">Save</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</section>';
    }

    // Display tables for each user type
    displayTable($coaches, 'Coach');
    displayTable($admins, 'Admin');
    displayTable($users, 'User');
    ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading screen
    const loadingScreen = document.querySelector('.loading-screen');
    if (loadingScreen) {
        loadingScreen.classList.add('fade-out');
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }

    // Make sure all tables are visible
    document.querySelectorAll('.styled-table').forEach(table => {
        table.style.display = 'table';
        table.style.visibility = 'visible';
    });

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
    }, { threshold: 0.1 });

    sections.forEach(section => {
        observer.observe(section);
    });

    // Edit button functionality
    document.querySelectorAll('.edit-button').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const editRow = row.nextElementSibling;
            
            if (editRow.style.display === 'none' || editRow.style.display === '') {
                editRow.style.display = 'table-row';
                this.textContent = 'Cancel';
                this.style.backgroundColor = 'red';
            } else {
                editRow.style.display = 'none';
                this.textContent = 'Edit';
                this.style.backgroundColor = '';
            }
        });
    });
});

function editInfo() {
    const infoDiv = document.getElementById('info');
    const editDiv = document.getElementById('editInfo');
    
    if (infoDiv.style.display !== 'none') {
        infoDiv.style.display = 'none';
        editDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'block';
        editDiv.style.display = 'none';
    }
}

// Force visibility of all content
window.onload = function() {
    document.querySelectorAll('section, .styled-table, .table-wrapper').forEach(element => {
        element.style.display = '';
        element.style.visibility = 'visible';
        element.style.opacity = '1';
    });
};
</script>
</body>
</html>