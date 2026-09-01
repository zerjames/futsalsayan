<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
    <div class="w-full">
        <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-2 pb-2 md:pb-0">
            <a href="dashboard.php" class="px-4 py-2 <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'booking_regular' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded hover:bg-blue-200">
                Booking Regular
            </a>
            <a href="dashboard.php?tab=booking_eskul" class="px-4 py-2 <?php echo isset($_GET['tab']) && $_GET['tab'] == 'booking_eskul' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded hover:bg-blue-200">
                Booking Eskul
            </a>
            <a href="dashboard.php?tab=booking_turnamen" class="px-4 py-2 <?php echo isset($_GET['tab']) && $_GET['tab'] == 'booking_turnamen' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded hover:bg-blue-200">
                Booking Turnamen
            </a>
            <a href="dashboard.php?tab=kelola_lapangan" class="px-4 py-2 <?php echo isset($_GET['tab']) && $_GET['tab'] == 'kelola_lapangan' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded hover:bg-blue-200">
                Kelola Lapangan
            </a>
            <a href="dashboard.php?tab=kelola_user" class="px-4 py-2 <?php echo isset($_GET['tab']) && $_GET['tab'] == 'kelola_user' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'; ?> rounded hover:bg-blue-200">
                Kelola User
            </a>
        </div>
    </div>
    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
    <div class="flex flex-col md:flex-row md:items-center md:space-x-2 w-full md:w-auto mt-4 md:mt-0">
        <div class="flex flex-col md:flex-row md:items-center md:space-x-2 w-full">
            <select id="bulan" class="border rounded px-2 py-2 mb-2 md:mb-0 w-full md:w-auto">
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
            <select id="tahun" class="border rounded px-2 py-2 mb-2 md:mb-0 w-full md:w-auto">
                <?php 
                $current_year = date('Y');
                for($year = $current_year - 1; $year <= $current_year + 1; $year++) {
                    $selected = (isset($_GET['tahun']) && $_GET['tahun'] == $year) || (!isset($_GET['tahun']) && $year == $current_year) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
            <button onclick="filterBookings()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 mb-2 md:mb-0 w-full md:w-auto">
                Filter
            </button>
            <button onclick="exportExcel()" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 mb-2 md:mb-0 w-full md:w-auto">
                Export Excel
            </button>
        </div>
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
