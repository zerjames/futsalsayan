<?php
require_once '../config/database.php';

// Read and execute SQL file
$sql = file_get_contents('../sql/add_reset_columns.sql');

if (mysqli_query($conn, $sql)) {
    echo "Reset password columns added successfully";
} else {
    echo "Error executing SQL: " . mysqli_error($conn);
}
?>
