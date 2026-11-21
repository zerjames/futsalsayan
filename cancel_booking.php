<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_POST['booking_id'])) {
    header("Location: index.php");
    exit();
}

if (!validate_csrf_request()) {
    $_SESSION['error'] = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.";
    header("Location: riwayat.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
$booking_type = mysqli_real_escape_string($conn, $_POST['booking_type']);

// Set table, date, and primary key field based on booking type
$table_name = '';
$date_field = '';
$primary_key = '';
switch ($booking_type) {
    case 'regular':
        $table_name = 'booking';
        $date_field = 'tanggal_main';
        $primary_key = 'id_booking';
        break;
    case 'eskul':
        $table_name = 'booking_eskul';
        $date_field = 'tanggal_mulai';
        $primary_key = 'id_booking_eskul';
        break;
    case 'turnamen':
        $table_name = 'booking_turnamen';
        $date_field = 'tanggal_mulai';
        $primary_key = 'id_booking_turnamen';
        break;
    default:
        $_SESSION['error'] = "Tipe booking tidak valid.";
        header("Location: riwayat.php");
        exit();
}

// Query and update logic for all types
$query = "SELECT * FROM $table_name 
          WHERE $primary_key = ? AND user_id = ? 
          AND status_pembayaran != 'dibatalkan'
          AND $date_field >= CURDATE()";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 1) {
    $booking = mysqli_fetch_assoc($result);

    // Update booking status to cancelled
    $update_query = "UPDATE $table_name 
                    SET status_pembayaran = 'dibatalkan'
                    WHERE $primary_key = ? AND user_id = ?";

    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ii', $booking_id, $user_id);

    if (mysqli_stmt_execute($update_stmt)) {
        $_SESSION['success'] = "Booking berhasil dibatalkan.";
        // Kirim notifikasi email ke admin jika bukan eskul
        if ($booking_type !== 'eskul') {
            require_once 'includes/mail.php';
            $admin_email = "alzierproject@gmail.com"; // Ganti dengan email admin yang sesuai
            $subject = "Booking Dibatalkan oleh User";
            $message = "Booking dengan ID $booking_id (Tipe: $booking_type) telah dibatalkan oleh user.";
            sendMail($admin_email, $subject, $message);
        }
    } else {
        $_SESSION['error'] = "Gagal membatalkan booking.";
    }
} else {
    $_SESSION['error'] = "Booking tidak ditemukan atau tidak dapat dibatalkan.";
}

header("Location: riwayat.php");
exit();
?>
