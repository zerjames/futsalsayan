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

// Proses booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tanggal'])) {
        // Cek booking aktif user (status pending)
        if ($_SESSION['role'] != 'admin') {
            $cek_query = "
                (SELECT COUNT(*) as total FROM booking WHERE user_id = '$user_id' AND status_pembayaran = 'pending')
                UNION ALL
                (SELECT COUNT(*) as total FROM booking_eskul WHERE user_id = '$user_id' AND status_pembayaran = 'pending')
                UNION ALL
                (SELECT COUNT(*) as total FROM booking_turnamen WHERE user_id = '$user_id' AND status_pembayaran = 'pending')
            ";
            $cek_result = mysqli_query($conn, $cek_query);
            $total_pending = 0;
            while ($row = mysqli_fetch_assoc($cek_result)) {
                $total_pending += $row['total'];
            }
            if ($total_pending > 0) {
                $error = 'Anda masih memiliki booking yang belum dikonfirmasi. Silakan tunggu konfirmasi admin sebelum melakukan booking lagi.';
            }
        }

        $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);

        // Jika form disubmit untuk booking (bukan untuk melihat jadwal)
        if (
            !isset($error) && isset($_POST['jam_mulai']) && isset($_POST['jam_selesai']) && 
            isset($_POST['metode_pembayaran'])
        ) {
            
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

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-4 sm:p-6 m-2 sm:m-0">
    <!-- Tombol Kembali ke Beranda -->
    <a href="index.php" class="inline-flex items-center bg-gray-200 text-green-700 px-4 py-2 rounded font-semibold hover:bg-gray-300 mb-4">
        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
    </a>
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-4 sm:p-6 my-4">
        <h2 class="text-2xl font-bold text-center text-green-600 mb-2">Booking Lapangan <?php echo htmlspecialchars($lapangan['nama']); ?></h2>
        <div class="text-center mb-6">
            <!-- Link ke lihat_jadwal.php dihapus sesuai permintaan -->
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4" id="bookingForm">
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="selected_user_id">
                        Pilih User
                    </label>
                    <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="selected_user_id" 
                            name="selected_user_id" 
                            required>
                        <option value="">Pilih Admin/User</option>
                        <option value="<?php echo $_SESSION['user_id']; ?>">Admin (<?php echo $_SESSION['username']; ?>)</option>
                        <optgroup label="Users">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id_user']; ?>">
                                    <?php echo $user['nama'] . ' (' . $user['username'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="tanggal">
                    Tanggal Main
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="tanggal" 
                       type="date" 
                       name="tanggal" 
                       value="<?php echo isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d'); ?>"
                       min="<?php echo date('Y-m-d'); ?>"
                       max="<?php echo date('Y-m-d', strtotime('+21 days')); ?>"
                       required>
            </div>

            <!-- Jadwal Booking yang Ada -->
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-semibold mb-3">Jadwal Booking yang Ada:</h3>
                <div class="space-y-2" id="jadwalContainer">
                    <!-- Jadwal booking akan dimuat di sini oleh JavaScript -->
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="jam_mulai">
                        Jam Mulai
                    </label>
                    <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
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
                    <p class="text-sm text-gray-500 mt-1">Buka: 08:00 - 23:00</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="jam_selesai">
                        Jam Selesai
                    </label>
                    <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           id="jam_selesai" 
                           name="jam_selesai" 
                           required>
                        <option value="">Pilih Jam Selesai</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Tutup: 23:00</p>
                </div>
            </div>

            <?php if ($_SESSION['role'] != 'admin'): ?>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Metode Pembayaran
                    </label>
                    <div class="space-y-3">
                        <label class="flex items-center p-3 border rounded hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="metode_pembayaran" value="transfer" required class="mr-3"> 
                            <span><i class="fas fa-university mr-2"></i>Transfer Bank</span>
                        </label>
                        <label class="flex items-center p-3 border rounded hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="metode_pembayaran" value="qris" required class="mr-3"> 
                            <span><i class="fas fa-qrcode mr-2"></i>QRIS (OVO, GoPay, Dana, dll)</span>
                        </label>
                        <label class="flex items-center p-3 border rounded hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="metode_pembayaran" value="cod" required class="mr-3"> 
                            <span><i class="fas fa-money-bill-wave mr-2"></i>Bayar di Tempat (COD)</span>
                        </label>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="metode_pembayaran" value="cod">
            <?php endif; ?>

            <!-- Panduan Booking -->
            <div class="bg-blue-50 p-4 rounded-lg mb-4">
                <h4 class="font-semibold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Panduan Booking:
                </h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Minimal durasi booking 1 jam</li>
                    <li>• Jam operasional: 08:00 - 23:00</li>
                    <li>• Booking dapat dilakukan maksimal 7 hari ke depan</li>
                    <li>• Upload bukti pembayaran setelah invoice keluar</li>
                    <li>• Untuk pembayaran COD, harap datang 30 menit sebelum jadwal</li>
                </ul>
            </div>

            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full text-center text-lg"
                    type="submit">
                Booking Sekarang
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
