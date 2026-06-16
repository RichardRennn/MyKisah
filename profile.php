<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// determine if current user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Fetch user's bookings
$sql = "SELECT b.*, m.title, m.poster_url, m.duration, h.hall_name, t.name as theater_name,
               s.show_date, s.show_time
        FROM bookings b
        INNER JOIN schedules s ON b.schedule_id = s.schedule_id
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        INNER JOIN theaters t ON h.theater_id = t.theater_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - MyKisah</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e293b;
            --accent: #CEF3F8;
            --accent-dark: #22d3ee;
            --text-dark: #1e293b;
            --text-light: #94a3b8;
            --white: #ffffff;
            --border: rgba(255,255,255,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary);
            color: var(--white);
            min-height: 100vh;
            padding-top: 80px;
        }

        /* --- HEADER --- */
        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
            text-decoration: none;
        }

        nav a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-weight: 500;
            margin-left: 20px;
            transition: color 0.2s;
            font-size: 14px;
        }
        nav a:hover { color: var(--white); }
        
        .logout-link {
            color: #ef4444 !important;
        }

        /* --- CONTAINER --- */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        /* --- PROFILE HEADER --- */
        .profile-header {
            background: var(--secondary);
            border-radius: 20px;
            padding: 40px;
            display: flex;
            align-items: center;
            gap: 24px;
            border: 1px solid var(--border);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        /* Decorative Background Blob */
        .profile-header::after {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 300px; height: 300px;
            background: var(--accent);
            filter: blur(100px);
            opacity: 0.1;
            border-radius: 50%;
        }

        .avatar-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent) 0%, #06b6d4 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            box-shadow: 0 10px 20px rgba(6, 182, 212, 0.3);
        }

        .profile-details h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .profile-details p {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            margin-left: 10px;
            vertical-align: middle;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* --- SECTION TITLE --- */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--accent);
            border-radius: 4px;
        }

        /* --- BOOKING GRID --- */
        .bookings-grid {
            display: grid;
            gap: 20px;
        }

        .booking-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .booking-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-color: rgba(255,255,255,0.2);
        }

        .poster-img {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }

        .booking-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .booking-info h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--white);
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-light);
            font-size: 13px;
            margin-bottom: 4px;
        }

        .info-row svg {
            width: 14px; height: 14px;
            opacity: 0.7;
        }

        .booking-code {
            display: inline-block;
            background: rgba(255,255,255,0.05);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            color: var(--accent);
            margin-top: 8px;
            border: 1px dashed rgba(255,255,255,0.1);
        }

        .booking-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            min-width: 120px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-paid { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .status-cancelled { background: rgba(239, 68, 68, 0.2); color: #f87171; }

        .price-tag {
            font-size: 18px;
            font-weight: 700;
            color: var(--white);
            margin-top: 8px;
        }

        .action-btn {
            text-decoration: none;
            font-size: 12px;
            color: var(--text-light);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 6px;
            margin-top: 12px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .action-btn:hover {
            background: var(--white);
            color: var(--primary);
        }

        /* --- EMPTY STATE --- */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--secondary);
            border-radius: 16px;
            border: 1px dashed var(--border);
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 24px;
        }

        .browse-btn {
            background: var(--accent);
            color: var(--primary);
            text-decoration: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: transform 0.2s;
            display: inline-block;
        }
        .browse-btn:hover { transform: translateY(-2px); }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .booking-card {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .booking-info {
                align-items: center;
            }
            .booking-status {
                align-items: center;
                flex-direction: row;
                justify-content: space-between;
                width: 100%;
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid var(--border);
            }
            .poster-img {
                width: 100%;
                height: 200px;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">MyKisah</a>
        <nav>
            <a href="index.php">Beranda</a>
            <?php if ($is_admin): ?>
                <a href="admin/index.php" style="color:var(--accent);">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-link">Logout</a>
        </nav>
    </header>

    <div class="container">
        <!-- Profile Info -->
        <div class="profile-header">
            <div class="avatar-circle">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <div class="profile-details">
                <h1>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    <?php if ($is_admin): ?>
                        <span class="role-badge">Admin</span>
                    <?php endif; ?>
                </h1>
                <p>@<?php echo htmlspecialchars($_SESSION['username']); ?> • Bergabung sejak 2025</p>
            </div>
        </div>

        <h2 class="section-title">Riwayat Tiket</h2>

        <?php if ($bookings->num_rows > 0): ?>
            <div class="bookings-grid">
                <?php while ($booking = $bookings->fetch_assoc()): ?>
                    <div class="booking-card">
                        <!-- Poster -->
                        <img src="<?php echo htmlspecialchars($booking['poster_url']); ?>" 
                             alt="<?php echo htmlspecialchars($booking['title']); ?>" 
                             class="poster-img">
                        
                        <!-- Info -->
                        <div class="booking-info">
                            <h3><?php echo htmlspecialchars($booking['title']); ?></h3>
                            
                            <div class="info-row">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <?php echo htmlspecialchars($booking['theater_name']); ?> • <?php echo htmlspecialchars($booking['hall_name']); ?>
                            </div>
                            
                            <div class="info-row">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <?php echo formatDate($booking['show_date']); ?> • <?php echo date('H:i', strtotime($booking['show_time'])); ?>
                            </div>

                            <span class="booking-code">#<?php echo htmlspecialchars($booking['booking_code']); ?></span>
                        </div>

                        <!-- Status & Price -->
                        <div class="booking-status">
                            <?php 
                            $status_map = [
                                'paid' => ['label' => 'Lunas', 'class' => 'status-paid'],
                                'pending' => ['label' => 'Menunggu', 'class' => 'status-pending'],
                                'cancelled' => ['label' => 'Dibatalkan', 'class' => 'status-cancelled']
                            ];
                            $status = $status_map[$booking['payment_status']];
                            ?>
                            <span class="status-badge <?php echo $status['class']; ?>">
                                <?php echo $status['label']; ?>
                            </span>

                            <div class="price-tag">
                                <?php echo formatPrice($booking['total_price']); ?>
                            </div>

                            <a href="confirmation.php?booking_code=<?php echo $booking['booking_code']; ?>" class="action-btn">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                Detail Tiket
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🎟️</div>
                <h3>Belum Ada Tiket</h3>
                <p>Anda belum memesan tiket film apapun saat ini.</p>
                <a href="index.php" class="browse-btn">Jelajahi Film Sekarang</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>