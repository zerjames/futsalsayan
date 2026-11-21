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

// Get current month and year with strict validation
$month = isset($_GET['month']) ? filter_var($_GET['month'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) : false;
if ($month === false) {
    $month = (int)date('n');
}

$year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) : false;
if ($year === false) {
    $year = (int)date('Y');
}

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

<div class="space-y-14">
    <!-- Hero Section: Editorial Split -->
    <?php
        $heroFile = __DIR__ . '/assets/images/backgoundindex.jpg';
        $heroVer = file_exists($heroFile) ? substr(md5_file($heroFile), 0, 8) : '1.0';
        $heroBg = $base_url . '/assets/images/backgoundindex.jpg?v=' . $heroVer;
    ?>
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10 items-center pt-1 sm:pt-2">
        <div class="lg:col-span-6 space-y-4 sm:space-y-5">
            <p class="text-[11px] sm:text-xs font-semibold uppercase tracking-widest text-green-800">
                Mustika Jaya, Bekasi &middot; Buka 08.00 – 23.00 WIB
            </p>
            <h1 class="text-3xl sm:text-4xl lg:text-[42px] font-extrabold tracking-tight text-stone-900 leading-[1.15]">
                Futsal Sayan Bekasi
            </h1>
            <p class="text-stone-600 text-sm sm:text-base leading-relaxed max-w-lg">
                Cek jadwal kosong dan pesan lapangan langsung dari sini — tersedia sewa regular per jam, paket bulanan ekstrakurikuler, hingga sewa harian untuk turnamen.
            </p>
            <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2.5 sm:gap-3 pt-1">
                <a href="#lapangan-regular" class="px-4 sm:px-5 py-3 sm:py-2.5 rounded-md bg-stone-900 hover:bg-green-800 active:bg-green-900 text-white text-sm font-semibold transition-colors text-center">
                    Pilih Lapangan
                </a>
                <a href="#jadwal-booking" class="px-4 sm:px-5 py-3 sm:py-2.5 rounded-md border border-stone-300 bg-white hover:border-stone-900 active:bg-stone-100 text-stone-800 text-sm font-medium transition-colors text-center">
                    Lihat Jadwal
                </a>
            </div>
        </div>
        <div class="lg:col-span-6">
            <div class="rounded-lg overflow-hidden border border-stone-200 bg-stone-100">
                <img src="<?php echo htmlspecialchars($heroBg, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="Lapangan Futsal Sayan Bekasi"
                     class="w-full h-52 sm:h-80 object-cover block">
            </div>
        </div>
    </section>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin'): ?>
      <?php
        $uid = $_SESSION['user_id'];
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username, foto_profil FROM users WHERE id_user=$uid"));
        $username = $user ? $user['username'] : '';
        $foto = !empty($user['foto_profil']) ? 'uploads/profile/' . $user['foto_profil'] : 'assets/images/default_profile.png';
      ?>
      <div class="bg-white rounded-lg border border-stone-200 flex items-center justify-between gap-4 px-5 py-3.5">
        <div class="flex items-center gap-3">
            <img src="<?php echo htmlspecialchars($foto, ENT_QUOTES, 'UTF-8'); ?>" alt="Profil" class="w-10 h-10 rounded-full object-cover border border-stone-200">
            <div>
                <p class="text-xs text-stone-500">Halo,</p>
                <p class="text-sm font-bold text-stone-900"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <a href="riwayat.php" class="text-xs sm:text-sm font-semibold text-stone-900 hover:text-green-800 underline">
            Lihat Riwayat Booking
        </a>
      </div>
    <?php endif; ?>

    <!-- Info Bar -->
    <section class="bg-white border border-stone-200 rounded-lg grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-stone-200">
        <div class="p-5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Jam Operasional</p>
            <p class="text-sm font-semibold text-stone-900 mt-1">Setiap hari, 08:00 – 23:00 WIB</p>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Pembayaran</p>
            <p class="text-sm font-semibold text-stone-900 mt-1">Transfer Bank, QRIS, atau Bayar di Tempat</p>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Kontak / WhatsApp</p>
            <p class="text-sm font-semibold text-stone-900 mt-1 tabular-nums">0857-4851-5801</p>
        </div>
    </section>

    <!-- Jadwal Booking Section -->
    <section id="jadwal-booking" class="scroll-mt-20 border-t border-stone-200 pt-8 sm:pt-10">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-stone-400">01 / Jadwal</p>
                <h2 class="text-xl sm:text-2xl font-bold text-stone-900 mt-1">Kalender Booking</h2>
            </div>
            <!-- Calendar Controls -->
            <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto">
                <a href="?month=<?php echo htmlspecialchars((string)$prevMonth, ENT_QUOTES, 'UTF-8'); ?>&year=<?php echo htmlspecialchars((string)$prevYear, ENT_QUOTES, 'UTF-8'); ?>#jadwal-booking" 
                   class="px-3.5 py-2.5 sm:py-1.5 rounded-md border border-stone-300 bg-white hover:border-stone-900 active:bg-stone-100 text-stone-700 text-xs font-medium transition-colors">
                    &larr; <span class="hidden sm:inline">Sebelumnya</span>
                </a>
                <h3 class="text-sm font-bold text-stone-900 min-w-[130px] text-center">
                    <?php echo htmlspecialchars($monthNames[$month] . ' ' . $year, ENT_QUOTES, 'UTF-8'); ?>
                </h3>
                <a href="?month=<?php echo htmlspecialchars((string)$nextMonth, ENT_QUOTES, 'UTF-8'); ?>&year=<?php echo htmlspecialchars((string)$nextYear, ENT_QUOTES, 'UTF-8'); ?>#jadwal-booking" 
                   class="px-3.5 py-2.5 sm:py-1.5 rounded-md border border-stone-300 bg-white hover:border-stone-900 active:bg-stone-100 text-stone-700 text-xs font-medium transition-colors">
                    <span class="hidden sm:inline">Berikutnya</span> &rarr;
                </a>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
            <!-- Days of Week -->
            <div class="grid grid-cols-7 bg-stone-50 border-b border-stone-200">
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Min</div>
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Sen</div>
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Sel</div>
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Rab</div>
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Kam</div>
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Jum</div>
                <div class="py-2.5 text-center font-semibold text-[11px] sm:text-xs text-stone-500">Sab</div>
            </div>

            <!-- Calendar Days -->
            <div class="grid grid-cols-7 gap-px bg-stone-200">
            <?php
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo '<div class="bg-stone-50/70 p-1.5 sm:p-2 h-16 sm:h-24 md:h-28"></div>';
            }

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $isToday = $day == date('j') && $month == date('m') && $year == date('Y');
                $hasBookings = isset($bookings[$day]);
                $dayClass = $isToday ? 'bg-green-50/60' : 'bg-white';
                $onclickAttr = '';
                if ($hasBookings) {
                    $bookingData = htmlspecialchars(json_encode($bookings[$day]), ENT_QUOTES, 'UTF-8');
                    $dayClass .= ' cursor-pointer hover:bg-stone-50 active:bg-stone-100 transition-colors';
                    $onclickAttr = ' onclick="showBookingDetails(\'' . $bookingData . '\', \'' . $day . ' ' . $monthNames[$month] . ' ' . $year . '\')"';
                }
                
                echo '<div class="' . $dayClass . ' p-1.5 sm:p-2 h-16 sm:h-24 md:h-28 relative flex flex-col"' . $onclickAttr . '>';
                echo '<div class="flex items-center justify-between">';
                if ($isToday) {
                    echo '<span class="inline-flex items-center justify-center w-6 h-6 rounded-sm bg-green-800 text-white text-xs font-bold tabular-nums">' . $day . '</span>';
                } else {
                    echo '<span class="text-xs font-medium text-stone-600 tabular-nums">' . $day . '</span>';
                }
                echo '</div>';
                
                if ($hasBookings) {
                    $regular = 0;
                    $eskul = 0;
                    $turnamen = 0;
                    foreach ($bookings[$day] as $b) {
                        if ($b['tipe'] == 'regular') $regular++;
                        elseif ($b['tipe'] == 'eskul') $eskul++;
                        else $turnamen++;
                    }
                    $totalDay = $regular + $eskul + $turnamen;

                    // Mobile compact indicator (dots + count, easy to read on small screens)
                    echo '<div class="sm:hidden mt-auto flex items-center gap-1 flex-wrap">';
                    if ($regular > 0) echo '<span class="w-2 h-2 rounded-full bg-sky-600 inline-block"></span>';
                    if ($eskul > 0) echo '<span class="w-2 h-2 rounded-full bg-green-700 inline-block"></span>';
                    if ($turnamen > 0) echo '<span class="w-2 h-2 rounded-full bg-amber-600 inline-block"></span>';
                    echo '<span class="text-[10px] font-semibold text-stone-600 tabular-nums ml-auto">' . $totalDay . '</span>';
                    echo '</div>';

                    // Desktop/Tablet detailed labels
                    echo '<div class="hidden sm:block mt-1 space-y-1">';
                    if ($regular > 0) {
                        echo '<div class="px-1.5 py-0.5 bg-sky-50 text-sky-900 border-l-2 border-sky-600 text-[11px] font-medium truncate">' . $regular . ' Regular</div>';
                    }
                    if ($eskul > 0) {
                        echo '<div class="px-1.5 py-0.5 bg-green-50 text-green-900 border-l-2 border-green-700 text-[11px] font-medium truncate">' . $eskul . ' Eskul</div>';
                    }
                    if ($turnamen > 0) {
                        echo '<div class="px-1.5 py-0.5 bg-amber-50 text-amber-900 border-l-2 border-amber-600 text-[11px] font-medium truncate">' . $turnamen . ' Turnamen</div>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }

            $lastDayOfWeek = date('w', mktime(0, 0, 0, $month, $daysInMonth, $year));
            for ($i = $lastDayOfWeek; $i < 6; $i++) {
                echo '<div class="bg-stone-50/70 p-1.5 sm:p-2 h-16 sm:h-24 md:h-28"></div>';
            }
            ?>
            </div>
        </div>

        <!-- Calendar Legend -->
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-stone-500">
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-green-800 inline-block"></span> Hari Ini</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full sm:rounded-none bg-sky-600 inline-block"></span> Regular</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full sm:rounded-none bg-green-700 inline-block"></span> Eskul</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full sm:rounded-none bg-amber-600 inline-block"></span> Turnamen</span>
            </div>
            <span>Ketuk tanggal bertanda untuk detail jam</span>
        </div>
    </section>

    <!-- Booking Regular -->
    <section id="lapangan-regular" class="scroll-mt-24 border-t border-stone-200 pt-10">
        <div class="mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-stone-400">02 / Sewa Per Jam</p>
            <h2 class="text-2xl font-bold text-stone-900 mt-1">Booking Regular</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while($lapangan = mysqli_fetch_assoc($result)): ?>
                <div class="bg-white rounded-lg border border-stone-200 overflow-hidden flex flex-col">
                    <?php if ($lapangan['gambar']): ?>
                        <img src="uploads/lapangan/<?php echo htmlspecialchars($lapangan['gambar'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="<?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-stone-100 flex items-center justify-center text-stone-400 text-xs">
                            Foto belum tersedia
                        </div>
                    <?php endif; ?>
                    <div class="p-5 flex-1 flex flex-col">
                        <h3 class="text-base font-bold text-stone-900 mb-1.5"><?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="text-sm text-stone-500 leading-relaxed mb-5 flex-1"><?php echo htmlspecialchars($lapangan['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="pt-4 border-t border-stone-100 flex items-center justify-between gap-3">
                            <div class="tabular-nums">
                                <span class="text-base font-bold text-stone-900">
                                    Rp <?php echo number_format($lapangan['harga_per_jam'], 0, ',', '.'); ?>
                                </span>
                                <span class="text-xs text-stone-400">/jam</span>
                            </div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="booking_regular.php?lapangan=<?php echo $lapangan['id_lapangan']; ?>"
                                   class="px-4 py-2 rounded-md bg-stone-900 hover:bg-green-800 text-white text-xs font-semibold transition-colors">
                                    Booking
                                </a>
                            <?php else: ?>
                                <a href="login.php"
                                   class="px-4 py-2 rounded-md border border-stone-300 hover:border-stone-900 text-stone-800 text-xs font-semibold transition-colors">
                                    Masuk
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- Paket Eskul -->
    <section class="border-t border-stone-200 pt-10">
        <div class="mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-stone-400">03 / Sekolah</p>
            <h2 class="text-2xl font-bold text-stone-900 mt-1">Paket Ekstrakurikuler</h2>
            <p class="text-sm text-stone-500 mt-1">Paket latihan bulanan (3 jam per pertemuan).</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while($paket = mysqli_fetch_assoc($result_eskul)): ?>
                <div class="bg-white rounded-lg border border-stone-200 overflow-hidden flex flex-col">
                    <?php if ($paket['gambar']): ?>
                        <img src="uploads/paket/<?php echo htmlspecialchars($paket['gambar'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="<?php echo htmlspecialchars($paket['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-stone-100 flex items-center justify-center text-stone-400 text-xs">
                            Foto belum tersedia
                        </div>
                    <?php endif; ?>
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex items-baseline justify-between gap-2 mb-1">
                            <h3 class="text-base font-bold text-stone-900"><?php echo htmlspecialchars($paket['nama_lapangan'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <span class="text-xs text-stone-500">3 jam/hari</span>
                        </div>
                        <div class="mb-3 tabular-nums">
                            <span class="text-xl font-bold text-green-800">
                                Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>
                            </span>
                            <span class="text-xs text-stone-400">/bulan</span>
                        </div>
                        <div class="text-sm text-stone-500 leading-relaxed mb-5 whitespace-pre-line flex-1">
                            <?php echo htmlspecialchars($paket['keterangan'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="booking_eskul.php?lapangan=<?php echo $paket['lapangan_id']; ?>" 
                               class="block text-center bg-stone-900 hover:bg-green-800 text-white text-xs font-semibold px-4 py-2.5 rounded-md transition-colors">
                                Booking Paket Eskul
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <p class="text-xs text-stone-500 mt-3">* Wajib mencantumkan nama sekolah dan nomor telepon guru penanggung jawab.</p>
    </section>

    <!-- Paket Turnamen -->
    <section class="border-t border-stone-200 pt-10">
        <div class="mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-stone-400">04 / Event</p>
            <h2 class="text-2xl font-bold text-stone-900 mt-1">Paket Turnamen</h2>
            <p class="text-sm text-stone-500 mt-1">Sewa harian untuk turnamen dengan durasi 1–7 hari.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
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
                <div class="bg-white rounded-lg border border-stone-200 overflow-hidden flex flex-col">
                    <?php if ($paket['gambar_sore']): ?>
                        <img src="uploads/paket/<?php echo htmlspecialchars($paket['gambar_sore'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="<?php echo htmlspecialchars($paket['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-stone-100 flex items-center justify-center text-stone-400 text-xs">
                            Foto belum tersedia
                        </div>
                    <?php endif; ?>
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex items-baseline justify-between gap-2 mb-3">
                            <h3 class="text-base font-bold text-stone-900"><?php echo htmlspecialchars($paket['nama'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <span class="text-xs text-stone-500">1–7 hari</span>
                        </div>
                        <div class="divide-y divide-stone-100 border-y border-stone-100 mb-4 text-sm tabular-nums">
                            <div class="py-2 flex justify-between items-center">
                                <span class="text-xs text-stone-500">Sore (10:00–17:00)</span>
                                <span class="font-bold text-stone-900">Rp <?php echo number_format($paket['harga_sore'] ?? 0, 0, ',', '.'); ?>/hr</span>
                            </div>
                            <div class="py-2 flex justify-between items-center">
                                <span class="text-xs text-stone-500">Malam (10:00–21:00)</span>
                                <span class="font-bold text-stone-900">Rp <?php echo number_format($paket['harga_malam'] ?? 0, 0, ',', '.'); ?>/hr</span>
                            </div>
                        </div>
                        <p class="text-sm text-stone-500 leading-relaxed mb-5 flex-1">
                            <?php echo htmlspecialchars(isset($deskripsi_turnamen[$paket['id_lapangan']]) ? $deskripsi_turnamen[$paket['id_lapangan']] : 'Lapangan futsal berkualitas tinggi dengan fasilitas lengkap', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="booking_turnamen.php?lapangan=<?php echo $paket['id_lapangan']; ?>" 
                               class="block text-center bg-stone-900 hover:bg-green-800 text-white text-xs font-semibold px-4 py-2.5 rounded-md transition-colors">
                                Booking Paket Turnamen
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <div class="text-xs text-stone-500 mt-3 space-y-0.5">
            <p>* Tersedia paket 1–7 hari. Wajib mencantumkan kontak panitia dan konfirmasi ke admin via WhatsApp.</p>
        </div>
    </section>

    <!-- Lokasi & Tentang Kami Section -->
    <section class="border-t border-stone-200 pt-10 grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-stone-400">05 / Tentang</p>
            <h2 class="text-2xl font-bold text-stone-900 mt-1 mb-4">Tentang Futsal Sayan</h2>
            <p class="text-sm sm:text-base text-stone-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($about, ENT_QUOTES, 'UTF-8')); ?></p>
            <div class="mt-6 pt-5 border-t border-stone-200 text-xs text-stone-500 space-y-1">
                <p class="font-semibold text-stone-800">Alamat</p>
                <p>Jl. Raya Mustikasari No.34, RT.002/RW.004, Mustikasari, Kec. Mustika Jaya, Kota Bekasi, Jawa Barat 17157</p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-stone-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-stone-900">Peta Lokasi</h3>
                <a href="https://share.google/BQBFVJJ2jyMocShFU" target="_blank" rel="noopener noreferrer"
                   class="text-xs font-semibold text-green-800 hover:underline">
                    Buka di Google Maps &rarr;
                </a>
            </div>
            <div class="rounded overflow-hidden border border-stone-200">
                <iframe
                    src="https://maps.google.com/maps?q=Sayan+Futsal+Family,+Jl.+Raya+Mustikasari+No.34,+Mustika+Jaya,+Bekasi&t=&z=16&ie=UTF8&iwloc=&output=embed"
                    width="100%" height="260" style="border:0; min-width:200px; min-height:220px; display:block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <!-- Hubungi Kami Section -->
    <section id="hubungi-kami" class="border-t border-stone-200 pt-10">
        <div class="bg-white border border-stone-200 rounded-lg p-6 sm:p-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div class="lg:col-span-5 flex items-center justify-center">
                <img src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/images/Contact_us.svg"
                     alt="Hubungi Futsal Sayan Bekasi"
                     class="w-full max-w-[240px] sm:max-w-xs h-auto">
            </div>
            <div class="lg:col-span-7 space-y-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-stone-400">06 / Kontak</p>
                    <h2 class="text-2xl font-bold text-stone-900 mt-1">Ada Pertanyaan atau Mau Booking Turnamen?</h2>
                    <p class="text-sm text-stone-500 leading-relaxed mt-2">
                        Tim admin Futsal Sayan siap membantu konfirmasi pembayaran, jadwal ekstrakurikuler sekolah, maupun reservasi turnamen.
                    </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 text-sm">
                    <div class="p-3.5 rounded-md bg-stone-50 border border-stone-200">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">WhatsApp / Telepon</p>
                        <p class="font-bold text-stone-900 mt-0.5 tabular-nums">0857-4851-5801</p>
                    </div>
                    <div class="p-3.5 rounded-md bg-stone-50 border border-stone-200">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Jam Operasional</p>
                        <p class="font-bold text-stone-900 mt-0.5">Setiap Hari, 08:00 – 23:00 WIB</p>
                    </div>
                </div>
                <div class="pt-1 flex flex-wrap gap-3">
                    <a href="https://wa.me/6285748515801" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-md bg-green-900 hover:bg-green-800 text-white text-sm font-semibold transition-colors">
                        <i class="fab fa-whatsapp text-base"></i> Chat via WhatsApp
                    </a>
                    <a href="https://share.google/BQBFVJJ2jyMocShFU" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center px-5 py-2.5 rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 text-sm font-medium transition-colors">
                        Petunjuk Arah
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal Detail Booking (Bottom sheet on mobile, centered on desktop) -->
<div id="bookingDetailModal" class="fixed inset-0 bg-stone-900/50 hidden z-50 p-0 sm:p-4">
    <div class="min-h-full flex items-end sm:items-center justify-center">
        <div class="w-full sm:max-w-md bg-white border-t sm:border border-stone-200 rounded-t-2xl sm:rounded-lg shadow-xl p-5 sm:p-6">
            <div class="w-10 h-1 bg-stone-300 rounded-full mx-auto mb-4 sm:hidden"></div>
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <h3 class="text-base font-bold text-stone-900" id="modalDate"></h3>
                <button onclick="closeModal()" class="w-9 h-9 -mr-1.5 flex items-center justify-center rounded-md text-stone-400 hover:text-stone-900 active:bg-stone-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent" class="mt-4 space-y-3 max-h-[60vh] overflow-y-auto">
                <!-- Content will be inserted here by JavaScript -->
            </div>
            <div class="mt-5">
                <button onclick="closeModal()" class="bg-stone-900 hover:bg-stone-800 active:bg-stone-700 text-white text-sm font-medium px-4 py-3 sm:py-2.5 rounded-md w-full transition-colors">
                    Tutup
                </button>
            </div>
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
    if (e.target === this || e.target === this.firstElementChild) {
        closeModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
