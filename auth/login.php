<?php
session_start();

if (isset($_SESSION['id'])) {
    header("Location: ../menu.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Display QC</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <h1>LOGIN</h1>

            <?php if (isset($_GET['error'])): ?>
                <p class="error">NIK atau password salah.</p>
            <?php endif; ?>

            <form action="proses_login.php" method="POST">
                <input type="text" name="nik" placeholder="NIK / Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">SIGN IN</button>
            </form>
        </div>
    </div>
</body>
</html>