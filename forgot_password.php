<?php
include 'includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once 'includes/mail.php';

$error = null;
$success = null;

date_default_timezone_set('Asia/Jakarta'); // Mengatur zona waktu ke Jakarta

$step = 1; // Step 1: ask username, Step 2: ask email if username not found

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username'])) {
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

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-10">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Lupa Password</h2>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex items-center mb-2">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <strong>Kode Reset Password Berhasil Dikirim!</strong>
            </div>
            <p class="mb-3"><?php echo $success; ?></p>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded">
                <h4 class="font-semibold text-blue-800 mb-2">Langkah Selanjutnya:</h4>
                <ol class="list-decimal list-inside text-blue-700 text-sm space-y-1">
                    <li>Cek email Anda (termasuk folder spam)</li>
                    <li>Salin kode 6 digit yang dikirim</li>
                    <li>Klik tombol "Masukkan Kode Reset Password" di bawah</li>
                    <li>Masukkan kode, email, dan password baru Anda</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!isset($success) && $step == 1): ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 font-semibold mb-2">Username</label>
                <input type="text" id="username" name="username" required
                       class="w-full border rounded px-3 py-2" 
                       value="<?php echo isset($_SESSION['forgot_username']) ? htmlspecialchars($_SESSION['forgot_username']) : (isset($username) ? htmlspecialchars($username) : ''); ?>">
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full">
                Kirim Kode Reset Password
            </button>
        </form>
    <?php elseif (!isset($success) && $step == 2): ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
                <input type="email" id="email" name="email" required
                       class="w-full border rounded px-3 py-2" 
                       value="<?php echo isset($_SESSION['forgot_email']) ? htmlspecialchars($_SESSION['forgot_email']) : (isset($email) ? htmlspecialchars($email) : ''); ?>">
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full">
                Kirim Kode Reset Password
            </button>
        </form>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <!-- Tombol untuk melanjutkan ke halaman reset password -->
        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="font-semibold text-blue-800 mb-3">Siap untuk Reset Password?</h3>
            <a href="reset_password.php" class="block w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 text-center font-semibold">
                Masukkan Kode Reset Password
            </a>
        </div>
    <?php endif; ?>

    <!-- Link navigasi -->
    <div class="mt-6 pt-4 border-t border-gray-200">
        <?php if (isset($success)): ?>
            <!-- Jika sudah berhasil kirim kode, tombol kembali ke halaman input username -->
            <p class="text-center text-gray-600 mb-2">
                <a href="forgot_password.php?reset=1" class="text-green-600 hover:text-green-800 font-medium">
                    ← Kembali ke input username
                </a>
            </p>
        <?php else: ?>
            <!-- Jika belum berhasil, tombol kembali ke login -->
            <p class="text-center text-gray-600 mb-2">
                <a href="login.php" class="text-green-600 hover:text-green-800 font-medium">
                    ← Kembali ke halaman login
                </a>
            </p>
        <?php endif; ?>
        
        <?php if (!isset($success)): ?>
            <p class="text-center text-gray-600">
                Sudah punya kode? <a href="reset_password.php" class="text-green-600 hover:text-green-800 font-medium">Masukkan kode di sini</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
