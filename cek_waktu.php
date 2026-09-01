<?php
// Set timezone ke Asia/Jakarta (ubah jika perlu)
date_default_timezone_set('Asia/Jakarta');

// Koneksi ke database
require_once __DIR__ . '/config/database.php';

// Waktu PHP
echo "<b>Waktu PHP:</b> " . date('Y-m-d H:i:s') . "<br>";

// Waktu MySQL
echo "<b>Waktu MySQL & Timezone:</b><br>";
$result = mysqli_query($conn, "SELECT NOW() as mysql_now, @@global.time_zone as global_tz, @@session.time_zone as session_tz");
if ($row = mysqli_fetch_assoc($result)) {
    echo "NOW(): " . $row['mysql_now'] . "<br>";
    echo "Global Timezone: " . $row['global_tz'] . "<br>";
    echo "Session Timezone: " . $row['session_tz'] . "<br>";
} else {
    echo "Gagal mengambil waktu MySQL.";
}
?> 