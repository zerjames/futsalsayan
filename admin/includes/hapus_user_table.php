<?php
// File: includes/hapus_user_table.php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Ambil semua user dengan role 'user'
$query = "SELECT id, username, email FROM users WHERE role = 'user' ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>
<div class="bg-white rounded shadow p-4 mt-6">
    <h2 class="text-lg font-bold mb-4">Daftar User</h2>
    <table class="min-w-full divide-y divide-gray-200">
        <thead>
            <tr>
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">Username</th>
                <th class="px-4 py-2">Email</th>
                <th class="px-4 py-2">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td class="border px-4 py-2"><?php echo $row['id']; ?></td>
                <td class="border px-4 py-2"><?php echo htmlspecialchars($row['username']); ?></td>
                <td class="border px-4 py-2"><?php echo htmlspecialchars($row['email']); ?></td>
                <td class="border px-4 py-2">
                    <form method="POST" action="proses_hapus_user.php" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white px-3 py-1 rounded">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
