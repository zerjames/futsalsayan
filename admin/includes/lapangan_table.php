<?php
// Ambil data lapangan
$query_lapangan = "SELECT * FROM lapangan ORDER BY id_lapangan ASC";
$result_lapangan = mysqli_query($conn, $query_lapangan);

// Ambil data harga paket eskul
$query_eskul = "SELECT * FROM harga_paket WHERE tipe LIKE 'eskul_lap%' ORDER BY tipe ASC";
$result_eskul = mysqli_query($conn, $query_eskul);
$num_eskul = mysqli_num_rows($result_eskul);

// Ambil data harga paket turnamen
$query_turnamen = "SELECT * FROM harga_paket WHERE tipe LIKE 'turnamen_lap%' ORDER BY tipe ASC";
$result_turnamen = mysqli_query($conn, $query_turnamen);
$num_turnamen = mysqli_num_rows($result_turnamen);

// Debug: Tampilkan jumlah data
echo "<!-- Debug: $num_eskul paket eskul, $num_turnamen paket turnamen -->";

// Ambil data lapangan untuk dropdown (hanya sekali)
$lapangan_dropdown_result = mysqli_query($conn, "SELECT id_lapangan, nama FROM lapangan ORDER BY id_lapangan ASC");
$lapangan_dropdown_options = "";
$counter = 1;
while ($lapangan_option = mysqli_fetch_assoc($lapangan_dropdown_result)) {
    $lapangan_dropdown_options .= "<option value='{$lapangan_option['id_lapangan']}'>Lapangan {$counter} - {$lapangan_option['nama']}</option>";
    $counter++;
}
?>

<!-- Kelola Lapangan -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-8">
    <div class="flex justify-between items-center p-6">
        <h2 class="text-xl font-bold text-gray-800">Kelola Lapangan</h2>
        <button type="button" onclick="showAddModal()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
            + Tambah Lapangan
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
        <?php while($lapangan = mysqli_fetch_assoc($result_lapangan)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="relative h-48">
                    <?php if ($lapangan['gambar']): ?>
                        <img src="../uploads/lapangan/<?php echo $lapangan['gambar']; ?>" 
                             alt="<?php echo $lapangan['nama']; ?>"
                             class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400">No Image</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($lapangan['status'] == 'maintenance'): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                            <span class="text-white font-bold">MAINTENANCE</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <h3 class="text-xl font-semibold mb-2"><?php echo $lapangan['nama']; ?></h3>
                    <p class="text-gray-600 mb-2"><?php echo $lapangan['deskripsi']; ?></p>
                    <p class="text-green-600 font-bold mb-4">
                        Rp <?php echo number_format($lapangan['harga_per_jam'], 0, ',', '.'); ?>/jam
                    </p>
                    <div class="space-y-2">
                        <div class="flex space-x-2">
                            <button type="button" onclick='showEditModal(<?php echo json_encode($lapangan); ?>)' 
                                    class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if ($lapangan['status'] == 'tersedia'): ?>
                                <button type="button" onclick="toggleStatus(<?php echo $lapangan['id_lapangan']; ?>, 'maintenance')"
                                        class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">
                                    <i class="fas fa-tools"></i> Set Maintenance
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="toggleStatus(<?php echo $lapangan['id_lapangan']; ?>, 'tersedia')"
                                        class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                    <i class="fas fa-check"></i> Set Tersedia
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="proses_lapangan.php" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus lapangan ini? Aksi ini tidak dapat dibatalkan dan lapangan yang memiliki riwayat booking tidak dapat dihapus.');" class="w-full">
                            <input type="hidden" name="id_lapangan" value="<?php echo $lapangan['id_lapangan']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" 
                                    class="w-full bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                <i class="fas fa-trash-alt"></i> Hapus Lapangan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Kelola Paket Ekstrakurikuler -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-8">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Paket Ekstrakurikuler</h2>
            <button onclick="showAddPaketModal('eskul')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                + Tambah Paket
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php while($paket = mysqli_fetch_assoc($result_eskul)): ?>
                <div class="border rounded-lg overflow-hidden">
                    <?php if ($paket['gambar']): ?>
                        <img src="../uploads/paket/<?php echo $paket['gambar']; ?>" 
                             alt="Paket <?php echo str_replace('eskul_lap', 'Lapangan ', $paket['tipe']); ?>"
                             class="w-full h-32 object-cover">
                    <?php else: ?>
                        <div class="w-full h-32 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400 text-sm">No Image</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="font-semibold">
                                    <?php 
                                    $lapangan = str_replace('eskul_lap', 'Lapangan ', $paket['tipe']);
                                    echo $lapangan;
                                    ?>
                                </h4>
                                <p class="text-2xl font-bold text-green-600 mt-1">
                                    Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>/bulan
                                </p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:gap-2 mt-2">
                                <button type="button" 
                                        onclick='showEditPaketModal(<?php echo $paket['id_paket']; ?>, "<?php echo $paket['tipe']; ?>", <?php echo $paket['harga']; ?>, <?php echo json_encode($paket['keterangan']); ?>, "<?php echo $paket['gambar']; ?>")'
                                        class="w-full sm:w-auto bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                    Edit
                                </button>
                                <a href="proses_harga_paket.php?action=delete&id=<?php echo $paket['id_paket']; ?>" 
                                   onclick="return confirm('Yakin ingin menghapus paket ini?')"
                                   class="w-full sm:w-auto bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                    Hapus
                                </a>
                            </div>
                        </div>
                        <div class="text-gray-600 whitespace-pre-line">
                            <?php echo $paket['keterangan']; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Kelola Paket Turnamen -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Paket Turnamen</h2>
            <button onclick="showAddPaketModal('turnamen')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                + Tambah Paket
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php while($paket = mysqli_fetch_assoc($result_turnamen)): ?>
                <div class="border rounded-lg overflow-hidden">
                    <?php if ($paket['gambar']): ?>
                        <img src="../uploads/paket/<?php echo $paket['gambar']; ?>" 
                             alt="Paket <?php echo str_replace(['turnamen_lap', '_sore', '_malam'], ['Lapangan ', ' (Sore)', ' (Malam)'], $paket['tipe']); ?>"
                             class="w-full h-32 object-cover">
                    <?php else: ?>
                        <div class="w-full h-32 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400 text-sm">No Image</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="font-semibold">
                                    <?php 
                                    $nama = str_replace(['turnamen_lap', '_sore', '_malam'], ['Lapangan ', ' (Sore)', ' (Malam)'], $paket['tipe']);
                                    echo $nama;
                                    ?>
                                </h4>
                                <p class="text-2xl font-bold text-green-600 mt-1">
                                    Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>/hari
                                </p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:gap-2 mt-2">
                                <button type="button"
                                        onclick='showEditPaketModal(<?php echo $paket['id_paket']; ?>, "<?php echo $paket['tipe']; ?>", <?php echo $paket['harga']; ?>, <?php echo json_encode($paket['keterangan']); ?>, "<?php echo $paket['gambar']; ?>")'
                                        class="w-full sm:w-auto bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                    Edit
                                </button>
                                <a href="proses_harga_paket.php?action=delete&id=<?php echo $paket['id_paket']; ?>" 
                                   onclick="return confirm('Yakin ingin menghapus paket ini?')"
                                   class="w-full sm:w-auto bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                    Hapus
                                </a>
                            </div>
                        </div>
                        <div class="text-gray-600 whitespace-pre-line">
                            <?php echo $paket['keterangan']; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Modal Lapangan -->
<div id="lapanganModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white p-6 rounded-lg max-w-md w-full">
        <h3 id="modalTitle" class="text-lg font-bold mb-4">Tambah Lapangan</h3>
        <form id="lapanganForm" method="POST" action="proses_lapangan.php" enctype="multipart/form-data">
            <input type="hidden" name="id_lapangan" id="lapangan_id">
            <input type="hidden" name="action" id="form_action" value="add">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lapangan</label>
                <input type="text" name="nama" id="nama" required
                       class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="deskripsi" id="deskripsi" rows="3" required
                          class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Harga per Jam</label>
                <input type="number" name="harga_per_jam" id="harga_per_jam" required
                       class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Foto Lapangan</label>
                <input type="file" name="gambar" id="gambar" accept="image/*"
                       class="w-full border border-gray-300 p-2 rounded">
                <p class="text-sm text-gray-500 mt-1">Format: JPG, PNG. Max: 2MB</p>
            </div>

            <div id="current_image" class="mb-4 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Foto Saat Ini</label>
                <img id="preview_image" src="" alt="Current Image" class="w-32 h-32 object-cover rounded">
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" class="cancelLapanganBtn"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50">
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

<!-- Modal Edit/Add Paket -->
<div id="paketModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white p-6 rounded-lg max-w-md w-full">
        <h3 id="paketModalTitle" class="text-lg font-bold mb-4">Edit Harga Paket</h3>
        <form method="POST" action="proses_harga_paket.php" enctype="multipart/form-data">
            <input type="hidden" name="action" id="paket_action" value="edit">
            <input type="hidden" name="id_paket" id="paket_id">
            <input type="hidden" name="paket_type" id="paket_type" value="">
            
            <div id="eskulFields" class="mb-4 hidden">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lapangan</label>
            <select name="lapangan_id_eskul" id="paket_lapangan_eskul" class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
                <?php echo $lapangan_dropdown_options; ?>
            </select>
                </div>
            </div>

            <div id="turnamenFields" class="mb-4 hidden">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lapangan</label>
            <select name="lapangan_id_turnamen" id="paket_lapangan" class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
                <?php echo $lapangan_dropdown_options; ?>
            </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
                    <select name="jenis" id="paket_jenis" class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
                        <option value="sore">Sore (10:00 - 17:00)</option>
                        <option value="malam">Malam (10:00 - 21:00)</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                <input type="number" name="harga" id="paket_harga" required
                       class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                <textarea name="keterangan" id="paket_keterangan" rows="4" required
                          class="w-full rounded border-gray-300 focus:border-green-500 focus:ring focus:ring-green-200"></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Paket</label>
                <input type="file" name="gambar" id="paket_gambar" accept="image/*"
                       class="w-full border border-gray-300 p-2 rounded">
                <p class="text-sm text-gray-500 mt-1">Format: JPG, PNG. Max: 2MB</p>
            </div>

            <div id="current_paket_image" class="mb-4 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Saat Ini</label>
                <img id="preview_paket_image" src="" alt="Current Image" class="w-32 h-32 object-cover rounded">
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" class="cancelPaketBtn"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50">
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
