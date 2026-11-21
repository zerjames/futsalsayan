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
    echo '<div class="bg-green-50 border-l-4 border-green-800 text-green-900 px-4 py-3 text-sm mb-4">' . htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-4 py-3 text-sm mb-4">' . htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['error']);
}
?>

<div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-stone-200">
        <h2 class="text-lg font-bold text-stone-900">Kelola User</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="py-3 px-4 text-center text-xs font-semibold uppercase tracking-wider text-stone-500">Foto</th>
                    <th class="py-3 px-4 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">ID</th>
                    <th class="py-3 px-4 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Nama</th>
                    <th class="py-3 px-4 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Username</th>
                    <th class="py-3 px-4 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Email</th>
                    <th class="py-3 px-4 text-left text-xs font-semibold uppercase tracking-wider text-stone-500">Telepon</th>
                    <th class="py-3 px-4 text-center text-xs font-semibold uppercase tracking-wider text-stone-500">Status</th>
                    <th class="py-3 px-4 text-center text-xs font-semibold uppercase tracking-wider text-stone-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                <tr class="hover:bg-stone-50/70 transition-colors">
                    <td class="py-3 px-4 text-center whitespace-nowrap">
                        <?php 
                            $foto = !empty($user['foto_profil']) ? '/futsalsayan/uploads/profile/' . $user['foto_profil'] : '/futsalsayan/assets/images/default_profile.png';
                            $safeFoto = htmlspecialchars($foto, ENT_QUOTES, 'UTF-8');
                        ?>
                        <img src="<?php echo $safeFoto; ?>" alt="Foto Profil" class="w-9 h-9 rounded-full object-cover border border-stone-200 mx-auto cursor-pointer" onclick="showProfileModal('<?php echo $safeFoto; ?>')">
                        <button type="button" onclick="showProfileModal('<?php echo $safeFoto; ?>')" class="text-[11px] text-green-800 hover:underline mt-0.5">
                            Lihat
                        </button>
                    </td>
                    <td class="py-3 px-4 text-stone-500 tabular-nums whitespace-nowrap"><?php echo htmlspecialchars($user['id_user'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-3 px-4 font-medium text-stone-900 whitespace-nowrap"><?php echo htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-3 px-4 text-stone-600 whitespace-nowrap"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-3 px-4 text-stone-600"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-3 px-4 text-stone-600 tabular-nums whitespace-nowrap"><?php echo htmlspecialchars($user['telepon'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-3 px-4 text-center whitespace-nowrap">
                        <?php if ($user['is_verified'] == 1): ?>
                            <span class="inline-block bg-green-50 text-green-800 text-xs font-medium px-2.5 py-1 rounded border border-green-200">Terverifikasi</span>
                        <?php else: ?>
                            <span class="inline-block bg-red-50 text-red-700 text-xs font-medium px-2.5 py-1 rounded border border-red-200">Belum Verifikasi</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-center whitespace-nowrap">
                        <div class="inline-flex items-center justify-center gap-2">
                            <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id_user'], ENT_QUOTES, 'UTF-8'); ?>"
                               class="px-3 py-1.5 text-xs font-medium rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 transition-colors">
                                Edit
                            </a>
                            <form method="POST" action="hapus_user.php" onsubmit="return openAdminConfirmForm(event, this, 'Hapus user ini?', 'Akun user yang dihapus tidak dapat dikembalikan.', 'Ya, Hapus')" class="inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id_user'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-medium rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal untuk preview foto profil -->
<div id="profileModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(28,25,23,0.6);align-items:center;justify-content:center;">
    <div class="bg-white border border-stone-200 p-5 rounded-lg max-w-[90vw] max-h-[90vh] flex flex-col items-center shadow-lg">
        <img id="profileModalImg" src="" alt="Preview Foto" class="max-w-[75vw] sm:max-w-md max-h-[65vh] rounded-md border border-stone-200 object-contain">
        <button type="button" onclick="closeProfileModal()" class="mt-4 px-5 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-md transition-colors">
            Tutup
        </button>
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
