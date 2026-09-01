<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Include database di awal
require_once __DIR__ . '/config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    
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
        
        // Verifikasi password dengan hash yang ada di database
        if ($password === $user['password']) {
            // Set session for admin users immediately
            if ($user['role'] == 'admin') {
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

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Masuk ke Akun Anda</h2>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                Username
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="username" 
                   type="text" 
                   name="username" 
                   required>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                Password
            </label>
            <div class="relative">
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       id="password" 
                       type="password" 
                       name="password" 
                       required>
                <button type="button" onclick="togglePassword()" tabindex="-1" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;outline:none;cursor:pointer;">
                    <span id="eyeIcon" style="font-size:18px;color:#888;">
                        <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" style="display:block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" style="display:none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.249-2.383A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.973 9.973 0 01-4.043 5.306M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" /></svg>
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

        <div class="flex items-center justify-between">
            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                    type="submit">
                Masuk
            </button>
        </div>
    </form>

    <div class="flex items-center justify-between mt-4">
        <a href="forgot_password.php" class="text-green-600 hover:text-green-800 text-sm">
            Lupa Password?
        </a>
    </div>

    <p class="text-center mt-4 text-gray-600">
        Belum punya akun? 
        <a href="register.php" class="text-green-600 hover:text-green-800">
            Daftar di sini
        </a>
    </p>

<?php include 'includes/footer.php'; ?>
