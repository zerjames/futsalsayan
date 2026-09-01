<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Fungsi untuk validasi nama (hanya huruf alphabet dan spasi)
function validateName($name) {
    return preg_match("/^[a-zA-Z\s]+$/", $name);
}

// Fungsi untuk mengecek status lapangan pada tanggal dan jam tertentu
function cekStatusLapangan($conn, $lapangan_id, $tanggal, $jam) {
    $query = "SELECT b.*, u.nama as nama_user 
              FROM booking b 
              JOIN users u ON b.user_id = u.id_user 
              WHERE b.lapangan_id = '$lapangan_id' 
              AND b.tanggal_main = '$tanggal' 
              AND (
                  (b.jam_mulai <= '$jam' AND b.jam_selesai > '$jam')
                  OR
                  (b.jam_mulai > '$jam' AND b.jam_mulai < DATE_ADD('$jam', INTERVAL 1 HOUR))
              )
              AND b.status_pembayaran != 'dibatalkan'";
    
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
        $status = [
            'tersedia' => false,
            'keterangan' => 'Dibooking oleh ' . $booking['nama_user'],
            'jam_mulai' => $booking['jam_mulai'],
            'jam_selesai' => $booking['jam_selesai']
        ];
        
        // Cek apakah sedang digunakan saat ini
        $now = date('Y-m-d H:i:s');
        $booking_start = $booking['tanggal_main'] . ' ' . $booking['jam_mulai'];
        $booking_end = $booking['tanggal_main'] . ' ' . $booking['jam_selesai'];
        
        if ($now >= $booking_start && $now <= $booking_end) {
            $status['keterangan'] = 'Sedang digunakan oleh ' . $booking['nama_user'];
        }
        
        return $status;
    }
    
    return [
        'tersedia' => true,
        'keterangan' => 'Tersedia',
        'jam_mulai' => null,
        'jam_selesai' => null
    ];
}

// Fungsi untuk mendapatkan jadwal booking lapangan pada tanggal tertentu
function getJadwalLapangan($conn, $lapangan_id, $tanggal) {
    // Query untuk booking regular
    $query_regular = "SELECT b.id_booking, b.user_id, b.lapangan_id, b.tanggal_main as tanggal, 
                            b.jam_mulai, b.jam_selesai, b.total_harga, b.status_pembayaran,
                            u.nama as nama_user, l.nama as nama_lapangan, 
                            'Regular' as jenis_booking,
                            NULL as durasi_bulan,
                            NULL as durasi_hari
                     FROM booking b 
                     JOIN users u ON b.user_id = u.id 
                     JOIN lapangan l ON b.lapangan_id = l.id
                     WHERE b.lapangan_id = '$lapangan_id' 
                     AND b.tanggal_main = '$tanggal' 
                     AND b.status_pembayaran != 'dibatalkan'";

    // Query untuk booking eskul (hanya pada hari yang sama dalam minggu)
    $query_eskul = "SELECT be.id_booking_eskul, be.user_id, be.lapangan_id, be.tanggal_mulai as tanggal,
                           be.jam_mulai, ADDTIME(be.jam_mulai, '03:00:00') as jam_selesai,
                           be.total_harga, be.status_pembayaran,
                           u.nama as nama_user, l.nama as nama_lapangan,
                           'Ekstrakurikuler' as jenis_booking,
                           be.durasi_bulan,
                           NULL as durasi_hari
                    FROM booking_eskul be
                    JOIN users u ON be.user_id = u.id
                    JOIN lapangan l ON be.lapangan_id = l.id
                    WHERE be.lapangan_id = '$lapangan_id'
                    AND '$tanggal' BETWEEN be.tanggal_mulai 
                    AND DATE_ADD(be.tanggal_mulai, INTERVAL be.durasi_bulan MONTH)
                    AND DAYOFWEEK('$tanggal') = DAYOFWEEK(be.tanggal_mulai)";

    // Query untuk booking turnamen
    $query_turnamen = "SELECT bt.id_booking_turnamen, bt.user_id, bt.lapangan_id, bt.tanggal_mulai as tanggal,
                             bt.jam_mulai, bt.jam_selesai, bt.total_harga,
                             bt.status_pembayaran,
                             u.nama as nama_user, l.nama as nama_lapangan,
                             'Turnamen' as jenis_booking,
                             NULL as durasi_bulan,
                             bt.durasi_hari
                      FROM booking_turnamen bt
                      JOIN users u ON bt.user_id = u.id
                      JOIN lapangan l ON bt.lapangan_id = l.id
                      WHERE bt.lapangan_id = '$lapangan_id'
                      AND '$tanggal' BETWEEN bt.tanggal_mulai 
                      AND DATE_ADD(bt.tanggal_mulai, INTERVAL bt.durasi_hari - 1 DAY)";

    // Gabungkan semua query
    $query = "($query_regular) UNION ALL ($query_eskul) UNION ALL ($query_turnamen) ORDER BY jam_mulai ASC";
    
    $result = mysqli_query($conn, $query);
    $jadwal = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $jadwal[] = [
            'nama_user' => $row['nama_user'] ?? 'Unknown',
            'nama_lapangan' => $row['nama_lapangan'] ?? 'Unknown',
            'jam_mulai' => $row['jam_mulai'],
            'jam_selesai' => $row['jam_selesai'],
            'status_pembayaran' => $row['status_pembayaran'] ?? 'pending',
            'jenis_booking' => $row['jenis_booking'],
            'tanggal' => $row['tanggal'],
            'durasi_bulan' => $row['durasi_bulan'],
            'durasi_hari' => $row['durasi_hari']
        ];
    }
    
    return $jadwal;
}

// Fungsi untuk format jam
function formatJam($jam) {
    return date('H:i', strtotime($jam));
}

// Fungsi untuk mengecek konflik booking di semua jenis booking
function cekKonflikBooking($conn, $lapangan_id, $tanggal, $jam_mulai, $jam_selesai, $exclude_booking_id = null) {
    // Cek booking regular
    $query_regular = "SELECT 'Regular' as jenis, jam_mulai, jam_selesai 
                     FROM booking 
                     WHERE lapangan_id = '$lapangan_id' 
                     AND tanggal_main = '$tanggal' 
                     AND status_pembayaran != 'dibatalkan'
                     AND ((jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai') 
                          OR (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai')
                          OR (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai'))";
    
    if ($exclude_booking_id) {
        $query_regular .= " AND id_booking != '$exclude_booking_id'";
    }

    // Cek booking eskul (hanya pada hari yang sama dalam minggu)
    $query_eskul = "SELECT 'Ekstrakurikuler' as jenis, 
                           jam_mulai, 
                           ADDTIME(jam_mulai, '03:00:00') as jam_selesai
                    FROM booking_eskul 
                    WHERE lapangan_id = '$lapangan_id' 
                    AND tanggal_mulai <= '$tanggal'
                    AND DATE_ADD(tanggal_mulai, INTERVAL durasi_bulan MONTH) >= '$tanggal'
                    AND DAYOFWEEK('$tanggal') = DAYOFWEEK(tanggal_mulai)
                    AND status_pembayaran != 'dibatalkan'
                    AND ((jam_mulai <= '$jam_mulai' AND ADDTIME(jam_mulai, '03:00:00') > '$jam_mulai')
                         OR (jam_mulai < '$jam_selesai' AND ADDTIME(jam_mulai, '03:00:00') >= '$jam_selesai')
                         OR (jam_mulai >= '$jam_mulai' AND ADDTIME(jam_mulai, '03:00:00') <= '$jam_selesai'))";

    // Cek booking turnamen
    $query_turnamen = "SELECT 'Turnamen' as jenis, jam_mulai, jam_selesai
                      FROM booking_turnamen 
                      WHERE lapangan_id = '$lapangan_id' 
                      AND '$tanggal' BETWEEN tanggal_mulai 
                      AND DATE_ADD(tanggal_mulai, INTERVAL durasi_hari - 1 DAY)
                      AND status_pembayaran != 'dibatalkan'
                      AND ((jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai')
                           OR (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai')
                           OR (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai'))";

    // Tentukan query yang akan digunakan berdasarkan jenis booking yang sedang dicek
    $url = $_SERVER['REQUEST_URI'];
    if (strpos($url, 'booking_eskul.php') !== false) {
        // Jika sedang booking eskul, cek konflik dengan regular dan eskul saja
        $query = "($query_regular) UNION ($query_eskul)";
    } elseif (strpos($url, 'booking_turnamen.php') !== false) {
        // Jika sedang booking turnamen, cek konflik dengan regular dan turnamen saja
        $query = "($query_regular) UNION ($query_turnamen)";
    } else {
        // Untuk booking regular, cek semua jenis booking
        $query = "($query_regular) UNION ($query_eskul) UNION ($query_turnamen)";
    }
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
        return [
            'ada_konflik' => true,
            'jenis' => $booking['jenis'],
            'jam_mulai' => $booking['jam_mulai'],
            'jam_selesai' => $booking['jam_selesai'],
            'pesan' => sprintf(
                "Sudah ada booking %s pada jam %s - %s. Silakan pilih jam lain atau cek jadwal yang tersedia di bawah form.",
                $booking['jenis'],
                date('H:i', strtotime($booking['jam_mulai'])),
                date('H:i', strtotime($booking['jam_selesai']))
            )
        ];
    }

    return [
        'ada_konflik' => false,
        'pesan' => 'Jadwal tersedia'
    ];
}

// Fungsi auto cancel booking yang belum dibayar/konfirmasi admin dalam 2 jam
function autoCancelUnpaidBookings($conn) {
    // Debug: log saat fungsi dijalankan
    file_put_contents(__DIR__.'/debug_auto_cancel.log', date('Y-m-d H:i:s')." - Fungsi dijalankan\n", FILE_APPEND);
    $now = date('Y-m-d H:i:s');
    $tables = [
        ['table'=>'booking', 'date_field'=>'tanggal_booking'],
        ['table'=>'booking_eskul', 'date_field'=>'tanggal_booking'],
        ['table'=>'booking_turnamen', 'date_field'=>'tanggal_booking']
    ];
    foreach ($tables as $tb) {
        $table = $tb['table'];
        $date_field = $tb['date_field'];
        // Auto-cancel booking yang belum dibayar/konfirmasi admin dalam 2 jam
        $sql = "UPDATE $table SET status_pembayaran='dibatalkan' WHERE status_pembayaran='pending' AND TIMESTAMPDIFF(HOUR, $date_field, '$now') >= 2";
        // Debug: log query SQL
        file_put_contents(__DIR__.'/debug_auto_cancel.log', $sql . "\n", FILE_APPEND);
        mysqli_query($conn, $sql);
        $affected = mysqli_affected_rows($conn);
        file_put_contents(__DIR__.'/debug_auto_cancel.log', "Baris diupdate: $affected\n", FILE_APPEND);
    }
    // Debug: tampilkan data booking yang masih pending
    $result = mysqli_query($conn, "SELECT id_booking, tanggal_booking, status_pembayaran FROM booking WHERE status_pembayaran='pending'");
    while ($row = mysqli_fetch_assoc($result)) {
        file_put_contents(__DIR__.'/debug_auto_cancel.log', 'ID: '.$row['id_booking'].' tanggal_booking: '.$row['tanggal_booking'].'\n', FILE_APPEND);
    }
}
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
