<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

// Ambil parameter
$booking_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$jenis = isset($_GET['jenis']) ? mysqli_real_escape_string($conn, $_GET['jenis']) : '';

if (empty($booking_id) || empty($jenis)) {
    die('Invalid parameters');
}

// Query berdasarkan jenis booking
switch ($jenis) {
    case 'Regular':
        $query = "SELECT b.*, u.nama as nama_user, l.nama as nama_lapangan 
                 FROM booking b 
                 JOIN users u ON b.user_id = u.id 
                 JOIN lapangan l ON b.lapangan_id = l.id 
                 WHERE b.id = '$booking_id'";
        break;
        
    case 'Ekstrakurikuler':
        $query = "SELECT be.*, u.nama as nama_user, l.nama as nama_lapangan,
                 be.jam_mulai, ADDTIME(be.jam_mulai, '03:00:00') as jam_selesai
                 FROM booking_eskul be
                 JOIN users u ON be.user_id = u.id
                 JOIN lapangan l ON be.lapangan_id = l.id
                 WHERE be.id = '$booking_id'";
        break;
        
    case 'Turnamen':
        $query = "SELECT bt.*, u.nama as nama_user, l.nama as nama_lapangan
                 FROM booking_turnamen bt
                 JOIN users u ON bt.user_id = u.id_user
                 JOIN lapangan l ON bt.lapangan_id = l.id
                 WHERE bt.id = '$booking_id'";
        break;
        
    default:
        die('Invalid booking type');
}

$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    die('Booking not found');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Detail Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold mb-4">Detail Booking</h2>
        <div class="space-y-3">
            <p><span class="font-semibold">Nama:</span> <?php echo htmlspecialchars($booking['nama_user']); ?></p>
            <p><span class="font-semibold">Jenis:</span> <?php echo htmlspecialchars($jenis); ?></p>
            <p><span class="font-semibold">Lapangan:</span> <?php echo htmlspecialchars($booking['nama_lapangan']); ?></p>
            <p><span class="font-semibold">Jam:</span> 
                <?php 
                echo htmlspecialchars(date('H:i', strtotime($booking['jam_mulai']))) . ' - ' . 
                     htmlspecialchars(date('H:i', strtotime($booking['jam_selesai']))); 
                ?>
            </p>
            <?php if ($jenis == 'Regular'): ?>
                <p><span class="font-semibold">Tanggal:</span> 
                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($booking['tanggal_main']))); ?>
                </p>
            <?php elseif ($jenis == 'Ekstrakurikuler'): ?>
                <p><span class="font-semibold">Durasi:</span> <?php echo $booking['durasi_bulan']; ?> bulan</p>
                <p><span class="font-semibold">Mulai:</span> 
                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($booking['tanggal_mulai']))); ?>
                </p>
            <?php elseif ($jenis == 'Turnamen'): ?>
                <p><span class="font-semibold">Durasi:</span> <?php echo $booking['durasi_hari']; ?> hari</p>
                <p><span class="font-semibold">Mulai:</span> 
                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($booking['tanggal_mulai']))); ?>
                </p>
            <?php endif; ?>
            <p>
                <span class="font-semibold">Status:</span>
                <span class="px-2 py-1 rounded-full text-xs 
                    <?php 
                    switch($booking['status_pembayaran']) {
                        case 'pending':
                        case 'menunggu_pembayaran':
                        case 'belum_dibayar':
                            echo 'bg-yellow-100 text-yellow-800';
                            break;
                        case 'dikonfirmasi':
                            echo 'bg-green-100 text-green-800';
                            break;
                        case 'dibatalkan':
                            echo 'bg-red-100 text-red-800';
                            break;
                        case 'dp_dibayar':
                            echo 'bg-blue-100 text-blue-800';
                            break;
                    }
                    ?>">
                    <?php echo ucfirst($booking['status_pembayaran']); ?>
                </span>
            </p>
        </div>
        <div class="mt-6 text-center">
            <button onclick="window.close()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                Tutup
            </button>
        </div>
    </div>
</body>
</html>
