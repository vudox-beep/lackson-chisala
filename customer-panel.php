<?php
session_start();

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// Database connection
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

$customer_id = $_SESSION['user_id'];

// Handle dish selection from URL parameter
$dish_id = $_GET['dish_id'] ?? null;
$selected_dish = null;

if ($dish_id) {
    try {
        $stmt = $pdo->prepare("SELECT l.*, d.business_name, d.business_phone, d.business_email 
                              FROM food_listings l 
                              LEFT JOIN dealers d ON l.dealer_id = d.dealer_id 
                              WHERE l.listing_id = ?");
        $stmt->execute([$dish_id]);
        $selected_dish = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $selected_dish = null;
        error_log("Error fetching selected dish: " . $e->getMessage());
    }
}

// Debug: Check customer ID and session
$customer_id = $_SESSION['user_id'] ?? null;

echo "<!-- Debug Info -->";
echo "<!-- Customer ID: " . ($customer_id ?? 'NULL') . " -->";
echo "<!-- Session data: " . print_r($_SESSION, true) . " -->";

if (!$customer_id) {
    echo "<div style='background: red; color: white; padding: 1rem;'>ERROR: No customer ID found in session</div>";
}

// Fetch customer's actual bookings from database with debug
try {
    // First, let's see if there are ANY bookings in the table
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_bookings FROM table_bookings");
    $stmt->execute();
    $total_bookings = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<!-- Total bookings in database: " . $total_bookings['total_bookings'] . " -->";
    
    // Now check bookings for this specific customer
    $stmt = $pdo->prepare("SELECT COUNT(*) as customer_bookings FROM table_bookings WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer_bookings = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<!-- Customer bookings: " . $customer_bookings['customer_bookings'] . " -->";
    
    // Get the actual bookings - fix the query to use correct column names
    $stmt = $pdo->prepare("SELECT b.*, l.title as dish_name, l.price as dish_price, d.business_name
                          FROM table_bookings b
                          LEFT JOIN food_listings l ON b.dish_id = l.listing_id
                          LEFT JOIN dealers d ON b.dealer_id = d.dealer_id
                          WHERE b.customer_id = ?
                          ORDER BY b.created_at DESC");
    $stmt->execute([$customer_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<!-- Bookings found: " . count($bookings) . " -->";
    echo "<!-- Bookings data: " . print_r($bookings, true) . " -->";
    
} catch (Exception $e) {
    $bookings = [];
    $error = "Error fetching bookings: " . $e->getMessage();
    echo "<!-- Database Error: " . $e->getMessage() . " -->";
}

// Sample favorites and cart data
$favorites = [
    ['id' => 1, 'name' => 'Grilled Salmon', 'price' => 24.99, 'image' => 'images/salmon.jpg'],
    ['id' => 2, 'name' => 'Pasta Carbonara', 'price' => 18.50, 'image' => 'images/pasta.jpg']
];

$cart_items = [
    ['id' => 1, 'name' => 'Caesar Salad', 'price' => 12.99, 'quantity' => 2, 'image' => 'images/salad.jpg'],
    ['id' => 3, 'name' => 'Beef Burger', 'price' => 16.99, 'quantity' => 1, 'image' => 'images/burger.jpg']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/customer-panel.css">
    <style>
        .booking-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn-primary, .btn-secondary {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .dish-selection {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .selected-dish-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .dish-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .price {
            font-weight: bold;
            color: #28a745;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .booking-status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .booking-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .booking-status.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .booking-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .booking-id {
            font-weight: bold;
            color: #666;
        }

        .booking-details {
            margin: 1rem 0;
        }

        .detail-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .detail-icon {
            width: 20px;
            margin-right: 0.5rem;
        }

        .detail-text {
            flex: 1;
            color: #333;
        }

        .booking-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .btn-edit, .btn-cancel {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .status-text {
            font-weight: 500;
            padding: 0.5rem;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🥄 My Account</div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="mainpage.php">Menu</a>
                <a href="customer-panel.php" class="active">My Account</a>
            </div>
            <div class="user-menu">
                <span class="customer-name">Welcome, <?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['username']) ?></span>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout" class="logout-btn">Logout</button>
                </form>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <!-- Add a prominent "Book New Table" button at the top -->
            <div class="quick-actions" style="margin-bottom: 2rem;">
                <button class="btn-primary btn-large" onclick="showBookingModal()" style="padding: 1rem 2rem; font-size: 1.1rem;">
                    📅 Book New Table
                </button>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-info">
                        <h3><?= count($bookings) ?></h3>
                        <p>Table Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">❤️</div>
                    <div class="stat-info">
                        <h3><?= count($favorites) ?></h3>
                        <p>Favorite Dishes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-info">
                        <h3><?= count($cart_items) ?></h3>
                        <p>Cart Items</p>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="showTab('bookings')">My Bookings</button>
                <button class="tab-btn" onclick="showTab('favorites')">Favorites</button>
                <button class="tab-btn" onclick="showTab('cart')">Shopping Cart</button>
                <button class="tab-btn" onclick="showTab('profile')">Profile</button>
            </div>

            <!-- Bookings Tab -->
            <div id="bookings" class="tab-content active">
                <div class="section-header">
                    <h2>My Table Bookings</h2>
                    <button class="btn-primary" onclick="bookNewTable()">Book New Table</button>
                </div>
                
                <!-- Debug Info Display -->
                <div style="background: #f8f9fa; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">
                    <strong>Debug Info:</strong><br>
                    Customer ID: <?= $customer_id ?? 'NULL' ?><br>
                    Total Bookings Found: <?= count($bookings) ?><br>
                    <?php if (isset($error)): ?>
                        Error: <?= htmlspecialchars($error) ?><br>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($_GET['booking']) && $_GET['booking'] === 'success'): ?>
                    <div class="success-message" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        ✅ Table booking submitted successfully! Your booking request has been sent to the restaurant for approval.
                        <br><small>Booking ID: <?= $_GET['booking_id'] ?? 'Unknown' ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['booking']) && $_GET['booking'] === 'error'): ?>
                    <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        ❌ Error: <?= htmlspecialchars($_GET['message'] ?? 'Unknown error') ?>
                    </div>
                <?php endif; ?>
                
                <div class="bookings-grid">
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state">
                            <h3>No bookings yet</h3>
                            <p>Book your first table to see it here</p>
                            <p><small>Customer ID: <?= $customer_id ?></small></p>
                            <button class="btn-primary" onclick="bookNewTable()">Book Your First Table</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div class="booking-status <?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </div>
                                    <div class="booking-id">#<?= $booking['booking_id'] ?></div>
                                </div>
                                <div class="booking-details">
                                    <div class="detail-row">
                                        <span class="detail-icon">🏪</span>
                                        <span class="detail-text"><?= htmlspecialchars($booking['business_name'] ?: 'Restaurant') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-icon">📅</span>
                                        <span class="detail-text">
                                            <?= date('M j, Y', strtotime($booking['booking_date'])) ?> at 
                                            <?= date('g:i A', strtotime($booking['booking_time'])) ?>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-icon">👥</span>
                                        <span class="detail-text"><?= $booking['party_size'] ?> people</span>
                                    </div>
                                    <?php if ($booking['dish_name']): ?>
                                        <div class="detail-row">
                                            <span class="detail-icon">🍽️</span>
                                            <span class="detail-text">
                                                Interested in: <?= htmlspecialchars($booking['dish_name']) ?>
                                                <?php if ($booking['dish_price']): ?>
                                                    ($<?= number_format($booking['dish_price'], 2) ?>)
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($booking['special_requests']): ?>
                                        <div class="detail-row">
                                            <span class="detail-icon">📝</span>
                                            <span class="detail-text"><?= htmlspecialchars($booking['special_requests']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-row">
                                        <span class="detail-icon">📞</span>
                                        <span class="detail-text"><?= htmlspecialchars($booking['customer_phone']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-icon">📧</span>
                                        <span class="detail-text"><?= htmlspecialchars($booking['customer_email']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-icon">🕒</span>
                                        <span class="detail-text">Booked on <?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="booking-actions">
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <button class="btn-edit" onclick="editBooking(<?= $booking['booking_id'] ?>)">Edit</button>
                                        <button class="btn-cancel" onclick="cancelBooking(<?= $booking['booking_id'] ?>)">Cancel</button>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <span class="status-text">✅ Confirmed - See you there!</span>
                                    <?php elseif ($booking['status'] === 'rejected'): ?>
                                        <span class="status-text">❌ Unfortunately declined</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Favorites Tab -->
            <div id="favorites" class="tab-content">
                <div class="section-header">
                    <h2>My Favorite Dishes</h2>
                </div>
                
                <div class="favorites-grid">
                    <?php foreach ($favorites as $favorite): ?>
                        <div class="favorite-card">
                            <div class="favorite-image">
                                <img src="<?= $favorite['image'] ?>" alt="<?= $favorite['name'] ?>" onerror="this.src='images/placeholder.jpg'">
                            </div>
                            <div class="favorite-info">
                                <h3><?= htmlspecialchars($favorite['name']) ?></h3>
                                <p class="favorite-price">$<?= number_format($favorite['price'], 2) ?></p>
                            </div>
                            <div class="favorite-actions">
                                <button class="btn-add-cart" onclick="addToCart(<?= $favorite['id'] ?>)">Add to Cart</button>
                                <button class="btn-remove" onclick="removeFavorite(<?= $favorite['id'] ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart Tab -->
            <div id="cart" class="tab-content">
                <div class="section-header">
                    <h2>Shopping Cart</h2>
                    <button class="btn-primary" onclick="checkout()">Checkout</button>
                </div>
                
                <div class="cart-items">
                    <?php 
                    $total = 0;
                    foreach ($cart_items as $item): 
                        $item_total = $item['price'] * $item['quantity'];
                        $total += $item_total;
                    ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" onerror="this.src='images/placeholder.jpg'">
                            </div>
                            <div class="item-info">
                                <h3><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="item-price">$<?= number_format($item['price'], 2) ?> each</p>
                            </div>
                            <div class="item-quantity">
                                <button onclick="updateQuantity(<?= $item['id'] ?>, -1)">-</button>
                                <span><?= $item['quantity'] ?></span>
                                <button onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                            </div>
                            <div class="item-total">
                                <span>$<?= number_format($item_total, 2) ?></span>
                            </div>
                            <div class="item-actions">
                                <button class="btn-remove" onclick="removeFromCart(<?= $item['id'] ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span>$<?= number_format($total * 0.1, 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>$<?= number_format($total * 1.1, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content">
                <div class="section-header">
                    <h2>My Profile</h2>
                </div>
                
                <div class="profile-form">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" value="<?= htmlspecialchars($_SESSION['first_name'] ?? '') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" value="<?= htmlspecialchars($_SESSION['last_name'] ?? '') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" readonly>
                    </div>
                    <button class="btn-primary">Edit Profile</button>
                </div>
            </div>
        </div>
    </main>

    <!-- Add booking modal to customer panel -->
    <div id="bookingModal" class="booking-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Book a Table</h2>
                <span class="close-modal" onclick="closeBookingModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="bookingForm" method="POST" action="process-booking.php">
                    <?php if ($selected_dish): ?>
                        <input type="hidden" name="dish_id" value="<?= $selected_dish['listing_id'] ?>">
                        <input type="hidden" name="dealer_id" value="<?= $selected_dish['dealer_id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="dealer_id" value="1">
                    <?php endif; ?>
                    <input type="hidden" name="customer_id" value="<?= $_SESSION['user_id'] ?>">
                    
                    <?php if ($selected_dish): ?>
                        <div class="dish-selection">
                            <h3>Selected Dish</h3>
                            <div class="selected-dish-info">
                                <div class="dish-details">
                                    <h4><?= htmlspecialchars($selected_dish['title']) ?></h4>
                                    <p><?= htmlspecialchars($selected_dish['description']) ?></p>
                                    <div class="dish-meta">
                                        <span class="price">$<?= number_format($selected_dish['price'], 2) ?></span>
                                        <span class="restaurant"><?= htmlspecialchars($selected_dish['business_name']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerName">Full Name *</label>
                            <input type="text" id="customerName" name="customer_name" 
                                   value="<?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="customerEmail">Email *</label>
                            <input type="email" id="customerEmail" name="customer_email" 
                                   value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerPhone">Phone Number *</label>
                            <input type="tel" id="customerPhone" name="customer_phone" required>
                        </div>
                        <div class="form-group">
                            <label for="partySize">Party Size *</label>
                            <select id="partySize" name="party_size" required>
                                <option value="">Select size</option>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> <?= $i == 1 ? 'person' : 'people' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bookingDate">Preferred Date *</label>
                            <input type="date" id="bookingDate" name="booking_date" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookingTime">Preferred Time *</label>
                            <select id="bookingTime" name="booking_time" required>
                                <option value="">Select time</option>
                                <?php 
                                for($hour = 9; $hour <= 22; $hour++) {
                                    for($min = 0; $min < 60; $min += 30) {
                                        $time = sprintf('%02d:%02d', $hour, $min);
                                        $display = date('g:i A', strtotime($time));
                                        echo "<option value='$time'>$display</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="specialRequests">Special Requests</label>
                        <textarea id="specialRequests" name="special_requests" rows="3" 
                                  placeholder="Any dietary restrictions, celebrations, or special requirements..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeBookingModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Book Table</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Debug: Check if dish_id is being passed
        console.log('Dish ID from URL:', '<?= $dish_id ?? 'none' ?>');
        console.log('Selected dish:', <?= json_encode($selected_dish) ?>);

        // Auto-show modal if coming from dish page
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($dish_id && $selected_dish): ?>
                console.log('Auto-showing booking modal for dish ID: <?= $dish_id ?>');
                showBookingModal();
            <?php endif; ?>
        });

        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function showBookingModal() {
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.style.display = 'flex';
                console.log('Modal shown');
            } else {
                console.error('Modal not found');
            }
        }

        function closeBookingModal() {
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.style.display = 'none';
                // Clear the dish_id from URL to prevent auto-showing again
                const url = new URL(window.location);
                url.searchParams.delete('dish_id');
                window.history.replaceState({}, document.title, url);
            }
        }

        function bookNewTable() {
            showBookingModal();
        }

        function editBooking(id) {
            alert(`Edit booking #${id} - Feature coming soon!`);
        }

        function cancelBooking(id) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                alert(`Booking #${id} cancelled!`);
                location.reload();
            }
        }

        function addToCart(id) {
            alert(`Added to cart! Item ID: ${id}`);
        }

        function removeFavorite(id) {
            if (confirm('Remove from favorites?')) {
                alert(`Removed from favorites! Item ID: ${id}`);
                location.reload();
            }
        }

        function updateQuantity(id, change) {
            alert(`Update quantity for item ${id} by ${change}`);
        }

        function removeFromCart(id) {
            if (confirm('Remove from cart?')) {
                alert(`Removed from cart! Item ID: ${id}`);
                location.reload();
            }
        }

        function checkout() {
            alert('Checkout feature coming soon!');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target == modal) {
                closeBookingModal();
            }
        }
    </script>
</body>
</html>






























