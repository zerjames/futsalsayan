<?php
ob_start();

// Include database dan functions di awal
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $booking_id = mysqli_real_escape_string($conn, $_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'regular';
    
    // Set table name based on booking type
    $table_name = 'booking';
    $invoice_page = 'invoice.php';
    $redirect_tab = 'booking_regular';
    $primary_key = 'id_booking';
    
    if ($type == 'turnamen') {
        $table_name = 'booking_turnamen';
        $invoice_page = 'invoice_turnamen.php';
        $redirect_tab = 'booking_turnamen';
        $primary_key = 'id_booking_turnamen';
    } else if ($type == 'eskul') {
        $table_name = 'booking_eskul';
        $invoice_page = 'invoice_eskul.php';
        $redirect_tab = 'booking_eskul';
        $primary_key = 'id_booking_eskul';
    }
    
    // Get booking details first
    $query_check = "SELECT * FROM $table_name WHERE $primary_key = '$booking_id'";
    $result_check = mysqli_query($conn, $query_check);
    
    if (!$result_check) {
        error_log("Error in SELECT query: " . mysqli_error($conn));
        $_SESSION['error'] = "Error checking booking: " . mysqli_error($conn);
        header("Location: dashboard.php?tab=" . $redirect_tab);
        exit();
    }
    
    $booking = mysqli_fetch_assoc($result_check);
    
    if ($booking) {
        // Log status change
        error_log("Booking $booking_id: Changing status from '" . $booking['status_pembayaran'] . "' to '$status'");
        
        $query = "UPDATE $table_name SET status_pembayaran = '$status' WHERE $primary_key = '$booking_id'";
        
        if (mysqli_query($conn, $query)) {
            error_log("Successfully updated status");
            $_SESSION['success'] = "Status pembayaran berhasil diupdate.";
            
            // Send email notification
            if ($status == 'dikonfirmasi' || $status == 'dibatalkan') {
                require_once '../includes/mail.php';
                if (sendBookingNotification($conn, $booking_id, $status, $type)) {
                    error_log("Email notification sent successfully");
                } else {
                    error_log("Failed to send email notification");
                }
            }
            
            // Force the browser to reload the page
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
            
            // Redirect ke dashboard tab jika ada parameter redirect
            if (isset($_GET['redirect']) && strpos($_GET['redirect'], 'dashboard_') === 0) {
                $tab = str_replace('dashboard_', '', $_GET['redirect']);
                $redirect_url = "dashboard.php?tab=booking_" . $tab;
                error_log("Redirecting to: $redirect_url");
                header("Location: $redirect_url");
                exit();
            } else {
                // Redirect back with timestamp (default)
                $redirect_url = "../$invoice_page?booking_id=$booking_id&t=" . time();
                error_log("Redirecting to: $redirect_url");
                header("Location: $redirect_url");
                exit();
            }
        } else {
            $_SESSION['error'] = "Error updating record: " . mysqli_error($conn);
            header("Location: dashboard.php?tab=" . $redirect_tab);
            exit();
        }
    }
}

$_SESSION['error'] = "Invalid request.";
header("Location: dashboard.php");
exit();
?>
