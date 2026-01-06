<?php
session_start();
session_unset();
session_destroy();

// Setelah logout, kembalikan ke halaman depan
header("Location: index.php");
exit;
?>