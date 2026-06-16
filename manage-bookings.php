<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

// Handle Status Update
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $booking_id = (int)$_GET['booking_id'];
    $action = $_GET['action'];
    
    if ($action == 'cancel') {
        // Cancel booking
        $conn->begin_transaction();
        
        try {
            // Get booking details
            $booking_sql = "SELECT schedule_id, total_seats FROM bookings WHERE booking_id = ?";
            $stmt = $db->prepare($booking_sql);
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();
            
            // Update booking status
            $update_booking = "UPDATE bookings SET payment_status = 'cancelled' WHERE booking_id = ?";
            $stmt = $db->prepare($update_booking);
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            
            // Update booking seats status
            $update_seats = "UPDATE booking_seats SET status = 'cancelled' WHERE booking_id = ?";
            $stmt = $db->prepare($update_seats);
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            
            // Return available seats to schedule
            $update_schedule = "UPDATE schedules SET available_seats = available_seats + ? WHERE schedule_id = ?";
            $stmt = $db->prepare($update_schedule);
            $stmt->bind_param("ii", $booking['total_seats'], $booking['schedule_id']);
            $stmt->execute();
            
            $conn->commit();
            $success = "Booking berhasil dibatalkan!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gagal membatalkan booking!";
        }
    } elseif ($action == 'confirm') {
        // Confirm pending booking
        $update_sql = "UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?";
        $stmt = $db->prepare($update_sql);
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $success = "Booking berhasil dikonfirmasi!";
        } else {
            $error = "Gagal mengkonfirmasi booking!";
        }
    }
}

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $db->escape($_GET['search']) : '';

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if ($filter_status != 'all') {
    $where_clauses[] = "b.payment_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_date) {
    $where_clauses[] = "DATE(b.booking_date) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

if ($search) {
    $where_clauses[] = "(b.booking_code LIKE ? OR u.full_name LIKE ? OR m.title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch bookings with filters
$bookings_sql = "SELECT b.*, m.title as movie_title, m.poster_url, 
                        u.full_name, u.email, u.phone,
                        h.hall_name, t.name as theater_name,
                        s.show_date, s.show_time
                 FROM bookings b
                 INNER JOIN schedules s ON b.schedule_id = s.schedule_id
                 INNER JOIN movies m ON s.movie_id = m.movie_id
                 INNER JOIN users u ON b.user_id = u.user_id
                 INNER JOIN halls h ON s.hall_id = h.hall_id
                 INNER JOIN theaters t ON h.theater_id = t.theater_id
                 $where_sql
                 ORDER BY b.booking_date DESC";

if (count($params) > 0) {
    $stmt = $db->prepare($bookings_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $bookings = $stmt->get_result();
} else {
    $bookings = $db->query($bookings_sql);
}

// Get booking details for modal
$booking_details = null;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    $details_sql = "SELECT b.*, m.title as movie_title, m.poster_url,
                           u.full_name, u.email, u.phone,
                           h.hall_name, t.name as theater_name, t.address,
                           s.show_date, s.show_time
                    FROM bookings b
                    INNER JOIN schedules s ON b.schedule_id = s.schedule_id
                    INNER JOIN movies m ON s.movie_id = m.movie_id
                    INNER JOIN users u ON b.user_id = u.user_id
                    INNER JOIN halls h ON s.hall_id = h.hall_id
                    INNER JOIN theaters t ON h.theater_id = t.theater_id
                    WHERE b.booking_id = ?";
    $stmt = $db->prepare($details_sql);
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $booking_details = $stmt->get_result()->fetch_assoc();
    
    // Get booked seats
    $seats_sql = "SELECT s.seat_row, s.seat_number
                  FROM booking_seats bs
                  INNER JOIN seats s ON bs.seat_id = s.seat_id
                  WHERE bs.booking_id = ?
                  ORDER BY s.seat_row, s.seat_number";
    $stmt = $db->prepare($seats_sql);
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $seats_result = $stmt->get_result();
    
    $booked_seats = [];
    while ($seat = $seats_result->fetch_assoc()) {
        $booked_seats[] = $seat['seat_row'] . $seat['seat_number'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - MyKisah Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* 1. STYLE GLOBAL & LAYOUT (Sama dengan manage-schedules) */
        :root {
            --accent: #CEF3F8;
            --card-bg: #ffffff;
            --muted: #64748b;
            --dark: #0f172a;
            --green: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --topbar-height: 56px;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            min-height: 100%;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #FFFFFF 0%, #CEF3F8 100%);
            color: var(--dark);
        }

        /* Topbar Asli */
        .topbar {
            position: sticky; top: 0; height: var(--topbar-height);
            background: #cef3f8; z-index: 9999; display: flex;
            align-items: center; gap: 16px; padding: 8px 20px;
            box-shadow: 0 2px 6px rgba(16,24,40,0.06);
        }
        .hamburger { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 8px; color: var(--dark); }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 18px; color: var(--dark); }
        .top-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .logout-btn { background: transparent; border: 0; cursor: pointer; padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }

        /* Sidebar Asli dengan Highlight Logic */
        .layout { display: flex; min-height: calc(100vh - var(--topbar-height)); }
        
        .sidebar {
            width: 80px; transition: width .22s ease; display: flex; flex-direction: column;
            align-items: center; padding-top: 12px; border-right: 1px solid rgba(0,0,0,0.06);
            position: relative; z-index: 10; background: transparent;
        }
        .sidebar.open { width: 240px; padding-left: 0; padding-right: 0; }
        
        .menu { 
            margin-top: 12px; 
            width: 100%; 
        }
        
        .menu-item { 
            display: flex; align-items: center; gap: 14px; padding: 12px; color: var(--dark); 
            text-decoration: none; border-radius: 8px; margin: 6px; cursor: pointer; 
            transition: background .15s; font-weight: 400;
        }
        .menu-item:hover { background: rgba(0,0,0,0.04); }
        
        /* Highlight Sidebar Active State */
        .menu-item.active { 
            background: var(--accent); /* Warna Highlight */
            color: var(--dark);
            font-weight: 600; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .menu-item svg { 
            width: 26px;
            height: 26px;
            flex-shrink: 0; 
            display: block; 
        }
        .menu-item .label { 
            display: none;
            font-weight: 500; 
            font-size: 15px; 
        }
        .sidebar.open .menu-item .label { 
            display: inline-block; 
        }

        .sidebar:not(.open) .menu-item { 
            justify-content: center; 
            padding-left: 0; 
            padding-right: 0; 
        }
        .sidebar-panel {
            position: absolute; left: 0; top: 0; width: 100%; height: 100%; 
            background: linear-gradient(180deg, rgba(206,243,248,1) 0%, rgba(206,243,248,1) 100%);
            z-index: -1; 
        }

        /* 2. MAIN CONTENT (Modern Style) */
        .content { 
            flex: 1; 
            padding: 20px 28px; 
            position: relative; 
            z-index: 1; 
            overflow-x: auto; 
        }

        .page-title { 
            font-size: 28px; 
            font-weight: 600; 
            margin: 6px 0 18px 0; 
            color: var(--dark);
        }

        .card { 
            background: #fff; 
            border-radius: 16px; 
            box-shadow: var(--shadow); 
            border: 1px solid #e2e8f0; 
            margin-bottom: 24px; 
            overflow: hidden; 
        }

        .stats-row {
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 20px; 
            margin-bottom: 24px;
        }
        .stat-box {
            background: #fff; padding: 20px; border-radius: 16px;
            box-shadow: var(--shadow); border: 1px solid #e2e8f0;
            display: flex; flex-direction: column; align-items: flex-start;
        }
        .stat-value { 
            font-size: 32px; 
            font-weight: 700; 
            color: #0f172a; 
            line-height: 1.2; 
        }
        
        .stat-label { 
            font-size: 14px; 
            color: #64748b; 
            font-weight: 500; 
            margin-top: 4px; 
        }

        .filter-bar {
            background: #f8fafc; padding: 16px 24px; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 12px;
            flex-wrap: nowrap; overflow-x: auto; white-space: nowrap;
            /* Hide Scrollbar */
            -ms-overflow-style: none; scrollbar-width: none;
        }
        .filter-bar::-webkit-scrollbar { display: none; }

        .filter-input {
            padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 13px; color: #334155; background-color: #fff;
            min-width: 200px; flex: 1;
        }
        .filter-select {
            padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 13px; color: #334155; background-color: #fff;
            min-width: 140px; flex-shrink: 0; outline: none; cursor: pointer;
        }
        .filter-input:focus, .filter-select:focus { border-color: #3b82f6; outline: none; }

        /* Buttons Modern */
        .btn {
            padding: 9px 16px; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif;
            font-weight: 500; font-size: 13px; cursor: pointer; transition: all 0.2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px; line-height: 1;
            white-space: nowrap; flex-shrink: 0;
        }
        .btn-primary { background: #0f172a; color: white; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2); }
        .btn-primary:hover { background: #1e293b; transform: translateY(-1px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }
        .btn-danger:hover { background: #fee2e2; }
        .btn-info { background: #eff6ff; color: #3b82f6; border: 1px solid #dbeafe; }
        .btn-info:hover { background: #dbeafe; }
        .btn-reset { color: #ef4444; font-size: 13px; font-weight: 500; margin-left: auto; text-decoration: none; }

        /* Modern Table */
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
        .text-code { font-family: monospace; font-weight: 600; color: #0f172a; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }

        /* Badges */
        .badge { padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px; line-height: 1; }
        .badge-success { background: #dcfce7; color: #166534; }
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
            width: 100%; max-width: 500px; max-height: 90vh; display: flex; flex-direction: column;
            padding: 0; overflow-y: auto; background: #fff; border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center; padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9; background: #fff; position: sticky; top: 0; z-index: 10;
        }
        .modal-header h2 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .close-modal {
            width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: 20px; cursor: pointer; color: #94a3b8; transition: all 0.2s;
        }
        .close-modal:hover { background: #f1f5f9; color: #0f172a; }
        .modal-body { padding: 24px; }

        /* Detail Rows */
        .detail-group { margin-bottom: 16px; }
        .detail-label { display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .detail-value { font-size: 15px; color: #0f172a; font-weight: 500; }
        .detail-divider { height: 1px; background: #f1f5f9; margin: 16px 0; }
        
        .seat-badge { 
            background: #f1f5f9; color: #0f172a; padding: 4px 8px; 
            border-radius: 6px; font-size: 13px; font-weight: 600; margin-right: 4px; margin-bottom: 4px; display: inline-block;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .sidebar { position: fixed; top: var(--topbar-height); left: -260px; width: 240px; box-shadow: none; border-right: 1px solid rgba(0,0,0,0.06); }
            .sidebar.open { left: 0; }
            .sidebar-panel { display: none; }
            .content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr; gap: 12px; }
            .filter-bar { padding: 12px 16px; }
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
          <span class="label">Movie</span>
        </a>
        <a class="menu-item" href="manage-schedules.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="#0f172a" stroke-width="1.6"/><path d="M16 3v4M8 3v4M3 11h18" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="label">Schedule</span>
        </a>
        <!-- Highlighted Active Item -->
        <a class="menu-item active" href="manage-bookings.php">
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
        <div class="page-title">Kelola Pesanan</div>

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

        <!-- Statistics (Modern Style) -->
        <div class="stats-row">
            <div class="stat-box">
                <?php $total_bookings = $db->query("SELECT COUNT(*) as total FROM bookings")->fetch_assoc()['total']; ?>
                <div class="stat-value"><?= $total_bookings ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <div class="stat-box">
                <?php $paid_bookings = $db->query("SELECT COUNT(*) as total FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['total']; ?>
                <div class="stat-value" style="color: #10b981;"><?= $paid_bookings ?></div>
                <div class="stat-label">Sudah Dibayar</div>
            </div>
            <div class="stat-box">
                <?php $pending_bookings = $db->query("SELECT COUNT(*) as total FROM bookings WHERE payment_status = 'cancelled'")->fetch_assoc()['total']; ?>
                <div class="stat-value" style="color: #ef4444;"><?= $pending_bookings ?></div>
                <div class="stat-label">Dibatalkan</div>
            </div>
        </div>

        <div class="card">
            <!-- Filter Bar (Modern 1 Line) -->
            <form method="GET" class="filter-bar">
                <input type="text" name="search" class="filter-input" placeholder="Cari kode booking, nama, film..." value="<?= htmlspecialchars($search); ?>">
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $filter_status == 'all' ? 'selected' : ''; ?>>-- Status --</option>
                    <option value="paid" <?= $filter_status == 'paid' ? 'selected' : ''; ?>>Lunas</option>
                    <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>

                <input type="date" name="date" class="filter-select" value="<?= $filter_date; ?>" onchange="this.form.submit()">

                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    Cari
                </button>

                <?php if($filter_status != 'all' || $filter_date || $search): ?>
                    <a href="manage-bookings.php" class="btn-reset">Reset</a>
                <?php endif; ?>
            </form>

            <!-- Modern Table -->
            <div class="table-responsive">
                <table role="table">
                    <thead>
                        <tr>
                            <th>Booking Info</th>
                            <th>Pelanggan</th>
                            <th>Detail Tayang</th>
                            <th>Tiket</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings->num_rows > 0): ?>
                            <?php while ($booking = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="text-code">#<?php echo htmlspecialchars($booking['booking_code']); ?></div>
                                        <div class="text-secondary"><?php echo date('d M Y, H:i', strtotime($booking['booking_date'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="text-primary-bold"><?php echo htmlspecialchars($booking['full_name']); ?></div>
                                        <div class="text-secondary"><?php echo htmlspecialchars($booking['email']); ?></div>
                                    </td>
                                    <td>
                                        <div class="text-primary-bold"><?php echo htmlspecialchars($booking['movie_title']); ?></div>
                                        <div class="text-secondary"><?php echo htmlspecialchars($booking['hall_name']); ?> • <?php echo date('H:i', strtotime($booking['show_time'])); ?></div>
                                    </td>
                                    <td><?php echo $booking['total_seats']; ?></td>
                                    <td>
                                        <div class="text-primary-bold">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($booking['payment_status'] == 'paid'): ?>
                                            <span class="badge badge-success">● Lunas</span>
                                        <?php elseif ($booking['payment_status'] == 'pending'): ?>
                                            <span class="badge badge-warning">● Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">● Batal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <a href="?view=<?php echo $booking['booking_id']; ?>" class="btn btn-info">Detail</a>
                                            
                                            <?php if ($booking['payment_status'] == 'pending'): ?>
                                                <a href="?action=confirm&booking_id=<?php echo $booking['booking_id']; ?>" 
                                                   class="btn btn-success"
                                                   onclick="return confirm('Konfirmasi pembayaran?')">OK</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['payment_status'] != 'cancelled'): ?>
                                                <a href="?action=cancel&booking_id=<?php echo $booking['booking_id']; ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Yakin ingin membatalkan?')">X</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:12px;">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" y1="8" x2="12" y2="12"></line>
                                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                        </svg>
                                        <span>Tidak ada data pesanan yang sesuai.</span>
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

  <!-- Modern Detail Modal -->
  <?php if ($booking_details): ?>
  <div id="detailModal" class="modal active">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detail Pesanan #<?php echo htmlspecialchars($booking_details['booking_code']); ?></h2>
            <div class="close-modal" onclick="closeModal()">&times;</div>
        </div>
        
        <div class="modal-body">
            <!-- Informasi Pelanggan -->
            <div class="detail-group">
                <span class="detail-label">Informasi Pelanggan</span>
                <div class="detail-value"><?php echo htmlspecialchars($booking_details['full_name']); ?></div>
                <div class="text-secondary"><?php echo htmlspecialchars($booking_details['email']); ?> • <?php echo htmlspecialchars($booking_details['phone']); ?></div>
            </div>

            <div class="detail-divider"></div>

            <!-- Informasi Film -->
            <div class="detail-group">
                <span class="detail-label">Detail Tayangan</span>
                <div class="detail-value"><?php echo htmlspecialchars($booking_details['movie_title']); ?></div>
                <div class="text-secondary">
                    <?php echo htmlspecialchars($booking_details['theater_name']); ?> (<?php echo htmlspecialchars($booking_details['hall_name']); ?>)<br>
                    <?php echo date('d M Y', strtotime($booking_details['show_date'])); ?>, <?php echo date('H:i', strtotime($booking_details['show_time'])); ?>
                </div>
            </div>

            <div class="detail-divider"></div>

            <!-- Kursi -->
            <div class="detail-group">
                <span class="detail-label">Kursi (<?php echo $booking_details['total_seats']; ?> Tiket)</span>
                <div style="margin-top:6px;">
                    <?php foreach ($booked_seats as $seat): ?>
                        <span class="seat-badge"><?php echo htmlspecialchars($seat); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="detail-divider"></div>

            <!-- Pembayaran -->
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span class="detail-label">Total Pembayaran</span>
                    <div style="font-size:20px; font-weight:700; color:#0f172a;">
                        Rp <?php echo number_format($booking_details['total_price'], 0, ',', '.'); ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <span class="detail-label">Status</span>
                    <?php if ($booking_details['payment_status'] == 'paid'): ?>
                        <span class="badge badge-success">Lunas</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Dibatalkan</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function closeModal() {
        window.location.href = 'manage-bookings.php';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('detailModal');
        if (modal && event.target == modal) {
            closeModal();
        }
    }

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
  </script>
</body>
</html>