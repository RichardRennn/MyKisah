<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

/* ===== Handle Delete (GET) ===== */
if (isset($_GET['delete'])) {
    $movie_id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM movies WHERE movie_id = ?";
    $stmt = $db->prepare($delete_sql);
    $stmt->bind_param("i", $movie_id);
    if ($stmt->execute()) {
        $success = "Film berhasil dihapus!";
        header("Location: manage-movies.php?success=" . urlencode($success));
        exit;
    } else {
        $error = "Gagal menghapus film!";
    }
}

/* ===== Handle Add/Edit (POST) ===== */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : 0;
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? ''); 
    $duration = (int)($_POST['duration'] ?? 0);
    $genre = trim($_POST['genre'] ?? '');
    $rating = trim($_POST['rating'] ?? '');
    $poster_url = trim($_POST['poster_url'] ?? '');
    $trailer_url = trim($_POST['trailer_url'] ?? ''); // <--- Field Baru
    $release_date = $_POST['release_date'] ?? '';
    $status = $_POST['status'] ?? 'archived';

    if ($movie_id > 0) {
        // Update (Tambahkan trailer_url)
        $sql = "UPDATE movies SET title=?, description=?, duration=?, genre=?, rating=?, poster_url=?, trailer_url=?, release_date=?, status=?, updated_at = NOW() WHERE movie_id=?";
        $stmt = $db->prepare($sql);
        // Type definition: s(title), s(desc), i(dur), s(gen), s(rat), s(post), s(trail), s(date), s(stat), i(id)
        $stmt->bind_param("ssissssssi", $title, $description, $duration, $genre, $rating, $poster_url, $trailer_url, $release_date, $status, $movie_id);
        
        if ($stmt->execute()) {
            $success = "Film berhasil diupdate!";
            header("Location: manage-movies.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal mengupdate film: " . $stmt->error;
        }
    } else {
        // Insert (Tambahkan trailer_url)
        $sql = "INSERT INTO movies (title, description, duration, genre, rating, poster_url, trailer_url, release_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssissssss", $title, $description, $duration, $genre, $rating, $poster_url, $trailer_url, $release_date, $status);
        
        if ($stmt->execute()) {
            $success = "Film berhasil ditambahkan!";
            header("Location: manage-movies.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menambahkan film: " . $stmt->error;
        }
    }
}

/* ===== Filter & Search Logic ===== */
$search = isset($_GET['search']) ? $db->escape($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

$where_clauses = [];
if ($search) {
    $where_clauses[] = "(title LIKE '%$search%' OR genre LIKE '%$search%')";
}
if ($filter_status && $filter_status != 'all') {
    $where_clauses[] = "status = '$filter_status'";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

/* ===== Fetch movies ===== */
$movies_sql = "SELECT * FROM movies $where_sql ORDER BY created_at DESC";
$movies = $db->query($movies_sql);

if (isset($_GET['success']) && !$success) { $success = $_GET['success']; }
if (isset($_GET['error']) && !$error) { $error = $_GET['error']; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MyKisah - Kelola Film</title>
  <!-- Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Style sama persis dengan sebelumnya */
    :root { --accent: #CEF3F8; --card-bg: #ffffff; --muted: #64748b; --dark: #0f172a; --green: #10b981; --danger: #ef4444; --warning: #f59e0b; --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); --topbar-height: 56px; }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { min-height: 100%; margin: 0; font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #FFFFFF 0%, #CEF3F8 100%); color: var(--dark); }
    .topbar { position: sticky; top: 0; height: var(--topbar-height); background: #cef3f8; z-index: 9999; display: flex; align-items: center; gap: 16px; padding: 8px 20px; box-shadow: 0 2px 6px rgba(16,24,40,0.06); }
    .hamburger { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 8px; color: var(--dark); }
    .brand { display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 18px; color: var(--dark); }
    .top-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
    .logout-btn { background: transparent; border: 0; cursor: pointer; padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .layout { display: flex; min-height: calc(100vh - var(--topbar-height)); }
    .sidebar { width: 80px; transition: width .22s ease; display: flex; flex-direction: column; align-items: center; padding-top: 12px; border-right: 1px solid rgba(0,0,0,0.06); position: relative; z-index: 10; background: transparent; }
    .sidebar.open { width: 240px; padding-left: 0; padding-right: 0; }
    .menu { margin-top: 12px; width: 100%; }
    .menu-item { display: flex; align-items: center; gap: 14px; padding: 12px; color: var(--dark); text-decoration: none; border-radius: 8px; margin: 6px; cursor: pointer; transition: background .15s; font-weight: 400; }
    .menu-item:hover { background: rgba(0,0,0,0.04); }
    .menu-item.active { background: var(--accent); color: var(--dark); font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .menu-item svg { width: 26px; height: 26px; flex-shrink: 0; display: block; }
    .menu-item .label { display: none; font-weight: 500; font-size: 15px; }
    .sidebar.open .menu-item .label { display: inline-block; }
    .sidebar:not(.open) .menu-item { justify-content: center; padding-left: 0; padding-right: 0; }
    .sidebar-panel { position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(180deg, rgba(206,243,248,1) 0%, rgba(206,243,248,1) 100%); z-index: -1; }
    .content { flex: 1; padding: 20px 28px; position: relative; z-index: 1; overflow-x: auto; }
    .page-title { font-size: 28px; font-weight: 600; margin: 6px 0 18px 0; color: var(--dark); }
    .card { background: #fff; border-radius: 16px; box-shadow: var(--shadow); border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; }
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
    .stat-box { background: #fff; padding: 20px; border-radius: 16px; box-shadow: var(--shadow); border: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: flex-start; }
    .stat-value { font-size: 32px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .stat-label { font-size: 14px; color: #64748b; font-weight: 500; margin-top: 4px; }
    .header-actions { padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; background: #fff; }
    .header-actions h2 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
    .filter-bar { background: #f8fafc; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; justify-content: flex-end; flex-wrap: nowrap; overflow-x: auto; white-space: nowrap; -ms-overflow-style: none; scrollbar-width: none; }
    .filter-bar::-webkit-scrollbar { display: none; }
    .filter-input { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #334155; background-color: #fff; width: 200px; flex-shrink: 0; }
    .filter-select { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #334155; background-color: #fff; width: 140px; flex-shrink: 0; outline: none; cursor: pointer; }
    .filter-input:focus, .filter-select:focus { border-color: #3b82f6; outline: none; }
    .btn { padding: 9px 16px; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; font-weight: 500; font-size: 13px; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; line-height: 1; white-space: nowrap; flex-shrink: 0; }
    .btn-primary { background: #0f172a; color: white; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2); }
    .btn-primary:hover { background: #1e293b; transform: translateY(-1px); }
    .btn-warning { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .btn-warning:hover { background: #ffedd5; }
    .btn-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }
    .btn-danger:hover { background: #fee2e2; }
    .btn-secondary { background: #f1f5f9; color: #475569; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-reset { color: #ef4444; font-size: 13px; font-weight: 500; margin-left: 12px; text-decoration: none; }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
    thead th { background: #f8fafc; color: #64748b; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 24px; text-align: left; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
    tbody td { padding: 16px 24px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; transition: background-color 0.2s; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: #f8fafc; }
    .text-primary-bold { color: #0f172a; font-weight: 600; font-size: 15px; }
    .text-secondary { color: #64748b; font-size: 13px; margin-top: 2px; }
    .movie-poster-small { width: 48px; height: 72px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .badge { padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px; line-height: 1; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-secondary { background: #f1f5f9; color: #475569; }
    .modal { display: none; position: fixed; inset: 0; padding: 20px; z-index: 1000; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
    .modal.active { display: flex; animation: fadeIn 0.2s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .modal-content { width: 100%; max-width: 550px; max-height: 90vh; display: flex; flex-direction: column; padding: 0; overflow-y: auto; background: #fff; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: #fff; position: sticky; top: 0; z-index: 10; }
    .modal-header h2 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
    .close-modal { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 20px; cursor: pointer; color: #94a3b8; transition: all 0.2s; }
    .close-modal:hover { background: #f1f5f9; color: #0f172a; }
    .modal-body { padding: 24px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .form-group { margin-bottom: 14px; width: 100%; }
    .form-group.full-width { grid-column: 1 / -1; }
    label { display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 13px; }
    input, select, textarea { width: 100%; padding: 10px 12px; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #fff; transition: all 0.2s; color: #0f172a; }
    input:focus, select:focus, textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .modal-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
    .modal-actions .btn { flex: 1; padding: 12px 16px; font-size: 15px; font-weight: 600; justify-content: center; }
    @media (max-width: 900px) { .sidebar { position: fixed; top: var(--topbar-height); left: -260px; width: 240px; box-shadow: none; border-right: 1px solid rgba(0,0,0,0.06); } .sidebar.open { left: 0; } .sidebar-panel { display: none; } .content { padding: 16px; } .stats-row { grid-template-columns: 1fr; gap: 12px; } .form-grid { grid-template-columns: 1fr; gap: 16px; } .filter-bar { justify-content: flex-start; padding: 12px 16px; } }
  </style>
</head>
<body>
  <div class="topbar" role="banner">
    <div id="hamburger" class="hamburger" aria-label="Toggle menu" title="Toggle menu"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M3 12h18M3 18h18" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
    <div class="brand" aria-label="Site name"><div>MyKisah</div></div>
    <div class="top-actions" role="region" aria-label="Top actions">
      <button class="logout-btn" onclick="window.location.href='../logout.php'" title="Logout"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2v10" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 8.5a7 7 0 1013 0" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    </div>
  </div>

  <div class="layout" role="application">
    <aside id="sidebar" class="sidebar" aria-label="Main menu">
      <nav class="menu" role="navigation" aria-label="Sidebar">
        <a class="menu-item" href="index.php"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 11.5L12 4l9 7.5" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20V11h14v9" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="label">Dashboard</span></a>
        <a class="menu-item active" href="manage-movies.php"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="4" width="20" height="16" rx="2" stroke="#0f172a" stroke-width="1.6"/><path d="M7 8v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/><path d="M7 12v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/><path d="M7 16v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/></svg><span class="label">Movies</span></a>
        <a class="menu-item" href="manage-schedules.php"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="#0f172a" stroke-width="1.6"/><path d="M16 3v4M8 3v4M3 11h18" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="label">Schedules</span></a>
        <a class="menu-item" href="manage-bookings.php"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6h14l-1 9H7L6 6z" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 6L4 3" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/></svg><span class="label">Orders</span></a>
        <a class="menu-item" href="../index.php"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l4-5-4-5" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 12H9" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="label">Exit</span></a>
      </nav>
      <div style="flex:1"></div>
      <div class="sidebar-panel" aria-hidden="true"></div>
    </aside>

    <main class="content" role="main">
      <div class="page-title">Kelola Film</div>

      <?php if ($success): ?><div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:16px; border-radius:12px; margin-bottom:24px;"><strong><?= htmlspecialchars($success) ?></strong></div><?php endif; ?>
      <?php if ($error): ?><div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:16px; border-radius:12px; margin-bottom:24px;"><strong><?= htmlspecialchars($error) ?></strong></div><?php endif; ?>

      <div class="stats-row">
          <div class="stat-box">
              <?php $total_movies = $db->query("SELECT COUNT(*) as total FROM movies")->fetch_assoc()['total']; ?>
              <div class="stat-value"><?= $total_movies ?></div>
              <div class="stat-label">Total Film</div>
          </div>
          <div class="stat-box">
              <?php $now_playing = $db->query("SELECT COUNT(*) as total FROM movies WHERE status = 'now_playing'")->fetch_assoc()['total']; ?>
              <div class="stat-value" style="color: #10b981;"><?= $now_playing ?></div>
              <div class="stat-label">Sedang Tayang</div>
          </div>
          <div class="stat-box">
              <?php $coming_soon = $db->query("SELECT COUNT(*) as total FROM movies WHERE status = 'coming_soon'")->fetch_assoc()['total']; ?>
              <div class="stat-value" style="color: #3b82f6;"><?= $coming_soon ?></div>
              <div class="stat-label">Coming Soon</div>
          </div>
      </div>

      <div class="card">
        <div class="header-actions">
            <h2>Daftar Film</h2>
            <button class="btn btn-primary" onclick="openModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round"/></svg>Tambah Film</button>
        </div>

        <form method="GET" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="Cari judul film..." value="<?= htmlspecialchars($search); ?>">
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?= $filter_status == 'all' ? 'selected' : ''; ?>>-- Status --</option>
                <option value="now_playing" <?= $filter_status == 'now_playing' ? 'selected' : ''; ?>>Sedang Tayang</option>
                <option value="coming_soon" <?= $filter_status == 'coming_soon' ? 'selected' : ''; ?>>Coming Soon</option>
                <option value="archived" <?= $filter_status == 'archived' ? 'selected' : ''; ?>>Archived</option>
            </select>
            <button type="submit" class="btn btn-primary"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>Cari</button>
            <?php if($filter_status != 'all' || $search): ?><a href="manage-movies.php" class="btn-reset">Reset</a><?php endif; ?>
        </form>

        <div class="table-responsive">
            <table role="table" aria-label="Daftar Film">
              <thead>
                <tr>
                  <th width="80">Poster</th>
                  <th>Detail Film</th>
                  <th>Info Teknis</th>
                  <th>Rilis</th>
                  <th>Status</th>
                  <th style="text-align:right;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($movies && $movies->num_rows > 0): ?>
                  <?php while ($movie = $movies->fetch_assoc()): ?>
                    <tr>
                      <td>
                        <?php $posterPath = htmlspecialchars($movie['poster_url'] ?: ''); ?>
                        <?php if ($posterPath): ?>
                          <img src="<?= '../' . $posterPath ?>" alt="<?= htmlspecialchars($movie['title']) ?>" class="movie-poster-small">
                        <?php else: ?>
                          <div style="width:48px;height:72px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:10px;">No</div>
                        <?php endif; ?>
                      </td>
                      <td>
                          <div class="text-primary-bold"><?= htmlspecialchars($movie['title']) ?></div>
                          <div class="text-secondary"><?= htmlspecialchars($movie['genre']) ?></div>
                      </td>
                      <td>
                          <div class="text-primary-bold"><?= (int)$movie['duration'] ?> menit</div>
                          <div class="text-secondary">Rating: <?= htmlspecialchars($movie['rating']) ?></div>
                      </td>
                      <td><div class="text-secondary"><?= date('d M Y', strtotime($movie['release_date'])) ?></div></td>
                      <td>
                        <?php if ($movie['status'] == 'now_playing'): ?><span class="badge badge-success">● Sedang Tayang</span>
                        <?php elseif ($movie['status'] == 'coming_soon'): ?><span class="badge badge-info">● Coming Soon</span>
                        <?php else: ?><span class="badge badge-secondary">● Archived</span><?php endif; ?>
                      </td>
                      <td style="text-align:right;">
                        <div style="display:inline-flex; gap:8px;">
                          <button class="btn btn-warning" onclick='editMovie(<?= json_encode($movie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Edit</button>
                          <a href="?delete=<?= (int)$movie['movie_id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus film ini?')">Hapus</a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:40px;">Tidak ada film yang sesuai.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Form -->
  <div id="movieModal" class="modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-header">
        <h2 id="modalTitle">Tambah Film Baru</h2>
        <div class="close-modal" onclick="closeModal()" title="Tutup">&times;</div>
      </div>
      <div class="modal-body">
          <form method="POST" action="">
            <input type="hidden" name="movie_id" id="movie_id">
            <div class="form-group">
                <label>Judul Film *</label>
                <input type="text" name="title" id="title" required placeholder="Contoh: Avengers: Endgame">
            </div>
            <div class="form-group full-width">
                <label>Deskripsi *</label>
                <textarea name="description" id="modal_description" required rows="4" placeholder="Sinopsis singkat film..."></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Durasi (menit) *</label>
                    <input type="number" name="duration" id="duration" required min="1" placeholder="120">
                </div>
                <div class="form-group">
                    <label>Rating Usia *</label>
                    <select name="rating" id="rating" required>
                        <option value="">-- Pilih Rating --</option>
                        <option value="G">G - Semua Umur</option>
                        <option value="PG">PG - Bimbingan Ortu</option>
                        <option value="PG-13">PG-13 - Remaja</option>
                        <option value="R">R - Dewasa</option>
                        <option value="NC-17">NC-17 - 17+</option>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Genre *</label>
                    <input type="text" name="genre" id="genre" placeholder="Action, Drama, Comedy" required>
                </div>
                <div class="form-group">
                    <label>Poster URL *</label>
                    <input type="text" name="poster_url" id="poster_url" placeholder="uploads/poster.jpg" required>
                </div>
            </div>
            <!-- INPUT TRAILER DI SINI -->
            <div class="form-group full-width">
                 <label>Link Trailer (YouTube) *</label>
                 <input type="text" name="trailer_url" id="trailer_url" placeholder="https://www.youtube.com/watch?v=..." required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Tanggal Rilis *</label>
                    <input type="date" name="release_date" id="release_date" required>
                </div>
                <div class="form-group">
                    <label>Status Tayang *</label>
                    <select name="status" id="status" required>
                        <option value="now_playing">Sedang Tayang</option>
                        <option value="coming_soon">Coming Soon</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data</button>
            </div>
          </form>
      </div>
    </div>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    let open = false;
    function setSidebarState(isOpen) {
      if (isOpen) sidebar.classList.add('open');
      else sidebar.classList.remove('open');
    }
    hamburger.addEventListener('click', (e) => { open = !open; setSidebarState(open); e.stopPropagation(); });
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 900 && open) {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) { open = false; setSidebarState(open); }
      }
    });
    setSidebarState(false);

    function openModal() {
      document.getElementById('movieModal').classList.add('active');
      document.getElementById('modalTitle').textContent = 'Tambah Film Baru';
      document.querySelector('#movieModal form').reset();
      document.getElementById('movie_id').value = '';
      document.getElementById('modal_description').value = '';
    }
    function closeModal() { document.getElementById('movieModal').classList.remove('active'); }
    function editMovie(movie) {
      document.getElementById('movieModal').classList.add('active');
      document.getElementById('modalTitle').textContent = 'Edit Film';
      document.getElementById('movie_id').value = movie.movie_id || '';
      document.getElementById('title').value = movie.title || '';
      document.getElementById('modal_description').value = movie.description || '';
      document.getElementById('duration').value = movie.duration || '';
      document.getElementById('genre').value = movie.genre || '';
      document.getElementById('rating').value = movie.rating || '';
      document.getElementById('poster_url').value = movie.poster_url || '';
      // Populate trailer
      document.getElementById('trailer_url').value = movie.trailer_url || ''; 
      document.getElementById('release_date').value = (movie.release_date ? movie.release_date.substring(0,10) : '');
      document.getElementById('status').value = movie.status || '';
    }
    window.onclick = function(event) {
      const modal = document.getElementById('movieModal');
      if (event.target == modal) closeModal();
    };
  </script>
</body>
</html>