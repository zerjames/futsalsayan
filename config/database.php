<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/env.php';

$host        = env('DB_HOST', 'localhost');
$db_username = env('DB_USER', 'root');
$password    = env('DB_PASS', '');
$database    = env('DB_NAME', 'futsaldot');

// Membuat koneksi ke database
$conn = mysqli_connect($host, $db_username, $password, $database);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}



