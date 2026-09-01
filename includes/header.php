<?php

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta'); // Set timezone untuk Indonesia/Jakarta
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
$current_page = basename($_SERVER['PHP_SELF']);
$is_index = $current_page === 'index.php' && !isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="<?php echo $is_index ? 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no' : 'width=device-width, initial-scale=1.0'; ?>">
    <title>Futsal Sayan Bekasi</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css' rel='stylesheet' />
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.js'></script>
    <!-- Custom Calendar JS -->
    <script src='<?php echo $base_url; ?>/assets/js/calendar.js'></script>
    <style>
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<?php
    $background_image = ($current_page === 'index.php')
        ? '/futsalsayan/assets/images/backgoundindex.jpg?v=' . filemtime(__DIR__ . '/../assets/images/backgoundindex.jpg')
        : '/futsalsayan/assets/images/backgroundfutsal.jpg';
?>
<body class="bg-gray-100 overflow-x-hidden" style="background-image: url('<?php echo $background_image; ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <!-- Navbar -->
    <nav class="bg-green-600 text-white shadow-lg sticky top-0 z-40">
        <div class="container mx-auto px-4 max-w-full">
            <div class="flex justify-between items-center py-4">
                <?php 
                $base_url = '/futsalsayan';
                $is_admin_page = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
                $admin_prefix = $is_admin_page ? '..' : '';
                ?>
                <a href="<?php echo $base_url; ?>/index.php" class="text-2xl font-bold">Futsal Sayan</a>
                
                <!-- Mobile Menu Checkbox (hidden) -->
                <input type="checkbox" id="nav-toggle" class="hidden peer">
                <label for="nav-toggle" class="md:hidden cursor-pointer">
                    <i class="fas fa-bars text-2xl"></i>
                </label>

                <!-- Navigation Links -->
                <div class="absolute md:relative top-16 md:top-0 right-0 md:right-auto w-full max-w-xs md:w-auto 
                            bg-green-600 md:bg-transparent
                            p-4 md:p-0 shadow-lg md:shadow-none
                            transform translate-x-full md:translate-x-0
                            peer-checked:translate-x-0
                            transition-transform duration-200 ease-in-out
                            flex flex-col md:flex-row md:space-x-4 space-y-4 md:space-y-0
                            z-50">
                    <a href="<?php echo $base_url; ?>/index.php" class="block md:inline-block hover:text-green-200">Beranda</a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                            <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="block md:inline-block hover:text-green-200">Dashboard Admin</a>
                            <?php else: ?>
                            <!-- Booking link removed as per user request -->
                            <a href="<?php echo $base_url; ?>/riwayat.php" class="block md:inline-block hover:text-green-200">Riwayat</a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="profile.php" class="block md:inline-block hover:text-green-200">Edit Profil</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?php echo $base_url; ?>/logout.php" class="block md:inline-block hover:text-green-200">Keluar</a>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>/login.php" class="block md:inline-block hover:text-green-200">Masuk</a>
                        <a href="<?php echo $base_url; ?>/register.php" class="block md:inline-block hover:text-green-200">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8 flex flex-col items-center justify-center min-h-screen">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
 
        <!-- Floating WhatsApp Button -->
        <a href="https://wa.me/6285748515801" 
           target="_blank"
           class="fixed bottom-6 right-6 bg-green-500 text-white rounded-full p-3 shadow-lg hover:bg-green-600 transition-colors duration-200 z-50 flex items-center justify-center w-14 h-14">
            <i class="fab fa-whatsapp text-2xl"></i>
        </a>
