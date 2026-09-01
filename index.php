<?php
include 'includes/header.php';

// Ambil data lapangan yang tersedia
$query = "SELECT * FROM lapangan WHERE status = 'tersedia'";
$result = mysqli_query($conn, $query);

// Ambil data harga paket eskul
$query_eskul = "SELECT hp.*, l.id_lapangan, l.nama AS nama_lapangan, l.id_lapangan AS lapangan_id
FROM harga_paket hp
JOIN lapangan l ON l.id_lapangan = CAST(SUBSTRING(hp.tipe, 10) AS UNSIGNED)
WHERE hp.tipe LIKE 'eskul_lap%' ORDER BY l.id_lapangan ASC";
$result_eskul = mysqli_query($conn, $query_eskul);

// Debug: Log the eskul query result
if (!$result_eskul) {
    error_log("Error in eskul query: " . mysqli_error($conn));
} else {
    $eskul_count = mysqli_num_rows($result_eskul);
    error_log("Eskul packages found: " . $eskul_count);
}

// Get current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Query untuk booking regular
$query_regular = "SELECT 
    b.id_booking,
    b.user_id,
    b.lapangan_id,
    u.nama as nama_user,
    l.nama as nama_lapangan,
    'regular' as tipe,
    b.tanggal_main as tanggal,
    b.jam_mulai,
    b.jam_selesai,
    b.total_harga,
    b.status_pembayaran,
    NULL as nama_sekolah,
    NULL as nama_guru,
    NULL as nama_turnamen,
    NULL as nama_panitia
    FROM booking b 
    JOIN users u ON b.user_id = u.id_user 
    JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
    WHERE MONTH(b.tanggal_main) = $month 
    AND YEAR(b.tanggal_main) = $year 
    AND b.status_pembayaran != 'dibatalkan'";

// Query untuk booking eskul
$query_bookings_eskul = "SELECT 
    be.id_booking_eskul,
    be.user_id,
    be.lapangan_id,
    u.nama as nama_user,
    l.nama as nama_lapangan,
    'eskul' as tipe,
    DATE_ADD(be.tanggal_mulai, INTERVAL nums.num * 7 DAY) as tanggal,
    be.jam_mulai,
    ADDTIME(be.jam_mulai, '03:00:00') as jam_selesai,
    be.total_harga,
    be.status_pembayaran,
    be.nama_sekolah,
    be.nama_guru,
    NULL as nama_turnamen,
    NULL as nama_panitia
FROM booking_eskul be
JOIN users u ON be.user_id = u.id_user 
JOIN lapangan l ON be.lapangan_id = l.id_lapangan 
JOIN (
    SELECT 0 as num UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
    SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL 
    SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
    SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL
    SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
    SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL
    SELECT 24 UNION ALL SELECT 25
) nums
WHERE DATE_ADD(be.tanggal_mulai, INTERVAL nums.num * 7 DAY) <= DATE_ADD(be.tanggal_mulai, INTERVAL be.durasi_bulan MONTH)
AND MONTH(DATE_ADD(be.tanggal_mulai, INTERVAL nums.num * 7 DAY)) = $month 
AND YEAR(DATE_ADD(be.tanggal_mulai, INTERVAL nums.num * 7 DAY)) = $year
AND be.status_pembayaran != 'dibatalkan'";

// Query untuk booking turnamen
$query_bookings_turnamen = "SELECT 
    bt.id_booking_turnamen,
    bt.user_id,
    bt.lapangan_id,
    u.nama as nama_user,
    l.nama as nama_lapangan,
    'turnamen' as tipe,
    DATE_ADD(bt.tanggal_mulai, INTERVAL nums.num DAY) as tanggal,
    bt.jam_mulai,
    bt.jam_selesai,
    bt.total_harga,
    bt.status_pembayaran,
    NULL as nama_sekolah,
    NULL as nama_guru,
    bt.nama_turnamen,
    bt.nama_panitia
FROM booking_turnamen bt
JOIN users u ON bt.user_id = u.id_user 
JOIN lapangan l ON bt.lapangan_id = l.id_lapangan 
JOIN (
    SELECT 0 as num UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL 
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL 
    SELECT 6 UNION ALL SELECT 7
) nums
WHERE DATE_ADD(bt.tanggal_mulai, INTERVAL nums.num DAY) <= DATE_ADD(bt.tanggal_mulai, INTERVAL bt.durasi_hari - 1 DAY)
AND MONTH(DATE_ADD(bt.tanggal_mulai, INTERVAL nums.num DAY)) = $month 
AND YEAR(DATE_ADD(bt.tanggal_mulai, INTERVAL nums.num DAY)) = $year
AND bt.status_pembayaran != 'dibatalkan'";

$query_bookings = "($query_regular) UNION ALL ($query_bookings_eskul) UNION ALL ($query_bookings_turnamen) ORDER BY tanggal, jam_mulai ASC";
$result_bookings = mysqli_query($conn, $query_bookings);

if (!$result_bookings) {
    die("Error in query: " . mysqli_error($conn));
}

// Create bookings array indexed by date
$bookings = array();
while ($booking = mysqli_fetch_assoc($result_bookings)) {
    $date = date('j', strtotime($booking['tanggal']));
    if (!isset($bookings[$date])) {
        $bookings[$date] = array();
    }
    $bookings[$date][] = $booking;
}

// Get first day info
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$firstDayOfWeek = date('w', $firstDay);

// Calendar navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Ambil konten tentang kami dari database
$about = 'Futsal Sayan Bekasi adalah tempat terbaik untuk bermain futsal di Bekasi. Kami menyediakan lapangan berkualitas, fasilitas lengkap, dan layanan booking online yang mudah dan cepat. Dengan pengalaman bertahun-tahun, kami berkomitmen memberikan pengalaman terbaik untuk komunitas futsal, sekolah, dan turnamen di wilayah Bekasi dan sekitarnya. Hubungi kami untuk informasi lebih lanjut atau booking lapangan!';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Hero Section -->
    <div class="bg-green-600 text-white rounded-lg shadow-lg p-6 md:p-8 mb-8 text-center md:text-left">
        <h1 class="text-3xl md:text-4xl font-bold mb-4">Futsal Sayan Bekasi</h1>
        <p class="text-base md:text-lg mb-6">Booking lapangan futsal Sayan dengan mudah dan cepat, ayo booking sekarang!</p>
    </div>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin'): ?>
      <?php
        $uid = $_SESSION['user_id'];
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username, foto_profil FROM users WHERE id_user=$uid"));
        $username = $user ? $user['username'] : '';
        $foto = !empty($user['foto_profil']) ? 'uploads/profile/' . $user['foto_profil'] : 'assets/images/default_profile.png';
      ?>
      <div class="bg-white rounded-xl shadow flex items-center gap-3 mb-6 px-5 py-4 max-w-md">
        <img src="<?php echo $foto; ?>" alt="Profil" class="w-12 h-12 rounded-full object-cover border-2 border-green-400 shadow">
        <div class="text-lg font-semibold text-green-700">Selamat datang kembali, <?php echo htmlspecialchars($username); ?>!</div>
      </div>
    <?php endif; ?>
    <!-- Tentang Kami Section -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold mb-4">Jadwal Booking</h2>
        
        <!-- Calendar Controls -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-4 space-y-2 md:space-y-0">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" 
               class="bg-gray-200 px-4 py-3 rounded hover:bg-gray-300 w-full md:w-auto text-center">
                <i class="fas fa-chevron-left"></i> Bulan Sebelumnya
            </a>
            <h3 class="text-xl font-semibold order-first md:order-none"><?php echo $monthNames[$month] . ' ' . $year; ?></h3>
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" 
               class="bg-gray-200 px-4 py-3 rounded hover:bg-gray-300 w-full md:w-auto text-center">
                Bulan Berikutnya <i class="fas fa-chevron-right"></i>
            </a>
        </div>

                <!-- Calendar Grid -->
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <!-- Days of Week -->
                    <div class="grid grid-cols-7 gap-px bg-gray-200">
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Min</div>
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Sen</div>
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Sel</div>
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Rab</div>
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Kam</div>
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Jum</div>
                        <div class="px-1 py-2 text-center font-semibold text-xs sm:text-sm md:text-base md:px-2">Sab</div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="grid grid-cols-7 gap-px bg-gray-200">
                <?php
                // Empty cells before start of month
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                    echo '<div class="bg-white p-2 h-24 md:h-32"></div>';
                }

                // Days of month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $isToday = $day == date('j') && $month == date('m') && $year == date('Y');
                    $dayClass = $isToday ? 'bg-green-50' : 'bg-white';
                    
                    echo '<div class="' . $dayClass . ' p-2 h-24 md:h-32 relative">';
                    echo '<span class="absolute top-1 right-1 text-sm ' . ($isToday ? 'text-green-600 font-bold' : 'text-gray-500') . '">' . $day . '</span>';
                    
                    // Display indicator if there are bookings
                    if (isset($bookings[$day])) {
                        $bookingData = htmlspecialchars(json_encode($bookings[$day]), ENT_QUOTES, 'UTF-8');
                        // Count bookings by type
                        $regular = 0;
                        $eskul = 0;
                        $turnamen = 0;
                        foreach ($bookings[$day] as $b) {
                            if ($b['tipe'] == 'regular') $regular++;
                            elseif ($b['tipe'] == 'eskul') $eskul++;
                            else $turnamen++;
                        }

                        echo '<div class="mt-2 md:mt-4 cursor-pointer" onclick="showBookingDetails(\'' . $bookingData . '\', \'' . $day . ' ' . $monthNames[$month] . ' ' . $year . '\')">';
                        echo '<div class="text-xs space-y-1">';
                        if ($regular > 0) {
                            echo '<div class="p-1 bg-blue-100 rounded text-center">';
                            echo '<div class="font-semibold">' . $regular . ' Regular</div>';
                            echo '</div>';
                        }
                        if ($eskul > 0) {
                            echo '<div class="p-1 bg-green-100 rounded text-center">';
                            echo '<div class="font-semibold">' . $eskul . ' Eskul</div>';
                            echo '</div>';
                        }
                        if ($turnamen > 0) {
                            echo '<div class="p-1 bg-purple-100 rounded text-center">';
                            echo '<div class="font-semibold">' . $turnamen . ' Turnamen</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }

                // Empty cells after end of month
                $lastDayOfWeek = date('w', mktime(0, 0, 0, $month, $daysInMonth, $year));
                for ($i = $lastDayOfWeek; $i < 6; $i++) {
                    echo '<div class="bg-white p-2 h-24 md:h-32"></div>';
                }
                ?>
            </div>
        </div>

        <!-- Calendar Legend -->
        <div class="mt-4 bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-3 text-center md:text-left">Keterangan:</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <div class="flex items-center justify-center md:justify-start">
                    <div class="w-5 h-5 bg-green-50 border border-green-200 mr-2"></div>
                    <span class="text-sm">Hari Ini</span>
                </div>
                <div class="flex items-center justify-center md:justify-start">
                    <div class="w-5 h-5 bg-blue-100 border border-blue-200 mr-2"></div>
                    <span class="text-sm">Regular</span>
                </div>
                <div class="flex items-center justify-center md:justify-start">
                    <div class="w-5 h-5 bg-green-100 border border-green-200 mr-2"></div>
                    <span class="text-sm">Eskul</span>
                </div>
                <div class="flex items-center justify-center md:justify-start">
                    <div class="w-5 h-5 bg-purple-100 border border-purple-200 mr-2"></div>
                    <span class="text-sm">Turnamen</span>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-200">
                <div class="flex items-center justify-center md:justify-start">
                    <i class="fas fa-info-circle text-gray-500 mr-2"></i>
                    <span class="text-sm text-gray-600">Klik tanggal untuk melihat detail jadwal booking</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Regular -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold mb-4 px-2">Booking Regular</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 px-2">
            <?php while($lapangan = mysqli_fetch_assoc($result)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-300">
                    <?php if ($lapangan['gambar']): ?>
                        <img src="uploads/lapangan/<?php echo $lapangan['gambar']; ?>" 
                             alt="<?php echo $lapangan['nama']; ?>"
                             class="w-full h-48 object-cover">
                    <?php endif; ?>
                    <div class="p-4">
                        <h3 class="text-xl font-semibold mb-2"><?php echo $lapangan['nama']; ?></h3>
                        <p class="text-gray-600 mb-4"><?php echo $lapangan['deskripsi']; ?></p>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-2 sm:space-y-0">
                            <span class="text-green-600 font-bold text-lg">
                                Rp <?php echo number_format($lapangan['harga_per_jam'], 0, ',', '.'); ?>/jam
                            </span>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="booking_regular.php?lapangan=<?php echo $lapangan['id_lapangan']; ?>" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 w-full sm:w-auto text-center">
                                    Booking
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Paket Eskul -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold mb-4 px-2">Paket Eskul</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 px-2">
            <?php while($paket = mysqli_fetch_assoc($result_eskul)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-300">
                    <?php if ($paket['gambar']): ?>
                        <img src="uploads/paket/<?php echo $paket['gambar']; ?>" 
                             alt="<?php echo htmlspecialchars($paket['nama_lapangan']); ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400">No Image</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-green-600 mb-3">
                            <?php 
                            echo htmlspecialchars($paket['nama_lapangan']);
                            ?>
                        </h3>
                        <p class="text-gray-600 mb-4">3 jam per hari</p>
                        <p class="text-2xl font-bold text-green-600 mb-4">
                            Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>
                            <span class="text-sm font-normal">/bulan</span>
                        </p>
                        <div class="text-gray-600 mb-6 whitespace-pre-line">
                            <?php echo $paket['keterangan']; ?>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="booking_eskul.php?lapangan=<?php echo $paket['lapangan_id']; ?>" 
                               class="block text-center bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                Booking Paket Eskul
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <p class="text-sm text-gray-600 mt-4">* Syarat: Wajib mencantumkan nama sekolah dan nomor telepon guru penanggung jawab</p>
    </div>

    <!-- Paket Turnamen -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold mb-4 px-2">Paket Turnamen</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 px-2">
            <?php
            // Query untuk mengambil harga turnamen per lapangan
            $query_turnamen = "SELECT 
                l.id_lapangan, 
                l.nama,
                MAX(CASE WHEN hp.tipe = CONCAT('turnamen_lap', l.id_lapangan, '_sore') THEN hp.harga END) as harga_sore,
                MAX(CASE WHEN hp.tipe = CONCAT('turnamen_lap', l.id_lapangan, '_malam') THEN hp.harga END) as harga_malam,
                MAX(CASE WHEN hp.tipe = CONCAT('turnamen_lap', l.id_lapangan, '_sore') THEN hp.gambar END) as gambar_sore,
                MAX(CASE WHEN hp.tipe = CONCAT('turnamen_lap', l.id_lapangan, '_malam') THEN hp.gambar END) as gambar_malam
                FROM lapangan l
                LEFT JOIN harga_paket hp ON hp.tipe LIKE CONCAT('turnamen_lap', l.id_lapangan, '_%')
                WHERE l.status = 'tersedia'
                GROUP BY l.id_lapangan, l.nama
                ORDER BY l.id_lapangan";
            $result_turnamen = mysqli_query($conn, $query_turnamen);
            
            // Array deskripsi khusus untuk setiap lapangan turnamen
            $deskripsi_turnamen = [
                1 => "Lapangan rumput sintetis ukuran standar dengan penerangan yang baik",
                2 => "Lapangan vinyl berkualitas tinggi dengan atap tertutup",
                3 => "Lapangan premium dengan rumput sintetis grade A dan tribun penonton",
                4 => "Lapangan futsal berkualitas tinggi dengan permukaan yang nyaman",
                5 => "Lapangan futsal berkualitas tinggi dengan fasilitas lengkap",
                6 => "Lapangan futsal berkualitas tinggi dengan fasilitas lengkap",
                7 => "Lapangan futsal berkualitas tinggi dengan fasilitas lengkap"
            ];

            while($paket = mysqli_fetch_assoc($result_turnamen)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-300">
                    <?php if ($paket['gambar_sore']): ?>
                        <img src="uploads/paket/<?php echo $paket['gambar_sore']; ?>" 
                             alt="<?php echo htmlspecialchars($paket['nama']); ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400">No Image</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-green-600 mb-3">
                            <?php echo $paket['nama']; ?>
                        </h3>
                        <p class="text-gray-600 mb-4">Durasi: 1-7 hari</p>
                        <div class="space-y-2 mb-4">
                            <p class="font-semibold">Paket Sore (10:00 - 17:00)</p>
                            <p class="text-xl font-bold text-green-600">
                                Rp <?php echo number_format($paket['harga_sore'], 0, ',', '.'); ?>
                                <span class="text-sm font-normal">/hari</span>
                            </p>
                            <p class="font-semibold mt-4">Paket Malam (10:00 - 21:00)</p>
                            <p class="text-xl font-bold text-green-600">
                                Rp <?php echo number_format($paket['harga_malam'], 0, ',', '.'); ?>
                                <span class="text-sm font-normal">/hari</span>
                            </p>
                        </div>
                        <div class="text-gray-600 mb-6">
                            <?php echo isset($deskripsi_turnamen[$paket['id_lapangan']]) ? $deskripsi_turnamen[$paket['id_lapangan']] : 'Lapangan futsal berkualitas tinggi dengan fasilitas lengkap'; ?>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="booking_turnamen.php?lapangan=<?php echo $paket['id_lapangan']; ?>" 
                               class="block text-center bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                Booking Paket Turnamen
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <p class="text-sm text-gray-600 mt-4 space-y-1">
            <p>* Tersedia paket 1-7 hari</p>
            <p>* Syarat: Wajib mencantumkan kontak panitia dan mengirimkan informasi turnamen ke admin via WhatsApp</p>
        </p>
    </div>

    <!-- Informasi Tambahan -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
        <div class="bg-white rounded-lg shadow-md p-6 text-center md:text-left border border-gray-300">
            <i class="fas fa-clock text-4xl text-green-600 mb-4"></i>
            <h3 class="text-xl font-semibold mb-2">Jam Operasional</h3>
            <p class="text-gray-600">Setiap hari: 08:00 - 23:00 WIB</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 text-center md:text-left border border-gray-300">
            <i class="fas fa-money-bill-wave text-4xl text-green-600 mb-4"></i>
            <h3 class="text-xl font-semibold mb-2">Metode Pembayaran</h3>
            <p class="text-gray-600">Transfer Bank, QRIS, atau Bayar di Tempat</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 text-center md:text-left border border-gray-300">
            <i class="fas fa-phone text-4xl text-green-600 mb-4"></i>
            <h3 class="text-xl font-semibold mb-2">Kontak</h3>
            <p class="text-gray-600">Telp: 0857-4851-5801</p>
        </div>
    </div>

    <!-- Modal Detail Booking -->
    <div id="bookingDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full p-2 sm:p-4">
        <div class="relative top-20 mx-auto p-4 sm:p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-semibold mb-2" id="modalDate"></h3>
                <div id="modalContent" class="mt-2 space-y-3">
                    <!-- Content will be inserted here by JavaScript -->
                </div>
                <div class="mt-4">
                    <button onclick="closeModal()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lokasi & Tentang Kami Section -->
<div class="container mx-auto my-12 flex flex-col md:flex-row items-start justify-center gap-8 px-2">
  <!-- Tentang Kami -->
  <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 sm:p-6 w-full max-w-2xl md:w-1/2">
    <h2 class="text-xl font-bold text-green-700 mb-4">Tentang Kami</h2>
    <p class="text-gray-700 text-base"><?php echo nl2br(htmlspecialchars($about)); ?></p>
  </div>
  <!-- Lokasi Futsal Sayan -->
  <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 sm:p-6 w-full max-w-2xl md:w-1/2">
    <div class="flex items-center mb-4">
      <svg class="w-7 h-7 text-green-600 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.104 0 2-.896 2-2s-.896-2-2-2-2 .896-2 2 .896 2 2 2zm0 0c-3.866 0-7 2.239-7 5v2a1 1 0 001 1h12a1 1 0 001-1v-2c0-2.761-3.134-5-7-5z"/></svg>
      <h2 class="text-xl font-bold text-gray-800">Lokasi Futsal Sayan</h2>
    </div>
    <div class="rounded-lg overflow-hidden border border-gray-300">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d127.123456789!2d112.3456789!3d-7.1234567!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f1a1b1b1b1b1%3A0x123456789abcdef!2sSayan%20Futsal%20Family!5e0!3m2!1sid!2sid!4v1710000000000!5m2!1sid!2sid"
        width="100%" height="320" style="border:0; min-width:200px; min-height:220px; display:block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
    <div class="flex justify-center mt-4">
      <a href="https://maps.app.goo.gl/7WqnEhBacYTYrwo17" target="_blank" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow hover:bg-green-700 transition">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 12.414a2 2 0 00-2.828 0l-4.243 4.243m0 0A8 8 0 1112 20a8 8 0 01-5.657-2.343z"/></svg>
        Buka di Google Maps
      </a>
    </div>
  </div>
</div>

<script>
function showBookingDetails(bookings, dateText) {
    const modal = document.getElementById('bookingDetailModal');
    const modalDate = document.getElementById('modalDate');
    const modalContent = document.getElementById('modalContent');
    
    // Parse the JSON string
    const bookingData = JSON.parse(bookings);
    
    // Set the date
    modalDate.textContent = 'Jadwal Booking - ' + dateText;
    
    // Clear previous content
    modalContent.innerHTML = '';
    
    // Group bookings by lapangan
    const bookingsByLapangan = {};
    bookingData.forEach(booking => {
        if (!bookingsByLapangan[booking.nama_lapangan]) {
            bookingsByLapangan[booking.nama_lapangan] = [];
        }
        bookingsByLapangan[booking.nama_lapangan].push(booking);
    });

    // Sort each lapangan's bookings by time
    Object.values(bookingsByLapangan).forEach(lapanganBookings => {
        lapanganBookings.sort((a, b) => a.jam_mulai.localeCompare(b.jam_mulai));
    });

    // Create content for each lapangan
    Object.entries(bookingsByLapangan).forEach(([namaLapangan, bookings], index) => {
        const lapanganEl = document.createElement('div');
        lapanganEl.className = 'p-3 bg-gray-50 rounded-lg' + (index > 0 ? ' mt-3' : '');
        
        // Lapangan header
        const headerEl = document.createElement('div');
        headerEl.className = 'font-semibold text-green-600 text-lg mb-2 pb-2 border-b border-gray-200';
        headerEl.textContent = namaLapangan;
        lapanganEl.appendChild(headerEl);
        
        // Bookings list
        const listEl = document.createElement('div');
        listEl.className = 'space-y-2';
        
        bookings.forEach(booking => {
            const bookingEl = document.createElement('div');
            bookingEl.className = 'text-sm';
            
            let bookingInfo = `${booking.jam_mulai} - ${booking.jam_selesai}`;
            if (booking.tipe === 'regular') {
                bookingInfo += `<br>Booking Regular`;
            } else if (booking.tipe === 'eskul') {
                bookingInfo += `<br>Ekstrakurikuler - ${booking.nama_sekolah}`;
            } else if (booking.tipe === 'turnamen') {
                bookingInfo += `<br>Turnamen - ${booking.nama_turnamen}`;
            }
            
            bookingEl.innerHTML = bookingInfo;
            listEl.appendChild(bookingEl);
        });
        
        lapanganEl.appendChild(listEl);
        modalContent.appendChild(lapanganEl);
    });
    
    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('bookingDetailModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('bookingDetailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
