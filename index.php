<?php
require_once 'includes/config.php';

// Fetch Now Playing (Ambil 15 film)
$now_playing_sql = "SELECT * FROM movies WHERE status = 'now_playing' ORDER BY release_date DESC LIMIT 15";
$now_playing_result = $db->query($now_playing_sql);
$now_playing_movies = [];
while ($row = $now_playing_result->fetch_assoc()) {
    $now_playing_movies[] = $row;
}

// Fetch Coming Soon (Ambil 15 film)
$coming_soon_sql = "SELECT * FROM movies WHERE status = 'coming_soon' ORDER BY release_date ASC LIMIT 15";
$coming_soon_result = $db->query($coming_soon_sql);
$coming_soon_movies = [];
while ($row = $coming_soon_result->fetch_assoc()) {
    $coming_soon_movies[] = $row;
}

$featured_movie = !empty($now_playing_movies) ? $now_playing_movies[1] : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MyKisah - Bioskop Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary: #0f172a;
            --accent: #CEF3F8;
            --text-dark: #1e293b;
            --white: #ffffff;
            --overlay-gradient: linear-gradient(to top, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.7) 50%, transparent 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: #0f172a; /* Dark background */
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
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
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

        /* --- HERO SECTION --- */
        .hero {
            position: relative;
            width: 100%;
            height: 85vh;
            min-height: 600px;
            display: flex;
            align-items: center;
            padding: 0 5%;
            overflow: hidden;
            margin-bottom: 60px;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center top;
            z-index: 1;
            opacity: 0.6;
            mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
        }
        
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, #0f172a 0%, rgba(15, 23, 42, 0.4) 60%, transparent 100%);
            z-index: 2;
        }

        .hero-content {
            position: relative;
            z-index: 3;
            max-width: 600px;
            padding-top: 60px;
        }

        .hero-tag {
            color: var(--accent);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            display: block;
        }

        .hero-title {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.1;
            background: linear-gradient(45deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-meta {
            display: flex;
            gap: 16px;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
            margin-bottom: 32px;
            align-items: center;
        }
        
        .meta-rating {
            border: 1px solid rgba(255,255,255,0.3);
            padding: 2px 8px;
            border-radius: 4px;
            color: #fff;
        }

        .hero-btn {
            background: var(--accent);
            color: var(--primary);
            padding: 16px 36px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(206, 243, 248, 0.2);
        }
        .hero-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 0 30px rgba(206, 243, 248, 0.4);
        }

        /* --- SECTIONS & SLIDER --- */
        section { margin-bottom: 80px; position: relative; }

        .section-header {
            padding: 0 5%;
            margin-bottom: 10px; /* Jarak antara judul dan slider */
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--white);
            position: relative;
            padding-left: 16px;
        }
        .section-title::before {
            content: '';
            position: absolute;
            left: 0; top: 4px; bottom: 4px; width: 4px;
            background: var(--accent);
            border-radius: 4px;
        }

        /* Navigasi Panah */
        .section-nav {
            display: flex;
            gap: 10px;
        }
        .nav-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .nav-btn:hover {
            background: var(--accent);
            color: var(--primary);
            border-color: var(--accent);
        }

        /* Container Scroll - FIX CLIPPING HERE */
        .slider-container {
            padding: 0 5%;
        }

        .movie-scroller {
            display: flex;
            gap: 24px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            
            /* PENTING: Padding besar untuk memberi ruang shadow dan scale */
            padding: 30px 10px 50px 10px; 
            
            margin: -20px -10px -40px -10px; /* Negatif margin agar padding tidak merusak layout luar */
            
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .movie-scroller::-webkit-scrollbar { display: none; }

        /* --- MOVIE CARD (Hover Reveal) --- */
        .movie-card {
            width: 220px;
            height: 330px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
            background: #1e293b;
            scroll-snap-align: start;
            transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .movie-card:hover {
            transform: translateY(-10px) scale(1.05);
            z-index: 10;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6); /* Shadow lebih besar */
        }

        .poster-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        /* Overlay Content - Hidden by default */
        .card-overlay {
            position: absolute;
            inset: 0;
            background: var(--overlay-gradient);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 20px;
            opacity: 0; /* Sembunyi */
            transition: all 0.3s ease;
            transform: translateY(20px);
        }

        /* Reveal on Hover */
        .movie-card:hover .card-overlay {
            opacity: 1;
            transform: translateY(0);
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1.3;
            color: #fff;
        }

        .card-meta {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 0;
        }

        /* Coming Soon Grayscale */
        .coming-soon-card {
            filter: grayscale(0.8);
            opacity: 0.8;
        }
        .coming-soon-card:hover {
            filter: grayscale(0);
            opacity: 1;
        }

        /* --- FOOTER --- */
        footer {
            background: #020617;
            padding: 60px 5%;
            text-align: center;
            color: rgba(255,255,255,0.4);
            font-size: 14px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .hero { height: 70vh; }
            .hero-title { font-size: 36px; }
            
            .movie-card { width: 160px; height: 240px; }
            .section-nav { display: none; } /* Hide arrow buttons on mobile */
            
            .card-overlay {
                opacity: 1;
                background: linear-gradient(to top, rgba(0,0,0,0.95), transparent);
                transform: translateY(0);
                padding: 12px;
            }
            .card-title { font-size: 14px; margin-bottom: 2px; }
            .card-meta { font-size: 10px; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header>
    <a href="index.php" class="logo">MyKisah</a>
    <nav>
        <?php if (isLoggedIn()): ?>
            <a href="profile.php">Tiket</a>
            <a href="profile.php" class="auth-btn">
                <?= htmlspecialchars(explode(' ', $_SESSION['username'])[0]); ?>
            </a>
        <?php else: ?>
            <a href="login.php" class="auth-btn">Masuk</a>
        <?php endif; ?>
    </nav>
</header>

<!-- Hero Section -->
<?php if ($featured_movie): ?>
<div class="hero">
    <div class="hero-bg" style="background-image: url('<?= htmlspecialchars($featured_movie['poster_url']) ?>');"></div>
    <div class="hero-content">
        <span class="hero-tag">FEATURED MOVIE</span>
        <h1 class="hero-title"><?= htmlspecialchars($featured_movie['title']) ?></h1>
        <div class="hero-meta">
            <span class="meta-rating"><?= htmlspecialchars($featured_movie['rating']) ?></span>
            <span><?= $featured_movie['duration'] ?> Menit</span>
            <span>•</span>
            <span><?= htmlspecialchars($featured_movie['genre']) ?></span>
        </div>
        <a href="movie-detail.php?id=<?= $featured_movie['movie_id'] ?>" class="hero-btn">
            Beli Tiket
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>
<?php endif; ?>

<main>
    <!-- Section: Sedang Tayang -->
    <?php if (!empty($now_playing_movies)): ?>
    <section>
        <div class="section-header">
            <h2 class="section-title">Sedang Tayang</h2>
            <div class="section-nav">
                <button class="nav-btn" onclick="scrollContainer('now-playing', -300)">←</button>
                <button class="nav-btn" onclick="scrollContainer('now-playing', 300)">→</button>
            </div>
        </div>
        
        <div class="slider-container">
            <div class="movie-scroller" id="now-playing">
                <?php foreach ($now_playing_movies as $m): ?>
                <a href="movie-detail.php?id=<?= $m['movie_id'] ?>" class="movie-card">
                    <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" class="poster-img" loading="lazy">
                    
                    <div class="card-overlay">
                        <h3 class="card-title"><?= htmlspecialchars($m['title']) ?></h3>
                        <div class="card-meta">
                            <?= htmlspecialchars(explode(',', $m['genre'])[0]) ?> • <?= htmlspecialchars($m['rating']) ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section: Segera Tayang -->
    <?php if (!empty($coming_soon_movies)): ?>
    <section>
        <div class="section-header">
            <h2 class="section-title">Segera Tayang</h2>
            <div class="section-nav">
                <button class="nav-btn" onclick="scrollContainer('coming-soon', -300)">←</button>
                <button class="nav-btn" onclick="scrollContainer('coming-soon', 300)">→</button>
            </div>
        </div>
        
        <div class="slider-container">
            <div class="movie-scroller" id="coming-soon">
                <?php foreach ($coming_soon_movies as $m): ?>
                <a href="movie-detail.php?id=<?= $m['movie_id'] ?>" class="movie-card coming-soon-card">
                    <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" class="poster-img" loading="lazy">
                    
                    <div class="card-overlay">
                        <h3 class="card-title"><?= htmlspecialchars($m['title']) ?></h3>
                        <div class="card-meta">
                            Rilis: <?= date('d M Y', strtotime($m['release_date'])) ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer>
    <p>&copy; 2025 MyKisah Cinema.</p>
    <p style="font-size: 12px; margin-top: 8px; color: rgba(255,255,255,0.2);">Designed for better experience.</p>
</footer>

<script>
    // Header Scroll Effect
    window.addEventListener('scroll', () => {
        const header = document.querySelector('header');
        if (window.scrollY > 50) {
            header.style.padding = '12px 5%';
            header.style.background = 'rgba(15, 23, 42, 0.9)';
        } else {
            header.style.padding = '20px 5%';
            header.style.background = 'rgba(15, 23, 42, 0.6)';
        }
    });

    // Smooth Scroll Button Function (With Loop Logic)
    function scrollContainer(id, amount) {
        const container = document.getElementById(id);
        const maxScroll = container.scrollWidth - container.clientWidth;
        
        // Logika Loop: Jika sudah di ujung kanan/kiri, lompat ke sisi sebaliknya
        if (amount > 0 && container.scrollLeft >= maxScroll - 10) {
            // Jika di ujung kanan & klik kanan -> Kembali ke Awal
            container.scrollTo({ left: 0, behavior: 'smooth' });
        } else if (amount < 0 && container.scrollLeft <= 10) {
            // Jika di ujung kiri & klik kiri -> Loncat ke Akhir
            container.scrollTo({ left: maxScroll, behavior: 'smooth' });
        } else {
            // Scroll Normal
            container.scrollBy({ left: amount, behavior: 'smooth' });
        }
    }
</script>

</body>
</html>