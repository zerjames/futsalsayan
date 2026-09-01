<?php
// Get current month and year
$month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Query untuk booking regular
$query_regular = "SELECT 
    b.id_booking,
    b.user_id,
    b.lapangan_id,
    u.nama as nama_user,
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
WHERE MONTH(b.tanggal_main) = $month 
AND YEAR(b.tanggal_main) = $year";

if ($search !== '') {
    if (is_numeric($search)) {
        $query_regular .= " AND (b.id_booking = $search OR u.nama LIKE '%$search%')";
    } else {
        $query_regular .= " AND (u.nama LIKE '%$search%')";
    }
}

$query_regular .= " ORDER BY b.tanggal_booking DESC";

$result_regular = mysqli_query($conn, $query_regular);

if (!$result_regular) {
    die("Error in query: " . mysqli_error($conn));
}
