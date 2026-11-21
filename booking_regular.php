<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get list of users if admin
$users = [];
if ($_SESSION['role'] == 'admin') {
    $query_users = "SELECT id_user, nama, username FROM users WHERE role = 'user'";
    $result_users = mysqli_query($conn, $query_users);
    while ($user = mysqli_fetch_assoc($result_users)) {
        $users[] = $user;
    }
}

// Jalankan auto-cancel sebelum cek jadwal / proses booking agar slot kadaluarsa langsung kosong
autoCancelUnpaidBookings($conn);

// Cek apakah ada parameter lapangan
if (!isset($_GET['lapangan'])) {
    header("Location: index.php");
    exit();
}

$lapangan_id = mysqli_real_escape_string($conn, $_GET['lapangan']);

// Ambil data lapangan
$query = "SELECT * FROM lapangan WHERE id_lapangan = '$lapangan_id' AND status = 'tersedia'";
$result = mysqli_query($conn, $query);
$lapangan = mysqli_fetch_assoc($result);

if (!$lapangan) {
    header("Location: index.php");
    exit();
}

// Set user_id based on role and selection
$user_id = $_SESSION['user_id'];
if ($_SESSION['role'] == 'admin' && isset($_POST['selected_user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['selected_user_id']);
}

// Cek apakah user (non-admin) masih memiliki booking aktif (maksimal 1 booking aktif per akun)
$active_booking = ($_SESSION['role'] != 'admin') ? getUserActiveBooking($conn, $user_id) : null;

// Proses booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tanggal'])) {
        // Cek batas maksimal 1 booking aktif per akun (non-admin)
        if ($_SESSION['role'] != 'admin' && $active_booking) {
            $error = 'Setiap akun hanya diperbolehkan memiliki 1 booking aktif. Anda masih memiliki booking aktif (' . $active_booking['nama_lapangan'] . ' - ' . date('d/m/Y', strtotime($active_booking['tanggal'])) . ' ' . date('H:i', strtotime($active_booking['jam_mulai'])) . ' WIB). Selesaikan atau batalkan booking tersebut terlebih dahulu.';
        }

        $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);

        // Jika form disubmit untuk booking (bukan untuk melihat jadwal)
        if (
            !isset($error) && isset($_POST['jam_mulai']) && isset($_POST['jam_selesai']) && 
            isset($_POST['metode_pembayaran'])
        ) {
            if (!validate_csrf_request()) {
                $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
            } else {
            $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
            $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
            // Jika admin yang booking, set metode pembayaran COD dan status langsung dikonfirmasi
            if ($_SESSION['role'] == 'admin') {
                $metode_pembayaran = 'cod';
                $status_pembayaran = 'dikonfirmasi';
            } else {
                $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);
                $status_pembayaran = 'pending';
            }
            
            // Hitung durasi dan total harga
            $start = strtotime($jam_mulai);
            $end = strtotime($jam_selesai);
            $durasi = ceil(($end - $start) / 3600); // dalam jam
            
            // Ambil harga lapangan
            $total_harga = $durasi * $lapangan['harga_per_jam'];
            
            // Cek apakah hari Minggu
            $is_sunday = (date('N', strtotime($tanggal)) == 7);
            
            // Terapkan diskon
            $diskon = 0;
            $alasan_diskon = [];
            
            // Diskon hari Minggu (5%)
            if ($is_sunday) {
                $diskon += 5;
                $alasan_diskon[] = "Diskon hari Minggu (5%)";
            }
            
            // Diskon durasi 2 jam atau lebih (5%) hanya untuk non-admin
            if ($durasi >= 2 && $_SESSION['role'] != 'admin') {
                $diskon += 5;
                $alasan_diskon[] = "Diskon booking 2 jam atau lebih (5%)";
            }
            
            // Hitung total setelah diskon
            if ($diskon > 0) {
                $total_diskon = ($total_harga * $diskon) / 100;
                $total_harga = $total_harga - $total_diskon;
            }
            
            // Cek konflik booking di semua jenis booking
            $konflik = cekKonflikBooking($conn, $lapangan_id, $tanggal, $jam_mulai, $jam_selesai);
            
            if ($konflik['ada_konflik']) {
                $error = $konflik['pesan'];
            } else {
                // Simpan booking
                $query = "INSERT INTO booking (user_id, lapangan_id, tanggal_main, jam_mulai, jam_selesai, 
                                             total_harga, diskon_persen, alasan_diskon, metode_pembayaran,
                                             status_pembayaran, tanggal_booking) 
                           VALUES ('$user_id', '$lapangan_id', '$tanggal', '$jam_mulai', '$jam_selesai', 
                                 '$total_harga', '$diskon', " . ($alasan_diskon ? "'" . implode(", ", $alasan_diskon) . "'" : "NULL") . ", 
                                 '$metode_pembayaran', '$status_pembayaran', NOW())";

                if (mysqli_query($conn, $query)) {
                    $booking_id = mysqli_insert_id($conn);

                    header("Location: invoice.php?booking_id=" . $booking_id);
                    exit();
                } else {
                    $error = "Terjadi kesalahan. Silakan coba lagi.";
                }
            }
            }
        }
    }
}

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors mb-5">
        &larr; Kembali ke Beranda
    </a>

    <div class="bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
        <div class="pb-5 border-b border-stone-200 mb-6 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-green-800">Booking Regular</p>
                <h2 class="text-xl sm:text-2xl font-bold text-stone-900 mt-0.5"><?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <p class="text-sm font-bold text-stone-900 tabular-nums">
                Rp <?php echo number_format($lapangan['harga_per_jam'], 0, ',', '.'); ?><span class="text-xs font-normal text-stone-500">/jam</span>
            </p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-4 py-3 text-sm mb-5">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($active_booking && !isset($error)): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-md p-4 text-sm mb-5">
                <p class="font-bold">Batas 1 Booking Aktif per Akun</p>
                <p class="text-xs sm:text-sm mt-1">
                    Anda masih memiliki booking aktif di <strong><?php echo htmlspecialchars($active_booking['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    (<?php echo htmlspecialchars($active_booking['jenis'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo date('d/m/Y', strtotime($active_booking['tanggal'])); ?>).
                    Selesaikan jadwal atau batalkan booking tersebut sebelum memesan lagi.
                </p>
                <a href="riwayat.php" class="inline-block mt-3 px-3.5 py-1.5 rounded-md bg-stone-900 hover:bg-green-800 text-white text-xs font-semibold transition-colors">
                    Lihat Riwayat Booking
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5" id="bookingForm">
            <?php echo csrf_field(); ?>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="selected_user_id">
                        Pilih User
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="selected_user_id" 
                            name="selected_user_id" 
                            required>
                        <option value="">Pilih Admin/User</option>
                        <option value="<?php echo $_SESSION['user_id']; ?>">Admin (<?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>)</option>
                        <optgroup label="Users">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id_user']; ?>">
                                    <?php echo htmlspecialchars($user['nama'] . ' (' . $user['username'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="tanggal">
                    Tanggal Main
                </label>
                <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                       id="tanggal" 
                       type="date" 
                       name="tanggal" 
                       value="<?php echo isset($_POST['tanggal']) ? htmlspecialchars($_POST['tanggal'], ENT_QUOTES, 'UTF-8') : date('Y-m-d'); ?>"
                       min="<?php echo date('Y-m-d'); ?>"
                       max="<?php echo date('Y-m-d', strtotime('+21 days')); ?>"
                       required>
            </div>

            <!-- Jadwal Booking yang Ada -->
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2.5">Jadwal Terisi pada Tanggal Ini</h3>
                <div class="space-y-2 text-sm" id="jadwalContainer">
                    <!-- Jadwal booking akan dimuat di sini oleh JavaScript -->
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="jam_mulai">
                        Jam Mulai
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="jam_mulai" 
                            name="jam_mulai" 
                            required>
                        <option value="">Pilih Jam Mulai</option>
                        <?php
                        for($i = 8; $i <= 23; $i++) {
                            $displayHour = $i;
                            if ($i === 24) $displayHour = 0;
                            $jam = str_pad($displayHour, 2, "0", STR_PAD_LEFT) . ":00";
                            echo "<option value='$jam'>$jam</option>";
                        }
                        ?>
                    </select>
                    <p class="text-xs text-stone-400 mt-1">Buka: 08:00 – 23:00 WIB</p>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="jam_selesai">
                        Jam Selesai
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                           id="jam_selesai" 
                           name="jam_selesai" 
                           required>
                        <option value="">Pilih Jam Selesai</option>
                    </select>
                    <p class="text-xs text-stone-400 mt-1">Tutup: 23:00 WIB</p>
                </div>
            </div>

            <?php if ($_SESSION['role'] != 'admin'): ?>
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-2">
                        Metode Pembayaran
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center p-3.5 border border-stone-200 rounded-md hover:border-stone-400 active:bg-stone-50 cursor-pointer text-sm min-h-[48px]">
                            <input type="radio" name="metode_pembayaran" value="transfer" required class="mr-3 w-4 h-4 accent-green-800"> 
                            <span class="text-stone-800 font-medium">Transfer Bank (SeaBank)</span>
                        </label>
                        <label class="flex items-center p-3.5 border border-stone-200 rounded-md hover:border-stone-400 active:bg-stone-50 cursor-pointer text-sm min-h-[48px]">
                            <input type="radio" name="metode_pembayaran" value="qris" required class="mr-3 w-4 h-4 accent-green-800"> 
                            <span class="text-stone-800 font-medium">QRIS (OVO, GoPay, DANA, ShopeePay)</span>
                        </label>
                        <label class="flex items-center p-3.5 border border-stone-200 rounded-md hover:border-stone-400 active:bg-stone-50 cursor-pointer text-sm min-h-[48px]">
                            <input type="radio" name="metode_pembayaran" value="cod" required class="mr-3 w-4 h-4 accent-green-800"> 
                            <span class="text-stone-800 font-medium">Bayar di Tempat (COD)</span>
                        </label>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="metode_pembayaran" value="cod">
            <?php endif; ?>

            <!-- Ketentuan Booking -->
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md text-xs text-stone-600 space-y-1">
                <p class="font-semibold text-stone-800 mb-1.5">Ketentuan Booking</p>
                <p>&bull; Minimal durasi booking 1 jam (jam operasional 08:00 – 23:00 WIB).</p>
                <p>&bull; Diskon 5% untuk hari Minggu dan diskon 5% untuk durasi 2 jam atau lebih.</p>
                <p>&bull; <strong class="text-stone-800">Booking mendadak (hari yang sama, &le; 3 jam sebelum main):</strong> batas pembayaran &amp; konfirmasi admin hanya <strong>15 menit</strong>, lewat dari itu otomatis dibatalkan.</p>
                <p>&bull; Booking normal: batas pembayaran &amp; konfirmasi admin 2 jam. Untuk COD harap datang 20 menit sebelum jadwal.</p>
            </div>

            <button class="w-full <?php echo $active_booking ? 'bg-stone-300 text-stone-500 cursor-not-allowed' : 'bg-stone-900 hover:bg-green-800 text-white'; ?> font-semibold py-2.5 px-4 rounded-md transition-colors text-sm"
                    type="submit" <?php echo $active_booking ? 'disabled' : ''; ?>>
                <?php echo $active_booking ? 'Selesaikan Booking Aktif Terlebih Dahulu' : 'Lanjutkan Booking'; ?>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jamMulaiSelect = document.getElementById('jam_mulai');
    const jamSelesaiSelect = document.getElementById('jam_selesai');
    const tanggalInput = document.getElementById('tanggal');
    
    function updateJamMulaiOptions() {
        const selectedDate = new Date(tanggalInput.value);
        const today = new Date();
        const currentHour = today.getHours();
        
        // Reset jam mulai options
        jamMulaiSelect.innerHTML = '<option value="">Pilih Jam Mulai</option>';
        
        // Jika tanggal yang dipilih adalah hari ini
        if (selectedDate.toDateString() === today.toDateString()) {
            // Tambahkan opsi mulai dari jam berikutnya
            for(let i = 8; i <= 23; i++) {
                if (i > currentHour) {  // Hanya tampilkan jam yang belum lewat
                    const jam = String(i).padStart(2, '0') + ":00";
                    const option = document.createElement('option');
                    option.value = jam;
                    option.textContent = jam;
                    jamMulaiSelect.appendChild(option);
                }
            }
        } else {
            // Untuk tanggal selain hari ini, tampilkan semua opsi jam
            for(let i = 8; i <= 23; i++) {
                const jam = String(i).padStart(2, '0') + ":00";
                const option = document.createElement('option');
                option.value = jam;
                option.textContent = jam;
                jamMulaiSelect.appendChild(option);
            }
        }
        
        // Reset jam selesai
        jamSelesaiSelect.innerHTML = '<option value="">Pilih Jam Selesai</option>';
    }
    
    // Update jam mulai options when tanggal changes
    tanggalInput.addEventListener('change', updateJamMulaiOptions);
    
    // Update jam selesai options when jam mulai changes
    jamMulaiSelect.addEventListener('change', function() {
        if (!this.value) {
            jamSelesaiSelect.innerHTML = '<option value="">Pilih Jam Selesai</option>';
            return;
        }
        
        const selectedHour = parseInt(this.value.split(':')[0]);
        
        // Clear existing options
        jamSelesaiSelect.innerHTML = '<option value="">Pilih Jam Selesai</option>';
        
        // Add new options starting from selected hour + 1
        for(let i = selectedHour + 1; i <= 23; i++) {
            const jam = String(i).padStart(2, '0') + ":00";
            const option = document.createElement('option');
            option.value = jam;
            option.textContent = jam;
            jamSelesaiSelect.appendChild(option);
        }
    });

    // Batasi tanggal booking maksimal 21 hari ke depan
    const today = new Date();
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 21);

    tanggalInput.min = today.toISOString().split('T')[0];
    tanggalInput.max = maxDate.toISOString().split('T')[0];

    // Initial update of jam mulai options
    updateJamMulaiOptions();

    // SCRIPT AJAX JADWAL BOOKING
    function loadJadwal() {
        var tanggal = document.getElementById('tanggal').value;
        var lapangan = "<?php echo $lapangan_id; ?>";
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_jadwal_booking.php?tanggal=' + tanggal + '&lapangan=' + lapangan, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                document.getElementById('jadwalContainer').innerHTML = xhr.responseText;
            } else {
                document.getElementById('jadwalContainer').innerHTML = '<p class="text-red-500">Gagal memuat jadwal.</p>';
            }
        };
        xhr.send();
    }
    document.getElementById('tanggal').addEventListener('change', loadJadwal);
    // Load jadwal saat halaman pertama kali dibuka
    loadJadwal();

    // HAPUS/COMMENT AUTO-SUBMIT FORM SAAT TANGGAL BERUBAH
    // (Cari dan hapus/komentar baris yang auto-submit form saat tanggal berubah)
});
</script>

<?php include 'includes/footer.php'; ?>
