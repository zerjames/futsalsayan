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
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    $_SESSION['error'] = "Parameter tidak lengkap";
    header("Location: dashboard.php");
    exit();
}

$id = (int)$_GET['id'];
$type = mysqli_real_escape_string($conn, $_GET['type']);

// Tentukan tabel dan tab berdasarkan tipe booking
switch ($type) {
    case 'regular':
        $table = 'booking';
        $redirect_tab = 'booking_regular';
        $primary_key = 'id_booking';
        break;
    case 'eskul':
        $table = 'booking_eskul';
        $redirect_tab = 'booking_eskul';
        $primary_key = 'id_booking_eskul';
        break;
    case 'turnamen':
        $table = 'booking_turnamen';
        $redirect_tab = 'booking_turnamen';
        $primary_key = 'id_booking_turnamen';
        break;
    default:
        $_SESSION['error'] = "Tipe booking tidak valid";
        header("Location: dashboard.php");
        exit();
}

// Cek apakah booking ada dan ambil data bukti pembayaran
$query = "SELECT bukti_pembayaran FROM $table WHERE $primary_key = $id";
$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    $_SESSION['error'] = "Booking tidak ditemukan";
    header("Location: dashboard.php?tab=" . $redirect_tab);
    exit();
}

// Hapus file bukti pembayaran jika ada
if ($booking['bukti_pembayaran']) {
    $file_path = "../uploads/bukti_pembayaran/" . $booking['bukti_pembayaran'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Send email notification before deleting
require_once '../includes/mail.php';
if (sendBookingNotification($conn, $id, 'dibatalkan', $type)) {
    error_log("Email notification sent successfully for booking cancellation");
} else {
    error_log("Failed to send email notification for booking cancellation");
}

// Hapus data booking
$query = "DELETE FROM $table WHERE $primary_key = $id";
if (mysqli_query($conn, $query)) {
    $_SESSION['success'] = "Booking berhasil dihapus dan notifikasi email telah dikirim";
} else {
    $_SESSION['error'] = "Gagal menghapus booking: " . mysqli_error($conn);
}

header("Location: dashboard.php?tab=" . $redirect_tab);
exit();
?>
