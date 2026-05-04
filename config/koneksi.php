<?php
$conn = mysqli_connect("db.yadin.com", "bernaz", "", "displayqc");

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>