<?php
session_start(); // Tetap dipertahankan agar Login jalan

// Konfigurasi Database
$dbname = "wastewise";
$username = "root";

// --- OPSI 1: Coba Konek ke DOCKER ---
// Host: 'db' (nama service di docker-compose)
// Pass: 'root' (password standar di docker-compose)
// Kita pakai tanda '@' supaya kalau gagal gak muncul error jelek
$conn = @mysqli_connect("db", $username, "root", $dbname);

// --- OPSI 2: Fallback ke XAMPP/LOCAL ---
// Jika koneksi Docker gagal (hasilnya false), kita coba cara lama
if (!$conn) {
    // Host: 'localhost'
    // Pass: '' (kosong, default XAMPP)
    $conn = mysqli_connect("localhost", $username, "", $dbname);
}

// --- Pengecekan Terakhir ---
if (!$conn) {
    die("Koneksi gagal (Baik Docker maupun Localhost tidak bisa): " . mysqli_connect_error());
}
?>