<div class="bg-white shadow mb-6">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-4">
            <div class="flex space-x-4">
                <a href="index.php" class="<?php echo !isset($_GET['tab']) || $_GET['tab'] == 'booking_regular' ? 'text-blue-600 font-bold' : 'text-gray-600'; ?>">
                    Booking Regular
                </a>
                <a href="dashboard.php?tab=booking_eskul" class="<?php echo isset($_GET['tab']) && $_GET['tab'] == 'booking_eskul' ? 'text-blue-600 font-bold' : 'text-gray-600'; ?>">
                    Booking Eskul
                </a>
                <a href="dashboard.php?tab=booking_turnamen" class="<?php echo isset($_GET['tab']) && $_GET['tab'] == 'booking_turnamen' ? 'text-blue-600 font-bold' : 'text-gray-600'; ?>">
                    Booking Turnamen
                </a>
                <a href="dashboard.php?tab=kelola_lapangan" class="<?php echo isset($_GET['tab']) && $_GET['tab'] == 'kelola_lapangan' ? 'text-blue-600 font-bold' : 'text-gray-600'; ?>">
                    Kelola Lapangan
                </a>
                <a href="kelola_paket.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kelola_paket.php' ? 'text-blue-600 font-bold' : 'text-gray-600'; ?>">
                    Kelola Paket
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <select id="bulan" class="border rounded px-2 py-1">
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
                <select id="tahun" class="border rounded px-2 py-1">
                    <?php 
                    $current_year = date('Y');
                    for($year = $current_year - 1; $year <= $current_year + 1; $year++) {
                        echo "<option value='$year'>$year</option>";
                    }
                    ?>
                </select>
                <button onclick="exportExcel()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-file-excel mr-2"></i>Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function exportExcel() {
    const bulan = document.getElementById('bulan').value;
    const tahun = document.getElementById('tahun').value;
    window.location.href = `export_excel.php?bulan=${bulan}&tahun=${tahun}`;
}

// Set default values to current month and year
document.addEventListener('DOMContentLoaded', function() {
    const currentMonth = new Date().getMonth() + 1; // getMonth() returns 0-11
    const currentYear = new Date().getFullYear();
    
    document.getElementById('bulan').value = currentMonth;
    document.getElementById('tahun').value = currentYear;
});
</script>
