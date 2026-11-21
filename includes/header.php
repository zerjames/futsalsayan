<?php
ob_start();
require_once __DIR__ . '/security.php';
date_default_timezone_set('Asia/Jakarta'); // Set timezone untuk Indonesia/Jakarta
$base_url = '/futsalsayan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
if (isset($conn) && function_exists('autoCancelUnpaidBookings')) {
    autoCancelUnpaidBookings($conn);
}
$current_page = basename($_SERVER['PHP_SELF']);
$is_index = $current_page === 'index.php' && !isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Futsal Sayan Bekasi</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css' rel='stylesheet' crossorigin="anonymous" />
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css' rel='stylesheet' crossorigin="anonymous" />
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.js' crossorigin="anonymous"></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.js' crossorigin="anonymous"></script>
    <!-- Custom Calendar JS -->
    <script src='<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/js/calendar.js'></script>
    <style>
        html {
            scroll-behavior: smooth;
        }
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #FAF9F6;
            color: #1C1917;
        }
        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }
        /* Mobile-friendly: prevent iOS zoom on input focus & hide scrollbar on horizontal tab strips */
        @media (max-width: 640px) {
            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="tel"],
            input[type="number"],
            input[type="date"],
            select,
            textarea {
                font-size: 16px !important;
            }
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        @keyframes fs-spin {
            to { transform: rotate(360deg); }
        }
        @keyframes fs-progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 92%; }
        }
        #fsLoaderOverlay {
            position: fixed;
            inset: 0;
            background: rgba(250, 249, 246, 0.78);
            backdrop-filter: blur(2px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        #fsLoaderOverlay.is-active {
            display: flex;
        }
        .fs-spinner {
            width: 34px;
            height: 34px;
            border: 3px solid #d6d3d1;
            border-top-color: #14532d;
            border-radius: 50%;
            animation: fs-spin 0.7s linear infinite;
        }
        #fsLoaderBar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            background: #22c55e;
            z-index: 10000;
            display: none;
        }
        #fsLoaderBar.is-active {
            display: block;
            animation: fs-progress 2.5s ease-out forwards;
        }
    </style>
</head>
<body class="bg-[#FAF9F6] text-stone-900 min-h-screen flex flex-col overflow-x-hidden antialiased">
    <!-- Top Progress Bar & Loading Overlay (only shown while loading) -->
    <div id="fsLoaderBar" class="print:hidden" aria-hidden="true"></div>
    <div id="fsLoaderOverlay" class="print:hidden" role="status" aria-live="polite">
        <div class="bg-white border border-stone-200 rounded-lg px-6 py-4 shadow-sm flex items-center gap-3.5">
            <div class="fs-spinner"></div>
            <span id="fsLoaderText" class="text-sm font-medium text-stone-700">Memuat...</span>
        </div>
    </div>
    <!-- Navbar -->
    <nav class="bg-green-900 text-white border-b border-green-950 sticky top-0 z-40 print:hidden">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-14 sm:h-16">
                <?php 
                $is_admin_page = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
                $admin_prefix = $is_admin_page ? '..' : '';
                ?>
                <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/index.php" class="text-base sm:text-lg font-extrabold tracking-tight text-white uppercase py-2">
                    Futsal Sayan
                </a>
                
                <!-- Mobile Menu Checkbox (hidden) -->
                <input type="checkbox" id="nav-toggle" class="hidden peer">
                <label for="nav-toggle" class="md:hidden cursor-pointer w-11 h-11 -mr-2 flex items-center justify-center rounded-md text-green-100 hover:text-white active:bg-green-800" aria-label="Buka Menu">
                    <i class="fas fa-bars text-xl"></i>
                </label>

                <!-- Mobile Backdrop (tap outside to close) -->
                <label for="nav-toggle" class="fixed inset-0 bg-black/50 z-40 hidden peer-checked:block md:hidden cursor-pointer" aria-hidden="true"></label>

                <!-- Navigation Links -->
                <div class="fixed md:static top-0 right-0 w-72 max-w-[82vw] md:w-auto h-full md:h-auto
                            bg-green-900 md:bg-transparent border-l md:border-l-0 border-green-800
                            p-5 md:p-0 shadow-2xl md:shadow-none
                            transform translate-x-full md:translate-x-0
                            peer-checked:translate-x-0
                            transition-transform duration-200 ease-out
                            flex flex-col md:flex-row md:items-center md:gap-6 gap-1
                            z-50">
                    <!-- Mobile Drawer Header -->
                    <div class="flex md:hidden items-center justify-between pb-4 mb-3 border-b border-green-800">
                        <span class="text-sm font-extrabold uppercase tracking-tight text-white">Menu</span>
                        <label for="nav-toggle" class="w-10 h-10 -mr-2 flex items-center justify-center rounded-md text-green-200 hover:text-white active:bg-green-800 cursor-pointer" aria-label="Tutup Menu">
                            <i class="fas fa-times text-lg"></i>
                        </label>
                    </div>

                    <a href="<?php echo $base_url; ?>/index.php"
                       class="text-base md:text-sm font-medium text-white transition-opacity py-3 px-3 md:py-1 md:px-0 rounded-md md:rounded-none <?php echo $current_page === 'index.php' ? 'bg-green-800 md:bg-transparent md:border-b-2 md:border-white' : 'hover:bg-green-800/60 md:hover:bg-transparent hover:opacity-80'; ?>">
                        Beranda
                    </a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                            <a href="<?php echo $base_url; ?>/admin/dashboard.php"
                               class="text-base md:text-sm font-medium text-white transition-opacity py-3 px-3 md:py-1 md:px-0 rounded-md md:rounded-none <?php echo $is_admin_page ? 'bg-green-800 md:bg-transparent md:border-b-2 md:border-white' : 'hover:bg-green-800/60 md:hover:bg-transparent hover:opacity-80'; ?>">
                                Dashboard Admin
                            </a>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>/riwayat.php"
                               class="text-base md:text-sm font-medium text-white transition-opacity py-3 px-3 md:py-1 md:px-0 rounded-md md:rounded-none <?php echo $current_page === 'riwayat.php' ? 'bg-green-800 md:bg-transparent md:border-b-2 md:border-white' : 'hover:bg-green-800/60 md:hover:bg-transparent hover:opacity-80'; ?>">
                                Riwayat
                            </a>
                            <a href="<?php echo $base_url; ?>/profile.php"
                               class="text-base md:text-sm font-medium text-white transition-opacity py-3 px-3 md:py-1 md:px-0 rounded-md md:rounded-none <?php echo $current_page === 'profile.php' ? 'bg-green-800 md:bg-transparent md:border-b-2 md:border-white' : 'hover:bg-green-800/60 md:hover:bg-transparent hover:opacity-80'; ?>">
                                Profil
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $base_url; ?>/logout.php"
                           class="mt-3 md:mt-0 pt-3 md:pt-1 border-t border-green-800 md:border-t-0 text-base md:text-sm font-medium text-red-200 hover:text-red-100 transition-colors py-3 px-3 md:py-1 md:px-0">
                            Keluar
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>/login.php"
                           class="text-base md:text-sm font-medium text-white transition-opacity py-3 px-3 md:py-1 md:px-0 rounded-md md:rounded-none <?php echo $current_page === 'login.php' ? 'bg-green-800 md:bg-transparent md:border-b-2 md:border-white' : 'hover:bg-green-800/60 md:hover:bg-transparent hover:opacity-80'; ?>">
                            Masuk
                        </a>
                        <a href="<?php echo $base_url; ?>/register.php"
                           class="mt-3 md:mt-0 px-4 py-3 md:py-1.5 rounded-md text-base md:text-sm font-semibold bg-white text-green-950 hover:bg-green-50 transition-colors text-center">
                            Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-800 text-green-900 px-4 py-3 text-sm" role="alert">
                <?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-700 text-red-900 px-4 py-3 text-sm" role="alert">
                <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
