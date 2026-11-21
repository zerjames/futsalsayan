<?php 
// Include security, database dan mail di awal
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once 'includes/mail.php';

date_default_timezone_set('Asia/Jakarta'); // Mengatur zona waktu ke Jakarta

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    
    // Validasi nama (hanya huruf alphabet dan spasi)
    if (!preg_match("/^[A-Za-z\s]+$/", $nama)) {
        $error = "Nama hanya boleh menggunakan huruf alphabet.";
    } elseif (!isset($_POST['confirm_password']) || $_POST['password'] !== $_POST['confirm_password']) {
        $error = "Konfirmasi password tidak cocok dengan password.";
    } else {
        $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Generate kode verifikasi 6 digit
        $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Cek apakah username atau email sudah ada
        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'ss', $username, $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $user = mysqli_fetch_assoc($check_result);
            if ($user['username'] == $username) {
                $error = "Username sudah digunakan. Silakan pilih username lain.";
            } else {
                $error = "Email sudah terdaftar. Silakan gunakan email lain.";
            }
        } else {
            // Insert user baru menggunakan prepared statement
            $query = "INSERT INTO users (nama, telepon, alamat, username, email, password, verification_code) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssssss', $nama, $telepon, $alamat, $username, $email, $password, $verification_code);
            
            if (mysqli_stmt_execute($stmt)) {
                // Kirim email verifikasi
                $email_body = "
                    <h2>Verifikasi Email Anda</h2>
                    <p>Halo sobat sayan!. Terima kasih telah mendaftar di Futsal Sayan. Harap untuk menyelesaikan pendaftaran dengan memasukkan kode verivikasi dulu ya. Berikut adalah kode verifikasi Anda:</p>
                    <h3 style='font-size: 24px; letter-spacing: 4px; text-align: center; background-color: #f8f9fa; padding: 20px; border-radius: 5px;'>{$verification_code}</h3>
                    <p>Masukkan kode ini pada halaman verifikasi untuk mengaktifkan akun Anda, terima kasih!!.</p>
                    <p>Kode ini hanya berlaku untuk satu kali penggunaan.</p>
                ";
                
                if (sendMail($email, "Verifikasi Email - Futsal Sayan", $email_body)) {
                    header("Location: verify.php?email=" . urlencode($email));
                    exit();
                } else {
                    $error = "Pendaftaran berhasil, tetapi gagal mengirim email verifikasi. Silakan hubungi admin.";
                }
            } else {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
        }
    }
    }
}

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-5xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-stone-500 hover:text-stone-900 transition-colors mb-6">
        &larr; Kembali ke Beranda
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center py-2">
        <!-- Ilustrasi -->
        <div class="lg:col-span-6 flex items-center justify-center">
            <img src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/images/register.svg"
                 alt="Ilustrasi Daftar Akun Futsal Sayan"
                 class="w-full max-w-[240px] sm:max-w-sm lg:max-w-md h-auto">
        </div>

        <!-- Form Register -->
        <div class="lg:col-span-6">
            <div class="max-w-md w-full mx-auto bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-stone-900">Registrasi Akun</h2>
                    <p class="text-sm text-stone-500 mt-1">
                        Sudah punya akun?
                        <a href="login.php" class="font-semibold text-green-800 hover:underline">Masuk di sini</a>
                    </p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-3.5 py-2.5 text-sm mb-5">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-3.5">
                    <?php echo csrf_field(); ?>
                    <div>
                        <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="nama">
                            Nama Lengkap
                        </label>
                        <input class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                               id="nama" 
                               type="text" 
                               name="nama" 
                               pattern="[A-Za-z\s]+"
                               title="Nama hanya boleh menggunakan huruf alphabet"
                               required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="username">
                                Username
                            </label>
                            <input class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                   id="username" 
                                   type="text" 
                                   name="username" 
                                   required>
                        </div>

                        <div>
                            <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="telepon">
                                Nomor Telepon
                            </label>
                            <input class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                   id="telepon" 
                                   type="tel" 
                                   name="telepon" 
                                   pattern="[0-9]+" 
                                   required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="email">
                            Email
                        </label>
                        <input class="w-full px-3.5 py-2.5 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                               id="email" 
                               type="email" 
                               name="email" 
                               required>
                    </div>

                    <div>
                        <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="alamat">
                            Alamat
                        </label>
                        <textarea class="w-full px-3.5 py-2 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                  id="alamat" 
                                  name="alamat" 
                                  rows="2" 
                                  required></textarea>
                    </div>

                    <div>
                        <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="password">
                            Password
                        </label>
                        <div class="relative">
                            <input class="w-full px-3.5 py-2.5 pr-10 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                   id="password" 
                                   type="password" 
                                   name="password" 
                                   required>
                            <button type="button" onclick="toggleRegPassword('password', this)" tabindex="-1" class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700 focus:outline-none" aria-label="Tampilkan password">
                                <i class="far fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide mb-1.5" for="confirm_password">
                            Konfirmasi Password
                        </label>
                        <div class="relative">
                            <input class="w-full px-3.5 py-2.5 pr-10 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                   id="confirm_password" 
                                   type="password" 
                                   name="confirm_password" 
                                   placeholder="Ketik ulang password"
                                   required>
                            <button type="button" onclick="toggleRegPassword('confirm_password', this)" tabindex="-1" class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700 focus:outline-none" aria-label="Tampilkan konfirmasi password">
                                <i class="far fa-eye text-sm"></i>
                            </button>
                        </div>
                        <p id="confirmPasswordMsg" class="text-xs text-red-700 mt-1 hidden">Konfirmasi password tidak cocok.</p>
                    </div>

                    <button class="w-full bg-green-900 hover:bg-green-800 text-white font-semibold py-2.5 px-4 rounded-md transition-colors text-sm mt-2"
                            type="submit">
                        Daftar
                    </button>
                </form>

                <script>
                function toggleRegPassword(inputId, btn) {
                    var input = document.getElementById(inputId);
                    var icon = btn.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
                (function() {
                    var pwd = document.getElementById('password');
                    var confirmPwd = document.getElementById('confirm_password');
                    var msg = document.getElementById('confirmPasswordMsg');
                    var form = pwd ? pwd.closest('form') : null;

                    function checkMatch() {
                        if (!confirmPwd.value) {
                            msg.classList.add('hidden');
                            confirmPwd.setCustomValidity('');
                            return true;
                        }
                        if (pwd.value !== confirmPwd.value) {
                            msg.classList.remove('hidden');
                            confirmPwd.setCustomValidity('Konfirmasi password tidak cocok.');
                            return false;
                        }
                        msg.classList.add('hidden');
                        confirmPwd.setCustomValidity('');
                        return true;
                    }

                    if (pwd && confirmPwd) {
                        pwd.addEventListener('input', checkMatch);
                        confirmPwd.addEventListener('input', checkMatch);
                    }
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            if (!checkMatch()) {
                                e.preventDefault();
                                confirmPwd.focus();
                            }
                        });
                    }
                })();
                </script>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
