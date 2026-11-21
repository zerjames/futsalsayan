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

// CSRF Validation for all POST requests in profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_request()) {
        if (isset($_POST['action']) && $_POST['action'] === 'kirim_kode') {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit();
        }
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.'];
        header('Location: profile.php');
        exit();
    }
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['password_lama']) && (!isset($_POST['action']) || $_POST['action'] !== 'kirim_kode')) {
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
    // Cek password lama (mendukung bcrypt hash & password lama plaintext)
    $is_valid_old = password_verify($password_lama, $user['password']) || hash_equals((string)$user['password'], (string)$password_lama);
    if (!$is_valid_old) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Password lama salah.'];
        header('Location: profile.php'); exit();
    }
    // Validasi password baru
    if ($password_baru !== $konfirmasi_password) {
        $_SESSION['notif'] = ['type'=>'error','msg'=>'Konfirmasi password tidak cocok.'];
        header('Location: profile.php'); exit();
    }
    // Update password dengan hash bcrypt
    $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
    $q = "UPDATE users SET password=? WHERE id_user=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'si', $password_hash, $user_id);
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
<div class="max-w-2xl mx-auto">
  <a href="index.php" class="inline-flex items-center gap-2 mb-5 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors">
    &larr; Kembali ke Beranda
  </a>
  <div class="bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
    <h2 class="text-xl font-bold text-stone-900 mb-1">Pengaturan Akun</h2>
    <p class="text-sm text-stone-500 mb-6">Perbarui data profil atau password akun Anda.</p>

    <?php if ($notif): ?>
      <div class="mb-5 px-4 py-3 text-sm border-l-4 <?php echo $notif['type']==='success'?'bg-green-50 text-green-900 border-green-800':'bg-red-50 text-red-900 border-red-700'; ?>">
        <?php echo htmlspecialchars($notif['msg'], ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form id="formProfile" method="POST" enctype="multipart/form-data" class="space-y-4">
      <?php echo csrf_field(); ?>
      <div class="flex flex-col sm:flex-row items-center gap-4 pb-5 border-b border-stone-200">
        <img src="<?php echo htmlspecialchars($foto_profil, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto Profil" class="w-16 h-16 rounded-full object-cover border border-stone-200" id="previewFoto">
        <div class="text-center sm:text-left">
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1">Foto Profil</label>
          <input type="file" name="foto_profil" accept="image/*" class="text-xs text-stone-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-stone-300 file:text-xs file:font-medium file:bg-white file:text-stone-700 hover:file:bg-stone-50 cursor-pointer" onchange="previewImage(event)">
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Nama</label>
          <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Username</label>
          <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Email</label>
          <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Nomor Telepon</label>
          <input type="text" name="telepon" value="<?php echo htmlspecialchars($user['telepon'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Alamat</label>
        <textarea name="alamat" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" rows="2" required><?php echo htmlspecialchars($user['alamat'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="bg-stone-900 hover:bg-green-800 text-white text-sm font-semibold px-5 py-2 rounded-md transition-colors">Simpan Perubahan</button>
      </div>
    </form>

    <hr class="my-8 border-stone-200">

    <h3 class="text-base font-bold text-stone-900 mb-4">Ganti Password</h3>
    <form id="formPassword" method="POST" class="space-y-4">
      <?php echo csrf_field(); ?>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Password Lama</label>
        <input type="password" name="password_lama" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Password Baru</label>
          <input type="password" name="password_baru" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-stone-600 mb-1.5">Konfirmasi Password Baru</label>
          <input type="password" name="konfirmasi_password" class="w-full px-3 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" required>
        </div>
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="bg-stone-900 hover:bg-green-800 text-white text-sm font-semibold px-5 py-2 rounded-md transition-colors">Ganti Password</button>
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
// AJAX kirim kode verifikasi
document.getElementById('btnKirimKode').onclick = function() {
  this.disabled = true;
  const csrfToken = '<?php echo csrf_token(); ?>';
  fetch('profile.php', {
    method: 'POST', 
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-TOKEN': csrfToken
    }, 
    body: 'action=kirim_kode&csrf_token=' + encodeURIComponent(csrfToken)
  })
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