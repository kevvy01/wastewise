<?php
// --- 1. SETUP & SESSION FIX ---
require 'db.php';

// Cek session aman
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek Login
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'warga') {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// --- 2. LOGIKA BACKEND ---

// Hitung Statistik (Real-time)
$stats = ['total' => 0, 'pending' => 0, 'process' => 0, 'done' => 0];
$count_query = mysqli_query($conn, "SELECT status, COUNT(*) as jumlah FROM reports WHERE user_id = '$uid' GROUP BY status");
while($row = mysqli_fetch_assoc($count_query)) {
    if($row['status'] == 'Pending') $stats['pending'] = $row['jumlah'];
    elseif($row['status'] == 'Diproses') $stats['process'] = $row['jumlah'];
    elseif($row['status'] == 'Selesai') $stats['done'] = $row['jumlah'];
}
$stats['total'] = $stats['pending'] + $stats['process'] + $stats['done'];

// Proses Kirim Laporan (Form Baru)
if (isset($_POST['submit_laporan'])) {
    $location = mysqli_real_escape_string($conn, $_POST['location_text']);
    $lat = mysqli_real_escape_string($conn, $_POST['latitude']);
    $lng = mysqli_real_escape_string($conn, $_POST['longitude']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    // Tangkap data berat
    $weight = mysqli_real_escape_string($conn, $_POST['weight']); 
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Upload Foto
    $image_name = null;
    if (!empty($_FILES['foto']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir);
        $image_name = time() . '_' . basename($_FILES['foto']['name']);
        move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $image_name);
    }

    // Insert ke database (termasuk kolom weight)
    $query = "INSERT INTO reports (user_id, category, weight, location, latitude, longitude, description, image) 
              VALUES ('$uid', '$category', '$weight', '$location', '$lat', '$lng', '$description', '$image_name')";
    
    if(mysqli_query($conn, $query)){
        echo "<script>alert('Laporan berhasil dikirim!'); window.location='warga.php?page=riwayat';</script>";
    } else {
        echo "<script>alert('Gagal: " . mysqli_error($conn) . "');</script>";
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Warga - WasteWise</title>

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
    /* --- CSS GLOBAL --- */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }

    :root {
        --primary: #16a34a;
        --primary-light: #dcfce7;
        --text-dark: #1e293b;
        --text-gray: #64748b;
        --bg-body: #f8fafc;
        --white: #ffffff;
        --border: #e2e8f0;
    }

    body {
        background: var(--bg-body);
        display: flex;
        min-height: 100vh;
        color: var(--text-dark);
        overflow-x: hidden;
    }

    /* --- SIDEBAR --- */
    .sidebar {
        width: 260px;
        background: var(--white);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        padding: 1.5rem;
        position: fixed;
        height: 100vh;
        z-index: 1000;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 2rem;
        text-decoration: none;
        color: var(--text-dark);
    }

    .brand i {
        font-size: 1.8rem;
        color: var(--primary);
    }

    .brand span {
        font-size: 1.2rem;
        font-weight: 700;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 1rem;
        background: #f1f5f9;
        border-radius: 12px;
        margin-bottom: 2rem;
    }

    .avatar {
        width: 40px;
        height: 40px;
        background: var(--primary-light);
        color: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .nav-menu {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        flex-grow: 1;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        color: var(--text-gray);
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        transition: 0.2s;
    }

    .nav-link:hover,
    .nav-link.active {
        background: var(--primary);
        color: var(--white);
    }

    .logout-link {
        color: #ef4444;
        margin-top: auto;
    }

    .logout-link:hover {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* --- MAIN CONTENT --- */
    .main-content {
        margin-left: 260px;
        flex-grow: 1;
        padding: 2rem;
        width: calc(100% - 260px);
    }

    .top-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .search-box {
        background: var(--white);
        padding: 0.6rem 1rem;
        border-radius: 8px;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
        width: 300px;
    }

    .search-box input {
        border: none;
        outline: none;
        width: 100%;
    }

    /* --- DASHBOARD STYLES --- */
    .welcome-banner {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
        padding: 2.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
    }

    .btn-banner {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        margin-top: 1rem;
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        font-weight: 500;
    }

    .btn-banner:hover {
        background: white;
        color: var(--primary);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--border);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .stat-info p {
        color: var(--text-gray);
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .stat-info h3 {
        font-size: 1.8rem;
        font-weight: 700;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .icon-total {
        background: #dcfce7;
        color: #16a34a;
    }

    .icon-wait {
        background: #fef9c3;
        color: #ca8a04;
    }

    .icon-process {
        background: #e0f2fe;
        color: #0284c7;
    }

    .icon-done {
        background: #d1fae5;
        color: #059669;
    }

    .table-container {
        background: var(--white);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--border);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0.5rem;
    }

    th,
    td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }

    th {
        color: var(--text-gray);
        font-size: 0.85rem;
        text-transform: uppercase;
    }

    .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .bg-pending {
        background: #fef9c3;
        color: #a16207;
    }

    .bg-process {
        background: #e0f2fe;
        color: #0369a1;
    }

    .bg-done {
        background: #dcfce7;
        color: #15803d;
    }

    /* --- FORM STYLES --- */
    .report-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
    }

    .card-form {
        background: var(--white);
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .card-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
    }

    .card-body {
        padding: 1.5rem;
    }

    #map {
        height: 300px;
        width: 100%;
        border-radius: 8px;
        margin-bottom: 1rem;
        z-index: 1;
        border: 1px solid #ddd;
    }

    .form-control {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        outline: none;
        margin-bottom: 1rem;
    }

    .form-control:focus {
        border-color: var(--primary);
    }

    .upload-area {
        border: 2px dashed var(--border);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        position: relative;
        background: #fcfcfc;
    }

    .upload-area:hover {
        border-color: var(--primary);
        background: #f0fdf4;
    }

    .btn-submit {
        background: var(--primary);
        color: white;
        border: none;
        padding: 1rem;
        width: 100%;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 1rem;
    }

    .btn-gps {
        padding: 6px 12px;
        border: 1px solid var(--primary);
        color: var(--primary);
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
    }

    @media (max-width: 900px) {

        .stats-grid,
        .report-grid {
            grid-template-columns: 1fr;
        }

        .sidebar {
            display: none;
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }
    }
    </style>
</head>

<body>

    <aside class="sidebar">
        <a href="#" class="brand">
            <i class="ph-fill ph-recycle"></i>
            <span>WasteWise</span>
        </a>
        <div class="user-profile">
            <div class="avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <div>
                <h4 style="font-size:0.9rem;"><?= htmlspecialchars($full_name) ?></h4>
                <p style="font-size:0.8rem; color:var(--text-gray);">Warga</p>
            </div>
        </div>
        <ul class="nav-menu">
            <li><a href="warga.php" class="nav-link <?= ($page=='dashboard')?'active':'' ?>"><i
                        class="ph-bold ph-squares-four"></i> Dashboard</a></li>
            <li><a href="?page=buat" class="nav-link <?= ($page=='buat')?'active':'' ?>"><i
                        class="ph-bold ph-file-plus"></i> Buat Laporan</a></li>
            <li><a href="?page=riwayat" class="nav-link <?= ($page=='riwayat')?'active':'' ?>"><i
                        class="ph-bold ph-clock-counter-clockwise"></i> Riwayat Laporan</a></li>
            <li class="logout-link"><a href="logout.php" class="nav-link" style="color:inherit;"><i
                        class="ph-bold ph-sign-out"></i> Keluar</a></li>
        </ul>
    </aside>

    <main class="main-content">

        <header class="top-header">
            <h2 class="page-title">
                <?php 
                    if($page=='dashboard') echo 'Dashboard Warga';
                    elseif($page=='buat') echo 'Buat Laporan Baru';
                    elseif($page=='riwayat') echo 'Riwayat Laporan';
                ?>
            </h2>
            <div class="search-box">
                <i class="ph-bold ph-magnifying-glass" style="color:#94a3b8;"></i>
                <input type="text" placeholder="Cari...">
            </div>
        </header>

        <?php if ($page == 'buat'): ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="report-grid">
                <div class="left-col">
                    <div class="card-form">
                        <div class="card-header">
                            <h3><i class="ph-bold ph-map-pin" style="color:var(--primary);"></i> Lokasi Sampah</h3>
                            <button type="button" class="btn-gps" onclick="getLocation()"><i
                                    class="ph-bold ph-crosshair"></i> Gunakan GPS</button>
                        </div>
                        <div class="card-body">
                            <div id="map"></div>
                            <input type="hidden" name="latitude" id="lat">
                            <input type="hidden" name="longitude" id="lng">

                            <label style="font-weight:600; font-size:0.9rem;">Alamat Terdeteksi</label>
                            <input type="text" name="location_text" id="location_text" class="form-control"
                                placeholder="Klik peta..." readonly required
                                style="background:#f9f9f9; margin-top:5px;">

                            <div style="display: flex; gap: 15px;">
                                <div style="flex: 1;">
                                    <label style="font-weight:600; font-size:0.9rem;">Jenis Sampah</label>
                                    <select name="category" class="form-control" style="margin-top:5px;">
                                        <option value="Organik">🌿 Organik</option>
                                        <option value="Anorganik">♻️ Anorganik</option>
                                        <option value="B3">☣️ B3</option>
                                        <option value="Lainnya">🗑️ Lainnya</option>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-weight:600; font-size:0.9rem;">Berat Perkiraan (Kg)</label>
                                    <input type="number" name="weight" class="form-control" placeholder="0.0" step="0.1"
                                        min="0" required style="margin-top:5px;">
                                </div>
                            </div>

                            <label style="font-weight:600; font-size:0.9rem;">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Kondisi sampah..."
                                required style="margin-top:5px;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="right-col">
                    <div class="card-form">
                        <div class="card-header">
                            <h3><i class="ph-bold ph-camera"></i> Foto Bukti</h3>
                        </div>
                        <div class="card-body">
                            <div class="upload-area">
                                <input type="file" name="foto" class="file-input" accept="image/*"
                                    onchange="previewImage(this)"
                                    style="position:absolute; width:100%; height:100%; top:0; left:0; opacity:0; cursor:pointer;">
                                <div id="upload-placeholder">
                                    <i class="ph-bold ph-image" style="font-size:2rem; color:#cbd5e1;"></i>
                                    <p style="font-size:0.85rem; color:var(--text-gray);">Klik untuk upload</p>
                                </div>
                                <img id="image-preview" style="width:100%; border-radius:8px; display:none;">
                            </div>
                            <button type="submit" name="submit_laporan" class="btn-submit">Kirim Laporan</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php elseif ($page == 'riwayat'): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Berat</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $query_all = mysqli_query($conn, "SELECT * FROM reports WHERE user_id = '$uid' ORDER BY created_at DESC");
                        while($r = mysqli_fetch_assoc($query_all)): 
                        ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td><?= isset($r['category']) ? $r['category'] : '-' ?></td>
                        <td><?= isset($r['weight']) ? $r['weight'] . ' Kg' : '-' ?></td>
                        <td><?= htmlspecialchars($r['location']) ?></td>
                        <td>
                            <?php 
                                    $s = $r['status'];
                                    $badge = 'bg-pending';
                                    if($s == 'Diproses') $badge = 'bg-process';
                                    if($s == 'Selesai') $badge = 'bg-done';
                                ?>
                            <span class="badge <?= $badge ?>"><?= $s ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Selamat Datang, <?= htmlspecialchars(explode(' ', $full_name)[0]) ?>! 👋</h2>
                <p>Laporkan sampah di sekitar Anda dan pantau status pengangkutannya secara real-time.</p>
                <a href="?page=buat" class="btn-banner"><i class="ph-bold ph-plus"></i> Buat Laporan Baru</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <p>Total Laporan</p>
                    <h3><?= $stats['total'] ?></h3>
                </div>
                <div class="stat-icon icon-total"><i class="ph-fill ph-file-text"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <p>Menunggu</p>
                    <h3><?= $stats['pending'] ?></h3>
                </div>
                <div class="stat-icon icon-wait"><i class="ph-fill ph-clock"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <p>Diproses</p>
                    <h3><?= $stats['process'] ?></h3>
                </div>
                <div class="stat-icon icon-process"><i class="ph-fill ph-truck"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <p>Selesai</p>
                    <h3><?= $stats['done'] ?></h3>
                </div>
                <div class="stat-icon icon-done"><i class="ph-fill ph-check-circle"></i></div>
            </div>
        </div>

        <div class="table-container">
            <h3 style="margin-bottom: 1rem; font-size: 1.1rem;">Laporan Terbaru</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $query_recent = mysqli_query($conn, "SELECT * FROM reports WHERE user_id = '$uid' ORDER BY created_at DESC LIMIT 5");
                        if(mysqli_num_rows($query_recent) > 0):
                            while($r = mysqli_fetch_assoc($query_recent)): 
                        ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td><?= htmlspecialchars($r['location']) ?></td>
                        <td>
                            <?php 
                                    $s = $r['status'];
                                    $badge = 'bg-pending';
                                    if($s == 'Diproses') $badge = 'bg-process';
                                    if($s == 'Selesai') $badge = 'bg-done';
                                ?>
                            <span class="badge <?= $badge ?>"><?= $s ?></span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; color:var(--text-gray);">Belum ada laporan.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview').src = e.target.result;
                document.getElementById('image-preview').style.display = 'block';
                document.getElementById('upload-placeholder').style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    if (document.getElementById('map')) {
        var map = L.map('map').setView([-6.2088, 106.8456], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        var marker;

        function updateMarker(lat, lng) {
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            map.panTo([lat, lng]);
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => document.getElementById('location_text').value = data.display_name || (lat + ", " + lng))
                .catch(() => document.getElementById('location_text').value = lat + ", " + lng);
        }
        map.on('click', function(e) {
            updateMarker(e.latlng.lat, e.latlng.lng);
        });

        function getLocation() {
            if (navigator.geolocation) navigator.geolocation.getCurrentPosition(pos => updateMarker(pos.coords.latitude,
                pos.coords.longitude));
            else alert("Geolocation tidak didukung.");
        }
    }
    </script>
</body>

</html>