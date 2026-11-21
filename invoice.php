<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_GET['booking_id'])) {
    header("Location: index.php");
    exit();
}

$booking_id = mysqli_real_escape_string($conn, $_GET['booking_id']);
$user_id = $_SESSION['user_id'];

// Jalankan auto-cancel sebelum mengambil data invoice agar status selalu akurat
autoCancelUnpaidBookings($conn);

// Mengambil data booking
$query = "SELECT b.*, u.nama as nama_user, u.telepon, u.alamat, 
          l.nama as nama_lapangan, l.harga_per_jam,
          b.bukti_pembayaran, b.tanggal_upload_bukti,
          b.status_pembayaran, b.metode_pembayaran,
          b.tanggal_booking, b.tanggal_main,
          b.jam_mulai, b.jam_selesai, b.total_harga,
          b.diskon_persen, b.alasan_diskon
          FROM booking b 
          JOIN users u ON b.user_id = u.id_user 
          JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
          WHERE b.id_booking = '$booking_id'" .
    (
        $_SESSION['role'] != 'admin' ? " AND b.user_id = '$user_id'" : ""
    );
$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header("Location: index.php");
    exit();
}

// Hitung harga sebelum diskon
$durasi = (strtotime($booking['jam_selesai']) - strtotime($booking['jam_mulai'])) / 3600;
$harga_sebelum_diskon = $durasi * $booking['harga_per_jam'];

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="invoice-sheet max-w-3xl mx-auto bg-white rounded-lg border border-stone-200 p-6 sm:p-8 lg:p-10">
    <!-- Header Invoice -->
    <div class="invoice-header flex flex-col sm:flex-row justify-between items-start gap-6 pb-6 border-b border-stone-200">
        <div>
            <span class="text-xs font-semibold uppercase tracking-widest text-green-800">Bukti Pemesanan</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-stone-900 tracking-tight mt-0.5">INVOICE</h1>
            <div class="mt-3 space-y-0.5 text-sm text-stone-600 tabular-nums">
                <p><span class="text-stone-400">No. Invoice:</span> <span class="font-semibold text-stone-900">INV-<?php echo str_pad($booking['id_booking'], 4, '0', STR_PAD_LEFT); ?></span></p>
                <p><span class="text-stone-400">Tanggal:</span> <span class="font-medium text-stone-800"><?php echo date('d/m/Y', strtotime($booking['tanggal_booking'])); ?></span></p>
            </div>
        </div>
        <div class="sm:text-right max-w-xs">
            <h2 class="text-lg font-bold text-stone-900">Futsal Sayan Bekasi</h2>
            <p class="text-xs text-stone-500 leading-relaxed mt-1">
                Jl. Raya Mustikasari No.34, RT.002/RW.004, Mustikasari, Kec. Mustika Jaya, Kota Bekasi, Jawa Barat 17157
            </p>
            <p class="text-xs text-stone-600 font-medium mt-1 tabular-nums">Telp: 0857-4851-5801</p>
        </div>
    </div>

    <!-- Informasi Pelanggan & Status -->
    <?php
    $status_class = '';
    $status_text = '';
    switch (strtolower($booking['status_pembayaran'])) {
        case 'pending':
        case 'menunggu_pembayaran':
        case 'belum_dibayar':
            $status_class = 'bg-amber-50 text-amber-800 border border-amber-200';
            $status_text = 'Menunggu Pembayaran';
            break;
        case 'dikonfirmasi':
            $status_class = 'bg-green-50 text-green-800 border border-green-200';
            $status_text = 'Pembayaran Diterima';
            break;
        case 'dibatalkan':
            $status_class = 'bg-red-50 text-red-700 border border-red-200';
            $status_text = 'Dibatalkan';
            break;
        case 'dp_dibayar':
            $status_class = 'bg-sky-50 text-sky-800 border border-sky-200';
            $status_text = 'DP Sudah Dibayar';
            break;
        default:
            $status_class = 'bg-stone-100 text-stone-700 border border-stone-200';
            $status_text = ucfirst($booking['status_pembayaran']);
    }

    $metode_label = '';
    switch ($booking['metode_pembayaran']) {
        case 'transfer': $metode_label = 'Transfer Bank'; break;
        case 'qris': $metode_label = 'QRIS'; break;
        case 'cod': $metode_label = 'Bayar di Tempat (COD)'; break;
        default: $metode_label = ucfirst($booking['metode_pembayaran']);
    }
    ?>
    <div class="invoice-meta grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-stone-200">
        <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-stone-400 mb-2">Informasi Pelanggan</h3>
            <div class="space-y-1 text-sm text-stone-700">
                <p class="font-semibold text-stone-900"><?php echo htmlspecialchars($booking['nama_user'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="tabular-nums">Telp: <?php echo htmlspecialchars($booking['telepon'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty(trim($booking['alamat'] ?? ''))): ?>
                    <p class="text-stone-500"><?php echo htmlspecialchars($booking['alamat'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="sm:text-right">
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-stone-400 mb-2">Pembayaran</h3>
            <p class="text-sm font-semibold text-stone-900 mb-2"><?php echo htmlspecialchars($metode_label, ENT_QUOTES, 'UTF-8'); ?></p>
            <span class="inline-block px-2.5 py-1 rounded text-xs font-medium <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>

    <!-- Detail Booking -->
    <div class="py-6 border-b border-stone-200">
        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-stone-400 mb-3">Detail Booking</h3>
        <div class="rounded-md border border-stone-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200 text-stone-500">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Deskripsi</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider w-44">Harga</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    <tr>
                        <td class="px-4 py-4 align-top">
                            <p class="font-semibold text-stone-900"><?php echo htmlspecialchars($booking['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="mt-1 text-xs text-stone-500 space-y-0.5 tabular-nums">
                                <p>Tanggal Main: <?php echo date('d/m/Y', strtotime($booking['tanggal_main'])); ?></p>
                                <p>Waktu: <?php echo date('H:i', strtotime($booking['jam_mulai'])) . ' – ' . date('H:i', strtotime($booking['jam_selesai'])); ?> WIB</p>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-right align-top font-medium text-stone-900 whitespace-nowrap tabular-nums">
                            Rp <?php echo number_format($harga_sebelum_diskon, 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Ringkasan Total -->
        <div class="mt-4 flex justify-end">
            <div class="w-full sm:w-72 space-y-2 text-sm tabular-nums">
                <?php if ($booking['diskon_persen'] > 0): ?>
                    <div class="flex justify-between text-stone-600">
                        <span>Subtotal</span>
                        <span>Rp <?php echo number_format($harga_sebelum_diskon, 0, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between text-green-800">
                        <span>
                            Diskon (<?php echo $booking['diskon_persen']; ?>%)
                            <?php if ($booking['alasan_diskon']): ?>
                                <span class="block text-xs text-stone-400"><?php echo htmlspecialchars($booking['alasan_diskon'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="whitespace-nowrap">- Rp <?php echo number_format($harga_sebelum_diskon - $booking['total_harga'], 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between items-center pt-2 border-t border-stone-200 text-base font-bold text-stone-900">
                    <span>Total</span>
                    <span class="text-green-800 whitespace-nowrap">Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Instruksi Metode Pembayaran -->
    <div class="py-6">
        <?php if (strtolower($booking['status_pembayaran']) === 'pending'): ?>
            <?php $dl = getBookingPaymentDeadline($booking['tanggal_booking'], $booking['tanggal_main'], $booking['jam_mulai']); ?>
            <div class="mb-5 p-4 rounded-md border no-print <?php echo $dl['is_urgent'] ? 'bg-red-50 border-red-200 text-red-900' : 'bg-amber-50 border-amber-200 text-amber-900'; ?>">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="text-xs sm:text-sm">
                        <p class="font-bold">
                            <?php echo $dl['is_urgent'] ? 'Batas Pembayaran Mendadak: 15 Menit' : 'Batas Waktu Pembayaran: 2 Jam'; ?>
                        </p>
                        <p class="mt-0.5 opacity-90">
                            Harap selesaikan pembayaran dan konfirmasi admin sebelum <strong><?php echo $dl['deadline_formatted']; ?> WIB</strong>, atau booking akan dibatalkan otomatis.
                        </p>
                    </div>
                    <div class="text-left sm:text-right flex-shrink-0">
                        <span class="text-[11px] uppercase tracking-wider opacity-75 block">Sisa Waktu</span>
                        <span id="paymentCountdown" data-remaining="<?php echo (int)$dl['remaining_seconds']; ?>" class="text-lg font-extrabold tabular-nums">--:--</span>
                    </div>
                </div>
            </div>
            <script>
            (function() {
                var el = document.getElementById('paymentCountdown');
                if (!el) return;
                var sec = parseInt(el.getAttribute('data-remaining'), 10) || 0;
                function render() {
                    if (sec <= 0) {
                        el.textContent = '00:00';
                        setTimeout(function() { window.location.reload(); }, 1200);
                        return;
                    }
                    var h = Math.floor(sec / 3600);
                    var m = Math.floor((sec % 3600) / 60);
                    var s = sec % 60;
                    var mm = String(m).padStart(2, '0');
                    var ss = String(s).padStart(2, '0');
                    el.textContent = h > 0 ? (String(h).padStart(2, '0') + ':' + mm + ':' + ss) : (mm + ':' + ss);
                    sec--;
                    setTimeout(render, 1000);
                }
                render();
            })();
            </script>
        <?php endif; ?>
        <?php if ($booking['metode_pembayaran'] == 'transfer'): ?>
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md">
                <p class="font-semibold text-sm text-stone-900 mb-2">Informasi Transfer Bank</p>
                <div class="space-y-1 text-sm text-stone-600">
                    <p><i class="fas fa-university w-5 text-green-800"></i>Bank SeaBank</p>
                    <p><i class="fas fa-credit-card w-5 text-green-800"></i>No. Rekening: <span class="font-semibold text-stone-900 tabular-nums">901245634730</span></p>
                    <p><i class="fas fa-user w-5 text-green-800"></i>A.n: Futsal Sayan Bekasi</p>
                </div>
                <p class="mt-2.5 text-xs text-stone-500">Mohon transfer sesuai nominal dan sertakan nomor invoice pada berita transfer.</p>
            </div>
        <?php elseif ($booking['metode_pembayaran'] == 'qris'): ?>
            <div class="p-5 bg-stone-50 border border-stone-200 rounded-md text-center no-print print:hidden">
                <p class="font-semibold text-sm text-stone-900 mb-1">Scan QRIS untuk Pembayaran</p>
                <p class="text-xs text-stone-500 mb-3">Mendukung OVO, GoPay, DANA, ShopeePay, dan Mobile Banking</p>
                <div class="flex justify-center mb-3">
                    <img src="assets/images/qris/qris-code.png" alt="QRIS Code"
                        class="w-40 h-40 sm:w-48 sm:h-48 bg-white border border-stone-200 rounded-md p-2">
                </div>
                <p class="text-xs text-stone-500">
                    Nominal yang harus dibayar:
                    <span class="block font-bold text-base text-stone-900 mt-0.5 tabular-nums">Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?></span>
                </p>
                <ol class="mt-3 text-xs text-stone-500 space-y-1 max-w-sm mx-auto text-left list-decimal list-inside">
                    <li>Buka aplikasi e-wallet atau mobile banking Anda</li>
                    <li>Pilih menu Scan QR / QRIS dan scan kode di atas</li>
                    <li>Masukkan nominal sesuai tagihan, sertakan No. Invoice & nama</li>
                </ol>
            </div>
        <?php elseif ($booking['metode_pembayaran'] == 'cod'): ?>
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-md">
                <p class="font-semibold text-sm text-stone-900 mb-2">Informasi Pembayaran di Tempat</p>
                <ul class="space-y-1 text-stone-600 text-xs sm:text-sm">
                    <li>&bull; Harap datang 20 menit sebelum jadwal main.</li>
                    <li>&bull; Siapkan uang pas sebesar <span class="font-semibold text-stone-900 tabular-nums">Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?></span>.</li>
                    <li>&bull; Booking akan dibatalkan jika tidak hadir 15 menit setelah jadwal mulai.</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (
            in_array(strtolower($booking['status_pembayaran']), ['pending', 'menunggu_pembayaran', 'belum_dibayar']) &&
            ($booking['metode_pembayaran'] == 'transfer' || $booking['metode_pembayaran'] == 'qris')
        ): ?>
            <!-- Form Upload Bukti Pembayaran -->
            <div class="mt-5 p-5 bg-white border border-stone-200 rounded-md no-print">
                <h4 class="font-bold text-sm text-stone-900 mb-3">Upload Bukti Pembayaran</h4>
                <?php if (!empty($booking['bukti_pembayaran'])): ?>
                    <div class="mb-4 pb-4 border-b border-stone-100 no-print">
                        <p class="text-xs text-stone-500 mb-2">Bukti pembayaran saat ini:</p>
                        <div class="flex justify-center bg-stone-50 border border-stone-200 rounded-md p-3">
                            <img src="uploads/bukti_pembayaran/<?php echo htmlspecialchars($booking['bukti_pembayaran'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Bukti Pembayaran" class="max-h-60 w-auto rounded object-contain bukti-pembayaran">
                        </div>
                        <p class="text-xs text-stone-400 mt-1.5 text-center tabular-nums">
                            Diupload pada: <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_upload_bukti'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <form action="upload_bukti.php" method="POST" enctype="multipart/form-data" class="space-y-3 no-print">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking_id, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="booking_type" value="regular">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">
                            <?php echo empty($booking['bukti_pembayaran']) ? 'Pilih file bukti pembayaran' : 'Update bukti pembayaran'; ?>
                        </label>
                        <input type="file" name="bukti_pembayaran" accept="image/*" required
                            class="w-full px-3 py-2 border border-stone-300 rounded-md text-xs text-stone-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-stone-300 file:text-xs file:font-medium file:bg-white file:text-stone-700 hover:file:bg-stone-50">
                        <p class="text-xs text-stone-400 mt-1">Format: JPG, JPEG, PNG. Maksimal 2MB.</p>
                    </div>
                    <button type="submit"
                        class="w-full sm:w-auto bg-green-900 hover:bg-green-800 text-white font-semibold px-5 py-2.5 rounded-md text-xs sm:text-sm transition-colors">
                        <?php echo empty($booking['bukti_pembayaran']) ? 'Upload Bukti Pembayaran' : 'Update Bukti Pembayaran'; ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Note & Actions -->
    <div class="pt-5 border-t border-stone-200 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <p class="text-xs text-stone-500 text-center sm:text-left">
            Invoice ini adalah bukti pembayaran yang sah. Terima kasih telah bermain di Futsal Sayan Bekasi.
        </p>
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2 flex-shrink-0 no-print">
            <?php if ($_SESSION['role'] == 'admin' && $booking['status_pembayaran'] != 'dikonfirmasi' && $booking['status_pembayaran'] != 'dibatalkan'): ?>
                <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dikonfirmasi&type=regular&redirect=dashboard_regular"
                    class="inline-flex items-center whitespace-nowrap px-3.5 py-2 bg-green-50 text-green-800 border border-green-200 rounded-md hover:bg-green-100 text-xs font-medium transition-colors">
                    <i class="fas fa-check mr-1.5"></i>Konfirmasi
                </a>
                <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dibatalkan&type=regular&redirect=dashboard_regular"
                    class="inline-flex items-center whitespace-nowrap px-3.5 py-2 bg-red-50 text-red-700 border border-red-200 rounded-md hover:bg-red-100 text-xs font-medium transition-colors"
                    onclick="return confirm('Yakin ingin membatalkan booking ini?')">
                    <i class="fas fa-times mr-1.5"></i>Batalkan
                </a>
            <?php endif; ?>
            <button onclick="window.print()"
                class="inline-flex items-center justify-center whitespace-nowrap bg-stone-900 hover:bg-green-800 text-white px-4 py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors">
                <i class="fas fa-print mr-2"></i>Cetak Invoice
            </button>
        </div>
    </div>

    <!-- Tombol Kembali -->
    <div class="mt-5 flex justify-end no-print">
        <?php if ($_SESSION['role'] != 'admin'): ?>
            <?php
            $from_riwayat = isset($_GET['from']) && $_GET['from'] === 'riwayat';
            $back_url = $from_riwayat ? 'riwayat.php' : 'index.php';
            ?>
            <a href="<?php echo $back_url; ?>"
                class="inline-flex items-center text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors">
                &larr; Kembali ke <?php echo $from_riwayat ? 'Riwayat' : 'Beranda'; ?>
            </a>
        <?php else: ?>
            <a href="admin/dashboard.php?tab=booking_regular"
                class="inline-flex items-center text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors">
                &larr; Kembali ke Dashboard
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Style khusus untuk print -->
<style type="text/css" media="print">
    @page {
        size: A4;
        margin: 14mm;
    }

    body {
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    nav, footer, .wa-float, .no-print, .bukti-pembayaran, button {
        display: none !important;
    }

    main {
        padding: 0 !important;
        margin: 0 !important;
        max-width: none !important;
    }

    .invoice-sheet {
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    .invoice-header {
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
    }

    .invoice-header > div:last-child {
        text-align: right !important;
    }

    .invoice-meta {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .invoice-meta > div:last-child {
        text-align: right !important;
    }
</style>

<?php include 'includes/footer.php'; ?>