<?php
session_start();

// Debug session info
echo "<!-- Debug Info: ";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . " | ";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . " | ";
echo "Session data: " . print_r($_SESSION, true);
echo " -->";

// Check if user is logged in as business owner
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

// Get dealer_id from session user_id
try {
    $stmt = $pdo->prepare("SELECT dealer_id FROM dealers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $dealer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dealer) {
        $dealer_id = $dealer['dealer_id'];
    } else {
        die("Dealer account not found. Please contact administrator.");
    }
} catch (Exception $e) {
    die("Error finding dealer account: " . $e->getMessage());
}

// Handle booking status updates with email notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['status'];
    
    try {
        // Get booking details for email
        $stmt = $pdo->prepare("SELECT b.*, l.title as dish_name, l.price as dish_price, 
                              d.business_name, d.business_email, d.business_phone, d.business_address,
                              i.image_url as dish_image
                              FROM table_bookings b
                              LEFT JOIN food_listings l ON b.dish_id = l.listing_id
                              LEFT JOIN dealers d ON b.dealer_id = d.dealer_id
                              LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                              WHERE b.booking_id = ? AND b.dealer_id = ?");
        $stmt->execute([$booking_id, $dealer_id]);
        $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking_details) {
            // Update booking status first
            $stmt = $pdo->prepare("UPDATE table_bookings SET status = ?, updated_at = NOW() WHERE booking_id = ? AND dealer_id = ?");
            $stmt->execute([$new_status, $booking_id, $dealer_id]);
            
            // Prepare email
            $to = $booking_details['customer_email'];
            $business_name = $booking_details['business_name'] ?: 'Restaurant';
            $customer_name = $booking_details['customer_name'] ?: 'Customer';
            
            if ($new_status === 'confirmed') {
                $subject = "✅ Booking Confirmed - " . $business_name;
                $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Booking Confirmed</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
    <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h1 style='color: #28a745; margin: 0; font-size: 28px;'>🎉 Booking Confirmed!</h1>
        </div>
        
        <p style='font-size: 16px; margin-bottom: 20px;'>Dear " . htmlspecialchars($customer_name) . ",</p>
        
        <p style='font-size: 16px; margin-bottom: 25px;'>Great news! Your table booking has been <strong style='color: #28a745;'>CONFIRMED</strong> by " . htmlspecialchars($business_name) . ".</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #28a745;'>
            <h3 style='margin-top: 0; color: #495057; font-size: 18px;'>📋 Booking Details</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Booking ID:</td><td style='padding: 8px 0;'>#" . $booking_details['booking_id'] . "</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Date & Time:</td><td style='padding: 8px 0;'>" . date('M j, Y', strtotime($booking_details['booking_date'])) . " at " . date('g:i A', strtotime($booking_details['booking_time'])) . "</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Party Size:</td><td style='padding: 8px 0;'>" . $booking_details['party_size'] . " people</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Restaurant:</td><td style='padding: 8px 0;'>" . htmlspecialchars($business_name) . "</td></tr>
                " . ($booking_details['business_address'] ? "<tr><td style='padding: 8px 0; font-weight: bold;'>Address:</td><td style='padding: 8px 0;'>" . htmlspecialchars($booking_details['business_address']) . "</td></tr>" : "") . "
                " . ($booking_details['business_phone'] ? "<tr><td style='padding: 8px 0; font-weight: bold;'>Phone:</td><td style='padding: 8px 0;'>" . htmlspecialchars($booking_details['business_phone']) . "</td></tr>" : "") . "
            </table>
        </div>";
        
        if ($booking_details['dish_name']) {
            $message .= "
        <div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #0056b3;'>
            <h3 style='margin-top: 0; color: #0056b3; font-size: 18px;'>🍽️ Dish of Interest</h3>
            <p style='margin: 10px 0; font-size: 16px;'><strong>" . htmlspecialchars($booking_details['dish_name']) . "</strong></p>
            <p style='margin: 10px 0; color: #28a745; font-weight: bold; font-size: 18px;'>Price: $" . number_format($booking_details['dish_price'], 2) . "</p>
        </div>";
        }
        
        if ($booking_details['special_requests']) {
            $message .= "
        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;'>
            <h3 style='margin-top: 0; color: #856404; font-size: 18px;'>📝 Special Requests</h3>
            <p style='margin: 0; color: #856404;'>" . htmlspecialchars($booking_details['special_requests']) . "</p>
        </div>";
        }
        
        $message .= "
        <div style='margin: 30px 0; padding: 20px; background: #e8f5e8; border-radius: 8px; text-align: center;'>
            <p style='margin: 0; font-size: 16px; color: #155724;'><strong>We look forward to serving you!</strong></p>
            <p style='margin: 10px 0 0 0; color: #155724;'>Please arrive on time and contact the restaurant if you need to make any changes.</p>
        </div>
        
        <div style='text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee;'>
            <p style='color: #666; font-size: 14px; margin: 0;'>Thank you for choosing " . htmlspecialchars($business_name) . "!</p>
            <p style='color: #999; font-size: 12px; margin: 10px 0 0 0;'>This is an automated message from FoodSale Platform</p>
        </div>
    </div>
</body>
</html>";
            } else {
                $subject = "❌ Booking Update - " . $business_name;
                $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Booking Update</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
    <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h1 style='color: #dc3545; margin: 0; font-size: 28px;'>Booking Update</h1>
        </div>
        
        <p style='font-size: 16px; margin-bottom: 20px;'>Dear " . htmlspecialchars($customer_name) . ",</p>
        
        <p style='font-size: 16px; margin-bottom: 25px;'>We regret to inform you that your table booking request has been <strong style='color: #dc3545;'>DECLINED</strong> by " . htmlspecialchars($business_name) . ".</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #dc3545;'>
            <h3 style='margin-top: 0; color: #495057; font-size: 18px;'>📋 Booking Details</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Booking ID:</td><td style='padding: 8px 0;'>#" . $booking_details['booking_id'] . "</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Requested Date & Time:</td><td style='padding: 8px 0;'>" . date('M j, Y', strtotime($booking_details['booking_date'])) . " at " . date('g:i A', strtotime($booking_details['booking_time'])) . "</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Party Size:</td><td style='padding: 8px 0;'>" . $booking_details['party_size'] . " people</td></tr>
            </table>
        </div>
        
        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;'>
            <h3 style='margin-top: 0; color: #856404; font-size: 18px;'>💡 What's Next?</h3>
            <ul style='color: #856404; margin: 10px 0; padding-left: 20px;'>
                <li>Try booking for a different date or time</li>
                " . ($booking_details['business_phone'] ? "<li>Contact the restaurant directly at " . htmlspecialchars($booking_details['business_phone']) . "</li>" : "") . "
                <li>Explore other available restaurants on our platform</li>
            </ul>
        </div>
        
        <div style='text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee;'>
            <p style='color: #666; font-size: 14px; margin: 0;'>Thank you for your understanding</p>
            <p style='color: #999; font-size: 12px; margin: 10px 0 0 0;'>This is an automated message from FoodSale Platform</p>
        </div>
    </div>
</body>
</html>";
            }
            
            // Set proper headers for HTML email
            $headers = array(
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $business_name . ' <noreply@foodsale.com>',
                'Reply-To: ' . ($booking_details['business_email'] ?: 'noreply@foodsale.com'),
                'X-Mailer: PHP/' . phpversion()
            );
            
            // Send email
            $email_sent = mail($to, $subject, $message, implode("\r\n", $headers));
            
            if ($email_sent) {
                $success = "✅ Booking " . ($new_status === 'confirmed' ? 'approved' : 'rejected') . " successfully! Customer has been notified via email.";
            } else {
                $success = "⚠️ Booking status updated successfully! However, email notification failed to send.";
            }
        } else {
            $error = "Booking not found or access denied.";
        }
    } catch (Exception $e) {
        $error = "Error updating booking: " . $e->getMessage();
    }
}

// Fetch bookings for this dealer with complete dish details and images
try {
    $stmt = $pdo->prepare("SELECT b.*, l.title as dish_name, l.price as dish_price, l.description as dish_description,
                          i.image_url as dish_image, c.name as category_name
                          FROM table_bookings b
                          LEFT JOIN food_listings l ON b.dish_id = l.listing_id
                          LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                          LEFT JOIN food_categories c ON l.category_id = c.category_id
                          WHERE b.dealer_id = ?
                          ORDER BY b.created_at DESC");
    $stmt->execute([$dealer_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bookings = [];
    $error = "Error fetching bookings: " . $e->getMessage();
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
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
        .bookings-container { max-width: 1200px; margin: 0 auto; }
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
        .status-text { color: #666; font-size: 0.9rem; }
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { margin: 0 0 0.5rem 0; color: #333; }
        .page-header p { margin: 0; color: #666; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .customer-section, .dish-section { margin-bottom: 1.5rem; }
        .customer-section h4, .dish-section h4 { color: #333; margin-bottom: 1rem; font-size: 1.1rem; }
        .dish-details { display: flex; gap: 1rem; align-items: flex-start; }
        .dish-image-container { flex-shrink: 0; }
        .dish-image { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
        .dish-image-placeholder { width: 80px; height: 80px; background: #f8f9fa; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #666; font-size: 0.8rem; }
        .dish-info { flex: 1; }
        .dish-info h5 { margin: 0 0 0.5rem 0; color: #333; }
        .dish-category { background: #e9ecef; color: #495057; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; }
        .dish-price { color: #28a745; font-weight: 600; font-size: 1.1rem; margin-top: 0.5rem; }
        .special-requests { background: #fff3cd; padding: 1rem; border-radius: 8px; margin-top: 1rem; }
        .special-requests h5 { margin: 0 0 0.5rem 0; color: #856404; }
        .special-requests p { margin: 0; color: #856404; }
        
        @media (max-width: 768px) {
            .dish-details { flex-direction: column; }
            .dish-image, .dish-image-placeholder { width: 100%; max-width: 200px; margin: 0 auto; }
            .booking-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">🥄 Dealer Panel</div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['first_name'] ?? 'D', 0, 1)) ?>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars($_SESSION['first_name'] ?? 'Dealer') ?></h4>
                <p><?= htmlspecialchars($_SESSION['username'] ?? 'dealer') ?></p>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dealer-panel.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="my-dishes.php" class="nav-item">
                <span class="nav-icon">🍽️</span>
                <span class="nav-text">My Dishes</span>
            </a>
            <a href="manage-bookings.php" class="nav-item active">
                <span class="nav-icon">📅</span>
                <span class="nav-text">Bookings</span>
            </a>
            <a href="business-profile.php" class="nav-item">
                <span class="nav-icon">🏢</span>
                <span class="nav-text">Business Profile</span>
            </a>
            <a href="index.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Home</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <form method="POST" class="logout-form">
                <button type="submit" name="logout" class="logout-btn">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="mobile-sidebar-toggle" onclick="toggleSidebar()">☰</button>
                <h1>Manage Bookings</h1>
            </div>
            <div class="header-right">
                <div class="header-stats">
                    <span class="quick-stat">
                        <span class="stat-label">Pending</span>
                        <span class="stat-value"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'pending')) ?></span>
                    </span>
                </div>
            </div>
        </header>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
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

                                <div class="booking-content">
                                    <!-- Customer Details -->
                                    <div class="customer-section">
                                        <h4>Customer Information</h4>
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
                                    </div>

                                    <!-- Dish Details with Image -->
                                    <?php if ($booking['dish_name']): ?>
                                        <div class="dish-section">
                                            <h4>Dish of Interest</h4>
                                            <div class="dish-details">
                                                <div class="dish-image-container">
                                                    <?php if ($booking['dish_image'] && file_exists($booking['dish_image'])): ?>
                                                        <img src="<?= htmlspecialchars($booking['dish_image']) ?>" 
                                                             alt="<?= htmlspecialchars($booking['dish_name']) ?>" 
                                                             class="dish-image">
                                                    <?php else: ?>
                                                        <div class="dish-image-placeholder">
                                                            <span>🍽️</span>
                                                            <p>No Image</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="dish-info">
                                                    <h5><?= htmlspecialchars($booking['dish_name']) ?></h5>
                                                    <?php if ($booking['category_name']): ?>
                                                        <span class="dish-category"><?= htmlspecialchars($booking['category_name']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($booking['dish_price']): ?>
                                                        <div class="dish-price">$<?= number_format($booking['dish_price'], 2) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Special Requests -->
                                    <?php if ($booking['special_requests']): ?>
                                        <div class="special-requests">
                                            <h5>Special Requests</h5>
                                            <p><?= htmlspecialchars($booking['special_requests']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <div class="booking-actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Send confirmation email to customer?')">
                                            <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" name="update_status" class="btn-action btn-approve">
                                                ✅ Approve & Notify
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Send rejection email to customer?')">
                                            <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" name="update_status" class="btn-action btn-reject">
                                                ❌ Reject & Notify
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <div class="booking-actions">
                                        <span class="status-text">✅ Approved - Customer notified via email</span>
                                        <form method="POST" style="display: inline; margin-left: 1rem;" onsubmit="return confirm('Cancel this booking and notify customer?')">
                                            <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" name="update_status" class="btn-action btn-reject btn-small">
                                                Cancel Booking
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($booking['status'] === 'rejected'): ?>
                                    <div class="booking-actions">
                                        <span class="status-text">❌ Rejected - Customer notified</span>
                                        <form method="POST" style="display: inline; margin-left: 1rem;" onsubmit="return confirm('Approve this booking and notify customer?')">
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
        </div>
    </main>

    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        // Show notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'} ${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Show notifications for PHP messages
        <?php if (isset($success)): ?>
            showNotification('<?= addslashes($success) ?>', 'success');
        <?php endif; ?>

        <?php if (isset($error)): ?>
            showNotification('<?= addslashes($error) ?>', 'error');
        <?php endif; ?>

        // Handle logout
        document.querySelector('.logout-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                // Clear session and redirect
                window.location.href = 'logout.php';
            }
        });
    </script>
</body>
</html>


