<?php
include 'includes/header.php';
require_once __DIR__ . '/config/database.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $code = mysqli_real_escape_string($conn, $_POST['verification_code']);

        // Cek kode verifikasi menggunakan prepared statement
        $query = "SELECT * FROM users WHERE email = ? AND BINARY verification_code = ? AND is_verified = 0";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ss', $email, $code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {
            // Update status verifikasi
            $update_query = "UPDATE users SET is_verified = 1, verification_code = NULL WHERE email = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, 's', $email);

            if (mysqli_stmt_execute($update_stmt) && mysqli_affected_rows($conn) > 0) {
                // Debug information
                error_log("Verification successful for email: " . $email);
                error_log("Update query: " . $update_query);

                // Double check the update
                $check_query = "SELECT is_verified FROM users WHERE email = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, 's', $email);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_user = mysqli_fetch_assoc($check_result);
                error_log("Verification status after update: " . $check_user['is_verified']);

                $success = "Email berhasil diverifikasi! Silakan login.";
            } else {
                $error = "Terjadi kesalahan saat verifikasi.";
            }
        } else {
            $error = "Kode verifikasi tidak valid atau email tidak ditemukan.";
        }
    }
}
?>

<div class="max-w-md w-full mx-auto my-6 sm:my-10">
    <a href="login.php" class="inline-flex items-center gap-2 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors mb-5">
        &larr; Kembali ke Login
    </a>

    <div class="bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-stone-900">Verifikasi Email</h2>
            <p class="text-sm text-stone-500 mt-1">Masukkan kode 6 digit yang dikirim ke email Anda.</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-3.5 py-2.5 text-sm mb-5">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-50 border-l-4 border-green-800 text-green-900 px-4 py-3 text-sm mb-5">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <a href="login.php" class="block w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md text-center transition-colors">
                Masuk Sekarang
            </a>
        <?php else: ?>
            <form method="POST" action="" class="space-y-4">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="email">
                        Email
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                           value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>

                <div class="p-3.5 bg-stone-50 border border-stone-200 rounded-md text-xs text-stone-600 leading-relaxed">
                    Jika belum menerima email verifikasi dari Futsal Sayan Bekasi, periksa folder Spam/Junk di email Anda.
                </div>

                <div>
                    <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="verification_code">
                        Kode Verifikasi (6 Digit)
                    </label>
                    <input type="text" id="verification_code" name="verification_code" required pattern="[0-9]{6}" maxlength="6"
                           class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm tracking-widest tabular-nums focus:outline-none focus:border-stone-900"
                           placeholder="000000">
                </div>

                <button type="submit" class="w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md transition-colors">
                    Verifikasi Email
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>