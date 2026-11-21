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

$query_harga_paket = "SELECT harga FROM harga_paket WHERE tipe = 'eskul_lap$lapangan_id' ORDER BY id_paket LIMIT 1";
$result_harga_paket = mysqli_query($conn, $query_harga_paket);
$harga_paket = mysqli_fetch_assoc($result_harga_paket);

// Debug: Log the query and result
error_log("Harga paket query: " . $query_harga_paket);
error_log("Harga paket result: " . print_r($harga_paket, true));

if (!$harga_paket) {
    $error = "Harga paket eskul untuk lapangan ini belum diatur. Silakan hubungi admin.";
}

$users = [];
if ($_SESSION['role'] == 'admin') {
    $query_users = "SELECT id_user, nama, username FROM users WHERE role = 'user'";
    $result_users = mysqli_query($conn, $query_users);
    while ($user = mysqli_fetch_assoc($result_users)) {
        $users[] = $user;
    }
}

// Set user_id based on role and selection
$user_id = $_SESSION['user_id'];
if ($_SESSION['role'] == 'admin' && isset($_POST['selected_user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['selected_user_id']);
}

require_once 'includes/mail.php';

// Cek batas maksimal 1 booking aktif per akun (non-admin)
$active_booking = ($_SESSION['role'] != 'admin') ? getUserActiveBooking($conn, $user_id) : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } elseif ($_SESSION['role'] != 'admin' && $active_booking) {
        $error = 'Setiap akun hanya diperbolehkan memiliki 1 booking aktif. Anda masih memiliki booking aktif (' . $active_booking['nama_lapangan'] . ' - ' . date('d/m/Y', strtotime($active_booking['tanggal'])) . '). Selesaikan atau batalkan booking tersebut terlebih dahulu.';
    } else {

    $nama_sekolah = mysqli_real_escape_string($conn, $_POST['nama_sekolah']);
    $nama_guru = mysqli_real_escape_string($conn, $_POST['nama_guru']);
    $telepon_guru = mysqli_real_escape_string($conn, $_POST['telepon_guru']);
    $durasi_bulan = mysqli_real_escape_string($conn, $_POST['durasi_bulan']);
    $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
    $tanggal_mulai = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']);
    $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);

    // Validasi nama guru (hanya huruf alphabet)
    if (!validateName($nama_guru)) {
        $error = "Nama guru hanya boleh mengandung huruf alphabet";
    } else {
        // Hitung total harga
        $total_harga = $durasi_bulan * $harga_paket['harga'];
        
$jam_selesai = date('H:i:s', strtotime($jam_mulai) + (3 * 3600));

// Cek konflik booking untuk setiap hari selama durasi bulan
$hari_booking = date('w', strtotime($tanggal_mulai));
$tanggal_cek = $tanggal_mulai;
$tanggal_akhir = date('Y-m-d', strtotime($tanggal_mulai . ' + ' . $durasi_bulan . ' months'));
$ada_konflik = false;
$detail_konflik = null;

while (strtotime($tanggal_cek) <= strtotime($tanggal_akhir)) {
    // Cek konflik hanya pada hari yang sama setiap minggu
    $konflik = cekKonflikBooking($conn, $lapangan_id, $tanggal_cek, $jam_mulai, $jam_selesai);
    if ($konflik['ada_konflik']) {
        $ada_konflik = true;
        $detail_konflik = $konflik;
        $tanggal_konflik = $tanggal_cek;
        break;
    }
    // Tambah 7 hari (ke minggu berikutnya)
    $tanggal_cek = date('Y-m-d', strtotime($tanggal_cek . ' + 7 days'));
}

        if ($ada_konflik) {
            $jam_mulai_konflik = date('H:i', strtotime($detail_konflik['jam_mulai']));
            $jam_selesai_konflik = date('H:i', strtotime($detail_konflik['jam_selesai']));
            $tanggal_konflik_format = date('d/m/Y', strtotime($tanggal_konflik));
            $error = "Maaf, terdapat konflik jadwal dengan booking {$detail_konflik['jenis']} pada tanggal {$tanggal_konflik_format} jam {$jam_mulai_konflik} - {$jam_selesai_konflik}.";
        } else {
            // Simpan booking
            $paket = 'eskul_lap' . $lapangan_id;
            $query = "INSERT INTO booking_eskul (user_id, lapangan_id, nama_sekolah, nama_guru, telepon_guru, 
                                               durasi_bulan, jam_mulai, tanggal_mulai, total_harga, paket,
                                               metode_pembayaran, status_pembayaran, tanggal_booking) 
                      VALUES ('$user_id', '$lapangan_id', '$nama_sekolah', '$nama_guru', '$telepon_guru', 
                             '$durasi_bulan', '$jam_mulai', '$tanggal_mulai', '$total_harga', '$paket',
                             '$metode_pembayaran', 'pending', NOW())";
            
if (mysqli_query($conn, $query)) {
    $booking_id = mysqli_insert_id($conn);
    header("Location: invoice_eskul.php?booking_id=" . $booking_id);
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

<div class="max-w-2xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors mb-5">
        &larr; Kembali ke Beranda
    </a>

    <div class="bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
        <div class="pb-5 border-b border-stone-200 mb-6 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-green-800">Paket Ekstrakurikuler</p>
                <h2 class="text-xl sm:text-2xl font-bold text-stone-900 mt-0.5"><?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <?php if (!empty($harga_paket['harga'])): ?>
                <p class="text-sm font-bold text-stone-900 tabular-nums">
                    Rp <?php echo number_format($harga_paket['harga'], 0, ',', '.'); ?><span class="text-xs font-normal text-stone-500">/bulan</span>
                </p>
            <?php endif; ?>
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
                </p>
                <a href="riwayat.php" class="inline-block mt-3 px-3.5 py-1.5 rounded-md bg-stone-900 hover:bg-green-800 text-white text-xs font-semibold transition-colors">
                    Lihat Riwayat Booking
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4" id="bookingEskulForm">
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
                <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="nama_sekolah">
                    Nama Sekolah
                </label>
                <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                       id="nama_sekolah" 
                       type="text" 
                       name="nama_sekolah" 
                       required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="nama_guru">
                        Guru Penanggung Jawab
                    </label>
                    <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           id="nama_guru" 
                           type="text" 
                           name="nama_guru" 
                           pattern="[A-Za-z\s]+"
                           title="Nama guru hanya boleh mengandung huruf alphabet"
                           required>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="telepon_guru">
                        Nomor Telepon Guru
                    </label>
                    <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           id="telepon_guru" 
                           type="tel" 
                           name="telepon_guru" 
                           required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="tanggal_mulai">
                        Tanggal Mulai
                    </label>
                    <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           id="tanggal_mulai" 
                           type="date" 
                           name="tanggal_mulai" 
                           value="<?php echo isset($_POST['tanggal_mulai']) ? htmlspecialchars($_POST['tanggal_mulai'], ENT_QUOTES, 'UTF-8') : date('Y-m-d'); ?>"
                           min="<?php echo date('Y-m-d'); ?>"
                           required>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="jam_mulai">
                        Jam Latihan (3 Jam)
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="jam_mulai" 
                            name="jam_mulai" 
                            required>
                        <option value="">Pilih Jam</option>
                        <?php
                        $startHours = range(8, 19);
                        foreach ($startHours as $startHour) {
                            $endHour = $startHour + 3;
                            $startFormatted = str_pad($startHour, 2, "0", STR_PAD_LEFT) . ":00";
                            $endFormatted = str_pad($endHour, 2, "0", STR_PAD_LEFT) . ":00";
                            echo "<option value='$startFormatted'>$startFormatted – $endFormatted</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="durasi_bulan">
                        Durasi
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="durasi_bulan"
                            name="durasi_bulan"
                            required>
                        <?php
                        $selected_durasi = isset($_POST['durasi_bulan']) ? (int)$_POST['durasi_bulan'] : 1;
                        for ($i = 1; $i <= 7; $i++) {
                            $selected = $selected_durasi == $i ? 'selected' : '';
                            echo "<option value=\"$i\" $selected>$i Bulan</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <?php if ($_SESSION['role'] != 'admin'): ?>
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-2">
                        Metode Pembayaran
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center p-3 border border-stone-200 rounded-md hover:border-stone-400 cursor-pointer text-sm">
                            <input type="radio" name="metode_pembayaran" value="transfer" required class="mr-3 accent-green-800"> 
                            <span class="text-stone-800 font-medium">Transfer Bank (SeaBank)</span>
                        </label>
                        <label class="flex items-center p-3 border border-stone-200 rounded-md hover:border-stone-400 cursor-pointer text-sm">
                            <input type="radio" name="metode_pembayaran" value="qris" required class="mr-3 accent-green-800"> 
                            <span class="text-stone-800 font-medium">QRIS (OVO, GoPay, DANA, ShopeePay)</span>
                        </label>
                        <label class="flex items-center p-3 border border-stone-200 rounded-md hover:border-stone-400 cursor-pointer text-sm">
                            <input type="radio" name="metode_pembayaran" value="cod" required class="mr-3 accent-green-800"> 
                            <span class="text-stone-800 font-medium">Bayar di Tempat (COD)</span>
                        </label>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="metode_pembayaran" value="cod">
            <?php endif; ?>

            <button class="w-full <?php echo $active_booking ? 'bg-stone-300 text-stone-500 cursor-not-allowed' : 'bg-stone-900 hover:bg-green-800 text-white'; ?> font-semibold py-2.5 px-4 rounded-md transition-colors text-sm mt-2"
                    type="submit" <?php echo $active_booking ? 'disabled' : ''; ?>>
                <?php echo $active_booking ? 'Selesaikan Booking Aktif Terlebih Dahulu' : 'Lanjutkan Booking Eskul'; ?>
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
