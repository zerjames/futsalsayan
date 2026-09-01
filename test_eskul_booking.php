<?php
session_start();
require_once 'config/database.php';

echo "<h2>Test Eskul Booking</h2>";

// Test 1: Check available eskul packages
echo "<h3>1. Available Eskul Packages:</h3>";
$query_eskul = "SELECT hp.*, l.id_lapangan, l.nama AS nama_lapangan, l.id_lapangan AS lapangan_id
FROM harga_paket hp
JOIN lapangan l ON l.id_lapangan = CAST(SUBSTRING(hp.tipe, 10) AS UNSIGNED)
WHERE hp.tipe LIKE 'eskul_lap%' ORDER BY l.id_lapangan ASC";
$result_eskul = mysqli_query($conn, $query_eskul);

if (!$result_eskul) {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
} else {
    $count = mysqli_num_rows($result_eskul);
    echo "<p>Found $count eskul packages:</p>";
    echo "<ul>";
    while ($paket = mysqli_fetch_assoc($result_eskul)) {
        echo "<li>Lapangan: " . $paket['nama_lapangan'] . " (ID: " . $paket['id_lapangan'] . ") - Harga: Rp " . number_format($paket['harga'], 0, ',', '.') . "</li>";
    }
    echo "</ul>";
}

// Test 2: Check specific lapangan pricing
echo "<h3>2. Specific Lapangan Pricing:</h3>";
$test_lapangan_id = 1; // Test with lapangan ID 1
$query_harga_paket = "SELECT harga FROM harga_paket WHERE tipe = 'eskul_lap$test_lapangan_id' ORDER BY id_paket LIMIT 1";
$result_harga_paket = mysqli_query($conn, $query_harga_paket);
$harga_paket = mysqli_fetch_assoc($result_harga_paket);

if (!$harga_paket) {
    echo "<p style='color: red;'>No pricing found for lapangan ID $test_lapangan_id</p>";
} else {
    echo "<p>Lapangan ID $test_lapangan_id - Harga: Rp " . number_format($harga_paket['harga'], 0, ',', '.') . "</p>";
}

// Test 3: Check all harga_paket entries
echo "<h3>3. All Harga Paket Entries:</h3>";
$query_all = "SELECT * FROM harga_paket WHERE tipe LIKE 'eskul%' ORDER BY tipe";
$result_all = mysqli_query($conn, $query_all);

if (!$result_all) {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
} else {
    $count = mysqli_num_rows($result_all);
    echo "<p>Found $count eskul entries in harga_paket:</p>";
    echo "<ul>";
    while ($entry = mysqli_fetch_assoc($result_all)) {
        echo "<li>ID: " . $entry['id_paket'] . " - Tipe: " . $entry['tipe'] . " - Harga: Rp " . number_format($entry['harga'], 0, ',', '.') . "</li>";
    }
    echo "</ul>";
}

// Test 4: Check lapangan table
echo "<h3>4. Available Lapangan:</h3>";
$query_lapangan = "SELECT * FROM lapangan WHERE status = 'tersedia' ORDER BY id_lapangan";
$result_lapangan = mysqli_query($conn, $query_lapangan);

if (!$result_lapangan) {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
} else {
    $count = mysqli_num_rows($result_lapangan);
    echo "<p>Found $count available lapangan:</p>";
    echo "<ul>";
    while ($lapangan = mysqli_fetch_assoc($result_lapangan)) {
        echo "<li>ID: " . $lapangan['id_lapangan'] . " - Nama: " . $lapangan['nama'] . " - Status: " . $lapangan['status'] . "</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='index.php'>Back to Index</a></p>";
?>


