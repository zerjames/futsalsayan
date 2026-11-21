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

// Ambil daftar user
$query_users = "SELECT id, nama, username FROM users WHERE role = 'user'";
$users_result = mysqli_query($conn, $query_users);

// Proses booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.";
    } else {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $lapangan_id = mysqli_real_escape_string($conn, $_POST['lapangan_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
    $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
    $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);
    $nama_pembayar = mysqli_real_escape_string($conn, $_POST['nama_pembayar']);
    
    // Hitung durasi dan total harga
    $start = strtotime($jam_mulai);
    $end = strtotime($jam_selesai);
    $durasi = ceil(($end - $start) / 3600); // dalam jam
    
    // Ambil harga lapangan
    $query = "SELECT harga_per_jam FROM lapangan WHERE id = '$lapangan_id'";
    $result = mysqli_query($conn, $query);
    $lapangan = mysqli_fetch_assoc($result);
    
    // Hitung total harga dasar
    $total_harga = $durasi * $lapangan['harga_per_jam'];
    
    // Tambah 10% untuk booking melalui admin
    $admin_charge = ($total_harga * 10) / 100;
    $total_harga = $total_harga + $admin_charge;
    
    // Set diskon ke 0 dan tambahkan keterangan biaya admin dan non member
    $diskon = -10; // Negative to indicate a surcharge
    $alasan_diskon = ["Harga non member (+10%)"];
    
    // Cek ketersediaan lapangan
    $check_query = "SELECT * FROM booking WHERE 
                    lapangan_id = '$lapangan_id' AND 
                    tanggal_main = '$tanggal' AND 
                    ((jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai') OR
                    (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai') OR
                    (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai'))";
    
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Maaf, lapangan sudah dibooking untuk waktu tersebut.";
    } else {
        // Simpan booking dengan status dikonfirmasi
        $alasan_diskon_str = $diskon > 0 ? implode(", ", $alasan_diskon) : null;
        
        $query = "INSERT INTO booking (user_id, lapangan_id, tanggal_main, jam_mulai, jam_selesai, 
                                     total_harga, diskon_persen, alasan_diskon, metode_pembayaran, 
                                     nama_pembayar, status_pembayaran, tanggal_booking) 
                  VALUES ('$user_id', '$lapangan_id', '$tanggal', '$jam_mulai', '$jam_selesai', 
                         '$total_harga', '$diskon', " . ($alasan_diskon_str ? "'$alasan_diskon_str'" : "NULL") . ", 
                         '$metode_pembayaran', '$nama_pembayar', 'dikonfirmasi', NOW())";
        
        if (mysqli_query($conn, $query)) {
            $booking_id = mysqli_insert_id($conn);
            $_SESSION['success'] = "Booking berhasil dibuat untuk user.";
            header("Location: dashboard.php?tab=booking_regular");
            exit();
        } else {
            $error = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
    }
}

// Ambil semua data lapangan
$query = "SELECT * FROM lapangan WHERE status = 'tersedia'";
$lapangan_result = mysqli_query($conn, $query);

// Include header setelah semua logic PHP dan redirect selesai
require_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Booking untuk User</h2>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <?php echo csrf_field(); ?>
            <!-- Pilih User -->
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="user_id">
                    Pilih User
                </label>
                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="user_id" 
                        name="user_id" 
                        required>
                    <option value="">Pilih User</option>
                    <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo $user['nama'] . ' (' . $user['username'] . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="lapangan_id">
                    Pilih Lapangan
                </label>
                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="lapangan_id" 
                        name="lapangan_id" 
                        required>
                    <option value="">Pilih Lapangan</option>
                    <?php while($lapangan = mysqli_fetch_assoc($lapangan_result)): ?>
                        <option value="<?php echo $lapangan['id']; ?>">
                            <?php echo $lapangan['nama']; ?> - Rp <?php echo number_format($lapangan['harga_per_jam'], 0, ',', '.'); ?>/jam
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="tanggal">
                    Tanggal Main
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="tanggal" 
                       type="date" 
                       name="tanggal" 
                       min="<?php echo date('Y-m-d'); ?>"
                       required>
            </div>

            <div class="grid grid-cols-2 gap-4">
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
                        for($i = 8; $i <= 22; $i++) {
                            $jam = str_pad($i, 2, "0", STR_PAD_LEFT) . ":00";
                            echo "<option value='$jam'>$jam</option>";
                        }
                        ?>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Buka: 08:00 - 22:00</p>
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

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Metode Pembayaran
                </label>
                <div class="space-y-2">
                    <label class="block">
                        <input type="radio" name="metode_pembayaran" value="transfer" required> 
                        <i class="fas fa-university mr-2"></i>Transfer Bank
                    </label>
                    <label class="block">
                        <input type="radio" name="metode_pembayaran" value="qris" required> 
                        <i class="fas fa-qrcode mr-2"></i>QRIS (OVO, GoPay, Dana, dll)
                    </label>
                    <label class="block">
                        <input type="radio" name="metode_pembayaran" value="cod" required> 
                        <i class="fas fa-money-bill-wave mr-2"></i>Bayar di Tempat (COD)
                    </label>
                </div>
            </div>

            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                    type="submit">
                Buat Booking
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jamMulaiSelect = document.getElementById('jam_mulai');
    const jamSelesaiSelect = document.getElementById('jam_selesai');
    
    // Update jam selesai options when jam mulai changes
    jamMulaiSelect.addEventListener('change', function() {
        const selectedHour = parseInt(this.value.split(':')[0]);
        
        // Clear existing options
        jamSelesaiSelect.innerHTML = '<option value="">Pilih Jam Selesai</option>';
        
        // Add new options starting from selected hour + 1
        for(let i = selectedHour + 1; i <= 23; i++) {
            const jam = String(i).padStart(2, '0') + ':00';
            const option = document.createElement('option');
            option.value = jam;
            option.textContent = jam;
            jamSelesaiSelect.appendChild(option);
        }
        
        // Reset jam selesai selection
        jamSelesaiSelect.value = '';
    });
});
</script>
