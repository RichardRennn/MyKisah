<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'select-seat.php?schedule_id=' . ($_GET['schedule_id'] ?? 0);
    redirect('login.php');
}

$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

// Fetch schedule details
$sql = "SELECT s.*, m.title, m.poster_url, m.duration, m.rating, 
               h.hall_name, t.name as theater_name, t.location
        FROM schedules s
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        INNER JOIN theaters t ON h.theater_id = t.theater_id
        WHERE s.schedule_id = ? AND s.status = 'active'";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('index.php');
}

$schedule = $result->fetch_assoc();

// Fetch seats
$seats_sql = "SELECT s.* FROM seats s WHERE s.hall_id = ? ORDER BY s.seat_row, s.seat_number";
$seats_stmt = $db->prepare($seats_sql);
$seats_stmt->bind_param("i", $schedule['hall_id']);
$seats_stmt->execute();
$seats_result = $seats_stmt->get_result();

// Organize seats
$seat_layout = [];
while ($seat = $seats_result->fetch_assoc()) {
    $seat_layout[$seat['seat_row']][] = $seat;
}

// Fetch booked seats
$booked_sql = "SELECT s.seat_id 
               FROM booking_seats bs
               INNER JOIN bookings b ON bs.booking_id = b.booking_id
               INNER JOIN seats s ON bs.seat_id = s.seat_id
               WHERE b.schedule_id = ? 
               AND bs.status = 'confirmed'";
$booked_stmt = $db->prepare($booked_sql);
$booked_stmt->bind_param("i", $schedule_id);
$booked_stmt->execute();
$booked_result = $booked_stmt->get_result();

$booked_seats = [];
while ($booked = $booked_result->fetch_assoc()) {
    $booked_seats[] = $booked['seat_id'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Pilih Kursi - <?php echo htmlspecialchars($schedule['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e293b;
            --accent: #CEF3F8;
            --accent-glow: rgba(206, 243, 248, 0.4);
            --text-dark: #1e293b;
            --text-light: #94a3b8;
            --white: #ffffff;
            --booked: #334155;
            --selected: #10b981;
            --vip: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary);
            color: var(--white);
            padding-top: 80px; /* Header space */
            min-height: 100vh;
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
        }
        nav a:hover { color: var(--white); }

        /* --- LAYOUT --- */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            align-items: start;
        }

        /* --- LEFT SIDE: MOVIE INFO & SEATS --- */
        
        /* Movie Info Bar */
        .movie-header {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .poster-thumb {
            width: 70px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .movie-meta h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .meta-details {
            color: var(--text-light);
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* Cinema Screen Area */
        .cinema-room {
            background: var(--secondary);
            border-radius: 24px;
            padding: 40px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .screen-container {
            margin-bottom: 60px;
            perspective: 500px;
        }

        .screen {
            height: 60px;
            width: 80%;
            margin: 0 auto;
            background: linear-gradient(to bottom, rgba(255,255,255,0.8), transparent);
            transform: rotateX(-30deg) scale(0.8);
            box-shadow: 0 30px 50px var(--accent-glow);
            border-radius: 12px;
            opacity: 0.5;
            position: relative;
        }
        
        .screen::after {
            content: 'LAYAR BIOSKOP';
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            letter-spacing: 4px;
            color: var(--text-light);
            opacity: 0.6;
        }

        /* Seats Grid */
        .seats-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
            overflow-x: auto; /* Untuk layar kecil */
            padding-bottom: 20px;
        }

        .row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .row-label {
            width: 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
            text-align: center;
            margin-right: 10px;
        }

        .seat {
            width: 36px;
            height: 36px;
            border-radius: 8px 8px 4px 4px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
        }

        /* Seat States */
        .seat:hover:not(.booked) {
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 0 10px var(--accent-glow);
            transform: scale(1.1);
            border-color: var(--white);
            z-index: 2;
        }

        .seat.selected {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--primary);
            font-weight: 700;
            box-shadow: 0 0 15px var(--accent-glow);
        }

        .seat.booked {
            background: var(--booked);
            border-color: transparent;
            color: rgba(255,255,255,0.1);
            cursor: not-allowed;
        }
        
        .seat.booked::after {
            content: '×';
            font-size: 18px;
        }

        .seat.vip {
            border-color: var(--vip);
            color: var(--vip);
        }
        .seat.vip.selected {
            background: var(--vip);
            color: var(--primary);
            border-color: var(--vip);
        }

        /* Legend */
        .legend {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-light);
        }

        .dot {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        /* --- RIGHT SIDE: SUMMARY SIDEBAR --- */
        .sidebar {
            position: sticky;
            top: 100px;
            background: var(--secondary);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5);
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed rgba(255,255,255,0.1);
        }

        .selected-list {
            min-height: 50px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .seat-badge {
            background: var(--accent);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .empty-msg {
            font-size: 13px;
            color: var(--text-light);
            font-style: italic;
        }

        .price-summary {
            background: rgba(0,0,0,0.2);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--text-light);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 18px;
            font-weight: 700;
            color: var(--white);
        }

        .btn-continue {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            opacity: 0.5;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-continue.active {
            opacity: 1;
            pointer-events: auto;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px var(--accent-glow);
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .container { grid-template-columns: 1fr; }
            .sidebar { position: fixed; bottom: 0; left: 0; right: 0; top: auto; border-radius: 20px 20px 0 0; z-index: 100; padding: 20px; }
            body { padding-bottom: 250px; }
        }
        
        @media (max-width: 480px) {
            .seat { width: 32px; height: 32px; font-size: 9px; }
            .row-label { font-size: 12px; margin-right: 6px; }
            .movie-header { flex-direction: column; align-items: center; text-align: center; }
            .cinema-room { padding: 30px 10px; }
        }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">MyKisah</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="profile.php">Tiket Saya</a>
        </nav>
    </header>

    <div class="container">
        <!-- Area Kiri: Info & Kursi -->
        <div class="main-area">
            
            <div class="movie-header">
                <img src="<?php echo htmlspecialchars($schedule['poster_url']); ?>" alt="Poster" class="poster-thumb">
                <div class="movie-meta">
                    <h1><?php echo htmlspecialchars($schedule['title']); ?></h1>
                    <div class="meta-details">
                        <span>📍 <?php echo htmlspecialchars($schedule['theater_name']); ?> • <?php echo htmlspecialchars($schedule['hall_name']); ?></span>
                        <span>🗓️ <?php echo formatDate($schedule['show_date']); ?> • 🕒 <?php echo date('H:i', strtotime($schedule['show_time'])); ?> WIB</span>
                    </div>
                </div>
            </div>

            <div class="cinema-room">
                <div class="screen-container">
                    <div class="screen"></div>
                </div>

                <div class="seats-grid">
                    <?php foreach ($seat_layout as $row => $seats): ?>
                        <div class="row">
                            <div class="row-label"><?php echo $row; ?></div>
                            <?php foreach ($seats as $seat): ?>
                                <?php
                                $is_booked = in_array($seat['seat_id'], $booked_seats);
                                $class = 'seat';
                                if ($is_booked) $class .= ' booked';
                                if ($seat['seat_type'] == 'vip') $class .= ' vip';
                                ?>
                                <div class="<?php echo $class; ?>" 
                                     data-seat-id="<?php echo $seat['seat_id']; ?>"
                                     data-seat-name="<?php echo $row . $seat['seat_number']; ?>"
                                     data-price="<?php echo $schedule['price']; ?>"
                                     <?php if (!$is_booked): ?>onclick="toggleSeat(this)"<?php endif; ?>>
                                    <?php echo $seat['seat_number']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="legend">
                    <div class="legend-item">
                        <div class="dot" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);"></div> Tersedia
                    </div>
                    <div class="legend-item">
                        <div class="dot" style="background: var(--accent);"></div> Dipilih
                    </div>
                    <div class="legend-item">
                        <div class="dot" style="background: var(--booked);"></div> Terisi
                    </div>
                    <div class="legend-item">
                        <div class="dot" style="border: 1px solid var(--vip);"></div> VIP
                    </div>
                </div>
            </div>
        </div>

        <!-- Area Kanan: Ringkasan Sticky -->
        <div class="sidebar">
            <div class="sidebar-title">Ringkasan Pesanan</div>
            
            <div class="selected-list" id="selected-seats-display">
                <span class="empty-msg">Silakan pilih kursi pada denah</span>
            </div>

            <div class="price-summary">
                <div class="summary-row">
                    <span>Tiket</span>
                    <span id="seat-count">0</span>
                </div>
                <div class="summary-row">
                    <span>Harga Satuan</span>
                    <span><?php echo formatPrice($schedule['price']); ?></span>
                </div>
                <div class="summary-total">
                    <span>Total Bayar</span>
                    <span id="total-price" style="color: var(--accent);">Rp 0</span>
                </div>
            </div>

            <form method="POST" action="payment.php" id="booking-form">
                <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                <input type="hidden" name="selected_seats" id="selected-seats-input">
                <button type="submit" class="btn-continue" id="continue-btn">
                    Lanjut Bayar 
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </form>
        </div>
    </div>

    <script>
        let selectedSeats = [];
        const pricePerSeat = <?php echo $schedule['price']; ?>;

        function toggleSeat(element) {
            const seatId = element.dataset.seatId;
            const seatName = element.dataset.seatName;

            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedSeats = selectedSeats.filter(s => s.id !== seatId);
            } else {
                element.classList.add('selected');
                selectedSeats.push({ id: seatId, name: seatName });
            }

            updateSummary();
        }

        function updateSummary() {
            const display = document.getElementById('selected-seats-display');
            const countEl = document.getElementById('seat-count');
            const totalEl = document.getElementById('total-price');
            const continueBtn = document.getElementById('continue-btn');
            const seatsInput = document.getElementById('selected-seats-input');

            // Sort seats by name for cleaner display
            selectedSeats.sort((a, b) => a.name.localeCompare(b.name, undefined, {numeric: true}));

            if (selectedSeats.length === 0) {
                display.innerHTML = '<span class="empty-msg">Silakan pilih kursi pada denah</span>';
                continueBtn.classList.remove('active');
            } else {
                display.innerHTML = selectedSeats.map(s => 
                    `<span class="seat-badge">${s.name}</span>`
                ).join('');
                continueBtn.classList.add('active');
            }

            countEl.textContent = selectedSeats.length + ' x';
            const total = selectedSeats.length * pricePerSeat;
            totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
            
            seatsInput.value = JSON.stringify(selectedSeats);
        }
    </script>
</body>
</html>