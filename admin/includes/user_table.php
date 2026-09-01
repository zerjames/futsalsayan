<?php
// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil daftar user dengan role 'user'
$query_users = "SELECT id_user, nama, username, email, telepon, is_verified, foto_profil FROM users WHERE role = 'user'";
$users_result = mysqli_query($conn, $query_users);

// Tampilkan pesan sukses atau error jika ada
if (isset($_SESSION['success'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>

<h2 class="text-2xl font-bold mb-6">Kelola User</h2>

<div class="overflow-x-auto">
<table class="min-w-full bg-white border border-gray-300 rounded-lg shadow-md">
    <thead class="bg-gray-100">
        <tr>
            <th class="py-3 px-6 border-b text-center text-gray-700 font-semibold">Foto</th>
            <th class="py-3 px-6 border-b text-left text-gray-700 font-semibold">ID</th>
            <th class="py-3 px-6 border-b text-left text-gray-700 font-semibold">Nama</th>
            <th class="py-3 px-6 border-b text-left text-gray-700 font-semibold">Username</th>
            <th class="py-3 px-6 border-b text-left text-gray-700 font-semibold">Email</th>
            <th class="py-3 px-6 border-b text-left text-gray-700 font-semibold">Telepon</th>
            <th class="py-3 px-6 border-b text-center text-gray-700 font-semibold">Status Akun</th>
            <th class="py-3 px-6 border-b text-center text-gray-700 font-semibold">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
        <tr class="hover:bg-gray-50">
            <td class="py-3 px-6 border-b text-center">
              <?php 
                $foto = !empty($user['foto_profil']) ? 'futsalsayan/uploads/profile/' . $user['foto_profil'] : 'assets/images/default_profile.png';
                $url = '/' . $foto;
              ?>
              <img src="<?php echo $url; ?>" alt="Foto Profil" class="w-10 h-10 rounded-full object-cover border mx-auto">
              <div style="font-size:10px;color:#888;word-break:break-all;max-width:120px;margin:auto;">
                <a href="#" onclick="showProfileModal('<?php echo $url; ?>');return false;" style="color:#007bff;word-break:break-all;">[Lihat]</a>
              </div>
            </td>
            <td class="py-3 px-6 border-b"><?php echo htmlspecialchars($user['id_user']); ?></td>
            <td class="py-3 px-6 border-b"><?php echo htmlspecialchars($user['nama']); ?></td>
            <td class="py-3 px-6 border-b"><?php echo htmlspecialchars($user['username']); ?></td>
            <td class="py-3 px-6 border-b"><?php echo htmlspecialchars($user['email']); ?></td>
            <td class="py-3 px-6 border-b"><?php echo htmlspecialchars($user['telepon']); ?></td>
            <td class="py-3 px-6 border-b text-center">
              <?php if ($user['is_verified'] == 1): ?>
                <span class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">Terverifikasi</span>
              <?php else: ?>
                <span class="inline-block bg-red-100 text-red-800 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">Belum Verifikasi</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-6 border-b text-center">
                <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id_user']); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200 mr-2">Edit</a>
                <form method="POST" action="hapus_user.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?');" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id_user']); ?>">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                        Hapus
                    </button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>
<!-- Modal untuk preview foto profil -->
<div id="profileModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
  <div style="background:#fff;padding:16px 24px;border-radius:12px;max-width:90vw;max-height:90vh;display:flex;flex-direction:column;align-items:center;">
    <img id="profileModalImg" src="" alt="Preview Foto" style="max-width:60vw;max-height:60vh;border-radius:12px;box-shadow:0 2px 16px #0002;">
    <button onclick="closeProfileModal()" style="margin-top:16px;padding:8px 24px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-weight:bold;cursor:pointer;">Tutup</button>
  </div>
</div>
<script>
function showProfileModal(url) {
  document.getElementById('profileModalImg').src = url;
  document.getElementById('profileModal').style.display = 'flex';
}
function closeProfileModal() {
  document.getElementById('profileModal').style.display = 'none';
  document.getElementById('profileModalImg').src = '';
}
</script>
