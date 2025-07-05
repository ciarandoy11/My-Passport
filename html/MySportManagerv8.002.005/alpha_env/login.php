<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "test";
$dbname = "pod_rota";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

// Form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputUsername = $_POST['username'];
    $inputPassword = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, `type-admin`, `type-coach` FROM users WHERE username = ?");
    $stmt->bind_param("s", $inputUsername);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userId, $hashedPassword, $typeAdmin, $typeCoach);
        $stmt->fetch();

        if (password_verify($inputPassword, $hashedPassword)) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $inputUsername;

            if (payment_verify($userId, $conn)) {
                if (subscription_tier($userId, $conn)) {
                    if ($typeAdmin == "1") {
                        header("Location: /alpha_env/admin.php");
                        exit();
                    } elseif ($typeCoach == "1") {
                        header("Location: /alpha_env/coachs-site.php");
                        exit();
                    } else {
                        header("Location: /alpha_env/dashboard.php");
                        exit();
                    }
                } else {
                    if ($typeAdmin == "1") {
                        header("Location: /admin.php");
                        exit();
                    } elseif ($typeCoach == "1") {
                        header("Location: /coachs-site.php");
                        exit();
                    } else {
                        header("Location: /dashboard.php");
                        exit();
                    }
                }
            } else {
                $error = "Club subscription is not active.";
                if ($typeAdmin == "1") {
                    echo '<a href="clubSettings.php">Subscription Settings</a>';
                }
            }
        } else {
            $error = "Invalid username and/or password.";
        }
    } else {
        $error = "Invalid username and/or password.";
    }

    $stmt->close();
}

function payment_verify($userId, $conn) {
    $sql = "SELECT club FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) return false;

    $club = htmlspecialchars($user['club']);
    require_once 'vendor/autoload.php';
    require_once 'stripe-sample-code/secrets.php';

    try {
        \Stripe\Stripe::setApiKey($stripeSecretKey);
        $subscriptions = \Stripe\Subscription::all(['limit' => 100]);

        foreach ($subscriptions->data as $subscription) {
            if (isset($subscription->metadata['clubName']) && $subscription->metadata['clubName'] === $club) {
                return $subscription->status === "active";
            }
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return false;
    }

    return false;
}

function subscription_tier($userId, $conn) {
    // Get the user's club name
    $sql = "SELECT club FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['club'])) return false;

    $club = htmlspecialchars($user['club']);

    try {
        // Retrieve all subscriptions
        $subscriptions = \Stripe\Subscription::all(['limit' => 100]);

        foreach ($subscriptions->data as $subscription) {
            // Match club name
            if (isset($subscription->metadata['clubName']) && $subscription->metadata['clubName'] === $club) {
                // Loop through items in the subscription
                foreach ($subscription->items->data as $item) {
                    $productId = $item->price->product;
                    $status = $subscription->status;

                    // Check for correct product and active status
                    if ($productId === "prod_RxywDMFM7hC3Rr" && $status === "active") {
                        return true;
                    }
                }
            }
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Log or handle error if needed
        return false;
    }

    return false;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" href="./favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            animation: backgroundAnimation 10s infinite alternate ease-in-out;
        }

        @keyframes backgroundAnimation {
            0% { background-position: left; }
            100% { background-position: right; }
        }
        main {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 400px;
            text-align: center;
            animation: fadeInMain 1.5s ease;
        }
        @keyframes fadeInMain {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        h1 {
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        input[type="text"],
        input[type="password"] {
            padding: 0.8rem;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        button[type="submit"],
        button[onclick] {
            padding: 0.8rem;
            border-radius: 5px;
            border: none;
            background-color: #007BFF;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
            margin-top: 0.5rem;
        }
        button[type="submit"]:hover,
        button[onclick]:hover {
            background-color: #0056b3;
        }

        button[onclick] {
            background-color: #6c757d;
        }

        button[onclick]:hover {
            background-color: #5a6268;
        }
        .error-message {
            color: red;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <main>
        <h1>Login</h1>

        <?php if ($error): ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <div style="position: relative;">
                <input type="password" id="password" name="password" placeholder="Password" required style="padding-right: 50px;">
                <a onclick="togglePassword()" style="position: absolute; right: 0; top: 0; height: 100%; line-height: 2.5; background-color: transparent; color: #007BFF; cursor: pointer; text-decoration: none;">Show</a>
            </div>
            <button type="submit">Login</button>
        </form>
        <script>
            function togglePassword() {
                const passwordField = document.getElementById('password');
                const toggleButton = passwordField.nextElementSibling;
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleButton.textContent = 'Hide';
                } else {
                    passwordField.type = 'password';
                    toggleButton.textContent = 'Show';
                }
            }
        </script>

        <div class="links">
            <button onclick="window.location.href='reset_pw.php'">Forgot password?</button>
            <button onclick="window.location.href='signup.php'" style="background-color: #28A745">Don't have an account? Sign up</button>
        </div>
    </main>

    <script src="script.js"></script>
</body>
</html>
