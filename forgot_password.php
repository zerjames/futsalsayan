<?php
include 'includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once 'includes/mail.php';

$error = null;
$success = null;

date_default_timezone_set('Asia/Jakarta'); // Mengatur zona waktu ke Jakarta

$step = 1; // Step 1: ask username, Step 2: ask email if username not found

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else if (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        if ($username === '') {
            $error = "Username harus diisi.";
        } else {
            // Check if username exists
            $query = "SELECT * FROM users WHERE username = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                $email = $user['email'];

                // Generate reset code
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $update_query = "UPDATE users SET reset_code = ?, reset_code_expire = ? WHERE id_user = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, 'ssi', $code, $expire, $user['id_user']);
                if (mysqli_stmt_execute($update_stmt)) {
                    $email_body = "
                        <h2>Reset Password</h2>
                        <p>Hai Sobat dengan Username: <b>$username</b></p>
                        <p>Anda telah meminta reset password. Gunakan kode berikut untuk mereset password Anda. Kode ini berlaku selama 1 jam.</p>
                        <h3 style='font-size: 24px; letter-spacing: 4px;'>$code</h3>
                        <p>Masukkan kode ini pada halaman reset password bersama dengan email dan password baru Anda.</p>
                        <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                    ";
                    if (sendMail($email, "Reset Password - Futsal Sayan", $email_body)) {
                        $success = "Kode reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
                        // Set session untuk menyimpan status dan username
                        $_SESSION['forgot_password_success'] = true;
                        $_SESSION['forgot_username'] = $username;
                    } else {
                        $error = "Gagal mengirim email reset password. Silakan coba lagi.";
                    }
                } else {
                    $error = "Gagal memperbarui data. Silakan coba lagi.";
                }
            } else {
                // Username not found, show alert
                $error = "Username tidak ditemukan.";
                $step = 2;
            }
        }
    } elseif (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        if ($email === '') {
            $error = "Email harus diisi.";
            $step = 2;
        } else {
            // Check if email exists
            $query = "SELECT * FROM users WHERE email = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                $username = $user['username'];

                // Generate reset code
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $update_query = "UPDATE users SET reset_code = ?, reset_code_expire = ? WHERE id_user = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, 'ssi', $code, $expire, $user['id_user']);
                if (mysqli_stmt_execute($update_stmt)) {
                    $email_body = "
                        <h2>Reset Password</h2>
                        <p>Halo $username, ini adalah Username akun Anda: <b>$username</b></p>
                        <p>Anda telah meminta reset password. Gunakan kode berikut untuk mereset password Anda. Kode ini berlaku selama 1 jam.</p>
                        <h3 style='font-size: 24px; letter-spacing: 4px;'>$code</h3>
                        <p>Masukkan kode ini pada halaman reset password bersama dengan email dan password baru Anda.</p>
                        <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                    ";
                    if (sendMail($email, "Reset Password - Futsal Sayan", $email_body)) {
                        $success = "Kode reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
                        // Set session untuk menyimpan status dan email
                        $_SESSION['forgot_password_success'] = true;
                        $_SESSION['forgot_email'] = $email;
                    } else {
                        $error = "Gagal mengirim email reset password. Silakan coba lagi.";
                    }
                } else {
                    $error = "Gagal memperbarui data. Silakan coba lagi.";
                }
            } else {
                // Email not found, show message to contact admin
                $error = "Email tidak ditemukan. Silakan hubungi admin dengan melampirkan nama, nomor telepon, dan alamat yang sesuai.";
            }
            $step = 2;
        }
    }
}

// Check if user is coming back from success state
if (isset($_SESSION['forgot_password_success']) && $_SESSION['forgot_password_success'] === true) {
    $success = "Kode reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
}

// Handle reset parameter to clear session and go back to username input
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    unset($_SESSION['forgot_password_success']);
    unset($_SESSION['forgot_username']);
    unset($_SESSION['forgot_email']);
    $success = null;
    $step = 1;
}

// Clear session if user is accessing the page for the first time (not from form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['reset'])) {
    unset($_SESSION['forgot_password_success']);
    $success = null;
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
                 alt="Ilustrasi Lupa Password Futsal Sayan"
                 class="w-full max-w-[240px] sm:max-w-sm lg:max-w-md h-auto">
        </div>

        <!-- Form Lupa Password -->
        <div class="lg:col-span-6">
            <div class="max-w-md w-full mx-auto bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-stone-900">Lupa Password</h2>
                    <p class="text-sm text-stone-500 mt-1">
                        <?php echo $step == 1 ? 'Masukkan username Anda untuk menerima kode reset 6 digit via email.' : 'Masukkan alamat email yang terdaftar di akun Anda.'; ?>
                    </p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-3.5 py-2.5 text-sm mb-5">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-800 text-green-900 px-4 py-3 text-sm mb-5">
                        <p class="font-semibold mb-1">Kode Reset Berhasil Dikirim</p>
                        <p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="p-4 bg-stone-50 border border-stone-200 rounded-md text-xs text-stone-600 mb-5">
                        <p class="font-semibold text-stone-800 mb-2">Langkah Selanjutnya</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Cek email Anda (termasuk folder spam).</li>
                            <li>Salin kode verifikasi 6 digit yang dikirim.</li>
                            <li>Klik tombol di bawah untuk mengatur password baru.</li>
                        </ol>
                    </div>

                    <a href="reset_password.php" class="block w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md text-center transition-colors">
                        Masukkan Kode Reset Password
                    </a>
                <?php elseif ($step == 1): ?>
                    <form method="POST" action="" class="space-y-4">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label for="username" class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5">Username</label>
                            <input type="text" id="username" name="username" required
                                   class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" 
                                   value="<?php echo isset($_SESSION['forgot_username']) ? htmlspecialchars($_SESSION['forgot_username'], ENT_QUOTES, 'UTF-8') : (isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''); ?>">
                        </div>
                        <button type="submit" class="w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md transition-colors">
                            Kirim Kode Reset Password
                        </button>
                    </form>
                <?php elseif ($step == 2): ?>
                    <form method="POST" action="" class="space-y-4">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label for="email" class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5">Email</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900" 
                                   value="<?php echo isset($_SESSION['forgot_email']) ? htmlspecialchars($_SESSION['forgot_email'], ENT_QUOTES, 'UTF-8') : (isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''); ?>">
                        </div>
                        <button type="submit" class="w-full bg-green-900 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2.5 rounded-md transition-colors">
                            Kirim Kode Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Link navigasi -->
                <div class="mt-6 pt-5 border-t border-stone-200 text-sm text-stone-500 space-y-2">
                    <?php if (isset($success)): ?>
                        <p>
                            <a href="forgot_password.php?reset=1" class="font-medium text-stone-700 hover:text-stone-900 underline">
                                &larr; Kembali ke input username
                            </a>
                        </p>
                    <?php else: ?>
                        <p>
                            Sudah punya kode? <a href="reset_password.php" class="font-semibold text-green-800 hover:underline">Masukkan kode di sini</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
