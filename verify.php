<?php
include 'includes/header.php';
require_once __DIR__ . '/config/database.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-10">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Verifikasi Email</h2>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success; ?>
        </div>
        <div class="text-center mt-4">
            <a href="login.php" class="inline-block bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700">
                Login Sekarang
            </a>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2" for="email">
                    Email
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required
                       class="w-full border rounded px-3 py-2"
                       value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
            </div>
            
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4">
                <p class="text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    Jika Anda belum menerima email verifikasi, mohon periksa folder Spam/Junk di email Anda. Email dikirim dari Futsal Sayan Bekasi.
                </p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2" for="verification_code">
                    Kode Verifikasi (6 digit)
                </label>
                <input type="text" 
                       id="verification_code" 
                       name="verification_code" 
                       required
                       pattern="[0-9]{6}"
                       maxlength="6"
                       class="w-full border rounded px-3 py-2"
                       placeholder="Masukkan kode 6 digit">
            </div>
            
            <button type="submit" 
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full">
                Verifikasi Email
            </button>
        </form>
        
        <p class="text-center mt-4 text-gray-600">
            <a href="login.php" class="text-green-600 hover:text-green-800">Kembali ke halaman login</a>
        </p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
