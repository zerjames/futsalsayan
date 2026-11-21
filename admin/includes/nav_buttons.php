<div class="border-b border-stone-200 pb-3 mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
    <div class="flex flex-nowrap overflow-x-auto no-scrollbar items-center gap-1.5 -mx-1 px-1 pb-1 lg:pb-0">
        <a href="dashboard.php" class="whitespace-nowrap flex-shrink-0 px-3.5 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'booking_regular' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200/70'; ?>">
            Booking Regular
        </a>
        <a href="dashboard.php?tab=booking_eskul" class="whitespace-nowrap flex-shrink-0 px-3.5 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors <?php echo isset($_GET['tab']) && $_GET['tab'] == 'booking_eskul' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200/70'; ?>">
            Booking Eskul
        </a>
        <a href="dashboard.php?tab=booking_turnamen" class="whitespace-nowrap flex-shrink-0 px-3.5 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors <?php echo isset($_GET['tab']) && $_GET['tab'] == 'booking_turnamen' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200/70'; ?>">
            Booking Turnamen
        </a>
        <a href="dashboard.php?tab=kelola_lapangan" class="whitespace-nowrap flex-shrink-0 px-3.5 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors <?php echo isset($_GET['tab']) && $_GET['tab'] == 'kelola_lapangan' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200/70'; ?>">
            Kelola Lapangan
        </a>
        <a href="dashboard.php?tab=kelola_user" class="whitespace-nowrap flex-shrink-0 px-3.5 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors <?php echo isset($_GET['tab']) && $_GET['tab'] == 'kelola_user' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200/70'; ?>">
            Kelola User
        </a>
    </div>
    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
    <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2">
        <select id="bulan" class="px-3 py-2 sm:py-1.5 rounded-md border border-stone-300 text-sm text-stone-700 bg-white focus:outline-none focus:border-stone-900">
            <?php
            $months = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            foreach ($months as $num => $name) {
                $selected = (isset($_GET['bulan']) && $_GET['bulan'] == $num) || (!isset($_GET['bulan']) && $num == date('n')) ? 'selected' : '';
                echo "<option value='$num' $selected>$name</option>";
            }
            ?>
        </select>
        <select id="tahun" class="px-3 py-2 sm:py-1.5 rounded-md border border-stone-300 text-sm text-stone-700 bg-white focus:outline-none focus:border-stone-900">
            <?php 
            $current_year = date('Y');
            for($year = $current_year - 1; $year <= $current_year + 1; $year++) {
                $selected = (isset($_GET['tahun']) && $_GET['tahun'] == $year) || (!isset($_GET['tahun']) && $year == $current_year) ? 'selected' : '';
                echo "<option value='$year' $selected>$year</option>";
            }
            ?>
        </select>
        <button onclick="filterBookings()" class="px-3.5 py-2 sm:py-1.5 bg-stone-900 hover:bg-stone-700 text-white text-sm font-medium rounded-md transition-colors">
            Filter
        </button>
        <button onclick="exportExcel()" class="px-3.5 py-2 sm:py-1.5 bg-white hover:bg-stone-100 text-stone-800 border border-stone-300 text-sm font-medium rounded-md transition-colors">
            Export Excel
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
function filterBookings() {
    const bulan = document.getElementById('bulan').value;
    const tahun = document.getElementById('tahun').value;
    // Ambil tab dari URL jika ada
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'booking_regular';
    window.location.href = `dashboard.php?tab=${tab}&bulan=${bulan}&tahun=${tahun}`;
}

function exportExcel() {
    const bulan = document.getElementById('bulan').value;
    const tahun = document.getElementById('tahun').value;
    window.location.href = `export_excel.php?bulan=${bulan}&tahun=${tahun}`;
}

// Set selected values on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const selectedMonth = urlParams.get('bulan') || (new Date().getMonth() + 1);
    const selectedYear = urlParams.get('tahun') || new Date().getFullYear();
    if (document.getElementById('bulan')) {
        document.getElementById('bulan').value = selectedMonth;
    }
    if (document.getElementById('tahun')) {
        document.getElementById('tahun').value = selectedYear;
    }
});
</script>
