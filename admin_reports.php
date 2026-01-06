<?php
// --- 1. SETUP & KONEKSI ---
require 'db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek sesi admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$full_name = $_SESSION['full_name'];

// --- 2. LOGIKA UPDATE STATUS & HAPUS (POST REQUEST) ---

// A. Handle Update Status
if (isset($_POST['action_status'])) {
    $report_id = intval($_POST['report_id']);
    $new_status = $_POST['new_status'];
    
    // Update status di database
    $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $report_id);
    $stmt->execute();
    
    // Redirect agar tidak resubmit saat refresh
    header("Location: admin_reports.php?msg=updated");
    exit;
}

// B. Handle Hapus Laporan
if (isset($_POST['delete_report'])) {
    $report_id = intval($_POST['report_id']);
    
    // Hapus file gambar jika perlu (opsional, disini kita hapus datanya saja)
    $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    
    header("Location: admin_reports.php?msg=deleted");
    exit;
}

// --- 3. FILTER & PENCARIAN (GET REQUEST) ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Base Query
$sql = "SELECT r.*, u.full_name FROM reports r JOIN users u ON r.user_id = u.id WHERE 1=1";

// Jika ada pencarian (Deskripsi, Lokasi, atau Nama Pelapor)
if ($search) {
    $sql .= " AND (r.description LIKE '%$search%' OR r.location LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

// Jika ada filter status
if ($filter_status && $filter_status != 'Semua') {
    $sql .= " AND r.status = '$filter_status'";
}

$sql .= " ORDER BY r.created_at DESC";
$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Laporan - WasteWise Admin</title>

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
    /* BASE STYLES (Sama dengan admin.php) */
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
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
    }

    body {
        background: var(--bg-body);
        display: flex;
        min-height: 100vh;
        color: var(--text-dark);
    }

    /* SIDEBAR */
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
        transition: all 0.2s;
    }

    .nav-link:hover,
    .nav-link.active {
        background: var(--primary);
        color: var(--white);
    }

    /* MAIN CONTENT */
    .main-content {
        margin-left: 260px;
        flex-grow: 1;
        padding: 2rem;
        width: calc(100% - 260px);
    }

    /* FILTER BAR */
    .filter-bar {
        background: var(--white);
        padding: 1rem;
        border-radius: 12px;
        border: 1px solid var(--border);
        margin-bottom: 1.5rem;
        display: flex;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    .search-box {
        display: flex;
        align-items: center;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px 12px;
        width: 300px;
    }

    .search-box input {
        border: none;
        background: transparent;
        outline: none;
        margin-left: 8px;
        width: 100%;
        color: var(--text-dark);
    }

    .filter-select {
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        outline: none;
        background: var(--white);
        cursor: pointer;
    }

    /* TABLE STYLES */
    .table-container {
        background: var(--white);
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        font-size: 0.95rem;
    }

    th {
        background: #f8fafc;
        font-weight: 600;
        color: var(--text-gray);
    }

    tr:last-child td {
        border-bottom: none;
    }

    /* BADGES */
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-menunggu {
        background: #fef9c3;
        color: #ca8a04;
    }

    .status-diproses {
        background: #e0f2fe;
        color: #0284c7;
    }

    .status-selesai {
        background: #dcfce7;
        color: #16a34a;
    }

    /* ACTION BUTTONS */
    .action-btn {
        border: none;
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-process {
        background: #e0f2fe;
        color: #0284c7;
        margin-right: 4px;
    }

    .btn-process:hover {
        background: #bae6fd;
    }

    .btn-done {
        background: #dcfce7;
        color: #16a34a;
        margin-right: 4px;
    }

    .btn-done:hover {
        background: #bbf7d0;
    }

    .btn-delete {
        background: #fee2e2;
        color: #ef4444;
    }

    .btn-delete:hover {
        background: #fecaca;
    }

    .btn-view {
        background: #f3f4f6;
        color: var(--text-dark);
        margin-right: 4px;
    }

    /* MODAL (Simple View) */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal-content {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        position: relative;
    }

    .close-modal {
        position: absolute;
        top: 1rem;
        right: 1rem;
        cursor: pointer;
        font-size: 1.5rem;
    }

    .detail-row {
        margin-bottom: 1rem;
    }

    .detail-label {
        font-size: 0.8rem;
        color: var(--text-gray);
        font-weight: 600;
    }

    @media (max-width: 1024px) {
        .sidebar {
            display: none;
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }

        .table-container {
            overflow-x: auto;
        }
    }
    </style>
</head>

<body>

    <aside class="sidebar">
        <a href="#" class="brand"><i class="ph-fill ph-recycle" style="color:var(--primary)"></i> WasteWise</a>
        <nav>
            <a href="admin.php" class="nav-link"><i class="ph-bold ph-squares-four"></i> Dashboard</a>
            <a href="admin_reports.php" class="nav-link active"><i class="ph-bold ph-file-text"></i> Kelola Laporan</a>
            <a href="#" class="nav-link"><i class="ph-bold ph-users"></i> Data Warga</a>
            <a href="logout.php" class="nav-link" style="color:var(--danger); margin-top: 20px;"><i
                    class="ph-bold ph-sign-out"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <h2 style="margin-bottom: 1.5rem; font-weight:700;">Kelola Laporan Masuk</h2>

        <form method="GET" class="filter-bar">
            <div class="search-box">
                <i class="ph-bold ph-magnifying-glass" style="color:var(--text-gray)"></i>
                <input type="text" name="search" placeholder="Cari lokasi, nama, atau deskripsi..."
                    value="<?= htmlspecialchars($search) ?>">
            </div>

            <div style="display:flex; gap:10px;">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="Semua">Semua Status</option>
                    <option value="Menunggu" <?= $filter_status == 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="Diproses" <?= $filter_status == 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                    <option value="Selesai" <?= $filter_status == 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                </select>
            </div>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="15%">Pelapor & Tanggal</th>
                        <th width="15%">Kategori</th>
                        <th width="25%">Lokasi & Deskripsi</th>
                        <th width="10%">Berat</th>
                        <th width="10%">Status</th>
                        <th width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                            <small
                                style="color:var(--text-gray)"><?= date('d M Y', strtotime($row['created_at'])) ?></small>
                        </td>
                        <td>
                            <?php 
                                        $icon = "ph-trash";
                                        if($row['category'] == 'Organik') $icon = "ph-leaf";
                                        elseif($row['category'] == 'Elektronik') $icon = "ph-desktop";
                                        elseif($row['category'] == 'B3') $icon = "ph-warning";
                                    ?>
                            <div style="display:flex; align-items:center; gap:5px;">
                                <i class="ph-fill <?= $icon ?>" style="color:var(--primary)"></i>
                                <?= htmlspecialchars($row['category']) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:500; margin-bottom:4px;">
                                <?= htmlspecialchars(substr($row['location'], 0, 25)) ?>...</div>
                            <small
                                style="color:var(--text-gray)"><?= htmlspecialchars(substr($row['description'], 0, 40)) ?>...</small>
                        </td>
                        <td><?= $row['weight'] ? $row['weight'] . ' Kg' : '-' ?></td>
                        <td>
                            <?php if($row['status'] == 'Menunggu'): ?>
                            <span class="badge status-menunggu"><i class="ph-fill ph-clock"></i> Menunggu</span>
                            <?php elseif($row['status'] == 'Diproses'): ?>
                            <span class="badge status-diproses"><i class="ph-fill ph-gear"></i> Diproses</span>
                            <?php else: ?>
                            <span class="badge status-selesai"><i class="ph-fill ph-check-circle"></i> Selesai</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:4px;">
                                <button class="action-btn btn-view"
                                    onclick="openModal(<?= htmlspecialchars(json_encode($row)) ?>)"
                                    title="Lihat Detail">
                                    <i class="ph-bold ph-eye"></i>
                                </button>

                                <?php if($row['status'] == 'Menunggu'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="report_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="new_status" value="Diproses">
                                    <button type="submit" name="action_status" class="action-btn btn-process"
                                        title="Proses Laporan">
                                        <i class="ph-bold ph-play"></i>
                                    </button>
                                </form>
                                <?php elseif($row['status'] == 'Diproses'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="report_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="new_status" value="Selesai">
                                    <button type="submit" name="action_status" class="action-btn btn-done"
                                        title="Selesaikan Laporan">
                                        <i class="ph-bold ph-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Yakin ingin menghapus laporan ini?');">
                                    <input type="hidden" name="report_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="delete_report" class="action-btn btn-delete"
                                        title="Hapus">
                                        <i class="ph-bold ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 2rem; color:var(--text-gray);">
                            Tidak ada laporan ditemukan.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3 style="margin-bottom:1.5rem;">Detail Laporan</h3>

            <div id="modalBody"></div>

            <div
                style="width:100%; height:150px; background:#f1f5f9; border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-gray); margin-top:1rem;">
                <i class="ph-fill ph-image" style="font-size:2rem; margin-right:10px;"></i> Foto Bukti (Disini)
            </div>

            <div style="margin-top:1.5rem; text-align:right;">
                <a id="mapsLink" href="#" target="_blank"
                    style="text-decoration:none; color:var(--primary); font-weight:600;">
                    Buka di Google Maps <i class="ph-bold ph-arrow-square-out"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
    // FUNGSI MODAL POPUP
    const modal = document.getElementById('detailModal');
    const modalBody = document.getElementById('modalBody');
    const mapsLink = document.getElementById('mapsLink');

    function openModal(data) {
        let html = `
                <div class="detail-row"><div class="detail-label">Pelapor</div><div>${data.full_name}</div></div>
                <div class="detail-row"><div class="detail-label">Kategori</div><div>${data.category}</div></div>
                <div class="detail-row"><div class="detail-label">Lokasi</div><div>${data.location}</div></div>
                <div class="detail-row"><div class="detail-label">Deskripsi</div><div>${data.description}</div></div>
                <div class="detail-row"><div class="detail-label">Berat Estimasi</div><div>${data.weight} Kg</div></div>
                <div class="detail-row"><div class="detail-label">Status</div><div>${data.status}</div></div>
            `;

        modalBody.innerHTML = html;

        // Set Link Google Maps jika koordinat ada
        if (data.latitude && data.longitude) {
            mapsLink.href = `https://www.google.com/maps?q=${data.latitude},${data.longitude}`;
            mapsLink.style.display = 'inline-block';
        } else {
            mapsLink.style.display = 'none';
        }

        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    // Close modal jika klik di luar box
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>

</body>

</html>