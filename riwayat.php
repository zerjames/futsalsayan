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

// Jalankan auto-cancel sebelum query riwayat agar status langsung akurat
autoCancelUnpaidBookings($conn);

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

<div class="max-w-5xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors mb-5">
        &larr; Kembali ke Beranda
    </a>
    <!-- Header dan Filter -->
    <div class="bg-white rounded-lg border border-stone-200 p-5 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4 pb-5 border-b border-stone-200 mb-5">
            <div>
                <h2 class="text-xl font-bold text-stone-900">Riwayat Booking</h2>
                <p class="text-sm text-stone-500 mt-0.5">Daftar pesanan lapangan dan status pembayaran Anda.</p>
            </div>
            <div class="flex items-center gap-6 tabular-nums">
                <div>
                    <p class="text-[11px] uppercase tracking-wider text-stone-400 font-semibold">Total Booking</p>
                    <p class="text-base font-bold text-stone-900"><?php echo $total_data['total_booking']; ?></p>
                </div>
                <div class="border-l border-stone-200 pl-6">
                    <p class="text-[11px] uppercase tracking-wider text-stone-400 font-semibold">Total Pembayaran</p>
                    <p class="text-base font-bold text-green-800">Rp <?php echo number_format($total_data['total_pembayaran'], 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-800 bg-white focus:outline-none focus:border-stone-900">
                    <option value="semua" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'semua') ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                    <option value="dikonfirmasi" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dikonfirmasi') ? 'selected' : ''; ?>>Pembayaran Diterima</option>
                    <option value="dibatalkan" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Dari Tanggal</label>
                <input type="date" name="dari" class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-800 focus:outline-none focus:border-stone-900" 
                       value="<?php echo isset($_GET['dari']) ? htmlspecialchars($_GET['dari'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Sampai Tanggal</label>
                <input type="date" name="sampai" class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-800 focus:outline-none focus:border-stone-900"
                       value="<?php echo isset($_GET['sampai']) ? htmlspecialchars($_GET['sampai'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-stone-900 hover:bg-green-800 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
                    Filter
                </button>
                <a href="riwayat.php" class="px-3.5 py-2 rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-700 text-sm font-medium transition-colors text-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <!-- Card layout for mobile -->
        <div class="block md:hidden space-y-3">
            <?php mysqli_data_seek($result, 0); while($booking = mysqli_fetch_assoc($result)): ?>
                <?php
                $metode_text = '';
                switch($booking['metode_pembayaran']) {
                    case 'transfer': $metode_text = 'Transfer Bank'; break;
                    case 'qris': $metode_text = 'QRIS'; break;
                    case 'cod': $metode_text = 'Bayar di Tempat (COD)'; break;
                    default: $metode_text = ucfirst($booking['metode_pembayaran']);
                }

                $status_class = 'bg-stone-100 text-stone-700 border-stone-200';
                $status_text = ucfirst($booking['status_pembayaran']);
                switch($booking['status_pembayaran']) {
                    case 'pending':
                        $status_class = 'bg-amber-50 text-amber-800 border-amber-200';
                        $status_text = 'Menunggu Pembayaran';
                        break;
                    case 'dikonfirmasi':
                        $status_class = 'bg-green-50 text-green-800 border-green-200';
                        $status_text = 'Pembayaran Diterima';
                        break;
                    case 'dibatalkan':
                        $status_class = 'bg-red-50 text-red-700 border-red-200';
                        $status_text = 'Dibatalkan';
                        break;
                }

                $invoice_file = 'invoice.php';
                if ($booking['jenis_booking'] === 'Ekstrakurikuler') $invoice_file = 'invoice_eskul.php';
                elseif ($booking['jenis_booking'] === 'Turnamen') $invoice_file = 'invoice_turnamen.php';

                $booking_type_map = [
                    'Regular' => 'regular',
                    'Ekstrakurikuler' => 'eskul',
                    'Turnamen' => 'turnamen'
                ];
                $booking_type_value = $booking_type_map[$booking['jenis_booking']] ?? strtolower($booking['jenis_booking']);
                ?>
                <div class="bg-white border border-stone-200 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-2 pb-3 border-b border-stone-100">
                        <div>
                            <p class="text-xs text-stone-400 font-medium">#<?php echo $booking['id']; ?> &middot; <?php echo htmlspecialchars($booking['jenis_booking'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <h3 class="text-base font-bold text-stone-900 mt-0.5"><?php echo htmlspecialchars($booking['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                        <span class="px-2.5 py-1 rounded border text-[11px] font-medium whitespace-nowrap <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </div>

                    <div class="py-3 space-y-1.5 text-xs text-stone-600 tabular-nums">
                        <div class="flex justify-between">
                            <span class="text-stone-400">Tanggal</span>
                            <span class="font-medium text-stone-800"><?php echo date('d/m/Y', strtotime($booking['tanggal_jadwal'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-stone-400">Jam</span>
                            <span class="font-medium text-stone-800"><?php echo date('H:i', strtotime($booking['jam_mulai'])) . ' – ' . date('H:i', strtotime($booking['jam_selesai'])); ?> WIB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-stone-400">Metode</span>
                            <span class="font-medium text-stone-800"><?php echo htmlspecialchars($metode_text, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-stone-100 text-sm">
                            <span class="font-medium text-stone-600">Total</span>
                            <span class="font-bold text-green-800">Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <?php if($booking['status_pembayaran'] == 'pending'): ?>
                        <?php $dl = getBookingPaymentDeadline($booking['tanggal_booking'], $booking['tanggal_jadwal'], $booking['jam_mulai']); ?>
                        <p class="text-xs <?php echo $dl['is_urgent'] ? 'text-red-700 bg-red-50 border-red-200' : 'text-amber-800 bg-amber-50 border-amber-200'; ?> border rounded px-3 py-2 mb-3">
                            <?php if ($dl['is_urgent']): ?>
                                <strong>Booking Mendadak:</strong> Bayar &amp; konfirmasi admin maksimal <strong>15 menit</strong> (s.d. <?php echo $dl['deadline_formatted']; ?> WIB), jika tidak dibatalkan otomatis.
                            <?php else: ?>
                                Harap selesaikan pembayaran &amp; konfirmasi admin dalam 2 jam (s.d. <?php echo $dl['deadline_formatted']; ?> WIB).
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <div class="grid <?php echo $booking['status_pembayaran'] == 'pending' ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-2 pt-2 border-t border-stone-100">
                        <a href="<?php echo $invoice_file; ?>?booking_id=<?php echo $booking['id']; ?>&from=riwayat" 
                           class="py-2.5 px-3 text-center text-xs font-semibold rounded-md bg-stone-900 hover:bg-green-800 text-white transition-colors">
                            Lihat Invoice
                        </a>
                        <?php if ($booking['status_pembayaran'] == 'pending'): ?>
                            <button type="button"
                                    onclick='openCancelModal(<?php echo (int)$booking["id"]; ?>, <?php echo json_encode($booking_type_value); ?>, <?php echo json_encode("#" . $booking["id"] . " — " . $booking["nama_lapangan"] . " (" . $booking["jenis_booking"] . ")"); ?>)'
                                    class="w-full py-2.5 px-3 text-xs font-semibold rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors">
                                Batalkan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Table layout for desktop -->
        <div class="hidden md:block bg-white rounded-lg border border-stone-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 border-b border-stone-200">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Detail Booking</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Pembayaran</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-stone-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        <?php mysqli_data_seek($result, 0); while($booking = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $metode_text = '';
                            switch($booking['metode_pembayaran']) {
                                case 'transfer': $metode_text = 'Transfer Bank'; break;
                                case 'qris': $metode_text = 'QRIS'; break;
                                case 'cod': $metode_text = 'Bayar di Tempat (COD)'; break;
                                default: $metode_text = ucfirst($booking['metode_pembayaran']);
                            }

                            $status_class = 'bg-stone-100 text-stone-700 border-stone-200';
                            $status_text = ucfirst($booking['status_pembayaran']);
                            switch($booking['status_pembayaran']) {
                                case 'pending':
                                    $status_class = 'bg-amber-50 text-amber-800 border-amber-200';
                                    $status_text = 'Menunggu Pembayaran';
                                    break;
                                case 'dikonfirmasi':
                                    $status_class = 'bg-green-50 text-green-800 border-green-200';
                                    $status_text = 'Pembayaran Diterima';
                                    break;
                                case 'dibatalkan':
                                    $status_class = 'bg-red-50 text-red-700 border-red-200';
                                    $status_text = 'Dibatalkan';
                                    break;
                            }

                            $invoice_file = 'invoice.php';
                            if ($booking['jenis_booking'] === 'Ekstrakurikuler') $invoice_file = 'invoice_eskul.php';
                            elseif ($booking['jenis_booking'] === 'Turnamen') $invoice_file = 'invoice_turnamen.php';

                            $booking_type_map = [
                                'Regular' => 'regular',
                                'Ekstrakurikuler' => 'eskul',
                                'Turnamen' => 'turnamen'
                            ];
                            $booking_type_value = $booking_type_map[$booking['jenis_booking']] ?? strtolower($booking['jenis_booking']);
                            ?>
                            <tr class="hover:bg-stone-50/70 transition-colors">
                                <!-- Detail Booking -->
                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold text-stone-900">
                                        #<?php echo $booking['id']; ?> &mdash; <?php echo htmlspecialchars($booking['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="text-xs font-normal text-stone-400 ml-1">(<?php echo htmlspecialchars($booking['jenis_booking'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                    </p>
                                    <p class="text-xs text-stone-500 mt-1 tabular-nums">
                                        <?php echo date('d/m/Y', strtotime($booking['tanggal_jadwal'])); ?> &middot;
                                        <?php echo date('H:i', strtotime($booking['jam_mulai'])) . ' – ' . date('H:i', strtotime($booking['jam_selesai'])); ?> WIB
                                    </p>
                                </td>

                                <!-- Detail Pembayaran -->
                                <td class="px-5 py-4 align-top tabular-nums">
                                    <p class="font-bold text-stone-900">
                                        Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                                    </p>
                                    <p class="text-xs text-stone-500 mt-0.5"><?php echo htmlspecialchars($metode_text, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if($booking['metode_pembayaran'] == 'transfer'): ?>
                                        <p class="text-[11px] text-stone-400">SeaBank: 901245634730</p>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-4 align-top">
                                    <span class="inline-block px-2.5 py-1 rounded border text-xs font-medium whitespace-nowrap <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                    <?php if($booking['status_pembayaran'] == 'pending'): ?>
                                        <?php $dl = getBookingPaymentDeadline($booking['tanggal_booking'], $booking['tanggal_jadwal'], $booking['jam_mulai']); ?>
                                        <p class="text-[11px] <?php echo $dl['is_urgent'] ? 'text-red-700 font-medium' : 'text-stone-500'; ?> mt-1.5">
                                            <?php echo $dl['is_urgent']
                                                ? 'Batas 15 menit (s.d. ' . $dl['deadline_formatted'] . ' WIB)'
                                                : 'Batas 2 jam (s.d. ' . $dl['deadline_formatted'] . ' WIB)'; ?>
                                        </p>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="px-5 py-4 align-top text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <a href="<?php echo $invoice_file; ?>?booking_id=<?php echo $booking['id']; ?>&from=riwayat" 
                                           class="px-3 py-1.5 text-xs font-medium rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 transition-colors">
                                            Lihat Invoice
                                        </a>
                                        <?php if ($booking['status_pembayaran'] == 'pending'): ?>
                                            <button type="button"
                                                    onclick='openCancelModal(<?php echo (int)$booking["id"]; ?>, <?php echo json_encode($booking_type_value); ?>, <?php echo json_encode("#" . $booking["id"] . " — " . $booking["nama_lapangan"] . " (" . $booking["jenis_booking"] . ")"); ?>)'
                                                    class="px-3 py-1.5 text-xs font-medium rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors">
                                                Batalkan
                                            </button>
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
        <div class="bg-white rounded-lg border border-stone-200 p-8 text-center">
            <p class="text-sm text-stone-500">Anda belum memiliki riwayat booking.</p>
            <a href="index.php#lapangan-regular" class="inline-block mt-4 bg-stone-900 hover:bg-green-800 text-white text-sm font-semibold px-5 py-2.5 rounded-md transition-colors">
                Booking Sekarang
            </a>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mt-4 bg-red-50 border-l-4 border-red-700 text-red-900 px-4 py-3 text-sm">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- Informasi Pembayaran Transfer -->
    <div class="mt-8 bg-white rounded-lg border border-stone-200 p-5 sm:p-6">
        <h3 class="text-base font-bold text-stone-900 mb-3">Informasi Pembayaran Transfer</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pb-4 border-b border-stone-100 text-sm">
            <div>
                <p class="text-[11px] uppercase tracking-wider text-stone-400 font-semibold">Bank</p>
                <p class="font-semibold text-stone-900 mt-0.5">Bank SeaBank</p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-stone-400 font-semibold">No. Rekening</p>
                <p class="font-semibold text-stone-900 mt-0.5 tabular-nums">901245634730</p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-stone-400 font-semibold">Atas Nama</p>
                <p class="font-semibold text-stone-900 mt-0.5">Futsal Sayan Bekasi</p>
            </div>
        </div>
        <ul class="mt-4 text-xs text-stone-500 space-y-1">
            <li>&bull; Mohon transfer sesuai nominal total booking dan sertakan nomor invoice pada berita transfer.</li>
            <li>&bull; Konfirmasi pembayaran diproses maksimal 1x24 jam.</li>
            <li>&bull; Untuk pembayaran COD, harap datang minimal 20–30 menit sebelum jadwal main.</li>
        </ul>
    </div>
</div>

<!-- Modal Konfirmasi Pembatalan Booking -->
<div id="cancelBookingModal" class="fixed inset-0 bg-stone-900/50 hidden z-50 p-0 sm:p-4">
    <div class="min-h-full flex items-end sm:items-center justify-center">
        <div class="w-full sm:max-w-md bg-white border-t sm:border border-stone-200 rounded-t-2xl sm:rounded-lg shadow-xl p-6 text-center">
            <div class="w-10 h-1 bg-stone-300 rounded-full mx-auto mb-4 sm:hidden"></div>
            <img src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/images/cancel.svg"
                 alt="Batalkan Booking"
                 class="w-40 sm:w-44 h-auto mx-auto mb-4">
            <h3 class="text-lg font-bold text-stone-900">Apakah Anda ingin membatalkan booking?</h3>
            <p id="cancelBookingLabel" class="text-sm font-medium text-stone-700 mt-1.5"></p>
            <p class="text-xs text-stone-500 mt-1">Tindakan ini tidak dapat dibatalkan setelah dikonfirmasi.</p>

            <form action="cancel_booking.php" method="POST" class="mt-6 grid grid-cols-2 gap-2.5">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="booking_id" id="cancelBookingId" value="">
                <input type="hidden" name="booking_type" id="cancelBookingType" value="">
                <button type="button" onclick="closeCancelModal()"
                        class="py-2.5 px-4 rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 text-sm font-medium transition-colors">
                    Tidak, Kembali
                </button>
                <button type="submit"
                        class="py-2.5 px-4 rounded-md bg-red-700 hover:bg-red-800 text-white text-sm font-semibold transition-colors">
                    Ya, Batalkan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openCancelModal(bookingId, bookingType, label) {
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancelBookingType').value = bookingType;
    document.getElementById('cancelBookingLabel').textContent = label || '';
    document.getElementById('cancelBookingModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelBookingModal').classList.add('hidden');
}

document.getElementById('cancelBookingModal').addEventListener('click', function(e) {
    if (e.target === this || e.target === this.firstElementChild) {
        closeCancelModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
