<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil semua data lapangan
$query = "SELECT * FROM lapangan WHERE status = 'tersedia'";
$lapangan_result = mysqli_query($conn, $query);

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Jadwal Lapangan</h2>

    <form method="POST" action="" class="space-y-4">
        <?php echo csrf_field(); ?>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="lapangan_id">
                    Pilih Lapangan
                </label>
                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="lapangan_id" 
                        name="lapangan_id" 
                        required>
                    <option value="">Pilih Lapangan</option>
                    <?php while($lapangan = mysqli_fetch_assoc($lapangan_result)): ?>
                        <option value="<?php echo $lapangan['id']; ?>" <?php echo (isset($_POST['lapangan_id']) && $_POST['lapangan_id'] == $lapangan['id']) ? 'selected' : ''; ?>>
                            <?php echo $lapangan['nama']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="tanggal">
                    Tanggal
                </label>
                <input class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="tanggal" 
                       type="date" 
                       name="tanggal" 
                       value="<?php echo isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d'); ?>"
                       min="<?php echo date('Y-m-d'); ?>"
                       required>
            </div>
        </div>

        <button type="submit" 
                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
            Lihat Jadwal
        </button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <?php
        // Ambil nama lapangan
        $lapangan_id = (int)$_POST['lapangan_id'];
        $query = "SELECT nama FROM lapangan WHERE id_lapangan = '$lapangan_id' OR id = '$lapangan_id'";
        $result = mysqli_query($conn, $query);
        $lapangan = $result ? mysqli_fetch_assoc($result) : null;
        $nama_lap = $lapangan ? $lapangan['nama'] : 'Lapangan';
        $tgl_display = !empty($_POST['tanggal']) ? date('d/m/Y', strtotime($_POST['tanggal'])) : '-';
        ?>
        <div class="mt-8">
            <div class="mb-4">
                <h3 class="text-lg font-semibold">Jadwal Booking:</h3>
                <p class="text-gray-600"><?php echo htmlspecialchars($nama_lap, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($tgl_display, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="space-y-4">
                <?php
                $jadwal = getJadwalLapangan($conn, $_POST['lapangan_id'], $_POST['tanggal']);
                if (empty($jadwal)) {
                    echo '<p class="text-green-500">Belum ada booking untuk jadwal ini</p>';
                } else {
                    foreach ($jadwal as $booking) {
                        $statusClass = '';
                        switch(strtolower($booking['status_pembayaran'])) {
                            case 'pending':
                            case 'menunggu_pembayaran':
                            case 'belum_dibayar':
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'dikonfirmasi':
                                $statusClass = 'bg-green-100 text-green-800';
                                break;
                            case 'dibatalkan':
                                $statusClass = 'bg-red-100 text-red-800';
                                break;
                            case 'dp_dibayar':
                                $statusClass = 'bg-blue-100 text-blue-800';
                                break;
                            default:
                                $statusClass = 'bg-gray-100 text-gray-800';
                        }
                        ?>
                        <div class="border rounded p-4 bg-white">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold"><?php echo htmlspecialchars($booking['nama_user']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo formatJam($booking['jam_mulai']) . ' - ' . formatJam($booking['jam_selesai']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <?php 
                                        switch($booking['jenis_booking']) {
                                            case 'Ekstrakurikuler':
                                                echo "Ekstrakurikuler (" . $booking['durasi_bulan'] . " bulan)";
                                                break;
                                            case 'Turnamen':
                                                echo "Turnamen (" . $booking['durasi_hari'] . " hari)";
                                                break;
                                            default:
                                                echo "Regular";
                                        }
                                        ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($booking['status_pembayaran']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-6 text-center">
        <a href="booking_regular.php?lapangan=<?php echo isset($_POST['lapangan_id']) ? htmlspecialchars($_POST['lapangan_id']) : ''; ?>" class="text-blue-500 hover:text-blue-600">
            Kembali ke Halaman Booking
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form saat lapangan atau tanggal berubah
    document.getElementById('lapangan_id').addEventListener('change', function() {
        if (document.getElementById('tanggal').value) {
            this.form.submit();
        }
    });

    document.getElementById('tanggal').addEventListener('change', function() {
        if (document.getElementById('lapangan_id').value) {
            this.form.submit();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
