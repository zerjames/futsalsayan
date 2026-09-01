<?php
require_once '../../config/database.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$last_id = isset($_GET['last_cancelled_id']) ? (int)$_GET['last_cancelled_id'] : 0;

// Booking Regular
$query_regular = "SELECT id, 'regular' AS jenis, nama_pembayar AS nama, tanggal_main AS tanggal, lapangan_id FROM booking WHERE status_pembayaran='dibatalkan' AND id > $last_id ORDER BY id ASC LIMIT 5";
$result_regular = mysqli_query($conn, $query_regular);
$cancelled_regular = [];
while ($row = mysqli_fetch_assoc($result_regular)) {
    $cancelled_regular[] = $row;
}
// Booking Eskul
$query_eskul = "SELECT id, 'eskul' AS jenis, nama_sekolah AS nama, tanggal_mulai AS tanggal, lapangan_id FROM booking_eskul WHERE status_pembayaran='dibatalkan' AND id > $last_id ORDER BY id ASC LIMIT 5";
$result_eskul = mysqli_query($conn, $query_eskul);
$cancelled_eskul = [];
while ($row = mysqli_fetch_assoc($result_eskul)) {
    $cancelled_eskul[] = $row;
}
// Booking Turnamen
$query_turnamen = "SELECT id, 'turnamen' AS jenis, nama_turnamen AS nama, tanggal_mulai AS tanggal, lapangan_id FROM booking_turnamen WHERE status_pembayaran='dibatalkan' AND id > $last_id ORDER BY id ASC LIMIT 5";
$result_turnamen = mysqli_query($conn, $query_turnamen);
$cancelled_turnamen = [];
while ($row = mysqli_fetch_assoc($result_turnamen)) {
    $cancelled_turnamen[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'regular' => $cancelled_regular,
    'eskul' => $cancelled_eskul,
    'turnamen' => $cancelled_turnamen
]); 