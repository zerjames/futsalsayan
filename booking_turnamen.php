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
    if ($result_users) {
        while ($user = mysqli_fetch_assoc($result_users)) {
            $users[] = $user;
        }
    }
}

// Set user_id based on role and selection
$user_id = $_SESSION['user_id'];
if ($_SESSION['role'] == 'admin' && isset($_POST['selected_user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['selected_user_id']);
}

// Cek apakah ada parameter lapangan
if (!isset($_GET['lapangan'])) {
    $_SESSION['error'] = "Parameter lapangan tidak ditemukan.";
    header("Location: index.php");
    exit();
}

$lapangan_id = mysqli_real_escape_string($conn, $_GET['lapangan']);

// Debug request parameters
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));
error_log("Lapangan ID from GET: " . $lapangan_id);

// Ambil data lapangan
$query_lapangan = "SELECT * FROM lapangan WHERE id_lapangan = '$lapangan_id' AND status = 'tersedia'";
$result_lapangan = mysqli_query($conn, $query_lapangan);

if (!$result_lapangan) {
    die("Error in lapangan query: " . mysqli_error($conn));
}

$lapangan = mysqli_fetch_assoc($result_lapangan);

if (!$lapangan) {
    $_SESSION['error'] = "Lapangan tidak ditemukan atau tidak tersedia.";
    header("Location: index.php");
    exit();
}

// Ambil harga paket turnamen
$query_harga = "SELECT 
    MAX(CASE WHEN tipe = CONCAT('turnamen_lap', '$lapangan_id', '_sore') THEN harga END) as harga_sore,
    MAX(CASE WHEN tipe = CONCAT('turnamen_lap', '$lapangan_id', '_malam') THEN harga END) as harga_malam
    FROM harga_paket 
    WHERE tipe LIKE CONCAT('turnamen_lap', '$lapangan_id', '_%')";
$result_harga = mysqli_query($conn, $query_harga);

if (!$result_harga) {
    die("Error in harga query: " . mysqli_error($conn));
}

$harga = mysqli_fetch_assoc($result_harga);

// Gabungkan data lapangan dengan harga
$lapangan['harga_sore'] = $harga['harga_sore'];
$lapangan['harga_malam'] = $harga['harga_malam'];

// Debug data
error_log("Lapangan data: " . print_r($lapangan, true));

// Debug info
if (isset($_GET['debug'])) {
    echo "<pre>";
    print_r($lapangan);
    echo "</pre>";
}

if (!function_exists('validateName')) {
    // Fungsi validasi nama (hanya huruf alphabet dan spasi)
    function validateName($name) {
        return preg_match('/^[A-Za-z\s]+$/', $name);
    }
}

// Jalankan auto-cancel & cek batas maksimal 1 booking aktif per akun (non-admin)
autoCancelUnpaidBookings($conn);
$active_booking = ($_SESSION['role'] != 'admin') ? getUserActiveBooking($conn, $user_id) : null;

// Proses booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } elseif ($_SESSION['role'] != 'admin' && $active_booking) {
        $error = 'Setiap akun hanya diperbolehkan memiliki 1 booking aktif. Anda masih memiliki booking aktif (' . $active_booking['nama_lapangan'] . ' - ' . date('d/m/Y', strtotime($active_booking['tanggal'])) . '). Selesaikan atau batalkan booking tersebut terlebih dahulu.';
    } else {

    $nama_turnamen = mysqli_real_escape_string($conn, $_POST['nama_turnamen']);
    $nama_panitia = mysqli_real_escape_string($conn, $_POST['nama_panitia']);
    $telepon_panitia = intval($_POST['telepon_panitia']);
    $durasi_hari = mysqli_real_escape_string($conn, $_POST['durasi_hari']);
    $jenis_paket = mysqli_real_escape_string($conn, $_POST['jenis_paket']);
    $tanggal_mulai = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']);
    $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);

    // Validasi nomor telepon (harus angka)
    if (!is_numeric($_POST['telepon_panitia']) || $telepon_panitia <= 0) {
        $error = "Nomor telepon panitia harus berupa angka yang valid";
    }
    // Validasi nama panitia (hanya huruf alphabet)
    if (!validateName($nama_panitia)) {
        $error = "Nama panitia hanya boleh mengandung huruf alphabet";
    } else {
        // Set jam berdasarkan jenis paket
        $jam_mulai = "10:00";
        $jam_selesai = $jenis_paket == 'sore' ? "17:00" : "21:00";
        
        // Hitung total harga
        $harga_per_hari = $jenis_paket == 'sore' ? $lapangan['harga_sore'] : $lapangan['harga_malam'];
        $total_harga = $durasi_hari * $harga_per_hari;
    
        // Cek konflik booking untuk setiap hari selama durasi turnamen
        $tanggal_akhir = date('Y-m-d', strtotime($tanggal_mulai . ' + ' . $durasi_hari . ' days'));
        $tanggal_cek = $tanggal_mulai;
        $ada_konflik = false;
        $detail_konflik = null;

        while (strtotime($tanggal_cek) < strtotime($tanggal_akhir)) {
            $konflik = cekKonflikBooking($conn, $lapangan_id, $tanggal_cek, $jam_mulai, $jam_selesai);
            if ($konflik['ada_konflik']) {
                $ada_konflik = true;
                $detail_konflik = $konflik;
                $tanggal_konflik = $tanggal_cek;
                break;
            }
            $tanggal_cek = date('Y-m-d', strtotime($tanggal_cek . ' + 1 day'));
        }

        if ($ada_konflik) {
            $jam_mulai_konflik = date('H:i', strtotime($detail_konflik['jam_mulai']));
            $jam_selesai_konflik = date('H:i', strtotime($detail_konflik['jam_selesai']));
            $tanggal_konflik_format = date('d/m/Y', strtotime($tanggal_konflik));
            $error = "Maaf, terdapat konflik jadwal dengan booking {$detail_konflik['jenis']} pada tanggal {$tanggal_konflik_format} jam {$jam_mulai_konflik} - {$jam_selesai_konflik}.";
        } else {
            // Simpan booking
            $paket = 'turnamen_lap' . $lapangan_id . '_' . $jenis_paket;
            $query = "INSERT INTO booking_turnamen (user_id, lapangan_id, nama_turnamen, nama_panitia, telepon_panitia, 
                                                  durasi_hari, jam_mulai, jam_selesai, tanggal_mulai, total_harga, paket,
                                                  metode_pembayaran, status_pembayaran, tanggal_booking) 
                      VALUES ('$user_id', '$lapangan_id', '$nama_turnamen', '$nama_panitia', '$telepon_panitia', 
                             '$durasi_hari', '$jam_mulai', '$jam_selesai', '$tanggal_mulai', '$total_harga', '$paket',
                             '$metode_pembayaran', 'pending', NOW())";
            
                if (mysqli_query($conn, $query)) {
                    $booking_id = mysqli_insert_id($conn);

                    header("Location: invoice_turnamen.php?booking_id=" . $booking_id);
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
        <div class="pb-5 border-b border-stone-200 mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-green-800">Paket Turnamen</p>
            <h2 class="text-xl sm:text-2xl font-bold text-stone-900 mt-0.5"><?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="text-sm text-stone-500 mt-1"><?php echo htmlspecialchars($lapangan['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></p>
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

        <!-- Harga Paket -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6 tabular-nums">
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md">
                <p class="text-xs text-stone-500">Paket Sore (10:00 – 17:00)</p>
                <p class="text-lg font-bold text-stone-900 mt-0.5">
                    Rp <?php echo number_format($lapangan['harga_sore'] ?? 0, 0, ',', '.'); ?><span class="text-xs font-normal text-stone-500">/hari</span>
                </p>
            </div>
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md">
                <p class="text-xs text-stone-500">Paket Malam (10:00 – 21:00)</p>
                <p class="text-lg font-bold text-stone-900 mt-0.5">
                    Rp <?php echo number_format($lapangan['harga_malam'] ?? 0, 0, ',', '.'); ?><span class="text-xs font-normal text-stone-500">/hari</span>
                </p>
            </div>
        </div>

        <form method="POST" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="lapangan_id" value="<?php echo htmlspecialchars($lapangan_id, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="selected_user_id">
                        Pilih User
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="selected_user_id" 
                            name="selected_user_id" 
                            required>
                        <option value="">Pilih User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id_user']; ?>">
                                <?php echo htmlspecialchars($user['nama'] . ' (' . $user['username'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="nama_turnamen">
                    Nama Turnamen
                </label>
                <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                       id="nama_turnamen" 
                       type="text" 
                       name="nama_turnamen" 
                       required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="nama_panitia">
                        Panitia Penanggung Jawab
                    </label>
                    <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           id="nama_panitia" 
                           type="text" 
                           name="nama_panitia" 
                           pattern="[A-Za-z\s]+"
                           title="Nama panitia hanya boleh mengandung huruf alphabet"
                           required>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="telepon_panitia">
                        Nomor Telepon Panitia
                    </label>
                    <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           id="telepon_panitia" 
                           type="number" 
                           name="telepon_panitia" 
                           min="1"
                           title="Nomor telepon harus berupa angka"
                           style="-moz-appearance: textfield; -webkit-appearance: textfield; appearance: textfield;"
                           required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="jenis_paket">
                        Jenis Paket
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="jenis_paket" 
                            name="jenis_paket" 
                            required>
                        <option value="">Pilih Paket</option>
                        <option value="sore">Sore (10:00–17:00)</option>
                        <option value="malam">Malam (10:00–21:00)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="durasi_hari">
                        Durasi
                    </label>
                    <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                            id="durasi_hari" 
                            name="durasi_hari" 
                            required>
                        <option value="">Pilih Durasi</option>
                        <?php for($i = 1; $i <= 7; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Hari</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="tanggal_mulai">
                        Tanggal Mulai
                    </label>
                    <input class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           id="tanggal_mulai" 
                           type="date" 
                           name="tanggal_mulai" 
                           min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                           required>
                </div>
            </div>

            <div>
                <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="metode_pembayaran">
                    Metode Pembayaran
                </label>
                <select class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm bg-white focus:outline-none focus:border-stone-900"
                        id="metode_pembayaran" 
                        name="metode_pembayaran" 
                        required>
                    <option value="">Pilih Metode Pembayaran</option>
                    <option value="transfer">Transfer Bank</option>
                </select>
            </div>

            <!-- Ketentuan Turnamen -->
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md text-xs text-stone-600 space-y-1">
                <p class="font-semibold text-stone-800 mb-1.5">Ketentuan Paket Turnamen</p>
                <p>&bull; Booking minimal 7 hari sebelum pelaksanaan.</p>
                <p>&bull; Wajib mengirimkan informasi turnamen ke admin via WhatsApp.</p>
                <p>&bull; Pembayaran DP 50% untuk konfirmasi, pelunasan maksimal H-1.</p>
            </div>

            <button class="w-full <?php echo $active_booking ? 'bg-stone-300 text-stone-500 cursor-not-allowed' : 'bg-stone-900 hover:bg-green-800 text-white'; ?> font-semibold py-2.5 px-4 rounded-md transition-colors text-sm"
                    type="submit" <?php echo $active_booking ? 'disabled' : ''; ?>>
                <?php echo $active_booking ? 'Selesaikan Booking Aktif Terlebih Dahulu' : 'Lanjutkan Booking Turnamen'; ?>
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
