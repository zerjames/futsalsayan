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
        $params[] = $password_baru;
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
<div class="min-h-screen flex items-center justify-center bg-gray-100">
  <div class="w-full max-w-xl bg-white rounded-xl shadow-lg p-8 my-8">
    <a href="dashboard.php?tab=kelola_user" class="inline-flex items-center mb-4 px-4 py-2 bg-gray-200 text-green-700 font-semibold rounded-lg shadow hover:bg-gray-300 transition">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      Kembali ke Kelola User
    </a>
    <h2 class="text-2xl font-bold mb-6 text-green-700 text-center">Edit Profil User</h2>
    <div class="flex justify-center mb-4">
      <?php if ($user['is_verified'] == 1): ?>
        <span class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">Terverifikasi</span>
      <?php else: ?>
        <span class="inline-block bg-red-100 text-red-800 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">Belum Verifikasi</span>
      <?php endif; ?>
    </div>
    <?php if (isset($_SESSION['error'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-red-100 text-red-800 border border-red-300">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="space-y-4">
      <div class="flex flex-col items-center mb-4">
        <img src="<?php echo $foto; ?>" alt="Foto Profil" class="w-24 h-24 rounded-full object-cover border mb-2" id="previewFoto">
        <label class="block text-sm font-medium text-gray-700">Foto Profil</label>
        <input type="file" name="foto_profil" accept="image/*" class="mt-1 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100" onchange="previewImage(event)">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
        <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" class="input input-bordered w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="input input-bordered w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="input input-bordered w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
        <input type="text" name="telepon" value="<?php echo htmlspecialchars($user['telepon']); ?>" class="input input-bordered w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
        <textarea name="alamat" class="input input-bordered w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" rows="2" required><?php echo htmlspecialchars($user['alamat']); ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru <span class="text-gray-400 text-xs">(Opsional)</span></label>
        <input type="password" name="password_baru" class="input input-bordered w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" placeholder="Biarkan kosong jika tidak ingin mengubah password">
      </div>
      <div class="flex justify-end">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow">Simpan Perubahan</button>
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