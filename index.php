<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php'); 
}

function compute_growth_percent_display($current, $previous) {
    $current = (float)$current;
    $previous = (float)$previous;

    if ($previous == 0) {
        return '—';
    }

    $diff = $current - $previous;
    $percent = ($diff / $previous) * 100;
    $sign = $percent >= 0 ? '+' : '';
    // Return HTML safe string with color class logic can be handled in view
    return [
        'text' => $sign . round($percent) . '%',
        'is_positive' => $percent >= 0
    ];
}

/* ====== Data queries ====== */
/* Bookings & Revenue (this week vs last week) */
$booking_week_field = "YEARWEEK(booking_date, 1)";
$curr_bookings_sql = "
    SELECT COUNT(*) AS cnt, COALESCE(SUM(total_price), 0) AS revenue
    FROM bookings
    WHERE payment_status = 'paid' AND {$booking_week_field} = YEARWEEK(CURDATE(), 1)
";
$curr_booking_row = $db->query($curr_bookings_sql)->fetch_assoc();
$this_week_bookings = $curr_booking_row['cnt'] ?? 0;
$this_week_revenue = $curr_booking_row['revenue'] ?? 0.0;

$last_bookings_sql = "
    SELECT COUNT(*) AS cnt, COALESCE(SUM(total_price), 0) AS revenue
    FROM bookings
    WHERE payment_status = 'paid' AND {$booking_week_field} = (YEARWEEK(CURDATE(), 1) - 1)
";
$last_booking_row = $db->query($last_bookings_sql)->fetch_assoc();
$last_week_bookings = $last_booking_row['cnt'] ?? 0;
$last_week_revenue = $last_booking_row['revenue'] ?? 0.0;

/* Totals for display */
$total_bookings_sql = "SELECT COUNT(*) as total FROM bookings WHERE payment_status = 'paid'";
$total_bookings = $db->query($total_bookings_sql)->fetch_assoc()['total'] ?? 0;

$total_revenue_sql = "SELECT COALESCE(SUM(total_price),0) as revenue FROM bookings WHERE payment_status = 'paid'";
$total_revenue = $db->query($total_revenue_sql)->fetch_assoc()['revenue'] ?? 0.0;

/* Movies: total now playing only */
$total_movies_sql = "SELECT COUNT(*) as total FROM movies WHERE status = 'now_playing'";
$total_movies = $db->query($total_movies_sql)->fetch_assoc()['total'] ?? 0;

$total_users_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
$total_users = $db->query($total_users_sql)->fetch_assoc()['total'] ?? 0;

/* Growth Calculation */
$growth_val = $this_week_bookings - $last_week_bookings;
$bookings_growth_text = ($growth_val >= 0 ? '+' : '') . $growth_val;
$bookings_growth_positive = $growth_val >= 0;

$revenue_growth_data = compute_growth_percent_display($this_week_revenue, $last_week_revenue);
$revenue_growth_text = is_array($revenue_growth_data) ? $revenue_growth_data['text'] : $revenue_growth_data;
$revenue_growth_positive = is_array($revenue_growth_data) ? $revenue_growth_data['is_positive'] : true;

/* Recent bookings (last 10) */
$recent_bookings_sql = "SELECT b.*, m.title, u.full_name, s.show_date, s.show_time
    FROM bookings b
    INNER JOIN schedules s ON b.schedule_id = s.schedule_id
    INNER JOIN movies m ON s.movie_id = m.movie_id
    INNER JOIN users u ON b.user_id = u.user_id
    ORDER BY b.booking_date DESC
    LIMIT 10";
$recent_bookings = $db->query($recent_bookings_sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MyKisah - Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    /* 1. STYLE GLOBAL & LAYOUT (Konsisten) */
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

    /* Topbar */
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

    /* Sidebar */
    .layout { display: flex; min-height: calc(100vh - var(--topbar-height)); }
    
    .sidebar {
        width: 80px; transition: width .22s ease; display: flex; flex-direction: column;
        align-items: center; padding-top: 12px; border-right: 1px solid rgba(0,0,0,0.06);
        position: relative; z-index: 10; background: transparent;
    }
    .sidebar.open { width: 240px; padding-left: 0; padding-right: 0; }
    
    .menu { margin-top: 12px; width: 100%; }
    
    .menu-item { 
        display: flex; align-items: center; gap: 14px; padding: 12px; color: var(--dark); 
        text-decoration: none; border-radius: 8px; margin: 6px; cursor: pointer; 
        transition: background .15s; font-weight: 400;
    }
    .menu-item:hover { background: rgba(0,0,0,0.04); }
    
    /* Highlight Sidebar Active State */
    .menu-item.active { 
        background: var(--accent);
        color: var(--dark);
        font-weight: 600; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .menu-item svg { width: 26px; height: 26px; flex-shrink: 0; display: block; }
    .menu-item .label { display: none; font-weight: 500; font-size: 15px; }
    .sidebar.open .menu-item .label { display: inline-block; }
    .sidebar:not(.open) .menu-item { justify-content: center; padding-left: 0; padding-right: 0; }
    .sidebar-panel {
        position: absolute; left: 0; top: 0; width: 100%; height: 100%; 
        background: linear-gradient(180deg, rgba(206,243,248,1) 0%, rgba(206,243,248,1) 100%);
        z-index: -1; 
    }

    /* 2. MAIN CONTENT */
    .content { flex: 1; padding: 20px 28px; position: relative; z-index: 1; overflow-x: auto; }
    .page-title { font-size: 28px; font-weight: 600; margin: 6px 0 24px 0; color: var(--dark); }

    /* Card Style */
    .card { 
        background: #fff; border-radius: 16px; box-shadow: var(--shadow); 
        border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; 
        padding: 24px;
    }
    .card-header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }

    /* Stats Grid (4 Columns for Dashboard) */
    .stats-row {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;
    }
    
    .stat-box {
        background: #fff; padding: 24px; border-radius: 16px;
        box-shadow: var(--shadow); border: 1px solid #e2e8f0;
        display: flex; flex-direction: column; 
        transition: transform 0.2s;
    }
    .stat-box:hover { transform: translateY(-2px); }

    .stat-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
    .stat-icon {
        width: 48px; height: 48px; border-radius: 12px; background: #f1f5f9;
        display: flex; align-items: center; justify-content: center; font-size: 20px;
    }
    
    .stat-value { font-size: 28px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .stat-label { font-size: 14px; color: #64748b; font-weight: 500; margin-top: 4px; }
    
    .stat-growth {
        font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 20px;
        display: inline-flex; align-items: center; gap: 4px; margin-top: 12px; align-self: flex-start;
    }
    .growth-up { background: #dcfce7; color: #166534; }
    .growth-down { background: #fee2e2; color: #991b1b; }
    .growth-neutral { background: #f1f5f9; color: #64748b; }

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
    .text-secondary { color: #64748b; font-size: 13px; margin-top: 2px; }
    .text-code { font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 600; }

    /* Badges */
    .badge { padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px; line-height: 1; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }

    /* Responsive */
    @media (max-width: 1100px) {
        .stats-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
        .sidebar { position: fixed; top: var(--topbar-height); left: -260px; width: 240px; box-shadow: none; border-right: 1px solid rgba(0,0,0,0.06); }
        .sidebar.open { left: 0; }
        .sidebar-panel { display: none; }
        .content { padding: 16px; }
        .stats-row { grid-template-columns: 1fr; }
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
      <div style="display:flex;flex-direction:column;">
        <div>MyKisah</div>
      </div>
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
        <!-- Highlight Active Item (Dashboard) -->
        <a class="menu-item active" href="index.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 11.5L12 4l9 7.5" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20V11h14v9" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="label">Dashboard</span>
        </a>

        <a class="menu-item" href="manage-movies.php">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="4" width="20" height="16" rx="2" stroke="#0f172a" stroke-width="1.6"/><path d="M7 8v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/><path d="M7 12v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/><path d="M7 16v0" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span class="label">Movies</span>
        </a>

        <a class="menu-item" href="manage-schedules.php">
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
      <div class="page-title">Admin Dashboard</div>

      <!-- Modern Stats Grid -->
      <div class="stats-row">
          <!-- Card 1: Total Bookings -->
          <div class="stat-box">
              <div class="stat-header">
                  <div>
                      <div class="stat-value"><?= htmlspecialchars($total_bookings) ?></div>
                      <div class="stat-label">Total Pemesanan</div>
                  </div>
                  <div class="stat-icon" style="color:#3b82f6;">🎫</div>
              </div>
              <div class="stat-growth <?= $bookings_growth_positive ? 'growth-up' : 'growth-down' ?>">
                  <?= $bookings_growth_positive ? '↗' : '↘' ?> <?= htmlspecialchars($bookings_growth_text) ?> minggu ini
              </div>
          </div>

          <!-- Card 2: Total Revenue -->
          <div class="stat-box">
              <div class="stat-header">
                  <div>
                      <div class="stat-value" style="font-size:24px;"><?= formatPrice($total_revenue) ?></div>
                      <div class="stat-label">Total Pendapatan</div>
                  </div>
                  <div class="stat-icon" style="color:#10b981;">💵</div>
              </div>
              <div class="stat-growth <?= $revenue_growth_positive ? 'growth-up' : 'growth-down' ?>">
                  <?= $revenue_growth_positive ? '↗' : '↘' ?> <?= htmlspecialchars($revenue_growth_text) ?> minggu ini
              </div>
          </div>

          <!-- Card 3: Movies Playing (CENTERED, NO TEXT BOTTOM) -->
          <div class="stat-box" style="align-items:center; text-align:center; justify-content:center;">
              <div class="stat-icon" style="color:#f59e0b; margin-bottom:10px;">🎬</div>
              <div class="stat-value"><?= htmlspecialchars($total_movies) ?></div>
              <div class="stat-label">Film Sedang Tayang</div>
          </div>

          <!-- Card 4: Total Users (CENTERED, NO TEXT BOTTOM) -->
          <div class="stat-box" style="align-items:center; text-align:center; justify-content:center;">
              <div class="stat-icon" style="color:#6366f1; margin-bottom:10px;">👥</div>
              <div class="stat-value"><?= htmlspecialchars($total_users) ?></div>
              <div class="stat-label">Total Pengguna</div>
          </div>
      </div>

      <!-- Recent Orders Table -->
      <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pesanan Terbaru</h3>
            <a href="manage-bookings.php" style="color:#3b82f6; font-size:13px; font-weight:600; text-decoration:none;">Lihat Semua &rarr;</a>
        </div>

        <div class="table-responsive">
          <table role="table">
            <thead>
              <tr>
                <th>Kode Booking</th>
                <th>Pelanggan</th>
                <th>Film</th>
                <th>Jadwal</th>
                <th>Kursi</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                <?php while ($b = $recent_bookings->fetch_assoc()): ?>
                  <tr>
                    <td><span class="text-code">#<?= htmlspecialchars($b['booking_code']) ?></span></td>
                    <td>
                        <div class="text-primary-bold"><?= htmlspecialchars($b['full_name']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td>
                        <div class="text-secondary">
                            <?= date('d M', strtotime($b['show_date'])) ?>, <?= date('H:i', strtotime($b['show_time'])) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($b['total_seats']) ?> tiket</td>
                    <td><span style="font-weight:600;"><?= formatPrice($b['total_price']) ?></span></td>
                    <td>
                      <?php if ($b['payment_status'] === 'paid'): ?>
                        <span class="badge badge-success">Lunas</span>
                      <?php elseif ($b['payment_status'] === 'pending'): ?>
                        <span class="badge badge-warning">Pending</span>
                      <?php else: ?>
                        <span class="badge badge-danger">Batal</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align:center;color:#94a3b8;padding:32px;">Belum ada pesanan terbaru.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <script>
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