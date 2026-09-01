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

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-4 sm:p-6 m-2 sm:m-0">
    <!-- Tombol Kembali ke Beranda -->
    <a href="index.php" class="inline-flex items-center bg-gray-200 text-green-700 px-4 py-2 rounded font-semibold hover:bg-gray-300 mb-4">
        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
    </a>
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-4 sm:p-6 my-4">
        <h2 class="text-2xl font-bold text-center text-green-600 mb-2">Booking Eskul Lapangan <?php echo htmlspecialchars($lapangan['nama']); ?></h2>
        <!-- Removed "Lihat Jadwal yang Tersedia" link as per user request -->
        <!--
        <div class="text-center mb-6">
            <a href="lihat_jadwal_eskul.php?lapangan=<?php echo $lapangan_id; ?>" class="text-blue-500 hover:text-blue-600">
                <i class="fas fa-calendar-alt mr-1"></i>Lihat Jadwal yang Tersedia
            </a>
        </div>
        -->
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4" id="bookingEskulForm">
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
                <label class="block text-gray-700 text-sm font-bold mb-2" for="nama_sekolah">
                    Nama Sekolah
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="nama_sekolah" 
                       type="text" 
                       name="nama_sekolah" 
                       required>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="nama_guru">
                    Guru Penanggung Jawab
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="nama_guru" 
                       type="text" 
                       name="nama_guru" 
                       pattern="[A-Za-z\s]+"
                       title="Nama guru hanya boleh mengandung huruf alphabet"
                       required>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="telepon_guru">
                    Telepon
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="telepon_guru" 
                       type="tel" 
                       name="telepon_guru" 
                       required>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="tanggal_mulai">
                    Tanggal Mulai
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="tanggal_mulai" 
                       type="date" 
                       name="tanggal_mulai" 
                       value="<?php echo isset($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : date('Y-m-d'); ?>"
                       min="<?php echo date('Y-m-d'); ?>"
                       required>
            </div>

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
                    $startHours = range(8, 19);
                    foreach ($startHours as $startHour) {
                        $endHour = $startHour + 3;
                        $startFormatted = str_pad($startHour, 2, "0", STR_PAD_LEFT) . ":00";
                        $endFormatted = str_pad($endHour, 2, "0", STR_PAD_LEFT) . ":00";
                        echo "<option value='$startFormatted'>$startFormatted - $endFormatted</option>";
                    }
                    ?>
                </select>
                <p class="text-sm text-gray-500 mt-1">Buka: 08:00 - 22:00</p>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="durasi_bulan">
                    Durasi (bulan)
                </label>
                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
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

            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full text-center text-lg"
                    type="submit">
                Booking Sekarang
            </button>
        </form>
    </div>
</div>
