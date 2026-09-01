<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
autoCancelUnpaidBookings($conn);
// Optional: log waktu cron jalan
file_put_contents(__DIR__.'/cron_log.txt', date('Y-m-d H:i:s')." - Cron jalan\n", FILE_APPEND); 