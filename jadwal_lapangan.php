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

// Ambil data lapangan
$query = "SELECT * FROM lapangan WHERE status = 'tersedia'";
$lapangan_result = mysqli_query($conn, $query);

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h2 class="text-2xl font-bold mb-6">Jadwal Lapangan</h2>

    <!-- Filter Lapangan dan Tanggal -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Lapangan</label>
                <select id="lapangan_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    <option value="">Pilih Lapangan</option>
                    <?php while($lapangan = mysqli_fetch_assoc($lapangan_result)): ?>
                        <option value="<?php echo $lapangan['id']; ?>">
                            <?php echo $lapangan['nama']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Tanggal</label>
                <input type="date" id="tanggal" 
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                       value="<?php echo date('Y-m-d'); ?>"
                       min="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>
    </div>

    <!-- Tampilan Jadwal -->
    <div id="jadwal_container" class="bg-white rounded-lg shadow-md p-6">
        <div class="grid grid-cols-1 gap-4" id="jadwal_list">
            <!-- Jadwal akan ditampilkan di sini -->
        </div>
    </div>
</div>

<script>
function loadJadwal() {
    const lapanganId = document.getElementById('lapangan_id').value;
    const tanggal = document.getElementById('tanggal').value;
    const jadwalList = document.getElementById('jadwal_list');

    if (!lapanganId) {
        jadwalList.innerHTML = '<p class="text-gray-500 text-center">Pilih lapangan untuk melihat jadwal</p>';
        return;
    }

    fetch(`check_jadwal.php?lapangan_id=${lapanganId}&tanggal=${tanggal}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                jadwalList.innerHTML = `<p class="text-red-500 text-center">${data.error}</p>`;
                return;
            }

            if (data.jadwal.length === 0) {
                jadwalList.innerHTML = '<p class="text-gray-500 text-center">Tidak ada booking pada tanggal ini</p>';
                return;
            }

            const jadwalHTML = data.jadwal.map(booking => `
                <div class="border rounded-lg p-4 hover:bg-gray-50 cursor-pointer"
                     onclick="showBookingDetail('${booking.id}', '${booking.jenis_booking}')">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold">${booking.nama_user}</p>
                            <p class="text-sm text-gray-600">${booking.nama_lapangan}</p>
                            <p class="text-sm text-gray-600">${booking.jam_mulai} - ${booking.jam_selesai}</p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full ${getStatusClass(booking.status_pembayaran)}">
                            ${booking.status_pembayaran}
                        </span>
                    </div>
                </div>
            `).join('');

            jadwalList.innerHTML = jadwalHTML;
        })
        .catch(error => {
            console.error('Error:', error);
            jadwalList.innerHTML = '<p class="text-red-500 text-center">Terjadi kesalahan saat memuat jadwal</p>';
        });
}

function showBookingDetail(id, jenis) {
    const url = `lihat_detail_booking.php?id=${id}&jenis=${encodeURIComponent(jenis)}`;
    const width = 600;
    const height = 400;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    window.open(url, 'BookingDetail', 
        `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no`
    );
}

function getStatusClass(status) {
    switch(status.toLowerCase()) {
        case 'pending':
        case 'menunggu_pembayaran':
        case 'belum_dibayar':
            return 'bg-yellow-100 text-yellow-800';
        case 'dikonfirmasi':
            return 'bg-green-100 text-green-800';
        case 'dibatalkan':
            return 'bg-red-100 text-red-800';
        case 'dp_dibayar':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Event listeners
document.getElementById('lapangan_id').addEventListener('change', loadJadwal);
document.getElementById('tanggal').addEventListener('change', loadJadwal);

// Load jadwal saat halaman dimuat
document.addEventListener('DOMContentLoaded', loadJadwal);
</script>

<?php include 'includes/footer.php'; ?>
