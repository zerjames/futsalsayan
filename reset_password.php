<?php
include 'includes/header.php';
require_once __DIR__ . '/config/database.php';

$error = null;
$success = null;
$show_form = true;

date_default_timezone_set('Asia/Jakarta'); // Mengatur zona waktu ke Jakarta

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $password_baru = mysqli_real_escape_string($conn, $_POST['password_baru']);
    $konfirmasi_password = mysqli_real_escape_string($conn, $_POST['konfirmasi_password']);

    // Validasi password dan konfirmasi
    if ($password_baru !== $konfirmasi_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    } else {
        // Cek kode reset dan email menggunakan prepared statements untuk menghindari SQL Injection
        $query = "SELECT * FROM users WHERE email = ? AND reset_code = ? AND reset_code_expire > NOW()";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ss', $email, $code); // 'ss' untuk string
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Hash password baru
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            
            // Update password dan hapus reset_code
            $update_query = "UPDATE users SET password = ?, reset_code = NULL, reset_code_expire = NULL WHERE id_user = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, 'si', $password_baru, $user['id_user']); // 'si' untuk string dan integer
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Password berhasil diubah. Silakan login dengan password baru Anda.";
                $show_form = false;
            } else {
                $error = "Gagal mengubah password. Silakan coba lagi.";
            }
        } else {
            $error = "Kode verifikasi tidak valid atau sudah kedaluwarsa.";
        }
    }
}
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-10">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Reset Password</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_form): ?>
    <form method="POST" action="">
        <div class="mb-4">
            <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
            <input type="email" id="email" name="email" required
                   class="w-full border rounded px-3 py-2" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        </div>
        <div class="mb-4">
            <label for="code" class="block text-gray-700 font-semibold mb-2">Kode Verifikasi 6 Digit</label>
            <input type="text" id="code" name="code" maxlength="6" pattern="\d{6}" required
                   class="w-full border rounded px-3 py-2" value="<?php echo isset($code) ? htmlspecialchars($code) : ''; ?>">
        </div>
        <div class="mb-4">
            <label for="password_baru" class="block text-gray-700 font-semibold mb-2">Password Baru</label>
            <input type="password" id="password_baru" name="password_baru" required
                   class="w-full border rounded px-3 py-2">
        </div>
        <div class="mb-6">
            <label for="konfirmasi_password" class="block text-gray-700 font-semibold mb-2">Konfirmasi Password</label>
            <input type="password" id="konfirmasi_password" name="konfirmasi_password" required
                   class="w-full border rounded px-3 py-2">
        </div>
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full">
            Ubah Password
        </button>
    </form>
    <?php endif; ?>

    <p class="text-center mt-4 text-gray-600">
        <a href="login.php" class="text-green-600 hover:text-green-800">Kembali ke halaman login</a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>
