<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
    <div class="bg-green-100 p-4 rounded-lg">
        <h3 class="text-green-800 font-semibold">Total Pendapatan</h3>
        <p class="text-2xl font-bold text-green-600">Rp <?php echo number_format($stats['total_pendapatan'] ?? 0, 0, ',', '.'); ?></p>
    </div>
    <div class="bg-green-200 p-4 rounded-lg">
        <h3 class="text-green-800 font-semibold">Pendapatan Bulan Ini</h3>
        <p class="text-2xl font-bold text-green-600">Rp <?php echo number_format($stats['total_pendapatan_bulan'] ?? 0, 0, ',', '.'); ?></p>
        <span class="text-xs text-gray-600">Bulan: <?php 
            $selected_month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
            $selected_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
            $month_names = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            echo $month_names[$selected_month] . ' ' . $selected_year;
        ?></span>
    </div>
    <div class="bg-blue-100 p-4 rounded-lg">
        <h3 class="text-blue-800 font-semibold">Total User</h3>
        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_user'] ?? 0; ?></p>
    </div>
    <div class="bg-purple-100 p-4 rounded-lg">
        <h3 class="text-purple-800 font-semibold">Total Booking</h3>
        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total_booking'] ?? 0; ?></p>
    </div>
    <div class="bg-yellow-100 p-4 rounded-lg">
        <h3 class="text-yellow-800 font-semibold">Total Pending</h3>
        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['total_pending'] ?? 0; ?></p>
    </div>
    <div class="bg-green-100 p-4 rounded-lg">
        <h3 class="text-green-800 font-semibold">Total Konfirmasi</h3>
        <p class="text-2xl font-bold text-green-600"><?php echo $stats['total_konfirmasi'] ?? 0; ?></p>
    </div>
    <div class="bg-red-100 p-4 rounded-lg">
        <h3 class="text-red-800 font-semibold">Total Dibatalkan</h3>
        <p class="text-2xl font-bold text-red-600"><?php echo $stats['total_dibatalkan'] ?? 0; ?></p>
    </div>
    <div class="bg-blue-200 p-4 rounded-lg">
        <h3 class="text-blue-800 font-semibold">Booking Regular (Bulan Ini)</h3>
        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_booking_regular'] ?? 0; ?></p>
    </div>
    <div class="bg-yellow-200 p-4 rounded-lg">
        <h3 class="text-yellow-800 font-semibold">Booking Eskul (Bulan Ini)</h3>
        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['total_booking_eskul'] ?? 0; ?></p>
    </div>
    <div class="bg-purple-200 p-4 rounded-lg">
        <h3 class="text-purple-800 font-semibold">Booking Turnamen (Bulan Ini)</h3>
        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total_booking_turnamen'] ?? 0; ?></p>
    </div>
</div>
