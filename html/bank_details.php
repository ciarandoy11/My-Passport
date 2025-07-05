<?php
include __DIR__ . '/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch existing bank details
$stmt = $conn->prepare("SELECT * FROM bank_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$bankDetails = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Details - Swimming Club Management</title>
    <link rel="stylesheet" href="style.css?v=8.002.004">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    <style>
        .bank-details-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .bank-details-form {
            margin-bottom: 30px;
        }

        .bank-details-list {
            margin-top: 20px;
        }

        .bank-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .bank-detail-info {
            flex-grow: 1;
        }

        .bank-detail-actions {
            display: flex;
            gap: 10px;
        }

        .default-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8d7da;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    <main>
        <div class="form-container">
            <h2>Bank Details</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="bank-details-section">
                <form method="POST" action="process_bank_details.php" class="bank-details-form">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="account_name">Account Name</label>
                        <input type="text" id="account_name" name="account_name" required>
                    </div>

                    <div class="form-group">
                        <label for="account_number">Account Number</label>
                        <input type="text" id="account_number" name="account_number" required pattern="[0-9]{8}" maxlength="8">
                    </div>

                    <div class="form-group">
                        <label for="sort_code">Sort Code</label>
                        <input type="text" id="sort_code" name="sort_code" required pattern="[0-9]{2}-[0-9]{2}-[0-9]{2}" placeholder="00-00-00">
                    </div>

                    <div class="form-group">
                        <label for="bank_name">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_default"> Set as default payment method
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Bank Details</button>
                </form>

                <div class="bank-details-list">
                    <h3>Your Bank Details</h3>
                    <?php if (empty($bankDetails)): ?>
                        <p>No bank details added yet.</p>
                    <?php else: ?>
                        <?php foreach ($bankDetails as $detail): ?>
                            <div class="bank-detail-item">
                                <div class="bank-detail-info">
                                    <strong><?php echo htmlspecialchars($detail['account_name']); ?></strong>
                                    <?php if ($detail['is_default']): ?>
                                        <span class="default-badge">Default</span>
                                    <?php endif; ?>
                                    <br>
                                    Account: <?php echo htmlspecialchars($detail['account_number']); ?><br>
                                    Sort Code: <?php echo htmlspecialchars($detail['sort_code']); ?><br>
                                    Bank: <?php echo htmlspecialchars($detail['bank_name']); ?>
                                </div>
                                <div class="bank-detail-actions">
                                    <?php if (!$detail['is_default']): ?>
                                        <form method="POST" action="process_bank_details.php" style="display: inline;">
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="id" value="<?php echo $detail['id']; ?>">
                                            <button type="submit" class="btn btn-success">Set as Default</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="process_bank_details.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete these bank details?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $detail['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    // Format sort code input
    document.getElementById('sort_code').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 6) value = value.substr(0, 6);
        if (value.length > 4) {
            value = value.substr(0, 4) + '-' + value.substr(4);
        }
        if (value.length > 2) {
            value = value.substr(0, 2) + '-' + value.substr(2);
        }
        e.target.value = value;
    });

    // Format account number input
    document.getElementById('account_number').addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substr(0, 8);
    });
    </script>
</body>
</html> 