<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google.php';
require_once __DIR__ . '/../includes/google_calendar.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* -------------------------------------------------
   CHECK CONNECTION STATUS
------------------------------------------------- */

$stmt = $pdo->query("SELECT google_refresh_token, google_calendar_connected_at FROM company_settings ORDER BY id ASC LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$is_connected = !empty($company['google_refresh_token']);


/* -------------------------------------------------
   HANDLE NEW APPOINTMENT
------------------------------------------------- */

$booking_error = '';
$booking_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {

    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 60);
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($title === '' || $date === '' || $start_time === '') {

        $booking_error = 'Please fill in the title, date, and start time.';

    } else {

        $start_datetime = $date . ' ' . $start_time . ':00';
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . " +{$duration_minutes} minutes"));

        $result = google_create_event($pdo, $title, $notes, $start_datetime, $end_datetime, $location);

        if ($result['success']) {
            $booking_success = 'Appointment booked and added to your Google Calendar.';
        } else {
            $booking_error = $result['error'];
        }

    }

}


/* -------------------------------------------------
   GET UPCOMING APPOINTMENTS (next 14 days)
------------------------------------------------- */

$upcoming = [];

if ($is_connected) {
    $upcoming = google_list_events($pdo, 'now', '+14 days');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - SecuTech Quotation System</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f5f7;
            color: #222;
        }

        .header {
            background: #111;
            color: white;
            padding: 14px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 20px;
        }

        .header-logo {
            width: 45px;
            height: auto;
            display: block;
        }

        .header a {
            color: white;
            text-decoration: none;
        }

        .container {
            max-width: 800px;
            margin: 45px auto;
            padding: 0 25px;
        }

        h2 {
            margin-top: 0;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .connect-box {
            text-align: center;
            padding: 40px 20px;
        }

        .connect-btn {
            display: inline-block;
            background: #172d4d;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 15px;
            margin-top: 15px;
        }

        .connected-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #dff5df;
            color: #216b21;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
            font-size: 14px;
        }

        input[type=text],
        input[type=date],
        input[type=time],
        input[type=number],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
        }

        textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            margin-top: 22px;
            background: #172d4d;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .error {
            background: #ffe1e1;
            color: #a00000;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .success {
            background: #dff5df;
            color: #216b21;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .appt-item {
            padding: 14px 0;
            border-bottom: 1px solid #eee;
        }

        .appt-item:last-child {
            border-bottom: none;
        }

        .appt-time {
            font-weight: bold;
            color: #172d4d;
            font-size: 13px;
        }

        .appt-title {
            font-size: 15px;
            margin-top: 2px;
        }

        .appt-location {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }

        .empty {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>

<div class="header">
    <a href="../dashboard.php" class="brand-link">
        <img src="../assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>
    <div style="display:flex; align-items:center; gap:20px;">
        <a href="../dashboard.php">Home</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <h2>Appointments</h2>

    <?php if (isset($_GET['google_error'])): ?>
        <div class="error">Google Calendar error: <?= e($_GET['google_error']) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['connected'])): ?>
        <div class="success">Google Calendar connected successfully.</div>
    <?php endif; ?>


    <?php if (!$is_connected): ?>

        <div class="card connect-box">
            <h3>Connect Google Calendar</h3>
            <p style="color:#666;">
                Book appointments straight into your own Google Calendar, with all
                your normal reminders and notifications.
            </p>
            <a href="../google/connect.php" class="connect-btn">
                Connect Google Calendar
            </a>
        </div>

    <?php else: ?>

        <div class="connected-badge">
            ✓ Google Calendar connected
        </div>

        <div class="card">

            <h3>Book an Appointment</h3>

            <?php if ($booking_error !== ''): ?>
                <div class="error"><?= e($booking_error) ?></div>
            <?php endif; ?>

            <?php if ($booking_success !== ''): ?>
                <div class="success"><?= e($booking_success) ?></div>
            <?php endif; ?>

            <form method="POST">

                <label>Title</label>
                <input type="text" name="title" placeholder="e.g. Site survey - Rainbow Electrical" required>

                <div class="form-row">
                    <div>
                        <label>Date</label>
                        <input type="date" name="date" required>
                    </div>
                    <div>
                        <label>Start Time</label>
                        <input type="time" name="start_time" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" value="60" min="15" step="15">
                    </div>
                    <div>
                        <label>Location</label>
                        <input type="text" name="location" placeholder="Optional">
                    </div>
                </div>

                <label>Notes</label>
                <textarea name="notes" placeholder="Optional"></textarea>

                <button type="submit" name="book_appointment" class="btn">
                    Book Appointment
                </button>

            </form>

        </div>

        <div class="card">

            <h3>Upcoming (next 14 days)</h3>

            <?php if (empty($upcoming)): ?>

                <p class="empty">No appointments in the next two weeks.</p>

            <?php else: ?>

                <?php foreach ($upcoming as $appt): ?>

                    <?php
                    $start_ts = strtotime($appt['start']);
                    $formatted = $start_ts ? date('D, j M - g:i A', $start_ts) : $appt['start'];
                    ?>

                    <div class="appt-item">
                        <div class="appt-time"><?= e($formatted) ?></div>
                        <div class="appt-title"><?= e($appt['summary']) ?></div>
                        <?php if (!empty($appt['location'])): ?>
                            <div class="appt-location">📍 <?= e($appt['location']) ?></div>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
