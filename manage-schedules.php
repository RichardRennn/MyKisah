<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM schedules WHERE schedule_id = ?";
    $stmt = $db->prepare($delete_sql);
    $stmt->bind_param("i", $schedule_id);
    if ($stmt->execute()) {
        $success = "Jadwal berhasil dihapus!";
        header("Location: manage-schedules.php?success=" . urlencode($success));
        exit;
    } else {
        $error = "Gagal menghapus jadwal!";
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    
    $movie_id = (int)$_POST['movie_id'];
    $hall_id = (int)$_POST['hall_id'];
    $show_date = $_POST['show_date'];
    $show_time = $_POST['show_time'];
    $price = (float)$_POST['price'];
    $status = $_POST['status'];
    
    // Get total seats from hall
    $hall_sql = "SELECT total_seats FROM halls WHERE hall_id = ?";
    $hall_stmt = $db->prepare($hall_sql);
    $hall_stmt->bind_param("i", $hall_id);
    $hall_stmt->execute();
    $hall_result = $hall_stmt->get_result();
    $total_seats = $hall_result->fetch_assoc()['total_seats'];
    
    if ($schedule_id > 0) {
        // Update
        $sql = "UPDATE schedules SET movie_id=?, hall_id=?, show_date=?, show_time=?, 
                price=?, status=? WHERE schedule_id=?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iissdsi", $movie_id, $hall_id, $show_date, $show_time, 
                         $price, $status, $schedule_id);
        if ($stmt->execute()) {
            $success = "Jadwal berhasil diupdate!";
            header("Location: manage-schedules.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal mengupdate jadwal!";
        }
    } else {
        // Insert
        $sql = "INSERT INTO schedules (movie_id, hall_id, show_date, show_time, price, available_seats, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iissdis", $movie_id, $hall_id, $show_date, $show_time, 
                         $price, $total_seats, $status);
        if ($stmt->execute()) {
            $success = "Jadwal berhasil ditambahkan!";
            header("Location: manage-schedules.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menambahkan jadwal!";
        }
    }
}

// Fetch all schedules (No Filters)
$schedules_sql = "SELECT s.*, m.title as movie_title, h.hall_name, t.name as theater_name, t.theater_id
                  FROM schedules s
                  INNER JOIN movies m ON s.movie_id = m.movie_id
                  INNER JOIN halls h ON s.hall_id = h.hall_id
                  INNER JOIN theaters t ON h.theater_id = t.theater_id
                  ORDER BY s.show_date DESC, s.show_time DESC";
$schedules = $db->query($schedules_sql);

// Fetch movies for dropdown
$movies_sql = "SELECT movie_id, title FROM movies ORDER BY title";
$movies = $db->query($movies_sql);

// Fetch theaters for dropdown
$theaters_sql = "SELECT theater_id, name FROM theaters ORDER BY name";
$theaters = $db->query($theaters_sql);

if (isset($_GET['success']) && !$success) {
    $success = $_GET['success'];
}
if (isset($_GET['error']) && !$error) {
    $error = $_GET['error'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MyKisah - Kelola Jadwal</title>

  <!-- Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    /* 1. STYLE ASLI (Navigasi & Layout Dasar) */
    :root{
      --accent:#CEF3F8;
      --card-bg:#ffffff;
      --muted:#6b7280;
      --dark:#0f172a;
      --green:#29bf51;
      --danger:#e02424;
      --shadow: 0 7px 15px rgba(0,0,0,0.12);
      --topbar-height:56px;
    }

    /* Box Sizing Penting */
    *, *::before, *::after { box-sizing: border-box; }

    html,body{ 
      height:100%; 
      margin:0; 
      font-family:'Poppins',sans-serif;
      background: linear-gradient(135deg, #FFFFFF 0%, #CEF3F8 100%);
      color:var(--dark);
    }

    /* Topbar Asli */
    .topbar {
      position: sticky;
      top: 0;
      height: var(--topbar-height);
      background: #cef3f8;
      z-index: 9999;
      display:flex;
      align-items:center;
      gap:16px;
      padding:8px 20px;
      box-shadow: 0 2px 6px rgba(16,24,40,0.06);
    }
    .hamburger { 
      width:40px; 
      height:40px; 
      display:flex; 
      align-items:center; 
      justify-content:center; 
      cursor:pointer; 
      border-radius:8px; 
      color: var(--dark);
    }
    .brand { 
      display:flex; 
      align-items:center; 
      gap:12px; 
      font-weight:600; 
      font-size:18px;
      color: var(--dark);
    }
    .top-actions { margin-left:auto; display:flex; align-items:center; gap:8px; }
    .logout-btn {
        background: transparent; border: 0; cursor: pointer; padding: 8px;
        border-radius: 8px; display: flex; align-items: center; justify-content: center;
    }

    /* Sidebar Asli */
    .layout { display:flex; min-height: calc(100vh - var(--topbar-height)); }

    .sidebar {
      width:80px; transition: width .22s ease; display:flex; flex-direction:column;
      align-items:center; padding-top:12px; border-right: 1px solid rgba(0,0,0,0.06);
      position: relative; z-index: 10; background: transparent;
    }
    .sidebar.open { width:240px; align-items: center; padding-left: 0; padding-right: 0; }

    .menu { margin-top: 12px; width:100%; }

    .menu-item { 
      display:flex; align-items:center; gap:14px; padding:12px; color:var(--dark); 
      text-decoration:none; border-radius:8px; margin:6px; cursor:pointer; 
      transition:background .15s; font-weight: 400;
    }
    .menu-item:hover { background: rgba(0,0,0,0.04); }
    
    /* Highlight Sidebar Active State */
    .menu-item.active { 
        background: var(--accent); /* Warna Highlight */
        color: var(--dark);
        font-weight: 600; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .menu-item svg { width:26px; height:26px; flex-shrink:0; display:block; }
    .menu-item .label { display:none; font-weight:500; font-size:15px; }
    .sidebar.open .menu-item .label { display:inline-block; }
    .sidebar:not(.open) .menu-item { justify-content:center; padding-left:0; padding-right:0; }
    .sidebar-panel {
      position: absolute; left: 0; top: 0; width: 100%; height: 100%; 
      background: linear-gradient(180deg, rgba(206,243,248,1) 0%, rgba(206,243,248,1) 100%);
      z-index: -1; 
    }

    /* 2. STYLE MODERN (Main Content Only) */
    .content { flex:1; padding: 20px 28px; position: relative; z-index:1; overflow-x: auto; }

    .page-title { font-size:28px; font-weight:600; margin:6px 0 18px 0; color: var(--dark); }

    .card { 
        background: #fff; 
        border-radius: 16px; 
        box-shadow: var(--shadow); 
        border: 1px solid #e2e8f0;
        margin-bottom: 24px; 
        overflow: hidden; 
    }

    .header-actions {
        padding: 20px 24px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
    }
    .header-actions h2 { margin:0; font-size:18px; font-weight: 600; color: #334155; }

    /* Buttons Modern */
    .btn {
      padding: 10px 18px; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif;
      font-weight: 500; font-size: 14px; cursor: pointer; transition: all 0.2s;
      text-decoration: none; display: inline-flex; align-items:center; gap:8px; line-height: 1;
      white-space: nowrap; 
      flex-shrink: 0;
    }
    .btn-primary { background: #0f172a; color: white; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2); }
    .btn-primary:hover { background: #1e293b; transform: translateY(-1px); }
    .btn-warning { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .btn-warning:hover { background: #ffedd5; }
    .btn-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }
    .btn-danger:hover { background: #fee2e2; }
    .btn-secondary { background: #f1f5f9; color: #475569; }
    .btn-secondary:hover { background: #e2e8f0; }

    /* Modern Table Styles */
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
    thead th {
        background: #f8fafc; color: #64748b; font-weight: 600; font-size: 12px;
        text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 24px;
        text-align: left; border-bottom: 1px solid #e2e8f0; white-space: nowrap;
    }
    tbody td {
        padding: 16px 24px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
        color: #334155; transition: background-color 0.2s;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: #f8fafc; }
    
    .text-primary-bold { color: #0f172a; font-weight: 600; font-size: 15px; }
    .text-secondary { color: #64748b; font-size: 13px; }

    /* Badges Modern */
    .badge {
        padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 9999px;
        display: inline-flex; align-items: center; gap: 4px; line-height: 1;
    }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-secondary { background: #f1f5f9; color: #475569; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }

    /* Modern Modal */
    .modal {
        display: none; position: fixed; inset: 0; padding: 20px; z-index: 1000;
        align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
    }
    .modal.active { display: flex; animation: fadeIn 0.2s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
    .modal-content {
        width: 100%; max-width: 600px; max-height: 90vh; display: flex; flex-direction: column;
        padding: 0; overflow-y: auto; background: #fff; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
        display: flex; justify-content: space-between; align-items: center; padding: 24px;
        border-bottom: 1px solid #f1f5f9; background: #fff; position: sticky; top: 0; z-index: 10;
    }
    .modal-header h2 { margin: 0; font-size: 20px; font-weight: 700; }
    .close-modal {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; font-size: 20px; cursor: pointer; color: #94a3b8; transition: all 0.2s;
    }
    .close-modal:hover { background: #f1f5f9; color: #0f172a; }
    
    .modal-body { padding: 24px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .form-group { margin-bottom: 20px; width: 100%; }
    label { display: block; margin-bottom: 8px; font-weight: 500; color: #334155; font-size: 14px; }
    input, select, textarea {
        width: 100%; padding: 12px 16px; font-size: 14px; border-radius: 8px;
        border: 1px solid #cbd5e1; background-color: #fff; transition: all 0.2s; color: #0f172a;
    }
    input:focus, select:focus, textarea:focus {
        border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Responsive */
    @media (max-width: 900px) {
        .sidebar { position: fixed; top: var(--topbar-height); left: -260px; width: 240px; box-shadow: none; border-right: 1px solid rgba(0,0,0,0.06); }
        .sidebar.open { left: 0; }
        .sidebar-panel { display: none; }
        .content { padding: 16px; }
        .form-grid { grid-template-columns: 1fr; gap: 16px; }
    }
  </style>
</head>
<body>
  <div class="topbar" role="banner">
    <div id="hamburger" class="hamburger" aria-label="Toggle menu" title="Toggle menu">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M3 6h18M3 12h18M3 18h18" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="brand" aria-label="Site name">
      <div style="display:flex;flex-direction:column;"><div>MyKisah</div></div>
    </div>
    <div class="top-actions" role="region" aria-label="Top actions">
      <button class="logout-btn" onclick="window.location.href='../logout.php'" title="Logout (sign out)" aria-label="Logout">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M12 2v10" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M5.5 8.5a7 7 0 1013 0" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>

  <div class="layout" role="application">
    <aside id="sidebar" class="sidebar" aria-label="Main menu">
      <nav class="menu" role="navigation" aria-label="Sidebar">
        <a class="menu-item" href="index.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 11.5L12 4l9 7.5" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20V11h14v9" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="label">Dashboard</span>
        </a>
        <a class="menu-item" href="manage-movies.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="4" width="20" height="16" rx="2" stroke="#0f172a" stroke-width="1.6"/><path d="M7 8v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/><path d="M7 12v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/><path d="M7 16v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span class="label">Movies</span>
        </a>
        <a class="menu-item active" href="manage-schedules.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="#0f172a" stroke-width="1.6"/><path d="M16 3v4M8 3v4M3 11h18" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="label">Schedules</span>
        </a>
        <a class="menu-item" href="manage-bookings.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6h14l-1 9H7L6 6z" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 6L4 3" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span class="label">Orders</span>
        </a>
        <a class="menu-item" href="../index.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l4-5-4-5" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 12H9" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="label">Exit</span>
        </a>
      </nav>
      <div style="flex:1"></div>
      <div class="sidebar-panel" aria-hidden="true"></div>
    </aside>

    <main class="content" role="main">
      <div class="page-title">Kelola Jadwal Tayang</div>

      <?php if ($success): ?>
          <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:16px; border-radius:12px; margin-bottom:24px;">
              <strong><?= htmlspecialchars($success) ?></strong>
          </div>
      <?php endif; ?>

      <?php if ($error): ?>
          <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:16px; border-radius:12px; margin-bottom:24px;">
              <strong><?= htmlspecialchars($error) ?></strong>
          </div>
      <?php endif; ?>

      <div class="card">
        <div class="header-actions">
            <h2>Daftar Jadwal Aktif</h2>
            <button class="btn btn-primary" onclick="openModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Tambah Jadwal
            </button>
        </div>

        <div class="table-responsive">
            <table role="table">
                <thead>
                    <tr>
                        <th>Detail Film</th>
                        <th>Lokasi & Studio</th>
                        <th>Waktu Tayang</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($schedules && $schedules->num_rows > 0): ?>
                        <?php while ($schedule = $schedules->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="text-primary-bold"><?php echo htmlspecialchars($schedule['movie_title']); ?></div>
                                    <div class="text-secondary">ID: #<?php echo $schedule['schedule_id']; ?></div>
                                </td>
                                <td>
                                    <div class="text-primary-bold"><?php echo htmlspecialchars($schedule['theater_name']); ?></div>
                                    <div class="text-secondary"><?php echo htmlspecialchars($schedule['hall_name']); ?> • <?php echo $schedule['available_seats']; ?> kursi sisa</div>
                                </td>
                                <td>
                                    <div class="text-primary-bold"><?php echo date('H:i', strtotime($schedule['show_time'])); ?> WIB</div>
                                    <div class="text-secondary"><?php echo date('d M Y', strtotime($schedule['show_date'])); ?></div>
                                </td>
                                <td>
                                    <div class="text-primary-bold" style="color:#0f172a;">Rp <?php echo number_format($schedule['price'], 0, ',', '.'); ?></div>
                                </td>
                                <td>
                                    <?php if ($schedule['status'] == 'active'): ?>
                                        <span class="badge badge-success">● Aktif</span>
                                    <?php elseif ($schedule['status'] == 'full'): ?>
                                        <span class="badge badge-warning">● Penuh</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">● Batal</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:8px;">
                                        <button class="btn btn-warning" style="padding: 8px 12px; font-size:12px;" 
                                                onclick='editSchedule(<?php echo json_encode($schedule, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                Edit
                                        </button>
                                        <a href="?delete=<?php echo $schedule['schedule_id']; ?>" 
                                           class="btn btn-danger"
                                           style="padding: 8px 12px; font-size:12px;"
                                           onclick="return confirm('Yakin ingin menghapus jadwal ini?')">
                                            Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="display:flex; flex-direction:column; align-items:center; gap:12px;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M12 8v4"/>
                                        <path d="M12 16h.01"/>
                                    </svg>
                                    <span>Tidak ada jadwal yang tersedia.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Form (Modern Style) -->
  <div id="scheduleModal" class="modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <h2 id="modalTitle">Tambah Jadwal Baru</h2>
            <div class="close-modal" onclick="closeModal()" title="Tutup">&times;</div>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="schedule_id" id="schedule_id">
                
                <div class="form-group">
                    <label>Pilih Film</label>
                    <select name="movie_id" id="movie_id" required>
                        <option value="">-- Pilih Judul Film --</option>
                        <?php 
                        $movies->data_seek(0);
                        while ($movie = $movies->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $movie['movie_id']; ?>">
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Lokasi Bioskop</label>
                        <select name="theater_id" id="theater_id" onchange="loadHalls()" required>
                            <option value="">-- Pilih Bioskop --</option>
                            <?php 
                            $theaters->data_seek(0);
                            while ($theater = $theaters->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $theater['theater_id']; ?>">
                                    <?php echo htmlspecialchars($theater['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Studio (Hall)</label>
                        <select name="hall_id" id="hall_id" required>
                            <option value="">Pilih Bioskop Dulu</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Tanggal Tayang</label>
                        <input type="date" name="show_date" id="show_date" required>
                    </div>

                    <div class="form-group">
                        <label>Jam Mulai</label>
                        <input type="time" name="show_time" id="show_time" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Harga Tiket (Rp)</label>
                        <input type="number" name="price" id="price" min="0" step="1000" placeholder="Contoh: 35000" required>
                    </div>

                    <div class="form-group">
                        <label>Status Tayang</label>
                        <select name="status" id="status" required>
                            <option value="active">Aktif</option>
                            <option value="full">Penuh</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px; padding-top:20px; border-top:1px solid #f1f5f9;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal()">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <script>
    // Sidebar logic
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    let open = false;

    function setSidebarState(isOpen) {
      if (isOpen) sidebar.classList.add('open');
      else sidebar.classList.remove('open');
    }

    hamburger.addEventListener('click', (e) => {
      open = !open;
      setSidebarState(open);
      e.stopPropagation();
    });

    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 900 && open) {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
          open = false;
          setSidebarState(open);
        }
      }
    });

    setSidebarState(false);

    // Modal & Schedule logic
    function openModal() {
        document.getElementById('scheduleModal').classList.add('active');
        document.getElementById('modalTitle').textContent = 'Tambah Jadwal Baru';
        document.querySelector('form').reset();
        document.getElementById('schedule_id').value = '';
        document.getElementById('hall_id').innerHTML = '<option value="">Pilih Bioskop Dulu</option>';
    }

    function closeModal() {
        document.getElementById('scheduleModal').classList.remove('active');
    }

    function editSchedule(schedule) {
        document.getElementById('scheduleModal').classList.add('active');
        document.getElementById('modalTitle').textContent = 'Edit Jadwal';
        
        document.getElementById('schedule_id').value = schedule.schedule_id;
        document.getElementById('movie_id').value = schedule.movie_id;
        document.getElementById('show_date').value = schedule.show_date;
        document.getElementById('show_time').value = schedule.show_time;
        document.getElementById('price').value = schedule.price;
        document.getElementById('status').value = schedule.status;
        
        if(schedule.theater_id) {
             document.getElementById('theater_id').value = schedule.theater_id;
             // Load halls and select correct one
             loadHalls(schedule.hall_id);
        }
    }

    function loadHalls(selectedHallId = null) {
        const theaterId = document.getElementById('theater_id').value;
        const hallSelect = document.getElementById('hall_id');
        
        if (!theaterId) {
            hallSelect.innerHTML = '<option value="">Pilih Bioskop Dulu</option>';
            return;
        }
        
        // Fetch halls via AJAX
        fetch(`get-halls.php?theater_id=${theaterId}`)
            .then(response => response.json())
            .then(data => {
                hallSelect.innerHTML = '<option value="">Pilih Studio</option>';
                data.forEach(hall => {
                    const option = document.createElement('option');
                    option.value = hall.hall_id;
                    option.textContent = hall.hall_name;
                    if (selectedHallId && hall.hall_id == selectedHallId) {
                        option.selected = true;
                    }
                    hallSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading halls:', error);
                hallSelect.innerHTML = '<option value="">Error loading studios</option>';
            });
    }

    window.onclick = function(event) {
        const modal = document.getElementById('scheduleModal');
        if (event.target == modal) {
            closeModal();
        }
    }
  </script>
</body>
</html>