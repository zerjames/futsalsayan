<?php 
// Include security, database dan functions di awal
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_request()) {
        $error = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = $_POST['password'];
        
        // Query untuk mencari pengguna berdasarkan username
        $query = "SELECT id_user, username, password, email, role, is_verified, nama, foto_profil FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            die('Query error: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, 's', $username); // 's' untuk string
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verifikasi password (mendukung bcrypt hash & migrasi otomatis dari password lama plaintext)
            $is_hashed_match = password_verify($password, $user['password']);
            $is_legacy_match = (!$is_hashed_match && hash_equals((string)$user['password'], (string)$password));

            if ($is_hashed_match || $is_legacy_match) {
                // Upgrade otomatis password lama (plaintext) ke bcrypt hash saat login berhasil
                if ($is_legacy_match || password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $rehash_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id_user = ?");
                    if ($rehash_stmt) {
                        mysqli_stmt_bind_param($rehash_stmt, 'si', $new_hash, $user['id_user']);
                        mysqli_stmt_execute($rehash_stmt);
                    }
                }

                // Set session for admin users immediately
                if ($user['role'] == 'admin') {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['foto_profil'] = $user['foto_profil'];
                    header("Location: admin/dashboard.php");
                    exit();
                }
                
                // Check verification for non-admin users
                if ($user['is_verified'] == 1) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['foto_profil'] = $user['foto_profil'];
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Email belum diverifikasi. Silakan cek email Anda untuk verifikasi.";
                }
            } else {
                $error = "Username atau password salah!";
            }
        } else {
            $error = "Username atau password salah!";
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center py-2 sm:py-6">
        <!-- Ilustrasi -->
        <div class="lg:col-span-6 flex items-center justify-center">
            <img src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/images/login.svg"
                 alt="Ilustrasi Masuk Futsal Sayan"
                 class="w-full max-w-[240px] sm:max-w-sm lg:max-w-md h-auto">
        </div>

        <!-- Form Login -->
        <div class="lg:col-span-6">
            <div class="max-w-md w-full mx-auto bg-white rounded-lg border border-stone-200 p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-stone-900">Masuk</h2>
                    <p class="text-sm text-stone-500 mt-1">
                        Belum punya akun?
                        <a href="register.php" class="font-semibold text-green-800 hover:underline">Daftar di sini</a>
                    </p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-700 text-red-900 px-3.5 py-2.5 text-sm mb-5">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4">
                    <?php echo csrf_field(); ?>
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
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-stone-700 text-xs font-semibold uppercase tracking-wide" for="password">
                                Password
                            </label>
                            <a href="forgot_password.php" class="text-xs text-stone-500 hover:text-stone-900 underline">
                                Lupa password?
                            </a>
                        </div>
                        <div class="relative">
                            <input class="w-full px-3.5 py-2.5 pr-10 rounded-md border border-stone-300 text-stone-900 text-sm focus:outline-none focus:border-stone-900"
                                   id="password" 
                                   type="password" 
                                   name="password" 
                                   required>
                            <button type="button" onclick="togglePassword()" tabindex="-1" class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700 focus:outline-none">
                                <span id="eyeIcon">
                                    <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" style="display:block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" style="display:none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.249-2.383A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.973 9.973 0 01-4.043 5.306M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" /></svg>
                                </span>
                            </button>
                        </div>
                    </div>
                    <script>
                    function togglePassword() {
                        var input = document.getElementById('password');
                        var eyeOpen = document.getElementById('eyeOpen');
                        var eyeClosed = document.getElementById('eyeClosed');
                        if (input.type === 'password') {
                            input.type = 'text';
                            eyeOpen.style.display = 'none';
                            eyeClosed.style.display = 'block';
                        } else {
                            input.type = 'password';
                            eyeOpen.style.display = 'block';
                            eyeClosed.style.display = 'none';
                        }
                    }
                    </script>

                    <button class="w-full bg-green-900 hover:bg-green-800 text-white font-semibold py-2.5 px-4 rounded-md transition-colors text-sm mt-2"
                            type="submit">
                        Masuk
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
