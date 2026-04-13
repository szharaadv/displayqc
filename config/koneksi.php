<?php
$conn = mysqli_connect("localhost", "root", "", "displayqc");

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>