<?php
session_start();

// Check if user is logged in and is a dealer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    header("Location: login.php");
    exit;
}

// Database configuration
$host = '127.0.0.1';
$dbname = 'foodsale';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get dealer_id for current user
try {
    $stmt = $pdo->prepare("SELECT dealer_id FROM dealers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $dealer = $stmt->fetch(PDO::FETCH_ASSOC);
    $dealer_id = $dealer['dealer_id'];
} catch (Exception $e) {
    die("Error finding dealer: " . $e->getMessage());
}

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE table_bookings SET status = ?, updated_at = NOW() WHERE booking_id = ? AND dealer_id = ?");
        $stmt->execute([$new_status, $booking_id, $dealer_id]);
        $success = "Booking status updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating booking: " . $e->getMessage();
    }
}

// Fetch bookings for this dealer with dish details
try {
    $stmt = $pdo->prepare("SELECT b.*, l.title as dish_name, l.price as dish_price
                          FROM table_bookings b
                          LEFT JOIN food_listings l ON b.dish_id = l.listing_id
                          WHERE b.dealer_id = ?
                          ORDER BY b.created_at DESC");
    $stmt->execute([$dealer_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bookings = [];
    $error = "Error fetching bookings: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Dealer Panel</title>
    <link rel="stylesheet" href="styles/dealer-panel.css">
    <style>
        .bookings-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .booking-card { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .booking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .status-badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .booking-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .detail-item { display: flex; flex-direction: column; }
        .detail-label { font-size: 0.8rem; color: #666; margin-bottom: 0.2rem; }
        .detail-value { font-weight: 500; }
        .booking-actions { display: flex; gap: 0.5rem; margin-top: 1rem; align-items: center; }
        .btn-action { padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 500; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-small { padding: 0.3rem 0.8rem; font-size: 0.8rem; }
        .btn-action:hover { opacity: 0.9; transform: translateY(-1px); }
        .dish-info { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
        .special-requests { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
        .special-requests p { margin: 0.5rem 0; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; }
        .status-text { color: #666; font-style: italic; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { margin: 0 0 0.5rem 0; color: #333; }
        .page-header p { margin: 0; color: #666; }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="logo">📋 Manage Bookings</div>
            <div class="nav-links">
                <a href="dealer-panel.php">Dashboard</a>
                <a href="my-dishes.php">My Dishes</a>
                <a href="business-profile.php">Profile</a>
                <a href="manage-bookings.php" class="active">Bookings</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="bookings-container">
            <div class="page-header">
                <h1>Table Bookings</h1>
                <p>Manage customer table reservations</p>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="bookings-list">
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <h3>No bookings yet</h3>
                        <p>Customer table reservations will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div>
                                    <h3>Booking #<?= $booking['booking_id'] ?></h3>
                                    <span class="status-badge status-<?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </div>
                                <div class="booking-date">
                                    <?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?>
                                </div>
                            </div>

                            <div class="booking-details">
                                <div class="detail-item">
                                    <span class="detail-label">Customer Name</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['customer_name']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['customer_email']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['customer_phone']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Date & Time</span>
                                    <span class="detail-value">
                                        <?= date('M j, Y', strtotime($booking['booking_date'])) ?> at 
                                        <?= date('g:i A', strtotime($booking['booking_time'])) ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Party Size</span>
                                    <span class="detail-value"><?= $booking['party_size'] ?> people</span>
                                </div>
                            </div>

                            <?php if ($booking['special_requests']): ?>
                                <div class="special-requests">
                                    <span class="detail-label">Special Requests</span>
                                    <p><?= htmlspecialchars($booking['special_requests']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($booking['dish_name']): ?>
                                <div class="dish-info">
                                    <div>
                                        <span class="detail-label">Interested in</span>
                                        <span class="detail-value"><?= htmlspecialchars($booking['dish_name']) ?></span>
                                        <?php if ($booking['dish_price']): ?>
                                            <span class="detail-price">$<?= number_format($booking['dish_price'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($booking['status'] === 'pending'): ?>
                                <div class="booking-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" name="update_status" class="btn-action btn-approve">
                                            ✅ Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" name="update_status" class="btn-action btn-reject">
                                            ❌ Reject
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                                <div class="booking-actions">
                                    <span class="status-text">✅ Approved - Customer notified</span>
                                    <form method="POST" style="display: inline; margin-left: 1rem;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" name="update_status" class="btn-action btn-reject btn-small">
                                            Cancel Approval
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($booking['status'] === 'rejected'): ?>
                                <div class="booking-actions">
                                    <span class="status-text">❌ Rejected</span>
                                    <form method="POST" style="display: inline; margin-left: 1rem;">
                                        <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" name="update_status" class="btn-action btn-approve btn-small">
                                            Approve Now
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>


