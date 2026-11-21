<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}

// Ambil ID user
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php?tab=kelola_user'); exit();
}
$user_id = (int)$_GET['id'];

// Ambil data user
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id_user=$user_id AND role='user'"));
if (!$user) {
    $_SESSION['error'] = 'User tidak ditemukan.';
    header('Location: dashboard.php?tab=kelola_user'); exit();
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_request()) {
        $_SESSION['error'] = 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.';
        header('Location: edit_user.php?id=' . $user_id);
        exit();
    }
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    $password_baru = isset($_POST['password_baru']) ? trim($_POST['password_baru']) : '';
    $foto_profil = null;

    // Validasi unik username/email
    $q = "SELECT id_user FROM users WHERE (username = ? OR email = ?) AND id_user != ?";
    $s = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($s, 'ssi', $username, $email, $user_id);
    mysqli_stmt_execute($s);
    $r = mysqli_stmt_get_result($s);
    if (mysqli_num_rows($r) > 0) {
        $_SESSION['error'] = 'Username atau email sudah digunakan user lain.';
        header('Location: edit_user.php?id=' . $user_id); exit();
    }

    // Upload foto profil jika ada
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = 'Format foto profil harus JPG, JPEG, PNG, atau WEBP.';
            header('Location: edit_user.php?id=' . $user_id); exit();
        }
        $newname = 'user_' . $user_id . '_' . time() . '.' . $ext;
        $upload_dir = '../uploads/profile/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $dest = $upload_dir . $newname;
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dest)) {
            // Hapus foto lama jika ada
            $old = $user['foto_profil'];
            if (!empty($old) && file_exists($upload_dir.$old)) {
                unlink($upload_dir.$old);
            }
            $foto_profil = $newname;
        } else {
            $_SESSION['error'] = 'Gagal upload foto profil.';
            header('Location: edit_user.php?id=' . $user_id); exit();
        }
    }

    // Update data
    $q = "UPDATE users SET nama=?, username=?, email=?, telepon=?, alamat=?";
    $params = [$nama, $username, $email, $telepon, $alamat];
    $types = 'sssss';
    if ($foto_profil) {
        $q .= ", foto_profil=?";
        $params[] = $foto_profil;
        $types .= 's';
    }
    if (!empty($password_baru)) {
        $q .= ", password=?";
        $params[] = password_hash($password_baru, PASSWORD_DEFAULT);
        $types .= 's';
    }
    $q .= " WHERE id_user=?";
    $params[] = $user_id;
    $types .= 'i';
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Profil user berhasil diperbarui.' . (!empty($password_baru) ? ' Password juga diubah.' : '');
    } else {
        $_SESSION['error'] = 'Gagal update profil user.';
    }
    header('Location: dashboard.php?tab=kelola_user'); exit();
}

$foto = !empty($user['foto_profil']) ? '../uploads/profile/' . $user['foto_profil'] : '../assets/images/default_profile.png';

// Include header setelah semua logic PHP dan redirect selesai
include '../includes/header.php';
?>
<div class="max-w-2xl mx-auto">
    <a href="dashboard.php?tab=kelola_user" class="inline-flex items-center gap-2 mb-5 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors">
        &larr; Kembali ke Kelola User
    </a>

    <div class="bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-5 border-b border-stone-200 mb-6">
            <div>
                <h2 class="text-xl font-bold text-stone-900">Edit Profil User</h2>
                <p class="text-sm text-stone-500 mt-0.5">Perbarui data akun pengguna</p>
            </div>
            <?php if ($user['is_verified'] == 1): ?>
                <span class="inline-block bg-green-50 text-green-800 text-xs font-medium px-2.5 py-1 rounded border border-green-200">Terverifikasi</span>
            <?php else: ?>
                <span class="inline-block bg-red-50 text-red-700 text-xs font-medium px-2.5 py-1 rounded border border-red-200">Belum Verifikasi</span>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-5 px-4 py-3 text-sm border-l-4 bg-red-50 text-red-900 border-red-700">
                <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?php echo csrf_field(); ?>
            <div class="flex flex-col sm:flex-row items-center gap-4 pb-5 border-b border-stone-200">
                <img src="<?php echo htmlspecialchars($foto, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto Profil" class="w-16 h-16 rounded-full object-cover border border-stone-200" id="previewFoto">
                <div class="text-center sm:text-left w-full sm:w-auto">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1">Foto Profil</label>
                    <input type="file" name="foto_profil" accept="image/*" class="text-xs text-stone-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-stone-300 file:text-xs file:font-medium file:bg-white file:text-stone-700 hover:file:bg-stone-50 cursor-pointer max-w-full" onchange="previewImage(event)">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Nama</label>
                    <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2.5 sm:py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2.5 sm:py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2.5 sm:py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Nomor Telepon</label>
                    <input type="text" name="telepon" value="<?php echo htmlspecialchars($user['telepon'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2.5 sm:py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Alamat</label>
                <textarea name="alamat" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" rows="2" required><?php echo htmlspecialchars($user['alamat'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">
                    Password Baru <span class="text-stone-400 font-normal normal-case">(Opsional)</span>
                </label>
                <input type="password" name="password_baru" class="w-full px-3 py-2.5 sm:py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" placeholder="Biarkan kosong jika tidak ingin mengubah password">
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-3 border-t border-stone-100">
                <a href="dashboard.php?tab=kelola_user" class="px-5 py-2.5 sm:py-2 rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-700 text-sm font-medium text-center transition-colors">
                    Batal
                </a>
                <button type="submit" class="bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-5 py-2.5 sm:py-2 rounded-md transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function previewImage(event) {
  const reader = new FileReader();
  reader.onload = function(){
    document.getElementById('previewFoto').src = reader.result;
  };
  reader.readAsDataURL(event.target.files[0]);
}
</script>
<?php include '../includes/footer.php'; ?> 