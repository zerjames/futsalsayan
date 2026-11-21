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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_request()) {
        $_SESSION['error'] = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.";
        header("Location: dashboard.php?tab=kelola_user");
        exit();
    }
    if (isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);

        // Cegah admin menghapus dirinya sendiri (optional)
        if ($user_id === $_SESSION['user_id']) {
            $_SESSION['error'] = "Anda tidak dapat menghapus user sendiri.";
            header("Location: dashboard.php?tab=kelola_user");
            exit();
        }

        // Hapus semua booking terkait user
        mysqli_query($conn, "DELETE FROM booking_eskul WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM booking_turnamen WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM booking WHERE user_id = $user_id");

        // Hapus user dari database
        $query = "DELETE FROM users WHERE id_user = '$user_id'";
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "User berhasil dihapus.";
        } else {
            $_SESSION['error'] = "Gagal menghapus user.";
        }
    } else {
        $_SESSION['error'] = "ID user tidak valid.";
    }
} else {
    $_SESSION['error'] = "Metode request tidak valid.";
}

header("Location: dashboard.php?tab=kelola_user");
exit();
?>
