<?php 
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Kolom yang sama untuk semua jenis booking dengan alias tabel
$common_columns_regular = "b.id_booking AS id, b.user_id, b.lapangan_id, b.total_harga, b.status_pembayaran, 
                         b.tanggal_booking, b.bukti_pembayaran, b.tanggal_upload_bukti";
$common_columns_eskul = "be.id_booking_eskul AS id, be.user_id, be.lapangan_id, be.total_harga, be.status_pembayaran, 
                        be.tanggal_booking, be.bukti_pembayaran, be.tanggal_upload_bukti";
$common_columns_turnamen = "bt.id_booking_turnamen AS id, bt.user_id, bt.lapangan_id, bt.total_harga, bt.status_pembayaran, 
                          bt.tanggal_booking, bt.bukti_pembayaran, bt.tanggal_upload_bukti";

// Filter conditions
$status_condition = "";
$date_condition = "";

if (isset($_GET['status']) && $_GET['status'] != 'semua') {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $status_condition = " AND status_pembayaran = '$status'";
}

if (isset($_GET['dari']) && !empty($_GET['dari'])) {
    $dari = mysqli_real_escape_string($conn, $_GET['dari']);
    if (isset($_GET['sampai']) && !empty($_GET['sampai'])) {
        $sampai = mysqli_real_escape_string($conn, $_GET['sampai']);
    } else {
        $sampai = date('Y-m-d'); // If end date not provided, use current date
    }
    $date_condition = " AND tanggal_jadwal BETWEEN '$dari' AND '$sampai'";
}

// Ambil data booking regular
$query_regular = "SELECT $common_columns_regular, 
                  b.tanggal_main as tanggal_jadwal,
                  b.jam_mulai, b.jam_selesai,
                  b.metode_pembayaran,
                  l.nama as nama_lapangan,
                  'Regular' as jenis_booking
                  FROM booking b 
                  JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
                  WHERE b.user_id = '$user_id' $status_condition 
                  AND b.tanggal_main IS NOT NULL";

if ($date_condition) {
    $query_regular .= str_replace('tanggal_jadwal', 'b.tanggal_main', $date_condition);
}

// Ambil data booking eskul
$query_eskul = "SELECT $common_columns_eskul,
                be.tanggal_mulai as tanggal_jadwal,
                be.jam_mulai, ADDTIME(be.jam_mulai, '03:00:00') as jam_selesai,
                'Transfer' as metode_pembayaran,
                l.nama as nama_lapangan,
                'Ekstrakurikuler' as jenis_booking
                FROM booking_eskul be 
                JOIN lapangan l ON be.lapangan_id = l.id_lapangan 
                WHERE be.user_id = '$user_id' $status_condition
                AND be.tanggal_mulai IS NOT NULL";

if ($date_condition) {
    $query_eskul .= str_replace('tanggal_jadwal', 'be.tanggal_mulai', $date_condition);
}

// Ambil data booking turnamen
$query_turnamen = "SELECT $common_columns_turnamen,
                   bt.tanggal_mulai as tanggal_jadwal,
                   bt.jam_mulai, bt.jam_selesai,
                   'Transfer' as metode_pembayaran,
                   l.nama as nama_lapangan,
                   'Turnamen' as jenis_booking
                   FROM booking_turnamen bt 
                   JOIN lapangan l ON bt.lapangan_id = l.id_lapangan 
                   WHERE bt.user_id = '$user_id' $status_condition
                   AND bt.tanggal_mulai IS NOT NULL";

if ($date_condition) {
    $query_turnamen .= str_replace('tanggal_jadwal', 'bt.tanggal_mulai', $date_condition);
}

// Gabungkan semua query
// Gabungkan semua query dengan proper error handling
$query = "($query_regular) UNION ALL ($query_eskul) UNION ALL ($query_turnamen) 
          ORDER BY tanggal_jadwal DESC";
$result = mysqli_query($conn, $query);
if (!$result) {
    $error = "Error retrieving bookings: " . mysqli_error($conn);
}

// Ambil total booking dan total pembayaran dari semua jenis booking dengan filter
$total_query = "(SELECT COUNT(*) as total_booking, SUM(total_harga) as total_pembayaran 
                FROM booking WHERE user_id = '$user_id' $status_condition" . 
                ($date_condition ? str_replace('tanggal_jadwal', 'tanggal_main', $date_condition) : "") . ")
                UNION ALL
                (SELECT COUNT(*) as total_booking, SUM(total_harga) as total_pembayaran 
                FROM booking_eskul WHERE user_id = '$user_id' $status_condition" . 
                ($date_condition ? str_replace('tanggal_jadwal', 'tanggal_mulai', $date_condition) : "") . ")
                UNION ALL
                (SELECT COUNT(*) as total_booking, SUM(total_harga) as total_pembayaran 
                FROM booking_turnamen WHERE user_id = '$user_id' $status_condition" . 
                ($date_condition ? str_replace('tanggal_jadwal', 'tanggal_mulai', $date_condition) : "") . ")";

$total_result = mysqli_query($conn, $total_query);
if (!$total_result) {
    $error = "Error calculating totals: " . mysqli_error($conn);
}
$total_data = ['total_booking' => 0, 'total_pembayaran' => 0];
while ($row = mysqli_fetch_assoc($total_result)) {
    $total_data['total_booking'] += $row['total_booking'];
    $total_data['total_pembayaran'] += $row['total_pembayaran'];
}

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
    <!-- Tombol Kembali ke Beranda -->
    <a href="index.php" class="inline-flex items-center bg-gray-200 text-green-700 px-4 py-2 rounded font-semibold hover:bg-gray-300 mb-4">
        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
    </a>
    <!-- Header dan Filter -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold text-green-600 mb-2">Riwayat Booking</h2>
                <div class="text-gray-600">
                    <p class="mb-2">Lihat dan kelola riwayat booking Anda:</p>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        <li>Lihat detail booking dan status pembayaran</li>
                        <li>Cetak atau simpan invoice untuk setiap booking</li>
                        <li>Pantau status pembayaran yang masih pending</li>
                        <li>Akses informasi pembayaran transfer atau QRIS</li>
                    </ul>
                </div>
            </div>
            <div class="flex-shrink-0 text-left sm:text-right w-full sm:w-auto">
                <p class="font-semibold whitespace-normal break-words">Total Booking: <?php echo $total_data['total_booking']; ?></p>
                <p class="text-green-600 font-semibold whitespace-normal break-words">
                    Total Pembayaran: Rp <?php echo number_format($total_data['total_pembayaran'], 0, ',', '.'); ?>
                </p>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="flex flex-wrap items-end gap-2 sm:space-x-4 sm:flex-nowrap mb-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status Pembayaran</label>
                <select name="status" class="rounded border-gray-300 shadow-sm">
                    <option value="semua" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'semua') ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                    <option value="dikonfirmasi" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dikonfirmasi') ? 'selected' : ''; ?>>Pembayaran Diterima</option>
                    <option value="dibatalkan" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="dari" class="rounded border-gray-300 shadow-sm" 
                       value="<?php echo isset($_GET['dari']) ? $_GET['dari'] : ''; ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai" class="rounded border-gray-300 shadow-sm"
                       value="<?php echo isset($_GET['sampai']) ? $_GET['sampai'] : ''; ?>">
            </div>
            <div class="flex flex-col w-full sm:w-auto gap-2 sm:flex-row sm:space-x-2 mt-2 sm:mt-0">
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full sm:w-auto">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <a href="riwayat.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 w-full sm:w-auto text-center">
                    <i class="fas fa-sync-alt mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Card layout for mobile -->
            <div class="block md:hidden px-2">
                <div class="grid grid-cols-1 gap-3">
                    <?php mysqli_data_seek($result, 0); while($booking = mysqli_fetch_assoc($result)): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow w-full max-w-full break-words whitespace-normal">
                            <div class="mb-2 font-semibold text-lg text-green-600 break-all whitespace-normal">
                                #<?php echo $booking['id']; ?> - <span class="break-all whitespace-normal"><?php echo $booking['nama_lapangan']; ?></span>
                                <span class="text-sm text-gray-500 break-all whitespace-normal">(<?php echo $booking['jenis_booking']; ?>)</span>
                            </div>
                            <div class="text-gray-600 mb-1 text-xs break-all whitespace-normal">
                                <i class="far fa-calendar mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($booking['tanggal_jadwal'])); ?>
                            </div>
                            <div class="text-gray-600 mb-1 text-xs break-all whitespace-normal">
                                <i class="far fa-clock mr-1"></i>
                                <?php echo date('H:i', strtotime($booking['jam_mulai'])) . ' - ' . date('H:i', strtotime($booking['jam_selesai'])); ?>
                            </div>
                            <div class="text-green-600 font-semibold mb-1 text-xs break-all whitespace-normal">
                                Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                            </div>
                            <div class="text-gray-600 mb-1 text-xs break-all whitespace-normal">
                                <?php 
                                $metode_icon = '';
                                $metode_text = '';
                                switch($booking['metode_pembayaran']) {
                                    case 'transfer':
                                        $metode_icon = 'university';
                                        $metode_text = 'Transfer Bank';
                                        break;
                                    case 'qris':
                                        $metode_icon = 'qrcode';
                                        $metode_text = 'QRIS';
                                        break;
                                    case 'cod':
                                        $metode_icon = 'money-bill-wave';
                                        $metode_text = 'Bayar di Tempat';
                                        break;
                                }
                                ?>
                                <i class="fas fa-<?php echo $metode_icon; ?> mr-1"></i>
                                <?php echo $metode_text; ?>
                            </div>
                            <div class="mb-2">
                                <?php 
                                $status_class = '';
                                $status_icon = '';
                                $status_text = '';
                                switch($booking['status_pembayaran']) {
                                    case 'pending':
                                        $status_class = 'bg-yellow-100 text-yellow-800 border border-yellow-300';
                                        $status_icon = 'clock';
                                        $status_text = 'Menunggu Pembayaran';
                                        break;
                                    case 'dikonfirmasi':
                                        $status_class = 'bg-green-100 text-green-800 border border-green-300';
                                        $status_icon = 'check-circle';
                                        $status_text = 'Pembayaran Diterima';
                                        break;
                                    case 'dibatalkan':
                                        $status_class = 'bg-red-100 text-red-800 border border-red-300';
                                        $status_icon = 'times-circle';
                                        $status_text = 'Dibatalkan';
                                        break;
                                }
                                ?>
                                <span class="px-2 py-1 rounded-full <?php echo $status_class; ?> text-xs inline-flex items-center break-all whitespace-normal">
                                    <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="<?php 
                                    switch($booking['jenis_booking']) {
                                        case 'Regular':
                                            echo 'invoice.php';
                                            break;
                                        case 'Ekstrakurikuler':
                                            echo 'invoice_eskul.php';
                                            break;
                                        case 'Turnamen':
                                            echo 'invoice_turnamen.php';
                                            break;
                                    }
                                ?>?booking_id=<?php echo $booking['id']; ?>&from=riwayat" 
                                   class="flex items-center justify-center bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 mb-2 break-all whitespace-normal">
                                    <i class="fas fa-eye mr-1"></i> Lihat
                                </a>
                                <?php if ($booking['status_pembayaran'] == 'pending'): ?>
                                    <form action="cancel_booking.php" method="POST" class="inline" 
                                          onsubmit="return confirm('Apakah Anda yakin ingin membatalkan booking ini?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <?php
                                        $booking_type_map = [
                                            'Regular' => 'regular',
                                            'Ekstrakurikuler' => 'eskul',
                                            'Turnamen' => 'turnamen'
                                        ];
                                        $booking_type_value = isset($booking_type_map[$booking['jenis_booking']]) ? $booking_type_map[$booking['jenis_booking']] : strtolower($booking['jenis_booking']);
                                        ?>
                                        <input type="hidden" name="booking_type" value="<?php echo $booking_type_value; ?>">
                                        <button type="submit" 
                                                class="flex items-center justify-center bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700 mb-2 break-all whitespace-normal">
                                            <i class="fas fa-times mr-1"></i> Batalkan
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php if($booking['status_pembayaran'] == 'pending' && $booking['metode_pembayaran'] != 'cod'): ?>
                                <p class="text-xs text-red-600 break-all whitespace-normal">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Harap selesaikan pembayaran dalam 8 jam
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <!-- Table layout for desktop -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-green-600 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left">Detail Booking</th>
                            <th class="px-6 py-3 text-left">Detail Pembayaran</th>
                            <th class="px-6 py-3 text-left">Status & Invoice</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php mysqli_data_seek($result, 0); while($booking = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-gray-50">
                                <!-- Detail Booking -->
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <p class="font-semibold">
                                            #<?php echo $booking['id']; ?> - <?php echo $booking['nama_lapangan']; ?>
                                            <span class="text-sm text-gray-500">(<?php echo $booking['jenis_booking']; ?>)</span>
                                        </p>
                                        <p class="text-gray-600">
                                            <i class="far fa-calendar mr-1"></i>
                                            <?php echo date('d/m/Y', strtotime($booking['tanggal_jadwal'])); ?>
                                        </p>
                                        <p class="text-gray-600">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo date('H:i', strtotime($booking['jam_mulai'])) . ' - ' . 
                                                 date('H:i', strtotime($booking['jam_selesai'])); ?>
                                        </p>
                                    </div>
                                </td>

                                <!-- Detail Pembayaran -->
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <p class="font-semibold text-green-600">
                                            Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                                        </p>
                                        <p class="text-gray-600">
                                            <?php 
                                            $metode_icon = '';
                                            $metode_text = '';
                                            switch($booking['metode_pembayaran']) {
                                                case 'transfer':
                                                    $metode_icon = 'university';
                                                    $metode_text = 'Transfer Bank';
                                                    break;
                                                case 'qris':
                                                    $metode_icon = 'qrcode';
                                                    $metode_text = 'QRIS';
                                                    break;
                                                case 'cod':
                                                    $metode_icon = 'money-bill-wave';
                                                    $metode_text = 'Bayar di Tempat';
                                                    break;
                                            }
                                            ?>
                                            <i class="fas fa-<?php echo $metode_icon; ?> mr-1"></i>
                                            <?php echo $metode_text; ?>
                                        </p>
                                        <?php if($booking['metode_pembayaran'] == 'transfer'): ?>
                                            <p class="text-sm text-gray-500">Seabank: 901245634730</p>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Status & Invoice -->
                                <td class="px-6 py-4">
                                    <div class="space-y-3">
                                        <?php 
                                        $status_class = '';
                                        $status_icon = '';
                                        $status_text = '';
                                        
                                        switch($booking['status_pembayaran']) {
                                            case 'pending':
                                                $status_class = 'bg-yellow-100 text-yellow-800 border border-yellow-300';
                                                $status_icon = 'clock';
                                                $status_text = 'Menunggu Pembayaran';
                                                break;
                                            case 'dikonfirmasi':
                                                $status_class = 'bg-green-100 text-green-800 border border-green-300';
                                                $status_icon = 'check-circle';
                                                $status_text = 'Pembayaran Diterima';
                                                break;
                                            case 'dibatalkan':
                                                $status_class = 'bg-red-100 text-red-800 border border-red-300';
                                                $status_icon = 'times-circle';
                                                $status_text = 'Dibatalkan';
                                                break;
                                        }
                                        ?>
                                        <div class="flex items-center space-x-2">
                                            <span class="px-3 py-1 rounded-full <?php echo $status_class; ?> text-sm">
                                                <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>

                                        <div class="flex space-x-2">
                                            <a href="<?php 
                                                switch($booking['jenis_booking']) {
                                                    case 'Regular':
                                                        echo 'invoice.php';
                                                        break;
                                                    case 'Ekstrakurikuler':
                                                        echo 'invoice_eskul.php';
                                                        break;
                                                    case 'Turnamen':
                                                        echo 'invoice_turnamen.php';
                                                        break;
                                                }
                                            ?>?booking_id=<?php echo $booking['id']; ?>&from=riwayat" 
                                               class="flex items-center justify-center bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                                <i class="fas fa-eye mr-1"></i> Lihat
                                            </a>
                                            <?php if ($booking['status_pembayaran'] == 'pending'): ?>
                                                <form action="cancel_booking.php" method="POST" class="inline" 
                                                      onsubmit="return confirm('Apakah Anda yakin ingin membatalkan booking ini?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <?php
                                                    $booking_type_map = [
                                                        'Regular' => 'regular',
                                                        'Ekstrakurikuler' => 'eskul',
                                                        'Turnamen' => 'turnamen'
                                                    ];
                                                    $booking_type_value = isset($booking_type_map[$booking['jenis_booking']]) ? $booking_type_map[$booking['jenis_booking']] : strtolower($booking['jenis_booking']);
                                                    ?>
                                                    <input type="hidden" name="booking_type" value="<?php echo $booking_type_value; ?>">
                                                    <button type="submit" 
                                                            class="flex items-center justify-center bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                        <i class="fas fa-times mr-1"></i> Batalkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <?php if($booking['status_pembayaran'] == 'pending' && $booking['metode_pembayaran'] != 'cod'): ?>
                                            <p class="text-xs text-red-600">
                                                <i class="fas fa-exclamation-circle mr-1"></i>
                                                Harap selesaikan pembayaran dalam 8 jam
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <p class="text-gray-600">Anda belum memiliki riwayat booking.</p>
            <a href="booking.php" class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Booking Sekarang
            </a>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Informasi Pembayaran Transfer -->
<div class="max-w-4xl mx-auto mt-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-xl font-bold text-green-600 mb-4">Informasi Pembayaran Transfer</h3>
        <div class="space-y-2">
            <p><strong>Bank:</strong> Bank Seabank</p>
            <p><strong>No. Rekening:</strong> 901245634730</p>
            <p><strong>Atas Nama:</strong> Futsal Sayan Bekasi</p>
        </div>
        <div class="mt-4 text-sm text-gray-600">
            <p>Catatan:</p>
            <ul class="list-disc list-inside space-y-1">
                <li>Mohon transfer sesuai dengan total harga booking</li>
                <li>Sertakan nomor invoice pada berita transfer</li>
                <li>Konfirmasi pembayaran akan diproses dalam 1x24 jam</li>
                <li>Untuk pembayaran COD, silakan melakukan pembayaran di tempat minimal 30 menit sebelum jadwal main</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
