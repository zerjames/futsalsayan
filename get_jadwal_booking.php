<?php
require_once 'config/database.php';
if (empty($_GET['lapangan'])) {
    echo '<p class="text-red-500">Lapangan tidak ditemukan.</p>';
    exit;
}
$lapangan_id = mysqli_real_escape_string($conn, $_GET['lapangan']);
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Query booking regular
$query_regular = "SELECT b.jam_mulai, b.jam_selesai, b.status_pembayaran, u.nama as nama_user, l.nama as nama_lapangan, 'Regular' as jenis_booking
            FROM booking b
            JOIN users u ON b.user_id = u.id_user
            JOIN lapangan l ON b.lapangan_id = l.id_lapangan
            WHERE b.tanggal_main = '$tanggal' AND b.lapangan_id = '$lapangan_id' AND b.status_pembayaran != 'dibatalkan'";

// Query booking eskul (jadwal mingguan)
$query_eskul = "SELECT be.jam_mulai, ADDTIME(be.jam_mulai, '03:00:00') as jam_selesai, be.status_pembayaran, u.nama as nama_user, l.nama as nama_lapangan, 'Ekstrakurikuler' as jenis_booking
            FROM booking_eskul be
            JOIN users u ON be.user_id = u.id_user
            JOIN lapangan l ON be.lapangan_id = l.id_lapangan
            WHERE be.lapangan_id = '$lapangan_id'
            AND '$tanggal' BETWEEN be.tanggal_mulai AND DATE_ADD(be.tanggal_mulai, INTERVAL be.durasi_bulan MONTH)
            AND DAYOFWEEK('$tanggal') = DAYOFWEEK(be.tanggal_mulai) AND be.status_pembayaran != 'dibatalkan'";

// Query booking turnamen (jadwal harian)
$query_turnamen = "SELECT bt.jam_mulai, bt.jam_selesai, bt.status_pembayaran, u.nama as nama_user, l.nama as nama_lapangan, 'Turnamen' as jenis_booking
            FROM booking_turnamen bt
            JOIN users u ON bt.user_id = u.id_user
            JOIN lapangan l ON bt.lapangan_id = l.id_lapangan
            WHERE bt.lapangan_id = '$lapangan_id'
            AND '$tanggal' BETWEEN bt.tanggal_mulai AND DATE_ADD(bt.tanggal_mulai, INTERVAL bt.durasi_hari - 1 DAY) AND bt.status_pembayaran != 'dibatalkan'";

// Gabungkan semua query
$query = "($query_regular) UNION ALL ($query_eskul) UNION ALL ($query_turnamen) ORDER BY jam_mulai ASC";

$result = mysqli_query($conn, $query);
if (!$result) {
    error_log("Gagal mengambil data jadwal: " . mysqli_error($conn));
    echo '<p class="text-red-500">Gagal mengambil data jadwal. Silakan coba lagi nanti.</p>';
    exit;
}

// Ambil semua data booking ke array
$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

// CSS responsive: hide table on mobile, show card; hide card on desktop, show table
// Card: tampil di max-width 600px, tabel: tampil di min-width 601px
// Card style sederhana agar tetap enak dibaca

echo '<style>
@media (max-width: 600px) {
  .jadwal-table-wrapper { display: none !important; }
  .jadwal-card-list { display: block !important; }
}
@media (min-width: 601px) {
  .jadwal-table-wrapper { display: block !important; }
  .jadwal-card-list { display: none !important; }
}
.jadwal-card {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.75rem;
  background: #f9fafb;
  font-size: 14px;
}
.jadwal-card-label {
  font-weight: 600;
  color: #374151;
  min-width: 110px;
  display: inline-block;
}
</style>';

// Tabel untuk desktop
$wrapperClass = 'jadwal-table-wrapper overflow-x-auto w-full';
$tableClass = 'jadwal-table min-w-full divide-y divide-gray-200 border border-gray-300';
$thClass = 'px-4 py-2 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase border-b';
$tdClass = 'px-4 py-2 whitespace-nowrap border-b';

echo '<div class="' . $wrapperClass . '" style="overflow-x:auto;width:100%;">';
echo '<table class="' . $tableClass . '" style="border-collapse:collapse;width:100%;table-layout:auto;">';
echo '<thead><tr>';
echo '<th class="' . $thClass . '">Nama User</th>';
echo '<th class="' . $thClass . '">Lapangan</th>';
echo '<th class="' . $thClass . '">Jam</th>';
echo '<th class="' . $thClass . '">Status</th>';
echo '<th class="' . $thClass . '">Jenis Booking</th>';
echo '</tr></thead><tbody>';
if (count($bookings) > 0) {
    foreach ($bookings as $row) {
        echo '<tr>';
        echo '<td class="' . $tdClass . '">' . htmlspecialchars($row['nama_user']) . '</td>';
        echo '<td class="' . $tdClass . '">' . htmlspecialchars($row['nama_lapangan']) . '</td>';
        echo '<td class="' . $tdClass . '">' . htmlspecialchars($row['jam_mulai']) . ' - ' . htmlspecialchars($row['jam_selesai']) . '</td>';
        echo '<td class="' . $tdClass . '">' . htmlspecialchars($row['status_pembayaran']) . '</td>';
        echo '<td class="' . $tdClass . '">' . htmlspecialchars($row['jenis_booking']) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5" class="text-green-500 ' . $tdClass . '">Belum ada booking untuk jadwal ini</td></tr>';
}
echo '</tbody></table></div>';

// Card untuk mobile
// Loop data booking, tampilkan dalam card

echo '<div class="jadwal-card-list" style="display:none;">';
if (count($bookings) > 0) {
    foreach ($bookings as $row) {
        echo '<div class="jadwal-card">';
        echo '<div><span class="jadwal-card-label">Nama User:</span> ' . htmlspecialchars($row['nama_user']) . '</div>';
        echo '<div><span class="jadwal-card-label">Lapangan:</span> ' . htmlspecialchars($row['nama_lapangan']) . '</div>';
        echo '<div><span class="jadwal-card-label">Jam:</span> ' . htmlspecialchars($row['jam_mulai']) . ' - ' . htmlspecialchars($row['jam_selesai']) . '</div>';
        echo '<div><span class="jadwal-card-label">Status:</span> ' . htmlspecialchars($row['status_pembayaran']) . '</div>';
        echo '<div><span class="jadwal-card-label">Jenis Booking:</span> ' . htmlspecialchars($row['jenis_booking']) . '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="jadwal-card text-green-600">Belum ada booking untuk jadwal ini</div>';
}
echo '</div>';