<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die('Unauthorized');
}

echo "<h2>Debug Form Submission</h2>";
echo "<p>Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>FILES Data:</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    $action = $_POST['action'] ?? 'NO_ACTION';
    echo "<p>Action: '$action'</p>";
    
    $paket_type = $_POST['paket_type'] ?? 'NO_TYPE';
    echo "<p>Paket Type: '$paket_type'</p>";
    
    if (isset($_POST['lapangan_id_eskul'])) {
        echo "<p>Lapangan Eskul: " . $_POST['lapangan_id_eskul'] . "</p>";
    }
    
    if (isset($_POST['lapangan_id_turnamen'])) {
        echo "<p>Lapangan Turnamen: " . $_POST['lapangan_id_turnamen'] . "</p>";
    }
    
    if (isset($_POST['jenis'])) {
        echo "<p>Jenis: " . $_POST['jenis'] . "</p>";
    }
} else {
    echo "<h3>GET Data:</h3>";
    echo "<pre>";
    print_r($_GET);
    echo "</pre>";
}
?>

<form method="POST" action="test_paket_form.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="paket_type" value="eskul">
    
    <div>
        <label>Lapangan Eskul:</label>
        <select name="lapangan_id_eskul">
            <option value="1">Lapangan 1</option>
            <option value="2">Lapangan 2</option>
        </select>
    </div>
    
    <div>
        <label>Harga:</label>
        <input type="number" name="harga" value="100000">
    </div>
    
    <div>
        <label>Keterangan:</label>
        <textarea name="keterangan">Test paket</textarea>
    </div>
    
    <button type="submit">Test Submit</button>
</form>

<form method="POST" action="test_paket_form.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="paket_type" value="turnamen">
    
    <div>
        <label>Lapangan Turnamen:</label>
        <select name="lapangan_id_turnamen">
            <option value="1">Lapangan 1</option>
            <option value="2">Lapangan 2</option>
        </select>
    </div>
    
    <div>
        <label>Jenis:</label>
        <select name="jenis">
            <option value="sore">Sore</option>
            <option value="malam">Malam</option>
        </select>
    </div>
    
    <div>
        <label>Harga:</label>
        <input type="number" name="harga" value="500000">
    </div>
    
    <div>
        <label>Keterangan:</label>
        <textarea name="keterangan">Test turnamen</textarea>
    </div>
    
    <button type="submit">Test Submit Turnamen</button>
</form>
