<?php
// Include database dan functions di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit();
}

$user_id = $_SESSION['user_id'];
$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['password_lama'])) {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    $foto_profil = '';

    // Validasi
    if (empty($nama) || empty($username) || empty($email) || empty($telepon) || empty($alamat)) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Semua field harus diisi.'];
        header('Location: profile.php'); exit();
    }

    // Cek username unik
    $q = "SELECT id_user FROM users WHERE username=? AND id_user!=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'si', $username, $user_id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Username sudah digunakan.'];
        header('Location: profile.php'); exit();
    }

    // Cek email unik
    $q = "SELECT id_user FROM users WHERE email=? AND id_user!=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'si', $email, $user_id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Email sudah digunakan.'];
        header('Location: profile.php'); exit();
    }

    // Upload foto
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $_SESSION['notif'] = ['type'=>'error','msg'=>'Format foto profil harus JPG, JPEG, PNG, atau WEBP.'];
            header('Location: profile.php'); exit();
        }
        $newname = 'user_' . $user_id . '_' . time() . '.' . $ext;
        $upload_dir = 'uploads/profile/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $dest = $upload_dir . $newname;
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dest)) {
            // Hapus foto lama jika ada
            $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto_profil FROM users WHERE id_user=$user_id"));
            if (!empty($old['foto_profil']) && file_exists($upload_dir.$old['foto_profil'])) {
                unlink($upload_dir.$old['foto_profil']);
            }
            $foto_profil = $newname;
        } else {
            $_SESSION['notif'] = ['type'=>'error','msg'=>'Gagal upload foto profil.'];
            header('Location: profile.php'); exit();
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
    $q .= " WHERE id_user=?";
    $params[] = $user_id;
    $types .= 'i';
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['notif'] = ['type'=>'success','msg'=>'Profil berhasil diperbarui.'];
    } else {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Gagal update profil.'];
    }
    header('Location: profile.php'); exit();
}

// Proses kirim kode verifikasi password (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'kirim_kode') {
    $kode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expire = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $q = "UPDATE users SET reset_code=?, reset_code_expire=? WHERE id_user=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'ssi', $kode, $expire, $user_id);
    mysqli_stmt_execute($stmt);
    // Kirim email kode
    require_once 'includes/mail.php';
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT email, nama FROM users WHERE id_user=$user_id"));
    $subject = "Kode Verifikasi Ganti Password";
    $body = "<p>Hai <b>{$user['nama']}</b>,</p><p>Kode verifikasi untuk ganti password akun Anda:</p><h2 style='letter-spacing:4px;'>$kode</h2><p>Kode berlaku 10 menit.</p>";
    $send = sendMail($user['email'], $subject, $body);
    echo json_encode(['success'=>$send]);
    exit();
}

// Proses ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password_lama'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id_user=$user_id"));
    // Cek password lama
    if ($password_lama !== $user['password']) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Password lama salah.'];
        header('Location: profile.php'); exit();
    }
    // Validasi password baru
    if ($password_baru !== $konfirmasi_password) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Konfirmasi password tidak cocok.'];
        header('Location: profile.php'); exit();
    }
    // Update password
    $q = "UPDATE users SET password=? WHERE id_user=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'si', $password_baru, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['notif'] = ['type'=>'success','msg'=>'Password berhasil diganti.'];
    } else {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Gagal update password.'];
    }
    header('Location: profile.php'); exit();
}

// Ambil data user terbaru
$query = "SELECT * FROM users WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
$foto_profil = !empty($user['foto_profil']) ? 'uploads/profile/' . $user['foto_profil'] : 'assets/images/default_profile.png';

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>
<div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-6 my-8">
  <a href="index.php" class="inline-flex items-center mb-4 px-4 py-2 bg-gray-200 text-green-700 font-semibold rounded-lg shadow hover:bg-gray-300 transition">
    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Kembali ke Beranda
  </a>
  <h2 class="text-2xl font-bold mb-6 text-green-700 text-center">Pengaturan Akun</h2>
  <?php if ($notif): ?>
    <div class="mb-4 px-4 py-3 rounded <?php echo $notif['type']==='success'?'bg-green-100 text-green-800 border border-green-300':'bg-red-100 text-red-800 border border-red-300'; ?>">
      <?php echo $notif['msg']; ?>
    </div>
  <?php endif; ?>
  <form id="formProfile" method="POST" enctype="multipart/form-data" class="space-y-4">
    <div class="flex flex-col items-center mb-4">
      <img src="<?php echo $foto_profil; ?>" alt="Foto Profil" class="w-24 h-24 rounded-full object-cover border mb-2" id="previewFoto">
      <label class="block text-sm font-medium text-gray-700">Foto Profil</label>
      <input type="file" name="foto_profil" accept="image/*" class="mt-1" onchange="previewImage(event)">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Nama</label>
      <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" class="input input-bordered w-full" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Username</label>
      <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="input input-bordered w-full" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Email</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="input input-bordered w-full" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Nomor Telepon</label>
      <input type="text" name="telepon" value="<?php echo htmlspecialchars($user['telepon']); ?>" class="input input-bordered w-full" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Alamat</label>
      <textarea name="alamat" class="input input-bordered w-full" rows="2" required><?php echo htmlspecialchars($user['alamat']); ?></textarea>
    </div>
    <div class="flex justify-end">
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg">Simpan Perubahan</button>
    </div>
  </form>
  <hr class="my-8">
  <h3 class="text-lg font-bold mb-2 text-gray-700">Ganti Password</h3>
  <form id="formPassword" method="POST" class="space-y-3">
    <div>
      <label class="block text-sm font-medium text-gray-700">Password Lama</label>
      <input type="password" name="password_lama" class="input input-bordered w-full" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Password Baru</label>
      <input type="password" name="password_baru" class="input input-bordered w-full" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
      <input type="password" name="konfirmasi_password" class="input input-bordered w-full" required>
    </div>
    <div class="flex justify-end">
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg">Ganti Password</button>
    </div>
  </form>
</div>
<script>
function previewImage(event) {
  const reader = new FileReader();
  reader.onload = function(){
    document.getElementById('previewFoto').src = reader.result;
  };
  reader.readAsDataURL(event.target.files[0]);
}
// AJAX kirim kode verifikasi
document.getElementById('btnKirimKode').onclick = function() {
  this.disabled = true;
  fetch('profile.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=kirim_kode'})
    .then(r=>r.json()).then(data=>{
      if(data.success){
        document.getElementById('kodeStatus').classList.remove('hidden');
      } else {
        alert('Gagal mengirim kode verifikasi!');
      }
      setTimeout(()=>{document.getElementById('btnKirimKode').disabled=false;}, 5000);
    });
}
</script>
<?php include 'includes/footer.php'; ?> 