<?php
// Include database dan functions di awal
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /futsalsayan/login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    header("Location: dashboard.php");
    exit();
}

$booking_id = mysqli_real_escape_string($conn, $_GET['id']);
$type = mysqli_real_escape_string($conn, $_GET['type']);

// Tentukan tabel dan tab berdasarkan tipe booking
switch ($type) {
    case 'regular':
        $table = 'booking';
        $redirect_tab = 'booking_regular';
        break;
    case 'eskul':
        $table = 'booking_eskul';
        $redirect_tab = 'booking_eskul';
        break;
    case 'turnamen':
        $table = 'booking_turnamen';
        $redirect_tab = 'booking_turnamen';
        break;
    default:
        header("Location: dashboard.php");
        exit();
}

// Update status pembayaran menjadi dibatalkan
$query = "UPDATE $table SET status_pembayaran = 'dibatalkan' WHERE id = '$booking_id'";

if (mysqli_query($conn, $query)) {
    $_SESSION['success'] = "Booking berhasil dibatalkan.";
} else {
    $_SESSION['error'] = "Terjadi kesalahan. Silakan coba lagi.";
}

header("Location: dashboard.php?tab=" . $redirect_tab);
exit();
?>
