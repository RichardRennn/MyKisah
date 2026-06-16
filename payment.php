<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['payment_method'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $selected_seats = json_decode($_POST['selected_seats'], true);
    
    // Store in session for payment page
    $_SESSION['booking_data'] = [
        'schedule_id' => $schedule_id,
        'selected_seats' => $selected_seats
    ];
}

// Check if booking data exists
if (!isset($_SESSION['booking_data'])) {
    redirect('index.php');
}

$booking_data = $_SESSION['booking_data'];
$schedule_id = $booking_data['schedule_id'];
$selected_seats = $booking_data['selected_seats'];

// Fetch schedule details
$sql = "SELECT s.*, m.title, m.poster_url, h.hall_name, t.name as theater_name
        FROM schedules s
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        INNER JOIN theaters t ON h.theater_id = t.theater_id
        WHERE s.schedule_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

$total_seats = count($selected_seats);
$total_price = $total_seats * $schedule['price'];

// Process payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    $booking_code = generateBookingCode();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert booking
        $insert_booking = "INSERT INTO bookings (user_id, schedule_id, booking_code, total_seats, total_price, payment_method, payment_status) 
                           VALUES (?, ?, ?, ?, ?, ?, 'paid')";
        $stmt = $db->prepare($insert_booking);
        $stmt->bind_param("iisids", $_SESSION['user_id'], $schedule_id, $booking_code, $total_seats, $total_price, $payment_method);
        $stmt->execute();
        $booking_id = $conn->insert_id;
        
        // Insert booking seats
        foreach ($selected_seats as $seat) {
            $insert_seat = "INSERT INTO booking_seats (booking_id, seat_id, status) VALUES (?, ?, 'confirmed')";
            $stmt = $db->prepare($insert_seat);
            $stmt->bind_param("ii", $booking_id, $seat['id']);
            $stmt->execute();
        }
        
        // Update available seats
        $update_schedule = "UPDATE schedules SET available_seats = available_seats - ? WHERE schedule_id = ?";
        $stmt = $db->prepare($update_schedule);
        $stmt->bind_param("ii", $total_seats, $schedule_id);
        $stmt->execute();
        
        $conn->commit();
        
        // Clear booking data
        unset($_SESSION['booking_data']);
        
        // Redirect to confirmation
        redirect('confirmation.php?booking_code=' . $booking_code);
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Terjadi kesalahan dalam pemrosesan pembayaran. Silakan coba lagi.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - MyKisah</title>
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
            padding-top: 80px;
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

        /* --- CONTAINER --- */
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--accent);
            border-radius: 4px;
        }

        /* --- CARDS --- */
        .card {
            background: var(--secondary);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        /* Summary Styling */
        .movie-header {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px dashed var(--border);
        }

        .poster-thumb {
            width: 80px;
            height: 120px;
            border-radius: 8px;
            object-fit: cover;
        }

        .movie-info h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--white);
        }

        .movie-info p {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 4px;
        }

        .summary-details {
            display: grid;
            gap: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--text-light);
        }

        .summary-value {
            font-weight: 600;
            color: var(--white);
            text-align: right;
        }

        .seats-display {
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
            border: 1px solid rgba(255,255,255,0.1);
        }

        .total-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 16px;
            color: var(--white);
        }

        .total-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent);
            text-shadow: 0 0 20px rgba(206, 243, 248, 0.2);
        }

        /* --- PAYMENT METHODS --- */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .payment-option {
            position: relative;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .payment-option:hover {
            background: rgba(255,255,255,0.06);
            transform: translateY(-2px);
            border-color: rgba(255,255,255,0.2);
        }

        .payment-option.selected {
            border-color: var(--accent);
            background: rgba(206, 243, 248, 0.05);
            box-shadow: 0 0 0 1px var(--accent);
        }

        /* Hide Radio Button */
        .payment-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        /* Custom Radio Indicator */
        .radio-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .radio-indicator::after {
            content: '';
            width: 10px;
            height: 10px;
            background: var(--accent);
            border-radius: 50%;
            transform: scale(0);
            transition: transform 0.2s;
        }

        .payment-option.selected .radio-indicator {
            border-color: var(--accent);
        }

        .payment-option.selected .radio-indicator::after {
            transform: scale(1);
        }

        .payment-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--white);
            margin-bottom: 2px;
        }

        .payment-desc {
            font-size: 12px;
            color: var(--text-light);
        }

        /* --- BUTTONS --- */
        .btn-pay {
            width: 100%;
            padding: 18px;
            background: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(206, 243, 248, 0.4);
        }

        /* --- ALERT --- */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
            .movie-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .summary-details {
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">MyKisah</a>
    </header>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="payment-form">
            
            <!-- Ringkasan Pesanan -->
            <div class="card">
                <div class="section-title">Ringkasan Pesanan</div>
                
                <div class="movie-header">
                    <img src="<?php echo htmlspecialchars($schedule['poster_url']); ?>" alt="Poster" class="poster-thumb">
                    <div class="movie-info">
                        <h3><?php echo htmlspecialchars($schedule['title']); ?></h3>
                        <p>📍 <?php echo htmlspecialchars($schedule['theater_name']); ?> - <?php echo htmlspecialchars($schedule['hall_name']); ?></p>
                        <p>🗓️ <?php echo formatDate($schedule['show_date']); ?> • <?php echo date('H:i', strtotime($schedule['show_time'])); ?></p>
                    </div>
                </div>

                <div class="summary-details">
                    <div class="summary-row">
                        <span>Kursi Dipilih</span>
                        <div class="seats-display">
                            <?php foreach ($selected_seats as $seat): ?>
                                <span class="seat-badge"><?php echo htmlspecialchars($seat['name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="summary-row">
                        <span>Jumlah Tiket</span>
                        <span class="summary-value"><?php echo $total_seats; ?> x <?php echo formatPrice($schedule['price']); ?></span>
                    </div>
                    
                    <div class="total-section">
                        <span class="total-label">Total Pembayaran</span>
                        <span class="total-price"><?php echo formatPrice($total_price); ?></span>
                    </div>
                </div>
            </div>

            <!-- Metode Pembayaran -->
            <div class="card">
                <div class="section-title">Metode Pembayaran</div>
                <div class="payment-methods">
                    <!-- Cash -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="cash" required>
                        <div class="radio-indicator"></div>
                        <div class="payment-icon">💵</div>
                        <div class="payment-info">
                            <div class="payment-name">Tunai</div>
                            <div class="payment-desc">Bayar di kasir bioskop</div>
                        </div>
                    </label>

                    <!-- Credit Card -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="credit_card" required>
                        <div class="radio-indicator"></div>
                        <div class="payment-icon">💳</div>
                        <div class="payment-info">
                            <div class="payment-name">Kartu Kredit</div>
                            <div class="payment-desc">Visa, Mastercard, JCB</div>
                        </div>
                    </label>

                    <!-- Debit Card -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="debit_card" required>
                        <div class="radio-indicator"></div>
                        <div class="payment-icon">🏦</div>
                        <div class="payment-info">
                            <div class="payment-name">Transfer Bank</div>
                            <div class="payment-desc">BCA, Mandiri, BNI</div>
                        </div>
                    </label>

                    <!-- E-Wallet -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="e-wallet" required>
                        <div class="radio-indicator"></div>
                        <div class="payment-icon">📱</div>
                        <div class="payment-info">
                            <div class="payment-name">E-Wallet</div>
                            <div class="payment-desc">GoPay, OVO, DANA</div>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-pay">
                Bayar Sekarang
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>

        </form>
    </div>

    <script>
        // Javascript untuk efek Selected pada Payment Option
        const options = document.querySelectorAll('.payment-option');
        
        options.forEach(option => {
            option.addEventListener('click', () => {
                // Remove selected class from all
                options.forEach(opt => opt.classList.remove('selected'));
                // Add selected class to clicked
                option.classList.add('selected');
                // Check radio button
                const radio = option.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
    </script>
</body>
</html>