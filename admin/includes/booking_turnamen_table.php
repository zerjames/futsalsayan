<?php
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../config/database.php';

$month = isset($_GET['bulan']) ? filter_var($_GET['bulan'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) : false;
if ($month === false) {
    $month = (int)date('m');
}
$year = isset($_GET['tahun']) ? filter_var($_GET['tahun'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) : false;
if ($year === false) {
    $year = (int)date('Y');
}

// Include filter component
$tab_name = 'booking_turnamen';
include 'booking_filter.php';

$where_sql = buildFilterWhere('bt');
$query_turnamen = "SELECT 
    bt.id_booking_turnamen,
    bt.user_id,
    bt.lapangan_id,
    u.nama as nama_user,
    u.telepon as telepon_user,
    l.nama as nama_lapangan,
    bt.nama_turnamen,
    bt.nama_panitia,
    bt.telepon_panitia,
    bt.durasi_hari,
    bt.jam_mulai,
    bt.jam_selesai,
    bt.tanggal_mulai,
    bt.total_harga,
    bt.status_pembayaran,
    bt.bukti_pembayaran,
    bt.tanggal_booking,
    bt.tanggal_upload_bukti
FROM booking_turnamen bt
JOIN users u ON bt.user_id = u.id_user 
JOIN lapangan l ON bt.lapangan_id = l.id_lapangan
WHERE $where_sql
ORDER BY bt.tanggal_booking DESC";
$result_turnamen = mysqli_query($conn, $query_turnamen);
if (!$result_turnamen) {
    die("Error in query: " . mysqli_error($conn));
}
?>

<h2 class="text-2xl font-bold mb-4">Booking Turnamen</h2>
<div id="bookingTurnamenTableContainer">
<div class="bg-white rounded-lg shadow overflow-x-auto max-w-full">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turnamen & Kontak</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lapangan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Booking</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durasi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mulai</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status & Pembayaran</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php while($booking = mysqli_fetch_assoc($result_turnamen)): ?>
                    <tr data-booking-id="<?php echo $booking['id_booking_turnamen']; ?>">
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['id_booking_turnamen']; ?></td>
                    <td class="px-6 py-4">
                        <?php echo $booking['nama_turnamen']; ?><br>
                        <span class="text-sm text-gray-500">PJ: <?php echo $booking['nama_panitia']; ?></span><br>
                        <span class="text-sm text-gray-500">
                            Telp PJ: <?php echo $booking['telepon_panitia']; ?>
                            <a href="https://wa.me/+62<?php echo preg_replace('/[^0-9]/', '', $booking['telepon_panitia']); ?>" 
                               target="_blank"
                               class="ml-2 inline-flex items-center px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-xs">
                                <i class="fab fa-whatsapp mr-1"></i>WA PJ
                            </a>
                        </span><br>
                        <span class="text-sm text-gray-500">
                            Telp User: <?php echo $booking['telepon_user']; ?>
                            <a href="https://wa.me/+62<?php echo preg_replace('/[^0-9]/', '', $booking['telepon_user']); ?>" 
                               target="_blank"
                               class="ml-2 inline-flex items-center px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-xs">
                                <i class="fab fa-whatsapp mr-1"></i>WA User
                            </a>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['nama_lapangan']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo date('d/m/Y H:i', strtotime($booking['tanggal_booking'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['durasi_hari']; ?> hari</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo date('d/m/Y', strtotime($booking['tanggal_mulai'])); ?>
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
                                <?php echo $booking['status_pembayaran'] == 'pending' ? 'Menunggu Pembayaran' : ucfirst($booking['status_pembayaran']); ?>
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
                        <div class="flex flex-col gap-1.5 w-32">
                            <?php if ($booking['status_pembayaran'] != 'dikonfirmasi' && $booking['status_pembayaran'] != 'dibatalkan'): ?>
                                <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking['id_booking_turnamen']; ?>&status=dikonfirmasi&type=turnamen" 
                                   class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold rounded-md bg-green-900 hover:bg-green-800 text-white whitespace-nowrap transition-colors">
                                    Konfirmasi
                                </a>
                                <a href="/futsalsayan/admin/konfirmasi.php?id=<?php echo $booking['id_booking_turnamen']; ?>&status=dibatalkan&type=turnamen" 
                                   class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-md border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 whitespace-nowrap transition-colors"
                                   onclick="return openAdminConfirmLink(event, this.href, 'Apakah Anda ingin membatalkan booking?', 'Booking turnamen ini akan dibatalkan.', 'Ya, Batalkan')">
                                    Batalkan
                                </a>
                            <?php endif; ?>
                            <a href="/futsalsayan/invoice_turnamen.php?booking_id=<?php echo $booking['id_booking_turnamen']; ?>"
                               class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 whitespace-nowrap transition-colors">
                                Lihat Invoice
                            </a>
                            <a href="/futsalsayan/admin/hapus_booking.php?id=<?php echo $booking['id_booking_turnamen']; ?>&type=turnamen" 
                               class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 whitespace-nowrap transition-colors"
                               onclick="return openAdminConfirmLink(event, this.href, 'Hapus data booking ini?', 'Menghapus booking akan menghilangkan semua data termasuk bukti pembayaran.', 'Ya, Hapus')">
                                Hapus
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>
