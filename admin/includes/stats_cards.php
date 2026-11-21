<!-- Stats Cards -->
<?php
$selected_month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$selected_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$periode_label = ($month_names[$selected_month] ?? '') . ' ' . $selected_year;
?>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6 tabular-nums">
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Total Pendapatan</p>
        <p class="text-lg font-bold text-stone-900 mt-1.5">Rp <?php echo number_format($stats['total_pendapatan'] ?? 0, 0, ',', '.'); ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Pendapatan Bulan Ini</p>
        <p class="text-lg font-bold text-green-800 mt-1.5">Rp <?php echo number_format($stats['total_pendapatan_bulan'] ?? 0, 0, ',', '.'); ?></p>
        <span class="text-[11px] text-stone-400"><?php echo htmlspecialchars($periode_label, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Total User</p>
        <p class="text-xl font-bold text-stone-900 mt-1.5"><?php echo $stats['total_user'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Total Booking</p>
        <p class="text-xl font-bold text-stone-900 mt-1.5"><?php echo $stats['total_booking'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Pending</p>
        <p class="text-xl font-bold text-amber-700 mt-1.5"><?php echo $stats['total_pending'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Dikonfirmasi</p>
        <p class="text-xl font-bold text-green-800 mt-1.5"><?php echo $stats['total_konfirmasi'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Dibatalkan</p>
        <p class="text-xl font-bold text-red-700 mt-1.5"><?php echo $stats['total_dibatalkan'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Regular (Bulan Ini)</p>
        <p class="text-xl font-bold text-stone-900 mt-1.5"><?php echo $stats['total_booking_regular'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Eskul (Bulan Ini)</p>
        <p class="text-xl font-bold text-stone-900 mt-1.5"><?php echo $stats['total_booking_eskul'] ?? 0; ?></p>
    </div>
    <div class="bg-white border border-stone-200 rounded-lg p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-400">Turnamen (Bulan Ini)</p>
        <p class="text-xl font-bold text-stone-900 mt-1.5"><?php echo $stats['total_booking_turnamen'] ?? 0; ?></p>
    </div>
</div>
