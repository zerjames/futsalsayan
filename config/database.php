<?php
$host = "localhost";
$db_username = "root";
$password = "";
$database = "futsaldot";

// Membuat koneksi ke database
$conn = mysqli_connect($host, $db_username, $password, $database);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}



