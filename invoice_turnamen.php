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

<div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-8 my-8">
    <!-- Header Invoice -->
    <div class="border-b pb-4 mb-4">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-green-600">INVOICE PAKET TURNAMEN</h1>
                <p class="text-gray-600">No. Invoice: TRN-<?php echo str_pad($booking['id_booking_turnamen'], 4, '0', STR_PAD_LEFT); ?></p>
                <p class="text-gray-600">Tanggal: <?php echo date('d/m/Y', strtotime($booking['tanggal_booking'])); ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold">Futsal Sayan Bekasi</h2>
                <p class="text-gray-600">Jl. Raya Mustikasari No.34, RT.002/RW.004, Mustikasari, Kec. Mustika Jaya, Kota Bks, Jawa Barat 17157</p>
                <p class="text-gray-600">Bekasi</p>
                <p class="text-gray-600">Telp: 0857-4851-5801</p>
            </div>
        </div>
    </div>

    <!-- Informasi Panitia -->
    <div class="mb-6">
        <h3 class="font-bold mb-2">Informasi Panitia:</h3>
        <p>Nama Turnamen: <?php echo $booking['nama_turnamen']; ?></p>
        <p>Penanggung Jawab: <?php echo $booking['nama_panitia']; ?></p>
        <p>Telepon: <?php echo $booking['telepon_panitia']; ?></p>
        <p>Alamat: <?php echo $booking['alamat']; ?></p>
    </div>

    <!-- Detail Booking -->
    <div class="mb-6">
        <h3 class="font-bold mb-2">Detail Booking:</h3>
        <table class="w-full mb-4">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left">Deskripsi</th>
                    <th class="px-4 py-2 text-right">Harga</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="px-4 py-2 border-b">
                        <p class="font-semibold"><?php echo $booking['nama_lapangan']; ?> - Paket Turnamen</p>
                        <p class="text-sm text-gray-600">
                            Durasi: <?php echo $booking['durasi_hari']; ?> hari<br>
                            Mulai: <?php echo date('d/m/Y', strtotime($booking['tanggal_mulai'])); ?><br>
                            Waktu: <?php echo date('H:i', strtotime($booking['jam_mulai'])); ?> - 
                                  <?php echo date('H:i', strtotime($booking['jam_selesai'])); ?>
                        </p>
                    </td>
                    <td class="px-4 py-2 text-right border-b">
                        Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="font-bold text-lg">
                    <td class="px-4 py-2 text-right">Total:</td>
                    <td class="px-4 py-2 text-right">
                        Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Status Pembayaran -->
    <div class="mb-6">
        <h3 class="font-bold mb-2">Status Pembayaran:</h3>
        <?php 
        $status_class = '';
        $status_text = '';
        
        switch($booking['status_pembayaran']) {
            case 'pending':
                $status_class = 'bg-yellow-100 text-yellow-800';
                $status_text = 'Menunggu Pembayaran';
                break;
            case 'dikonfirmasi':
                $status_class = 'bg-green-100 text-green-800';
                $status_text = 'Lunas';
                break;
            case 'dibatalkan':
                $status_class = 'bg-red-100 text-red-800';
                $status_text = 'Dibatalkan';
                break;
        }
        ?>
        <span class="px-3 py-1 rounded-full <?php echo $status_class; ?>">
            <?php echo $status_text; ?>
        </span>
    </div>

    <!-- Bukti Pembayaran -->
    <?php if (!empty($booking['bukti_pembayaran'])): ?>
    <div class="mb-6 no-print">
        <h3 class="font-bold mb-2">Bukti Pembayaran:</h3>
        <a href="uploads/bukti_pembayaran/<?php echo $booking['bukti_pembayaran']; ?>" target="_blank">
            <img src="uploads/bukti_pembayaran/<?php echo $booking['bukti_pembayaran']; ?>" alt="Bukti Pembayaran" class="max-w-xs border rounded shadow mb-2">
        </a>
        <div>
            <a href="uploads/bukti_pembayaran/<?php echo $booking['bukti_pembayaran']; ?>" download class="text-blue-600 hover:underline text-sm"><i class="fas fa-download mr-1"></i>Download Bukti</a>
        </div>
        <div class="text-xs text-gray-500 mt-1">Upload: <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_upload_bukti'])); ?></div>
    </div>
    <?php endif; ?>

    <?php if ($booking['status_pembayaran'] == 'pending'): ?>
    <div class="mt-4 p-3 sm:p-4 bg-gray-50 rounded no-print">
        <h4 class="font-semibold mb-3 text-sm sm:text-base">Upload Bukti Pembayaran:</h4>
        <?php if (!empty($booking['bukti_pembayaran'])): ?>
            <div class="mb-4">
                <p class="text-xs sm:text-sm text-gray-600 mb-2">Bukti pembayaran sudah diupload:</p>
                <div class="flex justify-center">
                    <img src="uploads/bukti_pembayaran/<?php echo $booking['bukti_pembayaran']; ?>" 
                         alt="Bukti Pembayaran" 
                         class="max-w-full sm:max-w-xs rounded border">
                </div>
                <p class="text-xs text-gray-500 mt-1 text-center">
                    Diupload pada: <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_upload_bukti'])); ?>
                </p>
            </div>
        <?php endif; ?>
        <form action="upload_bukti.php" method="POST" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            <input type="hidden" name="booking_type" value="turnamen">
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                    <?php echo empty($booking['bukti_pembayaran']) ? 'Pilih file bukti pembayaran:' : 'Update bukti pembayaran:'; ?>
                </label>
                <input type="file" 
                       name="bukti_pembayaran" 
                       accept="image/*"
                       required
                       class="w-full px-2 sm:px-3 py-2 border rounded-md text-xs sm:text-sm">
                <p class="text-xs text-gray-500 mt-1">Format yang diterima: JPG, JPEG, PNG. Maksimal 2MB</p>
            </div>
            <button type="submit" 
                    class="w-full sm:w-auto bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-xs sm:text-sm">
                <?php echo empty($booking['bukti_pembayaran']) ? 'Upload Bukti Pembayaran' : 'Update Bukti Pembayaran'; ?>
            </button>
        </form>
    </div>
<?php endif; ?>

    <!-- Admin Actions & Print Button -->
    <div class="flex justify-between items-center mt-8 pt-4 border-t">
        <div class="flex items-center space-x-4">
            <p class="text-sm text-gray-600">
                Invoice ini adalah bukti pembayaran yang sah.
            </p>
            <?php if ($_SESSION['role'] == 'admin' && $booking['status_pembayaran'] == 'pending'): ?>
                <div class="flex space-x-4">
                    <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dikonfirmasi&type=turnamen&redirect=dashboard_turnamen" 
                       class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                        Konfirmasi Lunas
                    </a>
                    <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dibatalkan&type=turnamen&redirect=dashboard_turnamen" 
                       class="inline-flex items-center px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
                       onclick="return confirm('Yakin ingin membatalkan booking ini?')">
                        Batalkan
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Cetak Invoice
        </button>
    </div>

    <!-- Tombol Kembali ke Halaman Utama untuk User -->
    <?php if ($_SESSION['role'] != 'admin'): ?>
    <div class="mt-8 flex justify-end no-print">
        <?php 
        $from_riwayat = isset($_GET['from']) && $_GET['from'] === 'riwayat';
        $back_url = $from_riwayat ? 'riwayat.php' : 'index.php';
        ?>
        <a href="<?php echo $back_url; ?>" class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-6 rounded transition duration-200">
            <i class="fas fa-arrow-left mr-2"></i>Kembali ke <?php echo $from_riwayat ? 'Riwayat' : 'Halaman Utama'; ?>
        </a>
    </div>
    <?php endif; ?>
    <?php if ($_SESSION['role'] == 'admin'): ?>
<div class="mt-8 flex justify-end no-print">
    <a href="admin/dashboard.php?tab=booking_turnamen" class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-6 rounded transition duration-200">
        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dashboard
    </a>
</div>
<?php endif; ?>
</div>

<!-- Style khusus untuk print -->
<style type="text/css" media="print">
    @page {
        size: auto;
        margin: 0mm;
    }
    
    nav, button, .container > *:not(.max-w-3xl) {
        display: none !important;
    }
    
    .max-w-3xl {
        max-width: none !important;
        margin: 0 !important;
        padding: 20px !important;
    }
    
    body {
        background: white !important;
    }

    .no-print { display: none !important; }
</style>

<?php include 'includes/footer.php'; ?>
