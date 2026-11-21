<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_POST['booking_id'])) {
    header("Location: index.php");
    exit();
}

if (!validate_csrf_request()) {
    $_SESSION['error'] = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header("Location: " . $ref);
    exit();
}

$booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
$user_id = $_SESSION['user_id'];

// Get booking type from POST
$booking_type = mysqli_real_escape_string($conn, $_POST['booking_type']);

// Tentukan kolom id yang benar untuk masing-masing tabel
$table_name = '';
$redirect_page = '';
$id_column = '';
switch ($booking_type) {
    case 'regular':
        $table_name = 'booking';
        $redirect_page = 'invoice.php';
        $id_column = 'id_booking';
        break;
    case 'eskul':
        $table_name = 'booking_eskul';
        $redirect_page = 'invoice_eskul.php';
        $id_column = 'id_booking_eskul';
        break;
    case 'turnamen':
        $table_name = 'booking_turnamen';
        $redirect_page = 'invoice_turnamen.php';
        $id_column = 'id_booking_turnamen';
        break;
    default:
        $_SESSION['error'] = "Tipe booking tidak valid.";
        header("Location: index.php");
        exit();
}

// Jika admin, bisa akses semua booking. Jika user biasa, hanya bisa akses booking miliknya
$check_query = $_SESSION['role'] == 'admin' 
    ? "SELECT * FROM $table_name WHERE $id_column = ?"
    : "SELECT * FROM $table_name WHERE $id_column = ? AND user_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
if ($_SESSION['role'] == 'admin') {
    mysqli_stmt_bind_param($check_stmt, 'i', $booking_id);
} else {
    mysqli_stmt_bind_param($check_stmt, 'ii', $booking_id, $user_id);
}
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($result) != 1) {
    $_SESSION['error'] = "Booking tidak ditemukan.";
    header("Location: index.php");
    exit();
}

$booking = mysqli_fetch_assoc($result);

// Check if file was uploaded
if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Terjadi kesalahan saat upload file.";
    header("Location: " . $redirect_page . "?booking_id=" . $booking_id);
    exit();
}

$file = $_FILES['bukti_pembayaran'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($file['type'], $allowed_types)) {
    $_SESSION['error'] = "Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.";
    header("Location: " . $redirect_page . "?booking_id=" . $booking_id);
    exit();
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 2MB.";
    header("Location: " . $redirect_page . "?booking_id=" . $booking_id);
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = 'uploads/bukti_pembayaran/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $extension;
$target_path = $upload_dir . $filename;

// Delete old file if exists
if (!empty($booking['bukti_pembayaran'])) {
    $old_file = $upload_dir . $booking['bukti_pembayaran'];
    if (file_exists($old_file)) {
        unlink($old_file);
    }
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Update database
    // Update query disesuaikan dengan role
    $update_query = $_SESSION['role'] == 'admin'
        ? "UPDATE $table_name SET 
           bukti_pembayaran = ?,
           tanggal_upload_bukti = CURRENT_TIMESTAMP,
           status_pembayaran = 'pending'
           WHERE $id_column = ?"
        : "UPDATE $table_name SET 
           bukti_pembayaran = ?,
           tanggal_upload_bukti = CURRENT_TIMESTAMP,
           status_pembayaran = 'pending'
           WHERE $id_column = ? AND user_id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    if ($_SESSION['role'] == 'admin') {
        mysqli_stmt_bind_param($update_stmt, 'si', $filename, $booking_id);
    } else {
        mysqli_stmt_bind_param($update_stmt, 'sii', $filename, $booking_id, $user_id);
    }
    
    if (mysqli_stmt_execute($update_stmt)) {
        $_SESSION['success'] = "Bukti pembayaran berhasil diupload.";
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan data.";
        // Delete uploaded file if database update fails
        unlink($target_path);
    }
} else {
    $_SESSION['error'] = "Terjadi kesalahan saat upload file.";
}

header("Location: " . $redirect_page . "?booking_id=" . $booking_id);
exit();
?>
