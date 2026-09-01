<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/database.php';

$month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Include filter component
$tab_name = 'booking_regular';
include 'booking_filter.php';

$where_sql = buildFilterWhere('b');
$query_regular = "SELECT 
    b.id_booking,
    b.user_id,
    b.lapangan_id,
    u.nama as nama_user,
    u.telepon as telepon_user,
    l.nama as nama_lapangan,
    b.tanggal_main,
    b.jam_mulai,
    b.jam_selesai,
    b.total_harga,
    b.status_pembayaran,
    b.bukti_pembayaran,
    b.tanggal_booking,
    b.tanggal_upload_bukti
FROM booking b
JOIN users u ON b.user_id = u.id_user 
JOIN lapangan l ON b.lapangan_id = l.id_lapangan
WHERE $where_sql
ORDER BY b.tanggal_booking DESC";
$result_regular = mysqli_query($conn, $query_regular);
if (!$result_regular) {
    die("Error in query: " . mysqli_error($conn));
}
?>

<h2 class="text-2xl font-bold mb-4">Booking Regular</h2>
<div id="bookingTableContainer">
<!-- Tambahkan wrapper untuk scrollbar atas -->
<div class="scrollbar-top-wrapper" style="overflow-x:auto; max-width:100%; margin-bottom:0;">
  <div style="height:16px;"></div>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto max-w-full" id="scrollableTableWrapper">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telepon</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lapangan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Booking</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Main</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status & Pembayaran</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php while($booking = mysqli_fetch_assoc($result_regular)): ?>
                    <tr data-booking-id="<?php echo $booking['id_booking']; ?>">
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['id_booking']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['nama_user']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        $wa_nomor = preg_replace('/[^0-9]/', '', $booking['telepon_user']);
                        if (substr($wa_nomor, 0, 1) === '0') {
                            $wa_nomor = '62' . substr($wa_nomor, 1);
                        } else if (substr($wa_nomor, 0, 2) !== '62') {
                            $wa_nomor = '62' . $wa_nomor;
                        }
                        ?>
                        <?php echo $booking['telepon_user']; ?>
                        <a href="https://wa.me/+62<?php echo $wa_nomor; ?>"
                           target="_blank"
                           class="ml-2 inline-flex items-center px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600">
                            <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['nama_lapangan']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_booking'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo date('d/m/Y', strtotime($booking['tanggal_main'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        echo date('H:i', strtotime($booking['jam_mulai'])) . ' - ' . 
                             date('H:i', strtotime($booking['jam_selesai'])); 
                        ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="space-y-2">
                            <?php 
                            $status_class = '';
                            switch($booking['status_pembayaran']) {
                                case 'pending':
                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'dikonfirmasi':
                                    $status_class = 'bg-green-100 text-green-800';
                                    break;
                                case 'dibatalkan':
                                    $status_class = 'bg-red-100 text-red-800';
                                    break;
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo ucfirst($booking['status_pembayaran']); ?>
                            </span>
                            
                            <div class="text-sm text-gray-600">
                                Rp <?php echo number_format($booking['total_harga'], 0, ',', '.'); ?>
                            </div>

                            <?php if ($booking['bukti_pembayaran']): ?>
                                <a href="#" onclick="showPaymentProof('/futsalsayan/uploads/bukti_pembayaran/<?php echo $booking['bukti_pembayaran']; ?>')" 
                                   class="text-blue-600 hover:text-blue-800 text-xs flex items-center">
                                    <i class="fas fa-image mr-1"></i>Lihat Bukti
                                </a>
                                <div class="text-xs text-gray-500">
                                    Upload: <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_upload_bukti'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="space-y-2">
                            <?php if ($booking['status_pembayaran'] != 'dikonfirmasi' && $booking['status_pembayaran'] != 'dibatalkan'): ?>
                                <div class="flex flex-col space-y-2">
                                    <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking['id_booking']; ?>&status=dikonfirmasi&type=regular" 
                                       class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                                        <i class="fas fa-check mr-2"></i>Konfirmasi
                                    </a>
                                    <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking['id_booking']; ?>&status=dibatalkan&type=regular" 
                                       class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
                                       onclick="return confirm('Yakin ingin membatalkan booking ini?')">
                                        <i class="fas fa-times mr-2"></i>Batalkan
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <a href="/futsalsayan/admin/hapus_booking.php?id=<?php echo $booking['id_booking']; ?>&type=regular" 
                               class="text-gray-600 hover:text-gray-900 block"
                               onclick="return confirm('PERINGATAN: Menghapus booking akan menghilangkan semua data termasuk bukti pembayaran. Lanjutkan?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                            <a href="/futsalsayan/invoice.php?booking_id=<?php echo $booking['id_booking']; ?>" class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                                <i class="fas fa-file-invoice mr-2"></i>Lihat Invoice
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>
