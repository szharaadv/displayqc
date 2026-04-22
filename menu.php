<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: auth/login.php");
    exit;
}

$is_qc = isset($_SESSION['role']) && $_SESSION['role'] === 'qc';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Utama Display QC</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card menu-card">
            <h1>MENU</h1>
            <p class="menu-user">Login sebagai: <strong><?php echo $_SESSION['nik']; ?></strong></p>

            <div class="menu-list">
                <a href="operator/create_order.php" class="menu-item">
                    <span>Created Order</span>
                    <span class="arrow">→</span>
                </a>

                <a href="qc/display.php" class="menu-item">
                    <span>Order Request</span>
                    <span class="arrow">→</span>
                </a>

                <?php if ($is_admin): ?>
                <a href="admin/dashboard.php" class="menu-item">
                    <span>Manager Dashboard</span>
                    <span class="arrow">→</span>
                </a>
                <?php endif; ?>

                <?php if ($is_qc): ?>
                <a href="qc/main_display.php" class="menu-item">
                    <span>Main Display QC</span>
                    <span class="arrow">→</span>
                </a>
                <?php endif; ?>

                <a href="auth/logout.php" class="menu-item logout-item">
                    <span>Logout</span>
                    <span class="arrow">→</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>