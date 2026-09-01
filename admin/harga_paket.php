<?php
// Include database dan functions di awal
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Cek apakah tabel harga_paket sudah ada
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'harga_paket'");
if (mysqli_num_rows($table_check) == 0) {
    // Buat tabel jika belum ada
    $sql_file = file_get_contents('sql/create_harga_paket.sql');
    mysqli_multi_query($conn, $sql_file);
    while (mysqli_next_result($conn)) {;} // clear multi_queries
}

// Ambil data harga paket eskul
$query_eskul = "SELECT * FROM harga_paket WHERE tipe LIKE 'eskul%' ORDER BY harga ASC";
$result_eskul = mysqli_query($conn, $query_eskul);

// Ambil data harga paket turnamen
$query_turnamen = "SELECT * FROM harga_paket WHERE tipe LIKE 'turnamen%' ORDER BY harga ASC";
$result_turnamen = mysqli_query($conn, $query_turnamen);

// Include header setelah semua logic PHP dan redirect selesai
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Navigation -->
    <?php include 'includes/nav_buttons.php'; ?>

    <!-- Harga Paket Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Paket Ekstrakurikuler -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Paket Ekstrakurikuler</h2>
                <button onclick="showAddModal('eskul')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    + Tambah Paket
                </button>
            </div>

            <div class="space-y-6">
                <?php while($paket = mysqli_fetch_assoc($result_eskul)): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    <?php 
                                    $durasi = str_replace(['eskul_', 'bulan'], '', $paket['tipe']);
                                    echo $durasi . " Bulan"; 
                                    ?>
                                </h3>
                                <p class="text-2xl font-bold text-green-600 mt-1">
                                    Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="showEditModal('<?php echo $paket['id']; ?>', '<?php echo $paket['tipe']; ?>', <?php echo $paket['harga']; ?>, `<?php echo addslashes($paket['keterangan']); ?>')"
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="if(confirm('Yakin ingin menghapus paket ini?')) window.location.href='proses_harga_paket.php?action=delete&id=<?php echo $paket['id']; ?>'"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-gray-600">
                            <?php echo nl2br($paket['keterangan']); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Paket Turnamen -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Paket Turnamen</h2>
                <button onclick="showAddModal('turnamen')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    + Tambah Paket
                </button>
            </div>

            <div class="space-y-6">
                <?php while($paket = mysqli_fetch_assoc($result_turnamen)): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    <?php 
                                    $durasi = str_replace(['turnamen_', 'hari'], '', $paket['tipe']);
                                    echo $durasi . " Hari"; 
                                    ?>
                                </h3>
                                <p class="text-2xl font-bold text-green-600 mt-1">
                                    Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="showEditModal('<?php echo $paket['id']; ?>', '<?php echo $paket['tipe']; ?>', <?php echo $paket['harga']; ?>, `<?php echo addslashes($paket['keterangan']); ?>')"
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="if(confirm('Yakin ingin menghapus paket ini?')) window.location.href='proses_harga_paket.php?action=delete&id=<?php echo $paket['id']; ?>'"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-gray-600">
                            <?php echo nl2br($paket['keterangan']); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="paketModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
        <h3 id="modalTitle" class="text-lg font-bold mb-4">Tambah Paket Baru</h3>
        <form id="paketForm" method="POST" action="proses_harga_paket.php">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="paketId">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Paket</label>
                <select name="tipe" id="tipe" required class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
                    <optgroup label="Ekstrakurikuler" id="eskulOptions">
                        <option value="eskul_3bulan">3 Bulan</option>
                        <option value="eskul_6bulan">6 Bulan</option>
                        <option value="eskul_12bulan">12 Bulan</option>
                    </optgroup>
                    <optgroup label="Turnamen" id="turnamenOptions">
                        <option value="turnamen_3hari">3 Hari</option>
                        <option value="turnamen_5hari">5 Hari</option>
                        <option value="turnamen_7hari">7 Hari</option>
                    </optgroup>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                <input type="number" name="harga" id="harga" required
                       class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                <textarea name="keterangan" id="keterangan" rows="4" required
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
function showAddModal(type) {
    document.getElementById('modalTitle').textContent = 'Tambah Paket ' + 
        (type === 'eskul' ? 'Ekstrakurikuler' : 'Turnamen');
    document.getElementById('formAction').value = 'add';
    document.getElementById('paketId').value = '';
    document.getElementById('harga').value = '';
    document.getElementById('keterangan').value = '';
    
    // Show only relevant options
    document.getElementById('eskulOptions').style.display = type === 'eskul' ? '' : 'none';
    document.getElementById('turnamenOptions').style.display = type === 'turnamen' ? '' : 'none';
    
    // Select first option of relevant type
    const firstOption = type === 'eskul' ? 
        document.querySelector('option[value^="eskul_"]') :
        document.querySelector('option[value^="turnamen_"]');
    if (firstOption) firstOption.selected = true;
    
    showModal();
}

function showEditModal(id, tipe, harga, keterangan) {
    document.getElementById('modalTitle').textContent = 'Edit Paket';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('paketId').value = id;
    document.getElementById('tipe').value = tipe;
    document.getElementById('harga').value = harga;
    document.getElementById('keterangan').value = keterangan;
    
    // Show only relevant options
    const isEskul = tipe.startsWith('eskul');
    document.getElementById('eskulOptions').style.display = isEskul ? '' : 'none';
    document.getElementById('turnamenOptions').style.display = isEskul ? 'none' : '';
    
    showModal();
}

function showModal() {
    document.getElementById('paketModal').classList.remove('hidden');
    document.getElementById('paketModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('paketModal').classList.add('hidden');
    document.getElementById('paketModal').classList.remove('flex');
}

// Close modal when clicking outside
document.getElementById('paketModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
