<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

function sendMail($to_email, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.sayan.biz.id'; // SMTP dari cPanel
        $mail->SMTPAuth = true;
        $mail->Username = 'futsalsayan@sayan.biz.id'; // Email domain
        $mail->Password = 'pqhIPUKyyTp1=PEh'; // Ganti dengan password email cPanel
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // atau 'ssl'
        $mail->Port = 465; // Port dari cPanel

        // Recipients
        $mail->setFrom('futsalsayan@sayan.biz.id', 'Futsal Booking');
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendBookingNotification($conn, $booking_id, $status, $booking_type = 'regular') {
    // Determine table and fields based on booking type
    switch ($booking_type) {
        case 'eskul':
            $query = "SELECT b.*, u.email, u.nama as nama_user, l.nama as nama_lapangan,
                            b.nama_sekolah, b.durasi_bulan, b.tanggal_mulai
                     FROM booking_eskul b 
                     JOIN users u ON b.user_id = u.id_user 
                     JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
                     WHERE b.id_booking_eskul = '$booking_id'";
            break;
        case 'turnamen':
            $query = "SELECT b.*, u.email, u.nama as nama_user, l.nama as nama_lapangan,
                            b.nama_turnamen, b.durasi_hari, b.tanggal_mulai
                     FROM booking_turnamen b 
                     JOIN users u ON b.user_id = u.id_user 
                     JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
                     WHERE b.id_booking_turnamen = '$booking_id'";
            break;
        default: // regular booking
            $query = "SELECT b.*, u.email, u.nama as nama_user, l.nama as nama_lapangan 
                     FROM booking b 
                     JOIN users u ON b.user_id = u.id_user 
                     JOIN lapangan l ON b.lapangan_id = l.id_lapangan 
                     WHERE b.id_booking = '$booking_id'";
    }
    
    $result = mysqli_query($conn, $query);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) return false;
    
    $subject = "Status Booking Lapangan - " . ucfirst($status);
    $message = "<p>Hai " . htmlspecialchars($booking['nama_user']) . ",</p>";
    
    if ($status == 'dikonfirmasi') {
        switch ($booking_type) {
            case 'eskul':
                $message .= "<p>Booking Eskul untuk lapangan " . htmlspecialchars($booking['nama_lapangan']) . 
                           " atas nama sekolah " . htmlspecialchars($booking['nama_sekolah']) .
                           " mulai tanggal " . date('d/m/Y', strtotime($booking['tanggal_mulai'])) .
                           " selama " . $booking['durasi_bulan'] . " bulan" .
                           " telah dikonfirmasi.</p>";
                break;
            case 'turnamen':
                $message .= "<p>Booking Turnamen " . htmlspecialchars($booking['nama_turnamen']) .
                           " di lapangan " . htmlspecialchars($booking['nama_lapangan']) . 
                           " mulai tanggal " . date('d/m/Y', strtotime($booking['tanggal_mulai'])) .
                           " selama " . $booking['durasi_hari'] . " hari" .
                           " telah dikonfirmasi.</p>";
                break;
            default:
                $message .= "<p>Booking lapangan " . htmlspecialchars($booking['nama_lapangan']) . 
                           " pada tanggal " . date('d/m/Y', strtotime($booking['tanggal_main'])) . 
                           " jam " . date('H:i', strtotime($booking['jam_mulai'])) . 
                           " - " . date('H:i', strtotime($booking['jam_selesai'])) . 
                           " telah dikonfirmasi.</p>";
        }
        $message .= "<p>Terima kasih telah menggunakan layanan kami.</p>";
    } else if ($status == 'dibatalkan') {
        switch ($booking_type) {
            case 'eskul':
                $message .= "<p>Booking Eskul untuk lapangan " . htmlspecialchars($booking['nama_lapangan']) . 
                           " atas nama sekolah " . htmlspecialchars($booking['nama_sekolah']) .
                           " telah dibatalkan.</p>";
                break;
            case 'turnamen':
                $message .= "<p>Booking Turnamen " . htmlspecialchars($booking['nama_turnamen']) .
                           " di lapangan " . htmlspecialchars($booking['nama_lapangan']) . 
                           " telah dibatalkan.</p>";
                break;
            default:
                $message .= "<p>Booking lapangan " . htmlspecialchars($booking['nama_lapangan']) . 
                           " pada tanggal " . date('d/m/Y', strtotime($booking['tanggal_main'])) . 
                           " telah dibatalkan.</p>";
        }
        $message .= "<p>Jika ada pertanyaan, silakan hubungi admin kami.</p>";
    }
    
    return sendMail($booking['email'], $subject, $message);
}

function sendWhatsAppNotification($phone, $message) {
    $token = '4x1XXx1te1wUemAEiMG8'; // Token Fonnte
    $url = 'https://api.fonnte.com/send';
    
    $data = [
        'target' => $phone,
        'message' => $message,
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $token
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('WhatsApp notification error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $response;
}
?>
