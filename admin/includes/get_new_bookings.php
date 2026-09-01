<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$last_booking_id = isset($_GET['last_booking_id']) ? (int)$_GET['last_booking_id'] : 0;

// Query booking regular baru
$query_regular = "SELECT 
    b.id,
    b.user_id,
    b.lapangan_id,
    u.nama as nama_user,
    l.nama as nama_lapangan,
    b.tanggal_main as tanggal_jadwal,
    b.jam_mulai,
    b.jam_selesai,
    b.total_harga,
    b.status_pembayaran,
    b.tanggal_booking,
    'Regular' as jenis_booking
FROM booking b
JOIN users u ON b.user_id = u.id 
JOIN lapangan l ON b.lapangan_id = l.id
WHERE b.id > $last_booking_id
  AND b.status_pembayaran = 'pending'";

// Query booking eskul baru
$query_eskul = "SELECT 
    b.id,
    b.user_id,
    b.lapangan_id,
    u.nama as nama_user,
    l.nama as nama_lapangan,
    b.tanggal_mulai as tanggal_jadwal,
    b.jam_mulai,
    ADDTIME(b.jam_mulai, '03:00:00') as jam_selesai,
    b.total_harga,
    b.status_pembayaran,
    b.tanggal_booking,
    'Eskul' as jenis_booking
FROM booking_eskul b
JOIN users u ON b.user_id = u.id 
JOIN lapangan l ON b.lapangan_id = l.id
WHERE b.id > $last_booking_id
  AND b.status_pembayaran = 'pending'";

// Query booking turnamen baru
$query_turnamen = "SELECT 
    b.id,
    b.user_id,
    b.lapangan_id,
    u.nama as nama_user,
    l.nama as nama_lapangan,
    b.tanggal_mulai as tanggal_jadwal,
    b.jam_mulai,
    b.jam_selesai,
    b.total_harga,
    b.status_pembayaran,
    b.tanggal_booking,
    'Turnamen' as jenis_booking
FROM booking_turnamen b
JOIN users u ON b.user_id = u.id 
JOIN lapangan l ON b.lapangan_id = l.id
WHERE b.id > $last_booking_id
  AND b.status_pembayaran = 'pending'";

$new_bookings = [];
foreach ([$query_regular, $query_eskul, $query_turnamen] as $query) {
    $result = mysqli_query($conn, $query);
    if ($result === false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Query failed', 'details' => mysqli_error($conn)]);
        exit();
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $new_bookings[] = $row;
    }
}

usort($new_bookings, function($a, $b) {
    return $a['id'] - $b['id'];
});

header('Content-Type: application/json');
echo json_encode([
    'new_bookings' => $new_bookings
]);
?>
