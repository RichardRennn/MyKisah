<?php
require_once 'includes/config.php';

// Ambil ID film dari URL
$movie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil detail film dari database
$sql = "SELECT * FROM movies WHERE movie_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();

// Jika film tidak ditemukan, kembali ke index
if ($result->num_rows == 0) {
    header("Location: index.php");
    exit;
}

$movie = $result->fetch_assoc();

// Fungsi Helper: Ubah link YouTube biasa jadi Embed Link
function getYoutubeEmbedUrl($url) {
    $shortUrlRegex = '/youtu.be\/([a-zA-Z0-9_-]+)\??/i';
    $longUrlRegex = '/youtube.com\/((?:embed)|(?:watch))((?:\?v\=)|(?:\/))([a-zA-Z0-9_-]+)/i';

    if (preg_match($longUrlRegex, $url, $matches)) {
        return "https://www.youtube.com/embed/" . $matches[3];
    }

    if (preg_match($shortUrlRegex, $url, $matches)) {
        return "https://www.youtube.com/embed/" . $matches[1];
    }

    return $url;
}

$embed_url = getYoutubeEmbedUrl($movie['trailer_url'] ?? '');

// Ambil jadwal tayang yang aktif
$theater_sql = "SELECT DISTINCT t.* FROM theaters t
                INNER JOIN halls h ON t.theater_id = h.theater_id
                INNER JOIN schedules s ON h.hall_id = s.hall_id
                WHERE s.movie_id = ? 
                  AND s.status = 'active'
                  AND s.show_date >= CURDATE()
                ORDER BY t.name";
$theater_stmt = $db->prepare($theater_sql);
$theater_stmt->bind_param("i", $movie_id);
$theater_stmt->execute();
$theaters = $theater_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - MyKisah</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e293b;
            --accent: #CEF3F8;
            --text-dark: #1e293b;
            --text-light: #94a3b8;
            --white: #ffffff;
            --youtube-red: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary);
            color: var(--white);
            overflow-x: hidden;
        }

        /* --- HEADER (Glassmorphism) --- */
        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        nav { display: flex; align-items: center; gap: 24px; }

        nav a {
            text-decoration: none;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
        }
        nav a:hover { color: var(--accent); }

        .auth-btn {
            background: var(--accent);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 99px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .auth-btn:hover { transform: scale(1.05); }

        /* --- MOVIE HERO SECTION --- */
        .movie-hero {
            position: relative;
            padding-top: 120px;
            padding-bottom: 80px;
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Backdrop Blur Effect */
        .hero-backdrop {
            position: absolute;
            inset: 0;
            background-image: url('<?php echo htmlspecialchars($movie['poster_url']); ?>');
            background-size: cover;
            background-position: center top;
            filter: blur(60px) brightness(0.3);
            z-index: 1;
            transform: scale(1.2); /* Zoom in agar blur tidak ada border putih */
        }
        
        .hero-backdrop::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, transparent 0%, var(--primary) 90%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1200px;
            padding: 0 5%;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 60px;
            align-items: start;
        }

        .poster-wrapper {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.6);
            aspect-ratio: 2/3;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .poster {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .poster-wrapper:hover .poster {
            transform: scale(1.03);
        }

        .movie-info {
            color: var(--white);
            padding-top: 20px;
        }

        .movie-title {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            background: linear-gradient(45deg, #fff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .movie-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            margin-bottom: 32px;
            font-size: 15px;
            color: rgba(255,255,255,0.9);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rating-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 700;
            color: var(--accent);
        }

        /* --- BUTTONS --- */
        .action-buttons {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
        }

        .trailer-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--youtube-red);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
            font-family: 'Poppins', sans-serif;
        }
        .trailer-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(239, 68, 68, 0.5);
        }

        .scroll-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .scroll-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .description {
            font-size: 16px;
            line-height: 1.8;
            color: rgba(255,255,255,0.75);
            margin-bottom: 30px;
            max-width: 750px;
        }

        /* --- THEATERS SECTION --- */
        .theaters-section {
            position: relative;
            z-index: 3;
            max-width: 1200px;
            margin: -50px auto 0; /* Naik sedikit ke atas */
            padding: 0 5% 100px 5%;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--white);
        }
        .section-title::before {
            content: '';
            width: 6px;
            height: 32px;
            background: var(--accent);
            border-radius: 4px;
        }

        .theater-card {
            background: var(--secondary);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            transition: transform 0.3s ease;
        }

        .theater-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255,255,255,0.15);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .theater-header {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .theater-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 6px;
        }

        .theater-location {
            font-size: 14px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 16px;
        }

        .time-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .time-button:hover {
            background: var(--accent);
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(206, 243, 248, 0.2);
        }

        .time-button:hover .time { color: var(--primary); font-weight: 800; }
        .time-button:hover .price { color: rgba(15, 23, 42, 0.7); }

        .time {
            font-size: 18px;
            font-weight: 600;
            color: var(--white);
            margin-bottom: 4px;
        }

        .price {
            font-size: 12px;
            color: var(--text-light);
        }

        .no-schedule {
            padding: 40px;
            text-align: center;
            color: var(--text-light);
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            font-size: 14px;
            border: 1px dashed rgba(255,255,255,0.1);
        }

        /* --- VIDEO MODAL --- */
        .video-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .video-modal.active { display: flex; opacity: 1; }

        .video-container {
            width: 90%;
            max-width: 1000px;
            aspect-ratio: 16/9;
            background: #000;
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(0,0,0,0.8);
            transform: scale(0.8);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .video-modal.active .video-container { transform: scale(1); }

        .close-video {
            position: absolute;
            top: -50px;
            right: -10px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            background: none;
            border: none;
            transition: transform 0.2s;
        }
        .close-video:hover { transform: rotate(90deg); color: var(--accent); }

        iframe { width: 100%; height: 100%; border: none; }

        /* --- RESPONSIVE --- */
        @media (max-width: 900px) {
            .movie-hero { padding-top: 100px; min-height: auto; }
            .hero-content { grid-template-columns: 1fr; gap: 40px; text-align: center; }
            .poster-wrapper { max-width: 280px; margin: 0 auto; }
            .movie-title { font-size: 36px; margin-bottom: 16px; }
            .movie-meta { justify-content: center; gap: 16px; font-size: 13px; }
            .action-buttons { justify-content: center; }
            .description { margin: 0 auto 30px auto; font-size: 14px; }
            .theaters-section { margin-top: 0; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <a href="index.php" class="logo">MyKisah</a>
        <nav>
            <a href="index.php">Home</a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php">Tiket</a>
                <a href="logout.php" class="auth-btn">Logout</a>
            <?php else: ?>
                <a href="login.php" class="auth-btn">Masuk</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Hero Section -->
    <div class="movie-hero">
        <div class="hero-backdrop"></div>
        
        <div class="hero-content">
            <div class="poster-wrapper">
                <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" 
                     alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                     class="poster">
            </div>
            
            <div class="movie-info">
                <h1 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h1>
                
                <div class="movie-meta">
                    <span class="rating-badge"><?php echo htmlspecialchars($movie['rating']); ?></span>
                    <div class="meta-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo $movie['duration']; ?> menit
                    </div>
                    <div class="meta-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 2l20 20"/><path d="M22 2L2 22"/><path d="M12 2v20"/></svg>
                        <?php echo htmlspecialchars($movie['genre']); ?>
                    </div>
                </div>

                <div class="action-buttons">
                    <?php if (!empty($movie['trailer_url'])): ?>
                        <button onclick="openTrailer()" class="trailer-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            Tonton Trailer
                        </button>
                    <?php endif; ?>

                    <button onclick="document.getElementById('schedule-section').scrollIntoView({behavior: 'smooth'})" class="scroll-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        Lihat Jadwal
                    </button>
                </div>

                <p class="description">
                    <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Theaters & Schedule Section -->
    <div id="schedule-section" class="theaters-section">
        <h2 class="section-title">Pilih Bioskop & Jadwal</h2>
        
        <?php if ($theaters->num_rows > 0): ?>
            <?php while ($theater = $theaters->fetch_assoc()): ?>
                <div class="theater-card">
                    <div class="theater-header">
                        <div class="theater-name"><?php echo htmlspecialchars($theater['name']); ?></div>
                        <div class="theater-location">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?php echo htmlspecialchars($theater['location']); ?>
                        </div>
                    </div>
                    
                    <?php
                    $schedule_sql = "SELECT s.*, h.hall_name 
                                    FROM schedules s
                                    INNER JOIN halls h ON s.hall_id = h.hall_id
                                    WHERE h.theater_id = ? 
                                    AND s.movie_id = ? 
                                    AND s.status = 'active'
                                    AND s.show_date >= CURDATE()
                                    ORDER BY s.show_date, s.show_time";
                    $schedule_stmt = $db->prepare($schedule_sql);
                    $schedule_stmt->bind_param("ii", $theater['theater_id'], $movie_id);
                    $schedule_stmt->execute();
                    $schedules = $schedule_stmt->get_result();
                    ?>
                    
                    <?php if ($schedules->num_rows > 0): ?>
                        <div class="schedule-grid">
                            <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                <a href="select-seat.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" 
                                   class="time-button">
                                    <span class="time">
                                        <?php echo date('H:i', strtotime($schedule['show_time'])); ?>
                                    </span>
                                    <span class="price">
                                        <?php echo formatPrice($schedule['price']); ?>
                                    </span>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-schedule">Jadwal belum tersedia di bioskop ini.</div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="theater-card" style="text-align:center; padding: 60px;">
                <p style="color:var(--text-light); font-size:16px;">Belum ada jadwal tayang untuk film ini di semua bioskop.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- VIDEO MODAL CONTAINER -->
    <div id="videoModal" class="video-modal" onclick="closeTrailer()">
        <div class="video-container" onclick="event.stopPropagation()">
            <button class="close-video" onclick="closeTrailer()">×</button>
            <!-- Iframe src akan diisi via JS saat tombol diklik agar tidak berat loading awal -->
            <iframe id="youtubeFrame" src="" allowfullscreen allow="autoplay"></iframe>
        </div>
    </div>

    <script>
        const embedUrl = "<?php echo $embed_url; ?>?autoplay=1&rel=0"; 

        function openTrailer() {
            const modal = document.getElementById('videoModal');
            const iframe = document.getElementById('youtubeFrame');
            
            modal.classList.add('active');
            iframe.src = embedUrl; // Set src saat dibuka
            document.body.style.overflow = 'hidden'; // Matikan scroll body
        }

        function closeTrailer() {
            const modal = document.getElementById('videoModal');
            const iframe = document.getElementById('youtubeFrame');
            
            modal.classList.remove('active');
            iframe.src = "";
            document.body.style.overflow = 'auto';
        }
        
        // Close on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeTrailer();
            }
        });
    </script>
</body>
</html>