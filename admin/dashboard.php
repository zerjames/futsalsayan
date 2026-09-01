<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session harus di paling atas sebelum output apapun
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database dan functions di awal
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

autoCancelUnpaidBookings($conn);

// Get selected month and year
$selected_month = isset($_GET['bulan']) ? $_GET['bulan'] : date('n');
$selected_year = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$query_stats = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_user,
    (SELECT COUNT(*) FROM booking) + (SELECT COUNT(*) FROM booking_eskul) + (SELECT COUNT(*) FROM booking_turnamen) as total_booking,
    (SELECT COUNT(*) FROM booking WHERE status_pembayaran = 'pending') + 
    (SELECT COUNT(*) FROM booking_eskul WHERE status_pembayaran = 'pending') + 
    (SELECT COUNT(*) FROM booking_turnamen WHERE status_pembayaran = 'pending') as total_pending,
    (SELECT COUNT(*) FROM booking WHERE status_pembayaran = 'dikonfirmasi') + 
    (SELECT COUNT(*) FROM booking_eskul WHERE status_pembayaran = 'dikonfirmasi') + 
    (SELECT COUNT(*) FROM booking_turnamen WHERE status_pembayaran = 'dikonfirmasi') as total_konfirmasi,
    (SELECT COUNT(*) FROM booking WHERE status_pembayaran = 'dibatalkan') + 
    (SELECT COUNT(*) FROM booking_eskul WHERE status_pembayaran = 'dibatalkan') + 
    (SELECT COUNT(*) FROM booking_turnamen WHERE status_pembayaran = 'dibatalkan') as total_dibatalkan,
    (SELECT COALESCE(SUM(total_harga), 0) FROM booking WHERE status_pembayaran = 'dikonfirmasi') + 
    (SELECT COALESCE(SUM(total_harga), 0) FROM booking_eskul WHERE status_pembayaran = 'dikonfirmasi') + 
    (SELECT COALESCE(SUM(total_harga), 0) FROM booking_turnamen WHERE status_pembayaran = 'dikonfirmasi') as total_pendapatan,
    (SELECT COALESCE(SUM(total_harga), 0) FROM booking WHERE status_pembayaran = 'dikonfirmasi' AND MONTH(tanggal_main) = $selected_month AND YEAR(tanggal_main) = $selected_year) + 
    (SELECT COALESCE(SUM(total_harga), 0) FROM booking_eskul WHERE status_pembayaran = 'dikonfirmasi' AND MONTH(tanggal_mulai) = $selected_month AND YEAR(tanggal_mulai) = $selected_year) + 
    (SELECT COALESCE(SUM(total_harga), 0) FROM booking_turnamen WHERE status_pembayaran = 'dikonfirmasi' AND MONTH(tanggal_mulai) = $selected_month AND YEAR(tanggal_mulai) = $selected_year) as total_pendapatan_bulan,
    (SELECT COUNT(*) FROM booking WHERE MONTH(tanggal_main) = $selected_month AND YEAR(tanggal_main) = $selected_year) as total_booking_regular,
    (SELECT COUNT(*) FROM booking_eskul WHERE MONTH(tanggal_mulai) = $selected_month AND YEAR(tanggal_mulai) = $selected_year) as total_booking_eskul,
    (SELECT COUNT(*) FROM booking_turnamen WHERE MONTH(tanggal_mulai) = $selected_month AND YEAR(tanggal_mulai) = $selected_year) as total_booking_turnamen";

$result_stats = mysqli_query($conn, $query_stats);
$stats = mysqli_fetch_assoc($result_stats);

// Debug: Cek nilai individual untuk total pendapatan
$debug_regular = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking WHERE status_pembayaran = 'dikonfirmasi'"));
$debug_eskul = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking_eskul WHERE status_pembayaran = 'dikonfirmasi'"));
$debug_turnamen = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking_turnamen WHERE status_pembayaran = 'dikonfirmasi'"));

// Jika total_pendapatan masih 0, gunakan nilai debug
if (empty($stats['total_pendapatan']) || $stats['total_pendapatan'] == 0) {
    $stats['total_pendapatan'] = ($debug_regular['total'] ?? 0) + ($debug_eskul['total'] ?? 0) + ($debug_turnamen['total'] ?? 0);
}

// Include header setelah semua logic PHP dan redirect selesai
require_once '../includes/header.php';
?>

<!-- Set page title -->
<script>document.title = "Dashboard Admin";</script>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }
    .modal-content {
        position: relative;
        margin: auto;
        padding: 20px;
        max-width: 90%;
        max-height: 90%;
        overflow: auto;
    }
    .modal img {
        max-width: 100%;
        max-height: 80vh;
        margin: auto;
        display: block;
    }
    .close {
        position: absolute;
        right: 25px;
        top: 10px;
        color: #fff;
        font-size: 35px;
        font-weight: bold;
        cursor: pointer;
    }
</style>

<div class="container mx-auto px-4 py-8">
        <?php include 'includes/nav_buttons.php'; ?>

        <!-- Notification area -->
        <div id="bookingNotification" class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded hidden">
            <strong>Notifikasi:</strong> <span id="notifTitle">Ada booking baru!</span>
            <ul id="bookingNotificationList" class="list-disc list-inside mt-2"></ul>
        </div>
        <button id="enableSoundBtn" class="mb-2 px-3 py-1 bg-green-200 text-green-800 rounded" style="display:none;">Aktifkan Suara Notifikasi</button>

        <!-- Include Stats Cards -->
        <?php include 'includes/stats_cards.php'; ?>

        <!-- Content based on tab -->
        <?php
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'booking_regular';
// Removed search variable and search form

        switch ($tab) {
            case 'booking_regular':
                include 'includes/get_regular_bookings.php';
                include 'includes/booking_regular_table.php';
                break;
            case 'booking_eskul':
                include 'includes/get_eskul_bookings.php';
                // include 'includes/booking_eskul_table.php'; // Dihandle oleh AJAX agar tidak dobel
                break;
            case 'booking_turnamen':
                include 'includes/get_turnamen_bookings.php';
                include 'includes/booking_turnamen_table.php';
                break;
            case 'kelola_user':
                include 'includes/user_table.php';
                break;
            case 'kelola_lapangan':
                include 'includes/lapangan_table.php';
                break;
            default:
                include 'includes/get_regular_bookings.php';
                include 'includes/booking_regular_table.php';
                break;
        }
        ?>
    </div>

    <script src="js/lapangan_modal.js"></script>

    <script>
    (function() {
        let lastBookingId = 0;
        let lastCancelledId = 0;
        let notifSoundEnabled = true; // AUTO ON

        // Sembunyikan tombol enable sound
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('enableSoundBtn');
            if (btn) btn.style.display = 'none';
        });

        // Hapus event listener enable sound
        // function enableNotifSound() { ... }
        // document.addEventListener('click', ...);
        // document.getElementById('enableSoundBtn').addEventListener('click', ...);

        function getActiveTab() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('tab') || 'booking_regular';
        }

        function reloadBookingTable() {
            if (getActiveTab() !== 'booking_regular') return;
            fetch('includes/booking_regular_table.php')
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const newTable = tempDiv.querySelector('#bookingTableContainer');
                    console.log('newTable (regular):', newTable);
                    if (newTable && document.getElementById('bookingTableContainer')) {
                        document.getElementById('bookingTableContainer').innerHTML = newTable.innerHTML;
                    } else {
                        console.log('bookingTableContainer not found in AJAX response');
                    }
                });
        }
        function reloadBookingEskulTable() {
            if (getActiveTab() !== 'booking_eskul') return;
            fetch('includes/booking_eskul_table.php')
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const newTable = tempDiv.querySelector('#bookingEskulTableContainer');
                    console.log('newTable (eskul):', newTable);
                    if (newTable && document.getElementById('bookingEskulTableContainer')) {
                        document.getElementById('bookingEskulTableContainer').innerHTML = newTable.innerHTML;
                    } else {
                        console.log('bookingEskulTableContainer not found in AJAX response');
                    }
                });
        }
        function reloadBookingTurnamenTable() {
            if (getActiveTab() !== 'booking_turnamen') return;
            fetch('includes/booking_turnamen_table.php')
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const newTable = tempDiv.querySelector('#bookingTurnamenTableContainer');
                    console.log('newTable (turnamen):', newTable);
                    if (newTable && document.getElementById('bookingTurnamenTableContainer')) {
                        document.getElementById('bookingTurnamenTableContainer').innerHTML = newTable.innerHTML;
                    } else {
                        console.log('bookingTurnamenTableContainer not found in AJAX response');
            }
        });
        }

        function pollNewBookings() {
            fetch('includes/get_new_bookings.php?last_booking_id=' + lastBookingId)
                .then(response => response.json())
                .then(data => {
                    if (data.new_bookings && data.new_bookings.length > 0) {
                        const notification = document.getElementById('bookingNotification');
                        const list = document.getElementById('bookingNotificationList');
                        const notifTitle = document.getElementById('notifTitle');
                        notifTitle.textContent = 'Ada booking baru!';
                        list.innerHTML = '';
                        data.new_bookings.forEach(booking => {
                            lastBookingId = Math.max(lastBookingId, booking.id);
                            let bulanMain = '';
                            if (booking.tanggal_jadwal) {
                                const tgl = new Date(booking.tanggal_jadwal);
                                const bulanArr = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                bulanMain = bulanArr[tgl.getMonth()] + ' ' + tgl.getFullYear();
                            }
                            const li = document.createElement('li');
                            li.textContent = `Booking baru: Jenis ${booking.jenis_booking || 'Regular'} di Lapangan ${booking.nama_lapangan}${bulanMain ? ' (Main: ' + bulanMain + ')' : ''}`;
                            list.appendChild(li);
                        });
                        notification.classList.remove('hidden');
                        notification.classList.remove('bg-red-100','border-red-400','text-red-700');
                        notification.classList.add('bg-yellow-100','border-yellow-400','text-yellow-700');
                        if (notifSoundEnabled) {
                            const notifSound = document.getElementById('notifSound');
                            if (notifSound) notifSound.play();
                        } else {
                            document.getElementById('enableSoundBtn').style.display = '';
                        }
                    }
                });
        }

        function pollAll() {
            pollNewBookings();
            reloadBookingTable();
            reloadBookingEskulTable();
            reloadBookingTurnamenTable();
        }
        setInterval(pollAll, 10000);
    })();
    </script>

    <!-- Modal for Payment Proof -->
    <div id="paymentProofModal" class="modal">
        <span class="close" onclick="closePaymentModal()">&times;</span>
        <div class="modal-content">
            <img id="paymentProofImage" src="" alt="Bukti Pembayaran">
        </div>
    </div>

    <script>
    function showPaymentProof(imagePath) {
        const modal = document.getElementById('paymentProofModal');
        const img = document.getElementById('paymentProofImage');
        img.src = imagePath;
        modal.style.display = "flex";
    }

    function closePaymentModal() {
        document.getElementById('paymentProofModal').style.display = "none";
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('paymentProofModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>

    <audio id="notifSound" src="../assets/sounds/notif.mp3" preload="auto"></audio>
