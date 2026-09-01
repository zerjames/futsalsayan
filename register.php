<?php 
// Include database dan mail di awal
require_once __DIR__ . '/config/database.php';
require_once 'includes/mail.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta'); // Mengatur zona waktu ke Jakarta

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    
    // Validasi nama (hanya huruf alphabet dan spasi)
    if (!preg_match("/^[A-Za-z\s]+$/", $nama)) {
        $error = "Nama hanya boleh menggunakan huruf alphabet.";
    } else {
        $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);
        
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

// Include header setelah semua logic PHP dan redirect selesai
include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-center text-green-600 mb-6">Daftar Akun Baru</h2>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="nama">
                Nama Lengkap
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="nama" 
                   type="text" 
                   name="nama" 
                   pattern="[A-Za-z\s]+"
                   title="Nama hanya boleh menggunakan huruf alphabet"
                   required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="telepon">
                Nomor Telepon
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="telepon" 
                   type="tel" 
                   name="telepon" 
                   pattern="[0-9]+" 
                   required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="alamat">
                Alamat
            </label>
            <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                      id="alamat" 
                      name="alamat" 
                      rows="3" 
                      required></textarea>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                Email
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="email" 
                   type="email" 
                   name="email" 
                   required>
        </div>

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
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   id="password" 
                   type="password" 
                   name="password" 
                   required>
        </div>

        <div class="flex items-center justify-between">
            <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                    type="submit">
                Daftar
            </button>
        </div>
    </form>

    <p class="text-center mt-4 text-gray-600">
        Sudah punya akun? 
        <a href="login.php" class="text-green-600 hover:text-green-800">
            Masuk di sini
        </a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>
