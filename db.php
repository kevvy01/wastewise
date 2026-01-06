<?php
session_start();

// Cek apakah ada variabel 'MYSQLHOST' (Tanda-tanda kita lagi di Railway)
if (getenv('MYSQLHOST')) {
    // --- SETTINGAN RAILWAY (ONLINE) ---
    $host = getenv('MYSQLHOST');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');
    $db   = getenv('MYSQLDATABASE');
    $port = getenv('MYSQLPORT');

    $conn = mysqli_connect($host, $user, $pass, $db, $port);
} else {
    // --- SETTINGAN LOKAL (DOCKER / XAMPP) ---
    // Coba Docker dulu ('db')
    $conn = @mysqli_connect("db", "root", "root", "wastewise");
    
    // Kalau gagal, coba XAMPP ('localhost')
    if (!$conn) {
        $conn = mysqli_connect("localhost", "root", "", "wastewise");
    }
}

// Cek error
if (!$conn) {
    die("Koneksi Gagal: " . mysqli_connect_error());
}
?>