<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die('Unauthorized');
}

$month = $_GET['bulan'] ?? date('n');
$year = $_GET['tahun'] ?? date('Y');

// Set headers for Excel
$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$month_name = $month_names[$month];

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Laporan_Booking_' . $month_name . '_' . $year . '.xls"');

// Get total income for each type (BULAN INI - berdasarkan tanggal main/mulai)
$query_regular = "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking WHERE status_pembayaran = 'dikonfirmasi' AND MONTH(tanggal_main) = $month AND YEAR(tanggal_main) = $year";
$result_regular = mysqli_query($conn, $query_regular);
$total_regular = mysqli_fetch_assoc($result_regular)['total'] ?? 0;

$query_eskul = "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking_eskul WHERE status_pembayaran = 'dikonfirmasi' AND MONTH(tanggal_mulai) = $month AND YEAR(tanggal_mulai) = $year";
$result_eskul = mysqli_query($conn, $query_eskul);
$total_eskul = mysqli_fetch_assoc($result_eskul)['total'] ?? 0;

$query_turnamen = "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking_turnamen WHERE status_pembayaran = 'dikonfirmasi' AND MONTH(tanggal_mulai) = $month AND YEAR(tanggal_mulai) = $year";
$result_turnamen = mysqli_query($conn, $query_turnamen);
$total_turnamen = mysqli_fetch_assoc($result_turnamen)['total'] ?? 0;

// Start output
echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    td { border: 1px solid #000; }
    th { border: 1px solid #000; background-color: #4CAF50; color: white; }
    .total { background-color: #E8F5E9; font-weight: bold; }
    .header { background-color: #2E7D32; color: white; font-weight: bold; }
    .info { background-color: #E3F2FD; font-weight: bold; }
</style>
</head>
<body>';

// Header information
echo '<table border="1" cellpadding="5" cellspacing="0" style="margin-bottom: 20px;">
<tr><th colspan="3" class="info">LAPORAN BOOKING FUTSAL SAYAN</th></tr>
<tr><td colspan="3" class="info">Periode: ' . $month_name . ' ' . $year . '</td></tr>
<tr><td colspan="3" class="info">Tanggal Export: ' . date('d/m/Y H:i:s') . '</td></tr>
</table>';

// Get total income for all time (for comparison)
$query_regular_all = "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking WHERE status_pembayaran = 'dikonfirmasi'";
$result_regular_all = mysqli_query($conn, $query_regular_all);
$total_regular_all = mysqli_fetch_assoc($result_regular_all)['total'] ?? 0;

$query_eskul_all = "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking_eskul WHERE status_pembayaran = 'dikonfirmasi'";
$result_eskul_all = mysqli_query($conn, $query_eskul_all);
$total_eskul_all = mysqli_fetch_assoc($result_eskul_all)['total'] ?? 0;

$query_turnamen_all = "SELECT COALESCE(SUM(total_harga), 0) as total FROM booking_turnamen WHERE status_pembayaran = 'dikonfirmasi'";
$result_turnamen_all = mysqli_query($conn, $query_turnamen_all);
$total_turnamen_all = mysqli_fetch_assoc($result_turnamen_all)['total'] ?? 0;

$total_all_time = $total_regular_all + $total_eskul_all + $total_turnamen_all;
$total_this_month = $total_regular + $total_eskul + $total_turnamen;

// Summary section
echo '<table border="1" cellpadding="5" cellspacing="0">
<tr><th colspan="3" class="header">RINGKASAN PENDAPATAN</th></tr>
<tr><th>Jenis Booking</th><th>Pendapatan Bulan Ini</th><th>Total Pendapatan (Semua Waktu)</th></tr>
<tr><td>Booking Regular</td><td align="right">Rp ' . number_format($total_regular, 0, ',', '.') . '</td><td align="right">Rp ' . number_format($total_regular_all, 0, ',', '.') . '</td></tr>
<tr><td>Booking Eskul</td><td align="right">Rp ' . number_format($total_eskul, 0, ',', '.') . '</td><td align="right">Rp ' . number_format($total_eskul_all, 0, ',', '.') . '</td></tr>
<tr><td>Booking Turnamen</td><td align="right">Rp ' . number_format($total_turnamen, 0, ',', '.') . '</td><td align="right">Rp ' . number_format($total_turnamen_all, 0, ',', '.') . '</td></tr>
<tr class="total"><td><strong>Total</strong></td><td align="right"><strong>Rp ' . number_format($total_this_month, 0, ',', '.') . '</strong></td><td align="right"><strong>Rp ' . number_format($total_all_time, 0, ',', '.') . '</strong></td></tr>
</table>';

echo '<br><br>';

// Regular Bookings
echo '<table border="1" cellpadding="5" cellspacing="0">
<tr><th colspan="8" class="header">DAFTAR BOOKING REGULAR</th></tr>
<tr>
    <th>No.</th>
    <th>Tanggal Booking</th>
    <th>Nama</th>
    <th>Lapangan</th>
    <th>Tanggal Main</th>
    <th>Jam</th>
    <th>Total Harga</th>
    <th>Status</th>
</tr>';

$query = "SELECT b.*, u.nama as nama_user, l.nama as nama_lapangan 
         FROM booking b 
         JOIN users u ON b.user_id = u.id_user 
         JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
         WHERE MONTH(b.tanggal_main) = $month 
         AND YEAR(b.tanggal_main) = $year 
         ORDER BY b.tanggal_main DESC";
$result = mysqli_query($conn, $query);
$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>
        <td>' . $no++ . '</td>
        <td>' . date('d/m/Y', strtotime($row['tanggal_booking'])) . '</td>
        <td>' . $row['nama_user'] . '</td>
        <td>' . $row['nama_lapangan'] . '</td>
        <td>' . date('d/m/Y', strtotime($row['tanggal_main'])) . '</td>
        <td>' . date('H:i', strtotime($row['jam_mulai'])) . (isset($row['jam_selesai']) ? ' - ' . date('H:i', strtotime($row['jam_selesai'])) : '') . '</td>
        <td align="right">Rp ' . number_format($row['total_harga'], 0, ',', '.') . '</td>
        <td>' . ucfirst($row['status_pembayaran']) . '</td>
    </tr>';
}

echo '<tr class="total">
    <td colspan="6">Total Pendapatan Regular</td>
    <td align="right" colspan="2">Rp ' . number_format($total_regular, 0, ',', '.') . '</td>
</tr>
</table>';

echo '<br><br>';

// Eskul Bookings
echo '<table border="1" cellpadding="5" cellspacing="0">
<tr><th colspan="9" class="header">DAFTAR BOOKING EKSTRAKURIKULER</th></tr>
<tr>
    <th>No.</th>
    <th>Tanggal Booking</th>
    <th>Nama</th>
    <th>Lapangan</th>
    <th>Tanggal Mulai</th>
    <th>Durasi (Bulan)</th>
    <th>Jam</th>
    <th>Total Harga</th>
    <th>Status</th>
</tr>';

$query = "SELECT be.*, u.nama as nama_user, l.nama as nama_lapangan 
         FROM booking_eskul be 
         JOIN users u ON be.user_id = u.id_user 
         JOIN lapangan l ON be.lapangan_id = l.id_lapangan 
         WHERE MONTH(be.tanggal_mulai) = $month 
         AND YEAR(be.tanggal_mulai) = $year 
         ORDER BY be.tanggal_mulai DESC";
$result = mysqli_query($conn, $query);
$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>
        <td>' . $no++ . '</td>
        <td>' . date('d/m/Y', strtotime($row['tanggal_booking'])) . '</td>
        <td>' . $row['nama_user'] . '</td>
        <td>' . $row['nama_lapangan'] . '</td>
        <td>' . date('d/m/Y', strtotime($row['tanggal_mulai'])) . '</td>
        <td align="center">' . $row['durasi_bulan'] . '</td>
        <td>' . date('H:i', strtotime($row['jam_mulai'])) . (isset($row['jam_selesai']) ? ' - ' . date('H:i', strtotime($row['jam_selesai'])) : '') . '</td>
        <td align="right">Rp ' . number_format($row['total_harga'], 0, ',', '.') . '</td>
        <td>' . ucfirst($row['status_pembayaran']) . '</td>
    </tr>';
}

echo '<tr class="total">
    <td colspan="7">Total Pendapatan Eskul</td>
    <td align="right" colspan="2">Rp ' . number_format($total_eskul, 0, ',', '.') . '</td>
</tr>
</table>';

echo '<br><br>';

// Turnamen Bookings
echo '<table border="1" cellpadding="5" cellspacing="0">
<tr><th colspan="10" class="header">DAFTAR BOOKING TURNAMEN</th></tr>
<tr>
    <th>No.</th>
    <th>Tanggal Booking</th>
    <th>Nama Turnamen</th>
    <th>Penanggung Jawab</th>
    <th>Lapangan</th>
    <th>Tanggal Mulai</th>
    <th>Durasi (Hari)</th>
    <th>Jam</th>
    <th>Total Harga</th>
    <th>Status</th>
</tr>';

$query = "SELECT bt.*, u.nama as nama_user, l.nama as nama_lapangan 
         FROM booking_turnamen bt 
         JOIN users u ON bt.user_id = u.id_user 
         JOIN lapangan l ON bt.lapangan_id = l.id_lapangan 
         WHERE MONTH(bt.tanggal_mulai) = $month 
         AND YEAR(bt.tanggal_mulai) = $year 
         ORDER BY bt.tanggal_mulai DESC";
$result = mysqli_query($conn, $query);
$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>
        <td>' . $no++ . '</td>
        <td>' . date('d/m/Y', strtotime($row['tanggal_booking'])) . '</td>
        <td>' . $row['nama_turnamen'] . '</td>
        <td>' . $row['nama_panitia'] . '</td>
        <td>' . $row['nama_lapangan'] . '</td>
        <td>' . date('d/m/Y', strtotime($row['tanggal_mulai'])) . '</td>
        <td align="center">' . $row['durasi_hari'] . '</td>
        <td>' . date('H:i', strtotime($row['jam_mulai'])) . (isset($row['jam_selesai']) ? ' - ' . date('H:i', strtotime($row['jam_selesai'])) : '') . '</td>
        <td align="right">Rp ' . number_format($row['total_harga'], 0, ',', '.') . '</td>
        <td>' . ucfirst($row['status_pembayaran']) . '</td>
    </tr>';
}

echo '<tr class="total">
    <td colspan="8">Total Pendapatan Turnamen</td>
    <td align="right" colspan="2">Rp ' . number_format($total_turnamen, 0, ',', '.') . '</td>
</tr>
</table>';

echo '</body></html>';
exit();
?>
