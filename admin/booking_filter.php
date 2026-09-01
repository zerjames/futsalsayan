<?php
// Komponen filter yang reusable untuk semua tab booking
// Parameter: $tab_name (nama tab), $month, $year

// Fungsi untuk membuat WHERE clause berdasarkan filter
function buildFilterWhere($table_alias = 'b') {
    global $conn, $month, $year;
    
    $where = [];
    
    // Filter berdasarkan bulan dan tahun (sesuaikan dengan tabel)
    if ($table_alias == 'b') {
        // Untuk booking regular
        $where[] = "MONTH($table_alias.tanggal_main) = $month";
        $where[] = "YEAR($table_alias.tanggal_main) = $year";
    } elseif ($table_alias == 'be') {
        // Untuk booking eskul
        $where[] = "MONTH($table_alias.tanggal_mulai) = $month";
        $where[] = "YEAR($table_alias.tanggal_mulai) = $year";
    } elseif ($table_alias == 'bt') {
        // Untuk booking turnamen
        $where[] = "MONTH($table_alias.tanggal_mulai) = $month";
        $where[] = "YEAR($table_alias.tanggal_mulai) = $year";
    }
    
    // Filter lapangan
    if (!empty($_GET['lapangan_id'])) {
        $lapangan_id = (int)$_GET['lapangan_id'];
        $where[] = "$table_alias.lapangan_id = $lapangan_id";
    }
    
    // Filter status
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $status = mysqli_real_escape_string($conn, $_GET['status']);
        $where[] = "$table_alias.status_pembayaran = '$status'";
    }
    
    return implode(' AND ', $where);
}

// Ambil daftar lapangan untuk filter
$lapangan_options = '';
$lapangan_result = mysqli_query($conn, "SELECT id_lapangan, nama FROM lapangan ORDER BY id_lapangan ASC");
while ($lap = mysqli_fetch_assoc($lapangan_result)) {
    $selected = (isset($_GET['lapangan_id']) && $_GET['lapangan_id'] == $lap['id_lapangan']) ? 'selected' : '';
    $lapangan_options .= "<option value='{$lap['id_lapangan']}' $selected>{$lap['nama']}</option>";
}

$status_options = [
    '' => 'Semua',
    'pending' => 'Menunggu Pembayaran',
    'dikonfirmasi' => 'Dikonfirmasi',
    'dibatalkan' => 'Dibatalkan'
];
$status_select = '';
foreach ($status_options as $val => $label) {
    $selected = (isset($_GET['status']) && $_GET['status'] === $val) ? 'selected' : '';
    $status_select .= "<option value='$val' $selected>$label</option>";
}
?>

<form method="get" class="mb-4 flex flex-wrap gap-2">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_name); ?>">
    <input type="hidden" name="bulan" value="<?php echo htmlspecialchars($month); ?>">
    <input type="hidden" name="tahun" value="<?php echo htmlspecialchars($year); ?>">
    <label>Lapangan:
        <select name="lapangan_id" class="border rounded px-2 py-1" onchange="this.form.submit()">
            <option value="">Semua</option>
            <?php echo $lapangan_options; ?>
        </select>
    </label>
    <label>Status:
        <select name="status" class="border rounded px-2 py-1" onchange="this.form.submit()">
            <?php echo $status_select; ?>
        </select>
    </label>
</form> 