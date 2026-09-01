<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle both POST and GET requests
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Debug: Log the action
    error_log("Action received: '$action'");
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    error_log("Action received (GET): '$action'");
}

switch ($action) {
    case 'add':
        // Validate required fields
        if (!isset($_POST['harga']) || !isset($_POST['keterangan'])) {
            $_SESSION['error'] = "Data tidak lengkap";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }

        $harga = (int)$_POST['harga'];
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        $gambar = '';
        
        // Handle image upload
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['gambar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Format file tidak diizinkan. Gunakan JPG atau PNG.";
                header("Location: dashboard.php?tab=kelola_lapangan");
                exit();
            }

            $new_filename = uniqid() . '.' . $ext;
            $upload_path = '../uploads/paket/';
            
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path . $new_filename)) {
                $gambar = $new_filename;
            }
        }
        
        // Generate tipe paket berdasarkan input form
        $tipe = '';
        $paket_type = isset($_POST['paket_type']) ? $_POST['paket_type'] : '';
        
        // Debug: Log the POST data
        error_log("POST data: " . print_r($_POST, true));
        
        if ($paket_type === 'eskul' && isset($_POST['lapangan_id_eskul']) && !empty($_POST['lapangan_id_eskul'])) {
            // Paket eskul
            $lapangan_id = mysqli_real_escape_string($conn, $_POST['lapangan_id_eskul']);
            $tipe = "eskul_lap{$lapangan_id}";
        } elseif ($paket_type === 'turnamen' && isset($_POST['lapangan_id_turnamen']) && !empty($_POST['lapangan_id_turnamen'])) {
            // Paket turnamen
            $lapangan_id = mysqli_real_escape_string($conn, $_POST['lapangan_id_turnamen']);
            if (isset($_POST['jenis'])) {
                $jenis = mysqli_real_escape_string($conn, $_POST['jenis']);
                $tipe = "turnamen_lap{$lapangan_id}_{$jenis}";
            } else {
                $_SESSION['error'] = "Jenis paket turnamen harus dipilih";
                header("Location: dashboard.php?tab=kelola_lapangan");
                exit();
            }
        } else {
            $_SESSION['error'] = "Data tidak lengkap. Paket type: '$paket_type', Lapangan eskul: " . (isset($_POST['lapangan_id_eskul']) ? $_POST['lapangan_id_eskul'] : 'tidak ada') . ", Lapangan turnamen: " . (isset($_POST['lapangan_id_turnamen']) ? $_POST['lapangan_id_turnamen'] : 'tidak ada');
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }
        
        // Validasi harga
        if ($harga <= 0) {
            $_SESSION['error'] = "Harga harus lebih dari 0";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }
        
        // Insert data
        $query = "INSERT INTO harga_paket (tipe, harga, keterangan, gambar) 
                  VALUES ('$tipe', $harga, '$keterangan', '$gambar')";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Paket baru berhasil ditambahkan";
        } else {
            $_SESSION['error'] = "Gagal menambahkan paket: " . mysqli_error($conn);
        }
        
        // Redirect to dashboard with kelola_lapangan tab
        header("Location: dashboard.php?tab=kelola_lapangan");
        exit();
        break;

    case 'edit':
        // Validate required fields
        if (!isset($_POST['id_paket']) || !isset($_POST['harga']) || !isset($_POST['keterangan'])) {
            $_SESSION['error'] = "Data tidak lengkap";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }

        $id = (int)$_POST['id_paket'];
        $harga = (int)$_POST['harga'];
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        
        // Validasi harga
        if ($harga <= 0) {
            $_SESSION['error'] = "Harga harus lebih dari 0";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }
        
        // Generate tipe paket berdasarkan input form
        $new_tipe = '';
        $paket_type = isset($_POST['paket_type']) ? $_POST['paket_type'] : '';
        
        if ($paket_type === 'eskul' && isset($_POST['lapangan_id_eskul']) && !empty($_POST['lapangan_id_eskul'])) {
            // Paket eskul
            $lapangan_id = mysqli_real_escape_string($conn, $_POST['lapangan_id_eskul']);
            $new_tipe = "eskul_lap{$lapangan_id}";
        } elseif ($paket_type === 'turnamen' && isset($_POST['lapangan_id_turnamen']) && !empty($_POST['lapangan_id_turnamen'])) {
            // Paket turnamen
            $lapangan_id = mysqli_real_escape_string($conn, $_POST['lapangan_id_turnamen']);
            if (isset($_POST['jenis'])) {
                $jenis = mysqli_real_escape_string($conn, $_POST['jenis']);
                $new_tipe = "turnamen_lap{$lapangan_id}_{$jenis}";
            } else {
                $_SESSION['error'] = "Jenis paket turnamen harus dipilih";
                header("Location: dashboard.php?tab=kelola_lapangan");
                exit();
            }
        } else {
            $_SESSION['error'] = "Lapangan harus dipilih";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }
        
        // Handle image upload
        $update_fields = [
            "tipe = '$new_tipe'",
            "harga = $harga",
            "keterangan = '$keterangan'",
            "updated_at = CURRENT_TIMESTAMP"
        ];
        
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['gambar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Format file tidak diizinkan. Gunakan JPG atau PNG.";
                header("Location: dashboard.php?tab=kelola_lapangan");
                exit();
            }

            $new_filename = uniqid() . '.' . $ext;
            $upload_path = '../uploads/paket/';
            
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path . $new_filename)) {
                // Delete old image
                $query = "SELECT gambar FROM harga_paket WHERE id_paket = $id";
                $result = mysqli_query($conn, $query);
                $old_data = mysqli_fetch_assoc($result);
                if ($old_data['gambar'] && file_exists($upload_path . $old_data['gambar'])) {
                    unlink($upload_path . $old_data['gambar']);
                }
                
                $update_fields[] = "gambar = '$new_filename'";
            }
        }
        
        $query = "UPDATE harga_paket 
                  SET " . implode(", ", $update_fields) . "
                  WHERE id_paket = $id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Harga paket berhasil diperbarui";
            
            // Update harga di booking yang belum dibayar
            if (strpos($new_tipe, 'eskul') !== false) {
                $update_booking = "UPDATE booking_eskul 
                                 SET total_harga = $harga 
                                 WHERE status_pembayaran = 'belum_dibayar' 
                                 AND paket = '$new_tipe'";
                mysqli_query($conn, $update_booking);
            } elseif (strpos($new_tipe, 'turnamen') !== false) {
                $update_booking = "UPDATE booking_turnamen 
                                 SET total_harga = $harga 
                                 WHERE status_pembayaran = 'belum_dibayar' 
                                 AND paket = '$new_tipe'";
                mysqli_query($conn, $update_booking);
            }
            
            // Redirect to dashboard with kelola_lapangan tab
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        } else {
            $_SESSION['error'] = "Gagal memperbarui harga paket: " . mysqli_error($conn);
        }
        break;

    case 'delete':
        // Validate ID parameter
        if (!isset($_GET['id'])) {
            $_SESSION['error'] = "ID paket tidak ditemukan";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }

        $id = (int)$_GET['id'];

        // Check if package exists
        $check_query = "SELECT tipe FROM harga_paket WHERE id_paket = $id";
        $result = mysqli_query($conn, $check_query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            $_SESSION['error'] = "Paket tidak ditemukan";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }

        $paket = mysqli_fetch_assoc($result);
        $tipe = $paket['tipe'];

        // Check for active bookings
        $booking_table = strpos($tipe, 'eskul') !== false ? 'booking_eskul' : 'booking_turnamen';
        $check_bookings = "SELECT COUNT(*) as count FROM $booking_table 
                          WHERE paket = '$tipe' 
                          AND status_pembayaran IN ('pending', 'belum_dibayar')";
        
        $booking_result = mysqli_query($conn, $check_bookings);
        $booking_count = mysqli_fetch_assoc($booking_result)['count'];

        if ($booking_count > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus paket karena masih ada booking aktif yang menggunakan paket ini";
            header("Location: dashboard.php?tab=kelola_lapangan");
            exit();
        }

        // Delete the package
        $delete_query = "DELETE FROM harga_paket WHERE id_paket = $id";
        
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = "Paket berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus paket: " . mysqli_error($conn);
        }
        
        // Redirect to dashboard with kelola_lapangan tab
        header("Location: dashboard.php?tab=kelola_lapangan");
        exit();
        break;
        
    default:
        // Debug: Log what action was received
        error_log("Invalid action received: '$action'");
        $_SESSION['error'] = "Aksi tidak valid: '$action'";
        break;
}

// Redirect back to dashboard with kelola_lapangan tab
header("Location: dashboard.php?tab=kelola_lapangan");
exit();
