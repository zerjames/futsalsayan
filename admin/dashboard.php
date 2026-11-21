<?php
// Include security, database dan functions di awal
require_once '../includes/security.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

autoCancelUnpaidBookings($conn);

// Get selected month and year with strict validation
$selected_month = isset($_GET['bulan']) ? filter_var($_GET['bulan'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) : false;
if ($selected_month === false) {
    $selected_month = (int)date('n');
}

$selected_year = isset($_GET['tahun']) ? filter_var($_GET['tahun'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) : false;
if ($selected_year === false) {
    $selected_year = (int)date('Y');
}

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

<!-- Set page title and CSRF token -->
<script>
    document.title = "Dashboard Admin";
    window.csrfToken = "<?php echo csrf_token(); ?>";
</script>

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

<div class="w-full">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Dashboard Admin</h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola booking, lapangan, dan pengguna Futsal Sayan</p>
        </div>
        <?php include 'includes/nav_buttons.php'; ?>

        <!-- Notification area -->
        <div id="bookingNotification" class="mb-5 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm hidden">
            <strong>Notifikasi:</strong> <span id="notifTitle">Ada booking baru!</span>
            <ul id="bookingNotificationList" class="list-disc list-inside mt-2"></ul>
        </div>
        <button id="enableSoundBtn" class="mb-3 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg" style="display:none;">Aktifkan Suara Notifikasi</button>

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

    <!-- Modal Konfirmasi Aksi Admin (Batalkan / Hapus) -->
    <div id="adminConfirmModal" class="fixed inset-0 bg-stone-900/50 hidden z-50 p-0 sm:p-4">
        <div class="min-h-full flex items-end sm:items-center justify-center">
            <div class="w-full sm:max-w-md bg-white border-t sm:border border-stone-200 rounded-t-2xl sm:rounded-lg shadow-xl p-6 text-center">
                <div class="w-10 h-1 bg-stone-300 rounded-full mx-auto mb-4 sm:hidden"></div>
                <img src="../assets/images/cancel.svg" alt="Konfirmasi" class="w-36 sm:w-40 h-auto mx-auto mb-4">
                <h3 id="adminConfirmTitle" class="text-lg font-bold text-stone-900">Apakah Anda yakin?</h3>
                <p id="adminConfirmMessage" class="text-xs sm:text-sm text-stone-500 mt-2 leading-relaxed"></p>
                <div class="mt-6 grid grid-cols-2 gap-2.5">
                    <button type="button" onclick="closeAdminConfirmModal()"
                            class="py-2.5 px-4 rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 text-sm font-medium transition-colors">
                        Tidak, Kembali
                    </button>
                    <button type="button" id="adminConfirmOkBtn"
                            class="py-2.5 px-4 rounded-md bg-red-700 hover:bg-red-800 text-white text-sm font-semibold transition-colors">
                        Ya, Lanjutkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var pendingAction = null;
        var modal = document.getElementById('adminConfirmModal');
        var titleEl = document.getElementById('adminConfirmTitle');
        var msgEl = document.getElementById('adminConfirmMessage');
        var okBtn = document.getElementById('adminConfirmOkBtn');

        function showAdminLoader() {
            var overlay = document.getElementById('fsLoaderOverlay');
            var bar = document.getElementById('fsLoaderBar');
            var label = document.getElementById('fsLoaderText');
            if (label) label.textContent = 'Memproses...';
            if (bar) bar.classList.add('is-active');
            if (overlay) overlay.classList.add('is-active');
        }

        window.openAdminConfirmLink = function(event, url, title, message, btnText) {
            if (event) event.preventDefault();
            titleEl.textContent = title || 'Apakah Anda yakin?';
            msgEl.textContent = message || 'Tindakan ini tidak dapat dibatalkan.';
            okBtn.textContent = btnText || 'Ya, Lanjutkan';
            pendingAction = function() {
                showAdminLoader();
                window.location.href = url;
            };
            modal.classList.remove('hidden');
            return false;
        };

        window.openAdminConfirmForm = function(event, formEl, title, message, btnText) {
            if (event) event.preventDefault();
            titleEl.textContent = title || 'Apakah Anda yakin?';
            msgEl.textContent = message || 'Tindakan ini tidak dapat dibatalkan.';
            okBtn.textContent = btnText || 'Ya, Hapus';
            pendingAction = function() {
                showAdminLoader();
                formEl.submit();
            };
            modal.classList.remove('hidden');
            return false;
        };

        window.closeAdminConfirmModal = function() {
            modal.classList.add('hidden');
            pendingAction = null;
        };

        okBtn.addEventListener('click', function() {
            var action = pendingAction;
            closeAdminConfirmModal();
            if (typeof action === 'function') action();
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal || e.target === modal.firstElementChild) {
                closeAdminConfirmModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAdminConfirmModal();
        });

        // Loading indicator saat pindah tab atau submit form di admin
        document.addEventListener('submit', function(e) {
            if (!e.defaultPrevented) showAdminLoader();
        });
        window.addEventListener('pageshow', function() {
            var overlay = document.getElementById('fsLoaderOverlay');
            var bar = document.getElementById('fsLoaderBar');
            if (overlay) overlay.classList.remove('is-active');
            if (bar) bar.classList.remove('is-active');
        });
    })();
    </script>

    <audio id="notifSound" src="../assets/sounds/notif.mp3" preload="auto"></audio>
</main>
</body>
</html>
