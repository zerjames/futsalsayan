<?php
// Get current month and year
$month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Query untuk booking turnamen
$query_turnamen = "SELECT 
    bt.id_booking_turnamen,
    bt.user_id,
    bt.lapangan_id,
    u.nama as nama_user,
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
WHERE MONTH(bt.tanggal_mulai) = $month 
AND YEAR(bt.tanggal_mulai) = $year";

if ($search !== '') {
    if (is_numeric($search)) {
        $query_turnamen .= " AND (bt.id_booking_turnamen = $search OR u.nama LIKE '%$search%' OR bt.nama_turnamen LIKE '%$search%' OR bt.nama_panitia LIKE '%$search%')";
    } else {
        $query_turnamen .= " AND (u.nama LIKE '%$search%' OR bt.nama_turnamen LIKE '%$search%' OR bt.nama_panitia LIKE '%$search%')";
    }
}

$query_turnamen .= " ORDER BY bt.tanggal_booking DESC";

$result_turnamen = mysqli_query($conn, $query_turnamen);

if (!$result_turnamen) {
    die("Error in query: " . mysqli_error($conn));
}
