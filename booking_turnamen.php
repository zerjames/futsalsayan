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
    $query_users = "SELECT id, nama, username FROM users WHERE role = 'user'";
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

// Proses booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-4 sm:p-6 m-2 sm:m-0">
    <!-- Tombol Kembali ke Beranda (paling atas) -->
    <a href="index.php" class="inline-flex items-center bg-gray-200 text-green-700 px-4 py-2 rounded font-semibold hover:bg-gray-300 mb-4">
        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
    </a>
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Booking Paket Turnamen</h2>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Detail Paket -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6 text-center sm:text-left">
        <h3 class="font-bold text-lg mb-2"><?php echo $lapangan['nama']; ?></h3>
        <p class="text-gray-600 mb-4"><?php echo $lapangan['deskripsi']; ?></p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 bg-white rounded-lg shadow-sm">
                <p class="font-semibold text-lg mb-2">Paket Sore (10:00 - 17:00)</p>
                <p class="text-xl font-bold text-green-600">
                    Rp <?php echo number_format($lapangan['harga_sore'], 0, ',', '.'); ?>/hari
                </p>
            </div>
            <div class="p-4 bg-white rounded-lg shadow-sm">
                <p class="font-semibold text-lg mb-2">Paket Malam (10:00 - 21:00)</p>
                <p class="text-xl font-bold text-green-600">
                    Rp <?php echo number_format($lapangan['harga_malam'], 0, ',', '.'); ?>/hari
                </p>
            </div>
        </div>
    </div>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="lapangan_id" value="<?php echo $lapangan_id; ?>">
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="selected_user_id">
                    Pilih User
                </label>
                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="selected_user_id" 
                        name="selected_user_id" 
                        required>
                    <option value="">Pilih User</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id_user']; ?>">
                            <?php echo $user['nama'] . ' (' . $user['username'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="nama_turnamen">
                Nama Turnamen
            </label>
            <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="nama_turnamen" 
                   type="text" 
                   name="nama_turnamen" 
                   required>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="nama_panitia">
                Nama Panitia Penanggung Jawab
            </label>
            <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="nama_panitia" 
                   type="text" 
                   name="nama_panitia" 
                   pattern="[A-Za-z\s]+"
                   title="Nama panitia hanya boleh mengandung huruf alphabet"
                   required>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="telepon_panitia">
                Nomor Telepon Panitia
            </label>
            <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="telepon_panitia" 
                   type="number" 
                   name="telepon_panitia" 
                   min="1"
                   title="Nomor telepon harus berupa angka"
                   style="-moz-appearance: textfield; -webkit-appearance: textfield; appearance: textfield;"
                   required>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="jenis_paket">
                Jenis Paket
            </label>
            <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="jenis_paket" 
                    name="jenis_paket" 
                    required>
                <option value="">Pilih Jenis Paket</option>
                <option value="sore">Paket Sore (10:00 - 17:00)</option>
                <option value="malam">Paket Malam (10:00 - 21:00)</option>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="durasi_hari">
                Durasi Turnamen
            </label>
            <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="durasi_hari" 
                    name="durasi_hari" 
                    required>
                <option value="">Pilih Durasi</option>
                <?php for($i = 1; $i <= 7; $i++): ?>
                    <option value="<?php echo $i; ?>">
                        <?php echo $i; ?> Hari
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="tanggal_mulai">
                Tanggal Mulai
            </label>
            <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="tanggal_mulai" 
                   type="date" 
                   name="tanggal_mulai" 
                   min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                   required>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-bold mb-2" for="metode_pembayaran">
                Metode Pembayaran
            </label>
            <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="metode_pembayaran" 
                    name="metode_pembayaran" 
                    required>
                <option value="">Pilih Metode Pembayaran</option>
                <option value="transfer">Transfer Bank</option>
            </select>
        </div>

        <!-- Panduan Booking -->
        <div class="bg-blue-50 p-4 rounded-lg mb-4">
            <h4 class="font-semibold text-blue-800 mb-3 text-center sm:text-left">
                <i class="fas fa-info-circle mr-2"></i>Informasi Paket Turnamen:
            </h4>
            <ul class="text-sm text-blue-700 space-y-2 px-2">
                <li>• Booking minimal 7 hari sebelum pelaksanaan</li>
                <li>• Wajib mengirimkan informasi turnamen ke admin via WhatsApp</li>
                <li>• Pembayaran DP 50% untuk konfirmasi booking</li>
                <li>• Pelunasan H-1 pelaksanaan turnamen</li>
            </ul>
        </div>

        <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full text-center text-lg"
                type="submit">
            Booking Sekarang
        </button>
    </form>
</div>
