<?php
require_once '../includes/security.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil data harga paket eskul
$query_eskul = "SELECT * FROM harga_paket WHERE tipe LIKE 'eskul%' ORDER BY tipe ASC";
$result_eskul = mysqli_query($conn, $query_eskul);

// Ambil data harga paket turnamen
$query_turnamen = "SELECT * FROM harga_paket WHERE tipe LIKE 'turnamen%' ORDER BY tipe ASC";
$result_turnamen = mysqli_query($conn, $query_turnamen);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Paket - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Navigation -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex space-x-2">
                <a href="index.php" class="px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Booking Regular</a>
                <a href="index.php?tab=booking_eskul" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Booking Eskul</a>
                <a href="index.php?tab=booking_turnamen" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Booking Turnamen</a>
                <a href="dashboard.php?tab=kelola_lapangan" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Kelola Lapangan</a>
                <a href="kelola_paket.php" class="px-4 py-2 bg-blue-500 text-white rounded">Kelola Paket</a>
            </div>
            <div class="flex items-center space-x-2">
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
                        echo "<option value='$year'" . ($year == $current_year ? ' selected' : '') . ">$year</option>";
                    }
                    ?>
                </select>
                <button onclick="exportExcel()" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    Export Excel
                </button>
            </div>
        </div>

        <!-- Paket Ekstrakurikuler -->
        <h2 class="text-2xl font-bold mb-4">Paket Ekstrakurikuler</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php while($paket = mysqli_fetch_assoc($result_eskul)): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-green-600">
                                <?php 
                                $lapangan = str_replace('eskul_lap', 'Lapangan ', $paket['tipe']);
                                echo $lapangan;
                                ?>
                            </h3>
                            <p class="text-2xl font-bold text-green-600">
                                Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>/bulan
                            </p>
                        </div>
                        <button onclick="showEditModal('<?php echo $paket['id']; ?>', '<?php echo $paket['tipe']; ?>', <?php echo $paket['harga']; ?>, `<?php echo addslashes($paket['keterangan']); ?>')"
                                class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Edit
                        </button>
                    </div>
                    <div class="text-gray-600 whitespace-pre-line">
                        <?php echo $paket['keterangan']; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Paket Turnamen -->
        <h2 class="text-2xl font-bold mb-4">Paket Turnamen</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php while($paket = mysqli_fetch_assoc($result_turnamen)): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-green-600">
                                <?php 
                                $nama = str_replace(['turnamen_lap', '_sore', '_malam'], ['Lapangan ', ' (Sore)', ' (Malam)'], $paket['tipe']);
                                echo $nama;
                                ?>
                            </h3>
                            <p class="text-2xl font-bold text-green-600">
                                Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>/hari
                            </p>
                        </div>
                        <button onclick="showEditModal('<?php echo $paket['id']; ?>', '<?php echo $paket['tipe']; ?>', <?php echo $paket['harga']; ?>, `<?php echo addslashes($paket['keterangan']); ?>')"
                                class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Edit
                        </button>
                    </div>
                    <div class="text-gray-600 whitespace-pre-line">
                        <?php echo $paket['keterangan']; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-bold mb-4">Edit Harga Paket</h3>
            <form method="POST" action="proses_harga_paket.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="tipe" id="edit_tipe">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                    <input type="number" name="harga" id="edit_harga" required
                           class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <textarea name="keterangan" id="edit_keterangan" rows="4" required
                              class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showEditModal(id, tipe, harga, keterangan) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_tipe').value = tipe;
        document.getElementById('edit_harga').value = harga;
        document.getElementById('edit_keterangan').value = keterangan;
        
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
    }

    function closeModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    function exportExcel() {
        const bulan = document.getElementById('bulan').value;
        const tahun = document.getElementById('tahun').value;
        window.location.href = `export_excel.php?bulan=${bulan}&tahun=${tahun}`;
    }

    // Set default month
    document.addEventListener('DOMContentLoaded', function() {
        const currentMonth = new Date().getMonth() + 1;
        document.getElementById('bulan').value = currentMonth;
    });
    </script>
</body>
</html>
