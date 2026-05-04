<?php
include 'config/koneksi.php';

$users = mysqli_query($conn, "SELECT id, password FROM users");

$updated = 0;
$skipped = 0;

while ($user = mysqli_fetch_assoc($users)) {
    $pwd = $user['password'];

    if (strlen($pwd) >= 60 && strpos($pwd, '$2y$') === 0) {
        $skipped++;
        continue;
    }

    $hashed = password_hash($pwd, PASSWORD_BCRYPT);
    $id = (int)$user['id'];

    mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE id = $id");
    $updated++;
}

echo "Selesai!<br>";
echo "Updated: <b>$updated</b> user<br>";
echo "Skipped (sudah hash): <b>$skipped</b> user<br>";
echo "<br><b style='color:red'>HAPUS FILE INI SEKARANG!</b>";
?>