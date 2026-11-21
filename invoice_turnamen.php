<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any existing session messages
unset($_SESSION['success']);
unset($_SESSION['error']);

if (!isset($_SESSION['user_id']) || !isset($_GET['booking_id'])) {
    header("Location: index.php");
    exit();
}

$booking_id = mysqli_real_escape_string($conn, $_GET['booking_id']);
error_log("Invoice turnamen requested for booking_id: " . $booking_id);
$user_id = $_SESSION['user_id'];

// Mengambil data booking
$query = "SELECT bt.*, u.nama as nama_user, u.telepon, u.alamat, 
          l.nama as nama_lapangan, 
          hp.harga, hp.keterangan,
          CASE 
              WHEN bt.paket LIKE '%sore' THEN 'sore'
              ELSE 'malam'
          END as jenis
          FROM booking_turnamen bt 
          JOIN users u ON bt.user_id = u.id_user 
          JOIN lapangan l ON bt.lapangan_id = l.id_lapangan 
          JOIN harga_paket hp ON hp.tipe = bt.paket
          WHERE bt.id_booking_turnamen = '$booking_id'" . 
          (
            $_SESSION['role'] != 'admin' ? " AND bt.user_id = '$user_id'" : ""
          );
$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    error_log("Invoice turnamen booking not found for booking_id: " . $booking_id);
    header("Location: index.php");
    exit();
}

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="invoice-sheet max-w-3xl mx-auto bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 lg:p-10">
    <!-- Header Invoice -->
    <div class="invoice-header flex flex-col sm:flex-row justify-between items-start gap-6 pb-6 border-b border-slate-200">
        <div>
            <span class="text-xs font-semibold uppercase tracking-widest text-emerald-600">Paket Turnamen</span>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight mt-0.5">INVOICE TURNAMEN</h1>
            <div class="mt-3 space-y-0.5 text-sm text-slate-600">
                <p><span class="text-slate-400">No. Invoice:</span> <span class="font-semibold text-slate-800">TRN-<?php echo str_pad($booking['id_booking_turnamen'], 4, '0', STR_PAD_LEFT); ?></span></p>
                <p><span class="text-slate-400">Tanggal:</span> <span class="font-medium text-slate-800"><?php echo date('d/m/Y', strtotime($booking['tanggal_booking'])); ?></span></p>
            </div>
        </div>
        <div class="sm:text-right max-w-xs">
            <h2 class="text-lg font-bold text-slate-900">Futsal Sayan Bekasi</h2>
            <p class="text-xs text-slate-500 leading-relaxed mt-1">
                Jl. Raya Mustikasari No.34, RT.002/RW.004, Mustikasari, Kec. Mustika Jaya, Kota Bekasi, Jawa Barat 17157
            </p>
            <p class="text-xs text-slate-600 font-medium mt-1">Telp: 0857-4851-5801</p>
        </div>
    </div>

    <!-- Informasi Panitia & Status -->
    <?php
    $status_class = '';
    $status_text = '';
    switch($booking['status_pembayaran']) {
        case 'pending':
            $status_class = 'bg-amber-50 text-amber-700 border border-amber-200';
            $status_text = 'Menunggu Pembayaran';
            break;
        case 'dikonfirmasi':
            $status_class = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
            $status_text = 'Lunas';
            break;
        case 'dibatalkan':
            $status_class = 'bg-red-50 text-red-700 border border-red-200';
            $status_text = 'Dibatalkan';
            break;
        default:
            $status_class = 'bg-slate-100 text-slate-700 border border-slate-200';
            $status_text = ucfirst($booking['status_pembayaran']);
    }
    ?>
    <div class="invoice-meta grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-slate-200">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Informasi Panitia</h3>
            <div class="space-y-1 text-sm text-slate-700">
                <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($booking['nama_turnamen'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>PJ: <?php echo htmlspecialchars($booking['nama_panitia'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Telp: <?php echo htmlspecialchars($booking['telepon_panitia'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty(trim($booking['alamat'] ?? ''))): ?>
                    <p class="text-slate-500"><?php echo htmlspecialchars($booking['alamat'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="sm:text-right">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Status Pembayaran</h3>
            <p class="text-sm font-semibold text-slate-900 mb-2">Transfer Bank</p>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>

    <!-- Detail Booking -->
    <div class="py-6 border-b border-slate-200">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Detail Booking</h3>
        <div class="rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <th class="px-4 py-3 text-left font-semibold">Deskripsi</th>
                        <th class="px-4 py-3 text-right font-semibold w-44">Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-4 py-4 align-top">
                            <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($booking['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?> - Paket Turnamen</p>
                            <div class="mt-1 text-xs text-slate-500 space-y-0.5">
                                <p>Durasi: <?php echo htmlspecialchars((string)$booking['durasi_hari'], ENT_QUOTES, 'UTF-8'); ?> hari</p>
                                <p>Mulai: <?php echo date('d/m/Y', strtotime($booking['tanggal_mulai'])); ?></p>
                                <p>Waktu: <?php echo date('H:i', strtotime($booking['jam_mulai'])) . ' - ' . date('H:i', strtotime($booking['jam_selesai'])); ?> WIB</p>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-right align-top font-medium text-slate-900 whitespace-nowrap">
                            Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end">
            <div class="w-full sm:w-72 flex justify-between items-center pt-2 border-t border-slate-200 text-base font-bold text-slate-900">
                <span>Total</span>
                <span class="text-emerald-700 whitespace-nowrap">Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>

    <!-- Bukti Pembayaran (Screen only) -->
    <?php if (!empty($booking['bukti_pembayaran']) && $booking['status_pembayaran'] != 'pending'): ?>
    <div class="py-6 border-b border-slate-200 no-print">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Bukti Pembayaran</h3>
        <a href="uploads/bukti_pembayaran/<?php echo htmlspecialchars($booking['bukti_pembayaran'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
            <img src="uploads/bukti_pembayaran/<?php echo htmlspecialchars($booking['bukti_pembayaran'], ENT_QUOTES, 'UTF-8'); ?>" alt="Bukti Pembayaran" class="max-w-xs border border-slate-200 rounded-xl shadow-sm mb-2">
        </a>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <a href="uploads/bukti_pembayaran/<?php echo htmlspecialchars($booking['bukti_pembayaran'], ENT_QUOTES, 'UTF-8'); ?>" download class="text-emerald-600 hover:underline font-medium"><i class="fas fa-download mr-1"></i>Download Bukti</a>
            <span>Upload: <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_upload_bukti'])); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($booking['status_pembayaran'] == 'pending'): ?>
    <div class="py-6 no-print">
        <div class="p-5 bg-white border border-stone-200 rounded-md">
            <h4 class="font-bold text-sm text-stone-900 mb-3">Upload Bukti Pembayaran</h4>
            <?php if (!empty($booking['bukti_pembayaran'])): ?>
                <div class="mb-4 pb-4 border-b border-stone-100">
                    <p class="text-xs text-stone-500 mb-2">Bukti pembayaran saat ini:</p>
                    <div class="flex justify-center bg-stone-50 border border-stone-200 rounded-md p-3">
                        <img src="uploads/bukti_pembayaran/<?php echo htmlspecialchars($booking['bukti_pembayaran'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="Bukti Pembayaran" 
                             class="max-h-60 w-auto rounded object-contain">
                    </div>
                    <p class="text-xs text-stone-400 mt-1.5 text-center tabular-nums">
                        Diupload pada: <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_upload_bukti'])); ?>
                    </p>
                </div>
            <?php endif; ?>
            <form action="upload_bukti.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking_id, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="booking_type" value="turnamen">
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
    </div>
    <?php endif; ?>

    <!-- Footer Note & Actions -->
    <div class="pt-5 border-t border-stone-200 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <p class="text-xs text-stone-500 text-center sm:text-left">
            Invoice ini adalah bukti pembayaran yang sah. Terima kasih telah bermain di Futsal Sayan Bekasi.
        </p>
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2 flex-shrink-0 no-print">
            <?php if ($_SESSION['role'] == 'admin' && $booking['status_pembayaran'] == 'pending'): ?>
                <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dikonfirmasi&type=turnamen&redirect=dashboard_turnamen" 
                   class="inline-flex items-center whitespace-nowrap px-3.5 py-2 bg-green-50 text-green-800 border border-green-200 rounded-md hover:bg-green-100 text-xs font-medium transition-colors">
                    <i class="fas fa-check mr-1.5"></i>Konfirmasi Lunas
                </a>
                <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dibatalkan&type=turnamen&redirect=dashboard_turnamen" 
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
            <a href="<?php echo $back_url; ?>" class="inline-flex items-center text-sm font-medium text-slate-600 hover:text-emerald-600 transition-colors">
                <i class="fas fa-arrow-left mr-2 text-xs"></i>Kembali ke <?php echo $from_riwayat ? 'Riwayat' : 'Beranda'; ?>
            </a>
        <?php else: ?>
            <a href="admin/dashboard.php?tab=booking_turnamen" class="inline-flex items-center text-sm font-medium text-slate-600 hover:text-emerald-600 transition-colors">
                <i class="fas fa-arrow-left mr-2 text-xs"></i>Kembali ke Dashboard
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

    nav, footer, .wa-float, .no-print, button {
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
