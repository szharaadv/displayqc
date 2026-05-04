<?php
include 'config/koneksi.php';

$nik = '2019092';        // ganti sesuai NIK yang dicoba login
$password = '12345';     // ganti sesuai password yang dicoba

$result = mysqli_query($conn, "SELECT * FROM users WHERE nik='$nik' AND status=1 LIMIT 1");
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "<b style='color:red'>User tidak ditemukan di database</b>";
} else {
    echo "User ditemukan: {$user['nama']}<br>";
    echo "Password di DB: {$user['password']}<br><br>";
    
    if (password_verify($password, $user['password'])) {
        echo "<b style='color:green'>✅ password_verify BERHASIL — login seharusnya jalan</b>";
    } else {
        echo "<b style='color:red'>❌ password_verify GAGAL — password tidak cocok dengan hash</b>";
    }
}
?>