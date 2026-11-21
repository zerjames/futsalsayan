<?php
require_once '../includes/security.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_request()) {
        $_SESSION['error'] = "Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.";
        header("Location: dashboard.php?tab=kelola_lapangan");
        exit();
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        try {
            if (!isset($_POST['nama'], $_POST['deskripsi'], $_POST['harga_per_jam'])) {
                throw new Exception("Data tidak lengkap");
            }

            $nama = mysqli_real_escape_string($conn, $_POST['nama']);
            $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
            $harga_per_jam = (int)$_POST['harga_per_jam'];
            $gambar = '';

            // Handle image upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['gambar']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    throw new Exception("Format file tidak diizinkan. Gunakan JPG atau PNG.");
                }

                $new_filename = uniqid() . '.' . $ext;
                $upload_path = '../uploads/lapangan/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path . $new_filename)) {
                    $gambar = $new_filename;
                }
            }

            $query = "INSERT INTO lapangan (nama, deskripsi, harga_per_jam, gambar, status) 
                      VALUES ('$nama', '$deskripsi', $harga_per_jam, '$gambar', 'tersedia')";
            
            if (mysqli_query($conn, $query)) {
                $_SESSION['success'] = "Lapangan berhasil ditambahkan";
            } else {
                throw new Exception(mysqli_error($conn));
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal menambahkan lapangan: " . $e->getMessage();
        }
        break;

    case 'edit':
        try {
            if (!isset($_POST['id_lapangan'], $_POST['nama'], $_POST['deskripsi'], $_POST['harga_per_jam'])) {
                throw new Exception("Data tidak lengkap");
            }

            $id = (int)$_POST['id_lapangan'];
            $nama = mysqli_real_escape_string($conn, $_POST['nama']);
            $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
            $harga_per_jam = (int)$_POST['harga_per_jam'];

            $update_fields = [
                "nama = '$nama'",
                "deskripsi = '$deskripsi'",
                "harga_per_jam = $harga_per_jam"
            ];

            // Handle new image upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['gambar']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    throw new Exception("Format file tidak diizinkan. Gunakan JPG atau PNG.");
                }

                $new_filename = uniqid() . '.' . $ext;
                $upload_path = '../uploads/lapangan/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path . $new_filename)) {
                    // Delete old image
                    $query = "SELECT gambar FROM lapangan WHERE id_lapangan = $id";
                    $result = mysqli_query($conn, $query);
                    $old_data = mysqli_fetch_assoc($result);
                    if ($old_data['gambar'] && file_exists($upload_path . $old_data['gambar'])) {
                        unlink($upload_path . $old_data['gambar']);
                    }
                    
                    $update_fields[] = "gambar = '$new_filename'";
                }
            }

            $query = "UPDATE lapangan SET " . implode(", ", $update_fields) . " WHERE id_lapangan = $id";
            
            if (mysqli_query($conn, $query)) {
                $_SESSION['success'] = "Lapangan berhasil diperbarui";
            } else {
                throw new Exception(mysqli_error($conn));
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal memperbarui lapangan: " . $e->getMessage();
        }
        break;

    case 'toggle_status':
        try {
            // Debug: Log incoming request
            error_log("Toggle Status Request - POST data: " . print_r($_POST, true));
            
            if (!isset($_POST['id_lapangan'], $_POST['new_status'])) {
                error_log("Toggle Status Error - Missing required fields");
                throw new Exception("Data tidak lengkap");
            }

            $id = (int)$_POST['id_lapangan'];
            $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
            
            error_log("Toggle Status - Processing for ID: $id, New Status: $new_status");

            if (!in_array($new_status, ['tersedia', 'maintenance'])) {
                error_log("Toggle Status Error - Invalid status value: $new_status");
                throw new Exception("Status tidak valid");
            }

            // Get current status
            $check_query = "SELECT status FROM lapangan WHERE id_lapangan = $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (!$check_result || mysqli_num_rows($check_result) === 0) {
                error_log("Toggle Status Error - Lapangan not found with ID: $id");
                throw new Exception("Lapangan tidak ditemukan");
            }
            
            $current_status = mysqli_fetch_assoc($check_result)['status'];
            error_log("Toggle Status - Current status: $current_status");

            // Execute status update
            $query = "UPDATE lapangan SET status = '$new_status' WHERE id_lapangan = $id";
            error_log("Executing query: $query");
            
            if (!mysqli_query($conn, $query)) {
                error_log("MySQL Error: " . mysqli_error($conn));
                throw new Exception("Gagal mengubah status: " . mysqli_error($conn));
            }

            // Verify the update
            $verify_query = "SELECT status FROM lapangan WHERE id_lapangan = $id";
            $verify_result = mysqli_query($conn, $verify_query);
            $updated_status = mysqli_fetch_assoc($verify_result)['status'];
            error_log("Status updated successfully. New status: $updated_status");
            
            $_SESSION['success'] = "Status lapangan berhasil diubah menjadi " . ($new_status == 'maintenance' ? 'maintenance' : 'tersedia');
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal mengubah status lapangan: " . $e->getMessage();
        }
        break;

    case 'delete':
        try {
            if (!isset($_POST['id_lapangan'])) {
                throw new Exception("ID lapangan tidak ditemukan");
            }

            $id = (int)$_POST['id_lapangan'];
            
            // Check if lapangan has any bookings
            $check_query = "SELECT COUNT(*) as total FROM booking WHERE lapangan_id = $id";
            $check_result = mysqli_query($conn, $check_query);
            $check_data = mysqli_fetch_assoc($check_result);
            
            if ($check_data['total'] > 0) {
                throw new Exception("Lapangan tidak dapat dihapus karena masih memiliki riwayat booking");
            }

            // Get current image
            $query = "SELECT gambar FROM lapangan WHERE id_lapangan = $id";
            $result = mysqli_query($conn, $query);
            $current = mysqli_fetch_assoc($result);
            
            // Delete the lapangan
            $query = "DELETE FROM lapangan WHERE id_lapangan = $id";
            
            if (mysqli_query($conn, $query)) {
                // Delete image file if exists
                if ($current['gambar']) {
                    $image_path = '../uploads/lapangan/' . $current['gambar'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $_SESSION['success'] = "Lapangan berhasil dihapus";
            } else {
                throw new Exception(mysqli_error($conn));
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal menghapus lapangan: " . $e->getMessage();
        }
        break;

    default:
        $_SESSION['error'] = "Aksi tidak valid";
        break;
}

header("Location: dashboard.php?tab=kelola_lapangan");
exit();
?>
