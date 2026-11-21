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
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden mb-8">
    <div class="flex flex-wrap justify-between items-center gap-3 px-5 py-4 border-b border-stone-200">
        <h2 class="text-lg font-bold text-stone-900">Kelola Lapangan</h2>
        <button type="button" onclick="showAddModal()" class="bg-stone-900 hover:bg-green-800 text-white text-xs sm:text-sm font-medium px-4 py-2 rounded-md transition-colors">
            + Tambah Lapangan
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 p-5">
        <?php while($lapangan = mysqli_fetch_assoc($result_lapangan)): ?>
            <div class="bg-white rounded-lg border border-stone-200 overflow-hidden flex flex-col">
                <div class="relative h-44 bg-stone-100">
                    <?php if ($lapangan['gambar']): ?>
                        <img src="../uploads/lapangan/<?php echo htmlspecialchars($lapangan['gambar'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="<?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-stone-400 text-xs">
                            Foto belum tersedia
                        </div>
                    <?php endif; ?>
                    <?php if ($lapangan['status'] == 'maintenance'): ?>
                        <div class="absolute inset-0 bg-stone-900/60 flex items-center justify-center">
                            <span class="px-2.5 py-1 rounded bg-white text-stone-900 text-xs font-bold tracking-wider">MAINTENANCE</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="text-base font-bold text-stone-900"><?php echo htmlspecialchars($lapangan['nama'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-lg font-bold text-green-800 mt-1 tabular-nums whitespace-nowrap">
                        Rp <?php echo number_format($lapangan['harga_per_jam'], 0, ',', '.'); ?><span class="text-xs font-normal text-stone-400">/jam</span>
                    </p>
                    <p class="text-xs sm:text-sm text-stone-500 mt-2 mb-4 flex-1 leading-relaxed"><?php echo htmlspecialchars($lapangan['deskripsi'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="pt-3 border-t border-stone-100 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick='showEditModal(<?php echo json_encode($lapangan); ?>)' 
                                    class="px-3 py-2 text-xs font-medium rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 transition-colors">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <?php if ($lapangan['status'] == 'tersedia'): ?>
                                <button type="button" onclick="toggleStatus(<?php echo $lapangan['id_lapangan']; ?>, 'maintenance')"
                                        class="px-3 py-2 text-xs font-medium rounded-md border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 transition-colors">
                                    Maintenance
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="toggleStatus(<?php echo $lapangan['id_lapangan']; ?>, 'tersedia')"
                                        class="px-3 py-2 text-xs font-medium rounded-md border border-green-300 bg-green-50 hover:bg-green-100 text-green-800 transition-colors">
                                    Set Tersedia
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="proses_lapangan.php" onsubmit="return openAdminConfirmForm(event, this, 'Hapus lapangan ini?', 'Aksi ini tidak dapat dibatalkan dan lapangan yang memiliki riwayat booking tidak dapat dihapus.', 'Ya, Hapus')" class="w-full">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id_lapangan" value="<?php echo $lapangan['id_lapangan']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" 
                                    class="w-full px-3 py-2 text-xs font-medium rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors">
                                <i class="fas fa-trash-alt mr-1"></i>Hapus Lapangan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Kelola Paket Ekstrakurikuler -->
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden mb-8">
    <div class="flex flex-wrap justify-between items-center gap-3 px-5 py-4 border-b border-stone-200">
        <h2 class="text-lg font-bold text-stone-900">Paket Ekstrakurikuler</h2>
        <button type="button" onclick="showAddPaketModal('eskul')" class="bg-stone-900 hover:bg-green-800 text-white text-xs sm:text-sm font-medium px-4 py-2 rounded-md transition-colors">
            + Tambah Paket
        </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 p-5">
        <?php while($paket = mysqli_fetch_assoc($result_eskul)): ?>
            <div class="bg-white border border-stone-200 rounded-lg overflow-hidden flex flex-col">
                <?php if ($paket['gambar']): ?>
                    <img src="../uploads/paket/<?php echo htmlspecialchars($paket['gambar'], ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="Paket <?php echo htmlspecialchars(str_replace('eskul_lap', 'Lapangan ', $paket['tipe']), ENT_QUOTES, 'UTF-8'); ?>"
                         class="w-full h-36 object-cover">
                <?php else: ?>
                    <div class="w-full h-36 bg-stone-100 flex items-center justify-center text-stone-400 text-xs">
                        Foto belum tersedia
                    </div>
                <?php endif; ?>
                <div class="p-4 flex-1 flex flex-col">
                    <h4 class="text-base font-bold text-stone-900">
                        <?php echo htmlspecialchars(str_replace('eskul_lap', 'Lapangan ', $paket['tipe']), ENT_QUOTES, 'UTF-8'); ?>
                    </h4>
                    <p class="text-lg font-bold text-green-800 mt-1 tabular-nums whitespace-nowrap">
                        Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?><span class="text-xs font-normal text-stone-400">/bulan</span>
                    </p>
                    <div class="text-xs sm:text-sm text-stone-500 whitespace-pre-line mt-3 mb-4 flex-1 leading-relaxed">
                        <?php echo htmlspecialchars($paket['keterangan'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="pt-3 border-t border-stone-100 grid grid-cols-2 gap-2">
                        <button type="button" 
                                onclick='showEditPaketModal(<?php echo $paket['id_paket']; ?>, "<?php echo $paket['tipe']; ?>", <?php echo $paket['harga']; ?>, <?php echo json_encode($paket['keterangan']); ?>, "<?php echo $paket['gambar']; ?>")'
                                class="px-3 py-2 text-xs font-medium rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 transition-colors text-center">
                            Edit
                        </button>
                        <a href="proses_harga_paket.php?action=delete&id=<?php echo $paket['id_paket']; ?>" 
                           onclick="return openAdminConfirmLink(event, this.href, 'Hapus paket ini?', 'Data harga paket akan dihapus permanen.', 'Ya, Hapus')"
                           class="px-3 py-2 text-xs font-medium rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors text-center">
                            Hapus
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Kelola Paket Turnamen -->
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
    <div class="flex flex-wrap justify-between items-center gap-3 px-5 py-4 border-b border-stone-200">
        <h2 class="text-lg font-bold text-stone-900">Paket Turnamen</h2>
        <button type="button" onclick="showAddPaketModal('turnamen')" class="bg-stone-900 hover:bg-green-800 text-white text-xs sm:text-sm font-medium px-4 py-2 rounded-md transition-colors">
            + Tambah Paket
        </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 p-5">
        <?php while($paket = mysqli_fetch_assoc($result_turnamen)): ?>
            <div class="bg-white border border-stone-200 rounded-lg overflow-hidden flex flex-col">
                <?php if ($paket['gambar']): ?>
                    <img src="../uploads/paket/<?php echo htmlspecialchars($paket['gambar'], ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="Paket <?php echo htmlspecialchars(str_replace(['turnamen_lap', '_sore', '_malam'], ['Lapangan ', ' (Sore)', ' (Malam)'], $paket['tipe']), ENT_QUOTES, 'UTF-8'); ?>"
                         class="w-full h-36 object-cover">
                <?php else: ?>
                    <div class="w-full h-36 bg-stone-100 flex items-center justify-center text-stone-400 text-xs">
                        Foto belum tersedia
                    </div>
                <?php endif; ?>
                <div class="p-4 flex-1 flex flex-col">
                    <h4 class="text-base font-bold text-stone-900">
                        <?php echo htmlspecialchars(str_replace(['turnamen_lap', '_sore', '_malam'], ['Lapangan ', ' (Sore)', ' (Malam)'], $paket['tipe']), ENT_QUOTES, 'UTF-8'); ?>
                    </h4>
                    <p class="text-lg font-bold text-green-800 mt-1 tabular-nums whitespace-nowrap">
                        Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?><span class="text-xs font-normal text-stone-400">/hari</span>
                    </p>
                    <div class="text-xs sm:text-sm text-stone-500 whitespace-pre-line mt-3 mb-4 flex-1 leading-relaxed">
                        <?php echo htmlspecialchars($paket['keterangan'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="pt-3 border-t border-stone-100 grid grid-cols-2 gap-2">
                        <button type="button"
                                onclick='showEditPaketModal(<?php echo $paket['id_paket']; ?>, "<?php echo $paket['tipe']; ?>", <?php echo $paket['harga']; ?>, <?php echo json_encode($paket['keterangan']); ?>, "<?php echo $paket['gambar']; ?>")'
                                class="px-3 py-2 text-xs font-medium rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 transition-colors text-center">
                            Edit
                        </button>
                        <a href="proses_harga_paket.php?action=delete&id=<?php echo $paket['id_paket']; ?>" 
                           onclick="return openAdminConfirmLink(event, this.href, 'Hapus paket ini?', 'Data harga paket akan dihapus permanen.', 'Ya, Hapus')"
                           class="px-3 py-2 text-xs font-medium rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors text-center">
                            Hapus
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Modal Lapangan -->
<div id="lapanganModal" class="fixed inset-0 bg-stone-900/50 hidden z-50 p-4 overflow-y-auto">
    <div class="min-h-full flex items-center justify-center">
        <div class="bg-white border border-stone-200 p-6 rounded-lg max-w-md w-full shadow-lg">
            <h3 id="modalTitle" class="text-lg font-bold text-stone-900 mb-4">Tambah Lapangan</h3>
            <form id="lapanganForm" method="POST" action="proses_lapangan.php" enctype="multipart/form-data" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id_lapangan" id="lapangan_id">
                <input type="hidden" name="action" id="form_action" value="add">
                
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Nama Lapangan</label>
                    <input type="text" name="nama" id="nama" required
                           class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 focus:outline-none focus:border-stone-900">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Deskripsi</label>
                    <textarea name="deskripsi" id="deskripsi" rows="3" required
                              class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 focus:outline-none focus:border-stone-900"></textarea>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Harga per Jam</label>
                    <input type="number" name="harga_per_jam" id="harga_per_jam" required
                           class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 focus:outline-none focus:border-stone-900">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Foto Lapangan</label>
                    <input type="file" name="gambar" id="gambar" accept="image/*"
                           class="w-full border border-stone-300 p-2 rounded-md text-xs text-stone-600">
                    <p class="text-xs text-stone-400 mt-1">Format: JPG, PNG. Maksimal 2MB.</p>
                </div>

                <div id="current_image" class="hidden">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Foto Saat Ini</label>
                    <img id="preview_image" src="" alt="Current Image" class="w-28 h-28 object-cover rounded-md border border-stone-200">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="cancelLapanganBtn px-4 py-2 text-sm text-stone-700 border border-stone-300 rounded-md hover:border-stone-900">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-stone-900 hover:bg-green-800 text-white text-sm font-medium rounded-md transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit/Add Paket -->
<div id="paketModal" class="fixed inset-0 bg-stone-900/50 hidden z-50 p-4 overflow-y-auto">
    <div class="min-h-full flex items-center justify-center">
        <div class="bg-white border border-stone-200 p-6 rounded-lg max-w-md w-full shadow-lg">
            <h3 id="paketModalTitle" class="text-lg font-bold text-stone-900 mb-4">Edit Harga Paket</h3>
            <form method="POST" action="proses_harga_paket.php" enctype="multipart/form-data" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="paket_action" value="edit">
                <input type="hidden" name="id_paket" id="paket_id">
                <input type="hidden" name="paket_type" id="paket_type" value="">
                
                <div id="eskulFields" class="hidden">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Lapangan</label>
                    <select name="lapangan_id_eskul" id="paket_lapangan_eskul" class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 bg-white focus:outline-none focus:border-stone-900">
                        <?php echo $lapangan_dropdown_options; ?>
                    </select>
                </div>

                <div id="turnamenFields" class="hidden space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Lapangan</label>
                        <select name="lapangan_id_turnamen" id="paket_lapangan" class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 bg-white focus:outline-none focus:border-stone-900">
                            <?php echo $lapangan_dropdown_options; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Jenis</label>
                        <select name="jenis" id="paket_jenis" class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 bg-white focus:outline-none focus:border-stone-900">
                            <option value="sore">Sore (10:00 - 17:00)</option>
                            <option value="malam">Malam (10:00 - 21:00)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Harga</label>
                    <input type="number" name="harga" id="paket_harga" required
                           class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 focus:outline-none focus:border-stone-900">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Keterangan</label>
                    <textarea name="keterangan" id="paket_keterangan" rows="4" required
                              class="w-full px-3 py-2 rounded-md border border-stone-300 text-sm text-stone-900 focus:outline-none focus:border-stone-900"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Gambar Paket</label>
                    <input type="file" name="gambar" id="paket_gambar" accept="image/*"
                           class="w-full border border-stone-300 p-2 rounded-md text-xs text-stone-600">
                    <p class="text-xs text-stone-400 mt-1">Format: JPG, PNG. Maksimal 2MB.</p>
                </div>

                <div id="current_paket_image" class="hidden">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Gambar Saat Ini</label>
                    <img id="preview_paket_image" src="" alt="Current Image" class="w-28 h-28 object-cover rounded-md border border-stone-200">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="cancelPaketBtn px-4 py-2 text-sm text-stone-700 border border-stone-300 rounded-md hover:border-stone-900">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-stone-900 hover:bg-green-800 text-white text-sm font-medium rounded-md transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
