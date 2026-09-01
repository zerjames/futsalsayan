<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['booking_id'])) {
    // Redirect to a more appropriate page or show an error message
    echo "<script>alert('Booking ID tidak ditemukan.'); window.location.href = 'index.php';</script>";
    exit();
}

$booking_id = mysqli_real_escape_string($conn, $_GET['booking_id']);
$user_id = $_SESSION['user_id'];

$query = "SELECT be.*, u.nama as nama_user, u.telepon, u.alamat, 
          l.nama as nama_lapangan, hp.harga
          FROM booking_eskul be 
          JOIN users u ON be.user_id = u.id_user 
          JOIN lapangan l ON be.lapangan_id = l.id_lapangan 
          JOIN harga_paket hp ON hp.tipe = be.paket
          WHERE be.id_booking_eskul = '$booking_id'" . 
          (
            $_SESSION['role'] != 'admin' ? " AND be.user_id = '$user_id'" : ""
          );
$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header("Location: index.php");
    exit();
}

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-4 sm:p-6 lg:p-8 my-4 sm:my-8">
    <!-- Header Invoice -->
    <div class="border-b pb-4 mb-4">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start space-y-4 lg:space-y-0">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-green-600">INVOICE PAKET ESKUL</h1>
                <p class="text-sm sm:text-base text-gray-600">No. Invoice: ESK-<?php echo str_pad($booking['id_booking_eskul'], 4, '0', STR_PAD_LEFT); ?></p>
                <p class="text-sm sm:text-base text-gray-600">Tanggal: <?php echo date('d/m/Y', strtotime($booking['tanggal_booking'])); ?></p>
            </div>
            <div class="text-left lg:text-right">
                <h2 class="text-lg sm:text-xl font-bold">Futsal Sayan Bekasi</h2>
                <p class="text-xs sm:text-sm text-gray-600">Jl. Raya Mustikasari No.34, RT.002/RW.004, Mustikasari, Kec. Mustika Jaya, Kota Bks, Jawa Barat 17157</p>
                <p class="text-xs sm:text-sm text-gray-600">Bekasi</p>
                <p class="text-xs sm:text-sm text-gray-600">Telp: 0857-4851-5801</p>
            </div>
        </div>
    </div>

    <!-- Informasi Pelanggan -->
    <div class="mb-6">
        <h3 class="font-bold mb-2 text-sm sm:text-base">Informasi Sekolah:</h3>
        <div class="space-y-1 text-sm sm:text-base">
            <p><span class="font-medium">Nama Sekolah:</span> <?php echo htmlspecialchars($booking['nama_sekolah']); ?></p>
            <p><span class="font-medium">Guru Penanggung Jawab:</span> <?php echo htmlspecialchars($booking['nama_guru']); ?></p>
            <p><span class="font-medium">Telepon:</span> <?php echo htmlspecialchars($booking['telepon_guru']); ?></p>
        </div>
    </div>

    <!-- Detail Booking -->
    <div class="mb-6">
        <h3 class="font-bold mb-2 text-sm sm:text-base">Detail Booking:</h3>
        <div class="overflow-x-auto">
            <table class="w-full mb-4 text-sm sm:text-base">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-2 sm:px-4 py-2 text-left">Deskripsi</th>
                        <th class="px-2 sm:px-4 py-2 text-right">Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-2 sm:px-4 py-2 border-b">
                            <p class="font-semibold"><?php echo $booking['nama_lapangan']; ?> - Paket Eskul</p>
                            <p class="text-xs sm:text-sm text-gray-600">
                                Durasi: <?php echo $booking['durasi_bulan']; ?> bulan<br>
                                Mulai: <?php echo date('d/m/Y', strtotime($booking['tanggal_mulai'])); ?><br>
                                Jam Latihan: <?php echo date('H:i', strtotime($booking['jam_mulai'])); ?> - 
                                           <?php echo date('H:i', strtotime($booking['jam_mulai']) + (3 * 3600)); ?>
                            </p>
                        </td>
                        <td class="px-2 sm:px-4 py-2 text-right border-b">
                            Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-bold text-base sm:text-lg">
                        <td class="px-2 sm:px-4 py-2 text-right">Total:</td>
                        <td class="px-2 sm:px-4 py-2 text-right">
                            Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Status Pembayaran -->
    <div class="mb-6">
        <h3 class="font-bold mb-2 text-sm sm:text-base">Status Pembayaran:</h3>
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
                $status_text = 'Pembayaran Diterima';
                break;
            case 'dibatalkan':
                $status_class = 'bg-red-100 text-red-800';
                $status_text = 'Dibatalkan';
                break;
        }
        ?>
        <span class="px-3 py-1 rounded-full text-sm <?php echo $status_class; ?>">
            <?php echo $status_text; ?>
        </span>

        <?php if ($booking['status_pembayaran'] == 'pending'): ?>
            <!-- Informasi Pembayaran -->
            <div class="mt-4 p-3 sm:p-4 bg-gray-50 rounded">
                <h4 class="font-semibold mb-3 text-sm sm:text-base">Informasi Pembayaran:</h4>
                <div class="space-y-1 text-sm">
                    <p><i class="fas fa-university mr-2"></i>Bank SeaBank</p>
                    <p><i class="fas fa-credit-card mr-2"></i>No. Rekening: 901245634730</p>
                    <p><i class="fas fa-user mr-2"></i>A.n: Futsal Sayan Bekasi</p>
                </div>
                <p class="mt-2 text-xs sm:text-sm text-gray-600">Mohon transfer sesuai nominal dan sertakan nomor invoice pada berita transfer</p>
                
                <!-- Form Upload Bukti -->
                <?php if (!empty($booking['bukti_pembayaran'])): ?>
                    <div class="mb-4 no-print">
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
                
                <form action="upload_bukti.php" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3 no-print">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="hidden" name="booking_type" value="eskul">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                            <?php echo empty($booking['bukti_pembayaran']) ? 'Upload Bukti Pembayaran:' : 'Update Bukti Pembayaran:'; ?>
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
    </div>

    <!-- Admin Actions & Print Button -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mt-6 sm:mt-8 pt-4 border-t space-y-4 sm:space-y-0">
        <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
            <p class="text-xs sm:text-sm text-gray-600">
                Invoice ini adalah bukti pembayaran yang sah.
            </p>
            <?php if ($_SESSION['role'] == 'admin' && $booking['status_pembayaran'] != 'dikonfirmasi' && $booking['status_pembayaran'] != 'dibatalkan'): ?>
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dikonfirmasi&type=eskul&redirect=dashboard_eskul" 
                       class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-xs sm:text-sm">
                        <i class="fas fa-check mr-2"></i>Konfirmasi
                    </a>
                    <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking_id; ?>&status=dibatalkan&type=eskul&redirect=dashboard_eskul" 
                       class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-xs sm:text-sm"
                       onclick="return confirm('Yakin ingin membatalkan booking ini?')">
                        <i class="fas fa-times mr-2"></i>Batalkan
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <button onclick="window.print()" class="w-full sm:w-auto bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-xs sm:text-sm">
            <i class="fas fa-print mr-2"></i> Cetak Invoice
        </button>
    </div>

    <!-- Tombol Kembali ke Halaman Utama untuk User -->
    <?php if ($_SESSION['role'] != 'admin'): ?>
    <div class="mt-6 sm:mt-8 flex justify-center sm:justify-end no-print">
        <?php 
        $from_riwayat = isset($_GET['from']) && $_GET['from'] === 'riwayat';
        $back_url = $from_riwayat ? 'riwayat.php' : 'index.php';
        ?>
        <a href="<?php echo $back_url; ?>" class="w-full sm:w-auto bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-4 sm:px-6 rounded transition duration-200 text-center text-sm">
            <i class="fas fa-arrow-left mr-2"></i>Kembali ke <?php echo $from_riwayat ? 'Riwayat' : 'Halaman Utama'; ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Style khusus untuk print -->
<style type="text/css" media="print">
    @page {
        size: auto;
        margin: 10mm;
    }
    
    nav, button, .container > *:not(.max-w-4xl) {
        display: none !important;
    }
    
    .max-w-4xl {
        max-width: none !important;
        margin: 0 !important;
        padding: 10px !important;
        box-shadow: none !important;
    }
    
    body {
        background: white !important;
        font-size: 12px !important;
    }
    
    .no-print { display: none !important; }
    
    /* Pastikan tabel tidak terpotong saat print */
    table {
        width: 100% !important;
        font-size: 11px !important;
    }
    
    /* Responsive untuk print */
    @media print {
        .flex-col { flex-direction: column !important; }
        .space-y-4 > * + * { margin-top: 1rem !important; }
    }
</style>

<?php if ($_SESSION['role'] == 'admin'): ?>
<div class="mt-8 flex justify-end no-print">
    <a href="admin/dashboard.php?tab=booking_eskul" class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-6 rounded transition duration-200">
        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dashboard
    </a>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
