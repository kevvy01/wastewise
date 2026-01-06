<?php
// --- 1. SETUP & KONEKSI ---
require 'db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$full_name = $_SESSION['full_name'];

// --- 2. DATA REAL DARI DATABASE ---

// A. KARTU STATISTIK UTAMA
// ------------------------------------------
// 1. Total Laporan
$res_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM reports");
$total_laporan = mysqli_fetch_assoc($res_total)['total'];

// 2. Laporan Hari Ini
$today = date('Y-m-d');
$res_today = mysqli_query($conn, "SELECT COUNT(*) as total FROM reports WHERE DATE(created_at) = '$today'");
$laporan_hari_ini = mysqli_fetch_assoc($res_today)['total'];

// 3. Status (Menunggu, Proses, Selesai)
$stats = ['Menunggu' => 0, 'Selesai' => 0, 'Diproses' => 0];
$res_status = mysqli_query($conn, "SELECT status, COUNT(*) as jumlah FROM reports GROUP BY status");
while($row = mysqli_fetch_assoc($res_status)){
    $stats[$row['status']] = $row['jumlah'];
}

// 4. Total Volume (KG) - Mengambil dari kolom 'weight'
$res_vol = mysqli_query($conn, "SELECT SUM(weight) as total_berat FROM reports");
$row_vol = mysqli_fetch_assoc($res_vol);
$total_volume = $row_vol['total_berat'] ? $row_vol['total_berat'] : 0;


// B. PERSIAPAN DATA UNTUK GRAFIK (CHART.JS)
// ------------------------------------------

// 1. Grafik Batang: Laporan per Bulan (Tahun Ini)
$current_year = date('Y');
$data_bulan_total = [];
$data_bulan_selesai = [];
$labels_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// Inisialisasi array dengan 0
for($i=1; $i<=12; $i++) {
    $data_bulan_total[$i] = 0;
    $data_bulan_selesai[$i] = 0;
}

// Query Total per Bulan
$q_bar = mysqli_query($conn, "SELECT MONTH(created_at) as bulan, COUNT(*) as jumlah 
                              FROM reports WHERE YEAR(created_at) = '$current_year' 
                              GROUP BY MONTH(created_at)");
while($r = mysqli_fetch_assoc($q_bar)) {
    $data_bulan_total[$r['bulan']] = $r['jumlah'];
}

// Query 'Selesai' per Bulan
$q_bar_done = mysqli_query($conn, "SELECT MONTH(created_at) as bulan, COUNT(*) as jumlah 
                                   FROM reports WHERE YEAR(created_at) = '$current_year' AND status = 'Selesai' 
                                   GROUP BY MONTH(created_at)");
while($r = mysqli_fetch_assoc($q_bar_done)) {
    $data_bulan_selesai[$r['bulan']] = $r['jumlah'];
}

// 2. Grafik Donat: Kategori Sampah
$label_kategori = [];
$data_kategori = [];
$q_pie = mysqli_query($conn, "SELECT category, COUNT(*) as jumlah FROM reports GROUP BY category");
while($r = mysqli_fetch_assoc($q_pie)) {
    $label_kategori[] = $r['category'];
    $data_kategori[] = $r['jumlah'];
}

// 3. Grafik Garis: Volume 7 Hari Terakhir
$label_harian = [];
$data_harian = [];
for ($i = 6; $i >= 0; $i--) {
    $date_chk = date('Y-m-d', strtotime("-$i days"));
    $label_harian[] = date('d M', strtotime($date_chk)); // Label tgl
    
    // Ambil sum weight
    $q_vol_day = mysqli_query($conn, "SELECT SUM(weight) as berat FROM reports WHERE DATE(created_at) = '$date_chk'");
    $d = mysqli_fetch_assoc($q_vol_day);
    $data_harian[] = $d['berat'] ? $d['berat'] : 0;
}

// C. DATA PETA (MAP)
// ------------------------------------------
$map_markers = [];
$q_map = mysqli_query($conn, "SELECT id, location, latitude, longitude, status, category FROM reports WHERE latitude IS NOT NULL AND latitude != ''");
while($r = mysqli_fetch_assoc($q_map)) {
    $map_markers[] = $r;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Realtime</title>

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
    /* STYLE SAMA SEPERTI SEBELUMNYA (TIDAK BERUBAH) */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }

    :root {
        --primary: #16a34a;
        --bg-body: #f1f5f9;
        --white: #ffffff;
        --border: #e2e8f0;
        --text-gray: #64748b;
        --text-dark: #0f172a;
    }

    body {
        background: var(--bg-body);
        display: flex;
        min-height: 100vh;
        color: var(--text-dark);
        overflow-x: hidden;
    }

    .sidebar {
        width: 260px;
        background: var(--white);
        border-right: 1px solid var(--border);
        position: fixed;
        height: 100vh;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        z-index: 1000;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 2rem;
        text-decoration: none;
        color: var(--text-dark);
        font-weight: 700;
        font-size: 1.25rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: var(--text-gray);
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        margin-bottom: 5px;
    }

    .nav-link:hover,
    .nav-link.active {
        background: var(--primary);
        color: var(--white);
    }

    .main-content {
        margin-left: 260px;
        flex-grow: 1;
        padding: 2rem;
        width: calc(100% - 260px);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--border);
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 10px 0;
    }

    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--border);
    }

    .bottom-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    #map {
        height: 300px;
        width: 100%;
        border-radius: 8px;
    }

    /* Tabel Terbaru */
    .recent-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    .recent-table th,
    .recent-table td {
        text-align: left;
        padding: 12px;
        border-bottom: 1px solid var(--border);
        font-size: 0.9rem;
    }

    .recent-table th {
        color: var(--text-gray);
        font-weight: 600;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .bg-green {
        background: #dcfce7;
        color: #16a34a;
    }

    .bg-yellow {
        background: #fef9c3;
        color: #ca8a04;
    }

    .bg-blue {
        background: #e0f2fe;
        color: #0284c7;
    }

    @media (max-width: 1024px) {

        .charts-grid,
        .bottom-grid {
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
        <a href="#" class="brand"><i class="ph-fill ph-recycle" style="color:var(--primary)"></i> WasteWise</a>
        <div style="margin-bottom: 2rem; padding:10px; background:#f8fafc; border-radius:8px;">
            <small style="color:var(--text-gray)">Login sebagai:</small><br>
            <strong><?= htmlspecialchars($full_name) ?></strong>
        </div>
        <nav>
            <a href="admin.php" class="nav-link active">
                <i class="ph-bold ph-squares-four"></i> Dashboard
            </a>

            <a href="admin_reports.php" class="nav-link">
                <i class="ph-bold ph-file-text"></i> Kelola Laporan
            </a>

            <a href="#" class="nav-link">
                <i class="ph-bold ph-users"></i> Data Warga
            </a>

            <a href="logout.php" class="nav-link" style="color:#ef4444; margin-top: 20px;">
                <i class="ph-bold ph-sign-out"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <h2 style="margin-bottom: 1.5rem; font-weight:700;">Dashboard Admin</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <span style="color:var(--text-gray)">Total Laporan</span>
                <div class="stat-value"><?= $total_laporan ?></div>
                <div style="font-size:0.8rem; color:#16a34a;"><i class="ph-bold ph-trend-up"></i> Data Realtime</div>
            </div>
            <div class="stat-card">
                <span style="color:var(--text-gray)">Menunggu</span>
                <div class="stat-value"><?= $stats['Menunggu'] ?></div>
                <div style="font-size:0.8rem; color:#ca8a04;">Perlu Tindakan</div>
            </div>
            <div class="stat-card">
                <span style="color:var(--text-gray)">Selesai</span>
                <div class="stat-value"><?= $stats['Selesai'] ?></div>
                <div style="font-size:0.8rem; color:#16a34a;">Terangani</div>
            </div>
            <div class="stat-card">
                <span style="color:var(--text-gray)">Volume Total</span>
                <div class="stat-value"><?= number_format($total_volume, 1) ?> <span
                        style="font-size:1rem; font-weight:400; color:var(--text-gray)">Kg</span></div>
                <div style="font-size:0.8rem; color:var(--text-gray);">Akumulasi Berat</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>Statistik Bulanan (<?= $current_year ?>)</h3>
                <div style="height: 250px;"><canvas id="barChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>Jenis Sampah</h3>
                <div style="height: 250px; display:flex; justify-content:center;"><canvas id="doughnutChart"></canvas>
                </div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="chart-card">
                <h3>Peta Sebaran (Realtime)</h3>
                <div id="map"></div>
            </div>
            <div class="chart-card">
                <h3>Volume Masuk (7 Hari Terakhir)</h3>
                <div style="height: 300px;"><canvas id="lineChart"></canvas></div>
            </div>
        </div>

        <div class="chart-card">
            <h3>5 Laporan Terakhir Masuk</h3>
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pelapor</th>
                        <th>Kategori</th>
                        <th>Berat (Kg)</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Join table reports & users untuk dapat nama pelapor
                    $q_table = mysqli_query($conn, "SELECT r.*, u.full_name 
                                                    FROM reports r 
                                                    JOIN users u ON r.user_id = u.id 
                                                    ORDER BY r.created_at DESC LIMIT 5");
                    while($row = mysqli_fetch_assoc($q_table)):
                        $badge = 'bg-yellow';
                        if($row['status'] == 'Selesai') $badge = 'bg-green';
                        if($row['status'] == 'Diproses') $badge = 'bg-blue';
                    ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= $row['weight'] ?> Kg</td>
                        <td><?= htmlspecialchars(substr($row['location'], 0, 30)) ?>...</td>
                        <td><span class="badge <?= $badge ?>"><?= $row['status'] ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // --- 1. DATA DARI PHP KE JS ---
    const dataBulanTotal = <?= json_encode(array_values($data_bulan_total)) ?>;
    const dataBulanSelesai = <?= json_encode(array_values($data_bulan_selesai)) ?>;

    const labelKategori = <?= json_encode($label_kategori) ?>;
    const dataKategori = <?= json_encode($data_kategori) ?>;

    const labelHarian = <?= json_encode($label_harian) ?>;
    const dataHarian = <?= json_encode($data_harian) ?>;

    const mapMarkers = <?= json_encode($map_markers) ?>;

    // --- 2. KONFIGURASI GRAFIK ---

    // Bar Chart
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                    label: 'Total Masuk',
                    data: dataBulanTotal,
                    backgroundColor: '#16a34a'
                },
                {
                    label: 'Selesai',
                    data: dataBulanSelesai,
                    backgroundColor: '#86efac'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Doughnut Chart
    new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: {
            labels: labelKategori,
            datasets: [{
                data: dataKategori,
                backgroundColor: ['#22c55e', '#3b82f6', '#ef4444', '#f59e0b', '#a855f7'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });

    // Line Chart
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: labelHarian,
            datasets: [{
                label: 'Volume (Kg)',
                data: dataHarian,
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // --- 3. KONFIGURASI PETA (LEAFLET) ---
    var map = L.map('map').setView([-6.2088, 106.8456], 5); // Default Zoom Jauh
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    // Loop data marker dari Database
    if (mapMarkers.length > 0) {
        var bounds = [];
        mapMarkers.forEach(function(m) {
            var lat = parseFloat(m.latitude);
            var lng = parseFloat(m.longitude);

            // Tentukan warna marker (opsional, disini default biru)
            var popupContent = `<b>${m.category}</b><br>Status: ${m.status}<br>${m.location}`;

            L.marker([lat, lng]).addTo(map).bindPopup(popupContent);
            bounds.push([lat, lng]);
        });
        // Auto zoom agar semua marker terlihat
        if (bounds.length > 0) map.fitBounds(bounds);
    }
    </script>
</body>

</html>