<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$booking_code = isset($_GET['booking_code']) ? $db->escape($_GET['booking_code']) : '';

if (empty($booking_code)) {
    redirect('index.php');
}

// Fetch booking details
$sql = "SELECT b.*, m.title, m.poster_url, h.hall_name, t.name as theater_name, t.location,
               s.show_date, s.show_time
        FROM bookings b
        INNER JOIN schedules s ON b.schedule_id = s.schedule_id
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        INNER JOIN theaters t ON h.theater_id = t.theater_id
        WHERE b.booking_code = ? AND b.user_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('index.php');
}

$booking = $result->fetch_assoc();

// Fetch booked seats
$seats_sql = "SELECT s.seat_row, s.seat_number
              FROM booking_seats bs
              INNER JOIN seats s ON bs.seat_id = s.seat_id
              WHERE bs.booking_id = ?
              ORDER BY s.seat_row, s.seat_number";
$seats_stmt = $db->prepare($seats_sql);
$seats_stmt->bind_param("i", $booking['booking_id']);
$seats_stmt->execute();
$seats_result = $seats_stmt->get_result();

$booked_seats = [];
while ($seat = $seats_result->fetch_assoc()) {
    $booked_seats[] = $seat['seat_row'] . $seat['seat_number'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pemesanan - MyKisah</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e293b;
            --accent: #CEF3F8;
            --accent-dark: #22d3ee;
            --text-dark: #1e293b;
            --text-light: #94a3b8;
            --white: #ffffff;
            --success-bg: rgba(16, 185, 129, 0.1);
            --success-text: #10b981;
            --border: rgba(255,255,255,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary);
            color: var(--white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        /* --- SUCCESS ANIMATION --- */
        .success-icon-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-bg);
            color: var(--success-text);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* --- CARD --- */
        .card {
            background: var(--secondary);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Ticket Notch Effect (Optional Decor) */
        .card::before, .card::after {
            content: '';
            position: absolute;
            top: 170px;
            width: 20px;
            height: 20px;
            background: var(--primary);
            border-radius: 50%;
        }
        .card::before { left: -10px; }
        .card::after { right: -10px; }

        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--white);
        }

        .subtitle {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* --- BOOKING CODE --- */
        .booking-code-box {
            background: linear-gradient(135deg, rgba(206, 243, 248, 0.1) 0%, rgba(34, 211, 238, 0.1) 100%);
            border: 1px dashed var(--accent);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .booking-code-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent);
            margin-bottom: 4px;
            font-weight: 600;
        }

        .booking-code-value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 4px;
            color: var(--white);
            text-shadow: 0 0 20px rgba(206, 243, 248, 0.3);
        }

        /* --- DETAILS LIST --- */
        .booking-details {
            text-align: left;
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 14px;
        }

        .detail-row:last-child { border-bottom: none; }

        .detail-label { color: var(--text-light); }
        .detail-value { font-weight: 600; color: var(--white); text-align: right; }

        .seats-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
            max-width: 60%;
        }

        .seat-badge {
            background: rgba(255,255,255,0.1);
            color: var(--accent);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .total-amount {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
        }

        /* --- INFO BOX --- */
        .info-box {
            background: rgba(245, 158, 11, 0.1); /* Warning color tint */
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 30px;
            text-align: left;
            display: flex;
            gap: 12px;
        }

        .info-icon { font-size: 20px; }
        
        .info-content h4 {
            font-size: 14px;
            font-weight: 700;
            color: #fbbf24;
            margin-bottom: 4px;
        }
        
        .info-content p {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            line-height: 1.5;
        }

        /* --- BUTTONS --- */
        .buttons {
            display: grid;
            gap: 12px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--primary);
            box-shadow: 0 4px 15px rgba(206, 243, 248, 0.2);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(206, 243, 248, 0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }

        @media print {
            body { background: white; color: black; padding: 0; }
            .card { box-shadow: none; border: 1px solid #000; color: black; background: white; }
            .booking-code-box { border: 1px solid #000; background: none; }
            .booking-code-value { color: black; text-shadow: none; }
            .detail-value, h1, .subtitle { color: black !important; }
            .buttons, .info-box { display: none; }
            .detail-row { border-bottom: 1px solid #ddd; }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="success-icon-wrapper">
            <div class="success-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
        </div>

        <div class="card">
            <h1>Pemesanan Berhasil!</h1>
            <p class="subtitle">Tiket Anda telah terbit. Selamat menonton!</p>

            <div class="booking-code-box">
                <div class="booking-code-label">Kode Booking</div>
                <div class="booking-code-value"><?php echo htmlspecialchars($booking['booking_code']); ?></div>
            </div>

            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Film</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['title']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Lokasi</span>
                    <span class="detail-value">
                        <?php echo htmlspecialchars($booking['theater_name']); ?><br>
                        <span style="font-size:12px; font-weight:400; color:var(--text-light);"><?php echo htmlspecialchars($booking['hall_name']); ?></span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Jadwal</span>
                    <span class="detail-value">
                        <?php echo formatDate($booking['show_date']); ?><br>
                        <?php echo date('H:i', strtotime($booking['show_time'])); ?> WIB
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Kursi (<?php echo $booking['total_seats']; ?>)</span>
                    <div class="seats-list">
                        <?php foreach ($booked_seats as $seat): ?>
                            <span class="seat-badge"><?php echo htmlspecialchars($seat); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pembayaran</span>
                    <span class="detail-value"><?php echo ucwords(str_replace('_', ' ', $booking['payment_method'])); ?></span>
                </div>
                <div class="detail-row" style="border-top: 1px dashed rgba(255,255,255,0.2); margin-top: 10px; padding-top: 15px;">
                    <span class="detail-label">Total Bayar</span>
                    <span class="detail-value total-amount"><?php echo formatPrice($booking['total_price']); ?></span>
                </div>
            </div>

            <div class="info-box">
                <div class="info-icon">💡</div>
                <div class="info-content">
                    <h4>Info Penting</h4>
                    <p>Tunjukkan kode booking ini atau scan QR Code pada tiket digital di menu "Tiket Saya" kepada petugas bioskop untuk masuk studio.</p>
                </div>
            </div>

            <div class="buttons">
                <a href="profile.php" class="btn btn-primary">Lihat Tiket Saya</a>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <button onclick="window.print()" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Cetak
                    </button>
                    <a href="index.php" class="btn btn-secondary">Beranda</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html> 