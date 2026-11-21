<?php
http_response_code(404);
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto my-8 sm:my-12">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center bg-white border border-stone-200 rounded-lg p-6 sm:p-10">
        <div class="lg:col-span-6 flex items-center justify-center">
            <img src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/assets/images/404_Error.svg"
                 alt="Halaman Tidak Ditemukan (404)"
                 class="w-full max-w-[260px] sm:max-w-sm h-auto">
        </div>
        <div class="lg:col-span-6 text-center lg:text-left">
            <p class="text-xs font-semibold uppercase tracking-widest text-green-800">Error 404</p>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-stone-900 tracking-tight mt-2">Halaman Tidak Ditemukan</h1>
            <p class="text-sm text-stone-500 leading-relaxed mt-3">
                URL yang Anda tuju tidak tersedia atau mungkin sudah dipindahkan. Silakan kembali ke halaman utama untuk melihat jadwal dan melakukan booking lapangan.
            </p>
            <div class="mt-6 flex flex-col sm:flex-row lg:justify-start justify-center gap-3">
                <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/index.php"
                   class="px-5 py-2.5 rounded-md bg-green-900 hover:bg-green-800 text-white text-sm font-semibold transition-colors text-center">
                    Kembali ke Beranda
                </a>
                <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/index.php#jadwal-booking"
                   class="px-5 py-2.5 rounded-md border border-stone-300 bg-white hover:border-stone-900 text-stone-800 text-sm font-medium transition-colors text-center">
                    Lihat Jadwal
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
