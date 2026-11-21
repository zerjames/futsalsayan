    </main>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/6285748515801"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="Hubungi via WhatsApp"
       class="wa-float print:hidden fixed bottom-5 right-4 sm:right-6 w-12 h-12 bg-green-800 text-white rounded-full shadow-lg hover:bg-stone-900 active:scale-95 transition-all z-40 flex items-center justify-center">
        <i class="fab fa-whatsapp text-2xl"></i>
    </a>

    <!-- Footer -->
    <footer class="border-t border-green-950 bg-green-900 text-green-100 mt-auto print:hidden">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-20 sm:py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
            <span class="font-bold uppercase tracking-tight text-white">Futsal Sayan Bekasi</span>
            <span class="text-green-200/80 text-center">Jl. Raya Mustikasari No.34, Mustika Jaya &middot; Telp 0857-4851-5801</span>
            <span class="text-green-200/70">&copy; <?php echo date('Y'); ?> Futsal Sayan</span>
        </div>
    </footer>

    <!-- Smart Page & Form Loading Controller -->
    <script>
    (function() {
        var overlay = document.getElementById('fsLoaderOverlay');
        var bar = document.getElementById('fsLoaderBar');
        var label = document.getElementById('fsLoaderText');
        var navTimer = null;

        function showLoader(text, immediate) {
            if (label && text) label.textContent = text;
            if (bar) bar.classList.add('is-active');
            if (immediate) {
                if (overlay) overlay.classList.add('is-active');
            } else {
                clearTimeout(navTimer);
                navTimer = setTimeout(function() {
                    if (overlay) overlay.classList.add('is-active');
                }, 180);
            }
        }

        function hideLoader() {
            clearTimeout(navTimer);
            if (overlay) overlay.classList.remove('is-active');
            if (bar) bar.classList.remove('is-active');
        }

        // Tampilkan segera saat form disubmit (booking, login, kirim email, upload bukti, dll)
        document.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;
            var form = e.target;
            if (form && form.target !== '_blank') {
                showLoader('Memproses...', true);
            }
        });

        // Tampilkan saat berpindah halaman (dengan jeda singkat agar klik cepat tidak berkedip)
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a');
            if (!link) return;
            var href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
            showLoader('Memuat halaman...', false);
        });

        // Sembunyikan saat halaman selesai dimuat atau kembali dari cache browser (bfcache)
        window.addEventListener('pageshow', hideLoader);
        window.addEventListener('load', hideLoader);
    })();
    </script>
</body>
</html>