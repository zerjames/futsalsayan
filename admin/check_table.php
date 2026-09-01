<?php
require_once '../config/database.php';

// Get table structure
$query = "DESCRIBE lapangan";
$result = mysqli_query($conn, $query);

echo "<h2>Struktur Tabel Lapangan:</h2>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}

// Get sample data
$query = "SELECT * FROM lapangan LIMIT 1";
$result = mysqli_query($conn, $query);

echo "<h2>Contoh Data Lapangan:</h2>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
?>
