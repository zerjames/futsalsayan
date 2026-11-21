<?php
include 'includes/header.php';
require_once __DIR__ . '/config/database.php';

$error = null;
$success = null;
$show_form = true;

date_default_timezone_set('Asia/Jakarta'); // Mengatur zona waktu ke Jakarta

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $code = mysqli_real_escape_string($conn, $_POST['code']);
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];

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
            mysqli_stmt_bind_param($update_stmt, 'si', $password_hash, $user['id_user']); // 'si' untuk string dan integer
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
}
?>

<div class="max-w-5xl mx-auto">
    <a href="login.php" class="inline-flex items-center gap-2 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors mb-6">
        &larr; Kembali ke Login
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center py-2 sm:py-6">
        <!-- Ilustrasi -->
        <div class="lg:col-span-6 flex items-center justify-center">
            <img src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/images/Forgot_password.svg"
                 alt="Ilustrasi Reset Password Futsal Sayan"
                 class="w-full max-w-[240px] sm:max-w-sm lg:max-w-md h-auto">
        </div>

        <!-- Form Reset Password -->
        <div class="lg:col-span-6">
            <div class="max-w-md w-full mx-auto bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-stone-900">Reset Password</h2>
                    <p class="text-sm text-stone-500 mt-1">Masukkan email, kode 6 digit, dan password baru Anda.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-3.5 py-2.5 text-sm mb-5">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-800 text-green-900 px-4 py-3 text-sm mb-5">
                        <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <a href="login.php" class="block w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md text-center transition-colors">
                        Masuk Sekarang
                    </a>
                <?php endif; ?>

                <?php if ($show_form): ?>
                    <form method="POST" action="" class="space-y-4">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label for="email" class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5">Email</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                   value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div>
                            <label for="code" class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5">Kode Verifikasi 6 Digit</label>
                            <input type="text" id="code" name="code" maxlength="6" pattern="\d{6}" required
                                   class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm tracking-widest tabular-nums focus:outline-none focus:border-stone-900"
                                   value="<?php echo isset($code) ? htmlspecialchars($code, ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div>
                            <label for="password_baru" class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5">Password Baru</label>
                            <input type="password" id="password_baru" name="password_baru" required
                                   class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900">
                        </div>
                        <div>
                            <label for="konfirmasi_password" class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5">Konfirmasi Password</label>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" required
                                   class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900">
                        </div>
                        <button type="submit" class="w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md transition-colors">
                            Ubah Password
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
