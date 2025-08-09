<?php
session_start();

// Include database connection
require_once 'config/database.php';

// Get dish ID from URL
$dishId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($dishId <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getConnection();
    
    // Get dish details with business info
    $dish = Database::fetchOne("
        SELECT l.*, c.name as category_name, d.business_name, d.business_logo,
               d.contact_phone, d.contact_email, d.address, d.operating_hours,
               d.description as business_description
        FROM food_listings l 
        LEFT JOIN food_categories c ON l.category_id = c.category_id 
        LEFT JOIN dealers d ON l.dealer_id = d.dealer_id 
        WHERE l.listing_id = ? AND l.status = 'active'
    ", [$dishId]);
    
    if (!$dish) {
        header('Location: index.php');
        exit;
    }
    
    // Get similar dishes from same category
    $similarDishes = Database::fetchAll("
        SELECT l.*, c.name as category_name, d.business_name
        FROM food_listings l 
        LEFT JOIN food_categories c ON l.category_id = c.category_id 
        LEFT JOIN dealers d ON l.dealer_id = d.dealer_id 
        WHERE l.category_id = ? AND l.listing_id != ? AND l.status = 'active'
        ORDER BY RAND() 
        LIMIT 4
    ", [$dish['category_id'], $dishId]);
    
} catch (Exception $e) {
    error_log("Error in dish-details.php: " . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?> - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/dish-details.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🥄 The Lucksons Spoon</div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="mainpage.php">Menu</a></li>
                <li><a href="#reservations">Reservations</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="nav-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'customer'): ?>
                        <a href="customer-panel.php" class="login-btn">My Account</a>
                        <button class="book-btn" onclick="bookTable()">Book a Table</button>
                    <?php elseif ($_SESSION['role'] === 'dealer'): ?>
                        <a href="dealer-panel.php" class="login-btn">Dashboard</a>
                        <button class="book-btn">Manage Business</button>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <a href="admin-panel.php" class="login-btn">Admin Panel</a>
                        <button class="book-btn">Manage System</button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Login</a>
                    <a href="register.php" class="book-btn">Sign Up</a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Button (Hamburger) -->
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </nav>
        
        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-content">
                <div class="mobile-nav-links">
                    <a href="index.php">Home</a>
                    <a href="mainpage.php">Menu</a>
                    <a href="#reservations">Reservations</a>
                    <a href="#about">About Us</a>
                    <a href="#contact">Contact</a>
                </div>
                <div class="mobile-nav-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'customer'): ?>
                            <a href="customer-panel.php" class="login-btn">My Account</a>
                            <button class="book-btn" onclick="bookTable()">Book a Table</button>
                        <?php elseif ($_SESSION['role'] === 'dealer'): ?>
                            <a href="dealer-panel.php" class="login-btn">Dashboard</a>
                            <button class="book-btn">Manage Business</button>
                        <?php elseif ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin-panel.php" class="login-btn">Admin Panel</a>
                            <button class="book-btn">Manage System</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="login-btn">Login</a>
                        <a href="register.php" class="book-btn">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Dish Details Section -->
        <section class="dish-details-section">
            <div class="container">
                <div class="dish-details-grid">
                    <!-- Dish Image -->
                    <div class="dish-image-container">
                        <?php if (!empty($dish['image_url']) && file_exists($dish['image_url'])): ?>
                            <img id="dishImage" src="<?= htmlspecialchars($dish['image_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="<?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?>" class="dish-image">
                        <?php else: ?>
                            <div class="dish-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                                <div style="text-align: center;">
                                    <span style="font-size: 4rem;">📷</span>
                                    <p>No Image Available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="image-gallery">
                            <div class="gallery-thumbnails" id="galleryThumbnails">
                                <!-- Thumbnails will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Dish Information -->
                    <div class="dish-info">
                        <div class="breadcrumb">
                            <a href="index.php">Home</a> > 
                            <a href="mainpage.php">Menu</a> > 
                            <span id="dishCategory"><?= htmlspecialchars($dish['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <h1 id="dishName" class="dish-title"><?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                        
                        <div class="dish-rating">
                            <div class="stars" id="dishStars">★★★★★</div>
                            <span class="rating-text" id="ratingText">(4.8 out of 5 - 124 reviews)</span>
                        </div>

                        <div class="dish-price">
                            <span class="current-price" id="dishPrice">$<?= number_format($dish['price'], 2) ?></span>
                            <span class="original-price" id="originalPrice"></span>
                        </div>

                        <div class="dish-description">
                            <h3>Description</h3>
                            <p id="dishDescription"><?= htmlspecialchars($dish['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div class="dish-details-info">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Cuisine Type:</span>
                                    <span class="info-value" id="cuisineType"><?= htmlspecialchars($dish['category_name'] ?: 'Various', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Prep Time:</span>
                                    <span class="info-value" id="prepTime">15-20 minutes</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Serves:</span>
                                    <span class="info-value" id="serves">1-2 people</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Added:</span>
                                    <span class="info-value"><?= date('M j, Y', strtotime($dish['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dietary-tags" id="dietaryTags">
                            <span class="dietary-tag">Fresh</span>
                            <span class="dietary-tag">Popular</span>
                        </div>

                        <div class="quantity-selector">
                            <label for="quantity">Quantity:</label>
                            <div class="quantity-controls">
                                <button class="qty-btn" onclick="changeQuantity(-1)">-</button>
                                <input type="number" id="quantity" value="1" min="1" max="10">
                                <button class="qty-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="btn-primary btn-add-to-cart" onclick="addToCart()">
                                <span class="btn-icon">🛒</span>
                                <span class="btn-text">Add to Cart</span>
                            </button>
                            <button class="btn-secondary btn-favorite" onclick="toggleFavorite()">
                                <span class="btn-icon">♡</span>
                                <span class="btn-text">Favorite</span>
                            </button>
                            <button class="btn-accent btn-book-table" onclick="bookTable()">
                                <span class="btn-icon">🍽️</span>
                                <span class="btn-text">Book Table</span>
                            </button>
                        </div>

                        <!-- Business Owner Information -->
                        <div class="business-owner-section">
                            <h3 class="section-title">Business Information</h3>
                            <div class="business-card">
                                <div class="business-header">
                                    <div class="business-avatar">
                                        <span class="avatar-icon">🏪</span>
                                    </div>
                                    <div class="business-main-info">
                                        <h4 class="business-name"><?= htmlspecialchars($dish['business_name'] ?: 'The Lucksons Spoon', ENT_QUOTES, 'UTF-8') ?></h4>
                                        <span class="business-type-badge">Restaurant</span>
                                    </div>
                                </div>
                                
                                <div class="business-details">
                                    <div class="detail-row">
                                        <span class="detail-icon">👤</span>
                                        <span class="detail-label">Contact Person:</span>
                                        <span class="detail-value">Restaurant Manager</span>
                                    </div>
                                    
                                    <div class="detail-row">
                                        <span class="detail-icon">📞</span>
                                        <span class="detail-label">Phone:</span>
                                        <span class="detail-value">
                                            <a href="tel:<?= htmlspecialchars($dish['business_phone'] ?: '+27123456789', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dish['business_phone'] ?: '+27 123 456 789', ENT_QUOTES, 'UTF-8') ?></a>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-row">
                                        <span class="detail-icon">✉️</span>
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value">
                                            <a href="mailto:info@lucksonsspoon.com">info@lucksonsspoon.com</a>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-row">
                                        <span class="detail-icon">📍</span>
                                        <span class="detail-label">Address:</span>
                                        <span class="detail-value">123 Food Street, Cape Town, South Africa</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="business-info-card">
                            <div class="business-header">
                                <?php if (!empty($dish['business_logo']) && file_exists($dish['business_logo'])): ?>
                                    <img src="<?= htmlspecialchars($dish['business_logo']) ?>" alt="Business Logo" class="business-logo">
                                <?php else: ?>
                                    <div class="business-logo-placeholder">🏢</div>
                                <?php endif; ?>
                                <div class="business-name">
                                    <h3><?= htmlspecialchars($dish['business_name'] ?: 'Restaurant', ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p>Restaurant Partner</p>
                                </div>
                            </div>
                            
                            <div class="business-details">
                                <div class="detail-row">
                                    <span class="detail-icon">📞</span>
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value">
                                        <a href="tel:<?= htmlspecialchars($dish['business_phone'] ?: '+27123456789', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dish['business_phone'] ?: '+27 123 456 789', ENT_QUOTES, 'UTF-8') ?></a>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-icon">✉️</span>
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value">
                                        <a href="mailto:<?= htmlspecialchars($dish['business_email'] ?: 'info@lucksonsspoon.com', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dish['business_email'] ?: 'info@lucksonsspoon.com', ENT_QUOTES, 'UTF-8') ?></a>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-icon">📍</span>
                                    <span class="detail-label">Address:</span>
                                    <span class="detail-value"><?= htmlspecialchars($dish['business_address'] ?: '123 Food Street, Cape Town, South Africa', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                            
                            <!-- Operating Hours -->
                            <div class="operating-hours">
                                <h4><span class="detail-icon">🕐</span> Operating Hours</h4>
                                <div class="hours-list">
                                    <?php 
                                    $operating_hours = $dish['operating_hours'] ? json_decode($dish['operating_hours'], true) : [];
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    
                                    foreach ($days as $day): 
                                        $dayHours = $operating_hours[strtolower($day)] ?? ['open' => '09:00', 'close' => '17:00', 'closed' => false];
                                    ?>
                                        <div class="hours-row">
                                            <span class="day-name"><?= $day ?></span>
                                            <span class="day-hours">
                                                <?php if ($dayHours['closed']): ?>
                                                    <span class="closed">Closed</span>
                                                <?php else: ?>
                                                    <?= date('g:i A', strtotime($dayHours['open'])) ?> - <?= date('g:i A', strtotime($dayHours['close'])) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="additional-info">
                            <div class="info-tabs">
                                <button class="tab-btn active" onclick="showTab('details')">Dish Details</button>
                                <button class="tab-btn" onclick="showTab('reviews')">Customer Reviews</button>
                            </div>
                            
                            <div class="tab-content">
                                <div id="details" class="tab-panel active">
                                    <div class="details-grid">
                                        <div class="detail-card">
                                            <span class="detail-label">Category</span>
                                            <span class="detail-value"><?= htmlspecialchars($dish['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="detail-card">
                                            <span class="detail-label">Price</span>
                                            <span class="detail-value price-highlight">$<?= number_format($dish['price'], 2) ?></span>
                                        </div>
                                        <div class="detail-card">
                                            <span class="detail-label">Added</span>
                                            <span class="detail-value"><?= date('M j, Y', strtotime($dish['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="reviews" class="tab-panel">
                                    <div class="reviews-list">
                                        <div class="review-card">
                                            <div class="review-header">
                                                <div class="reviewer-info">
                                                    <span class="reviewer-avatar">👤</span>
                                                    <span class="reviewer-name">Sarah Johnson</span>
                                                </div>
                                                <span class="review-date"><?= date('M j, Y') ?></span>
                                            </div>
                                            <div class="review-rating">★★★★★</div>
                                            <p class="review-text">Absolutely delicious! Fresh ingredients and perfect seasoning. Highly recommend!</p>
                                        </div>
                                        
                                        <div class="review-card">
                                            <div class="review-header">
                                                <div class="reviewer-info">
                                                    <span class="reviewer-avatar">👨</span>
                                                    <span class="reviewer-name">Mike Chen</span>
                                                </div>
                                                <span class="review-date"><?= date('M j, Y', strtotime('-3 days')) ?></span>
                                            </div>
                                            <div class="review-rating">★★★★☆</div>
                                            <p class="review-text">Great taste and generous portion. The presentation was beautiful too!</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Similar Dishes Section -->
        <section class="similar-dishes-section">
            <div class="container">
                <h2 class="section-title">Similar Dishes You Might Like</h2>
                <div class="similar-dishes-grid" id="similarDishesGrid">
                    <?php if (!empty($similarDishes)): ?>
                        <?php foreach ($similarDishes as $similarDish): ?>
                            <div class="similar-dish-card" onclick="viewDish(<?= $similarDish['listing_id'] ?>)">
                                <div class="card-image-container">
                                    <?php if (!empty($similarDish['image_url']) && file_exists($similarDish['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($similarDish['image_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                             alt="<?= htmlspecialchars($similarDish['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                             class="similar-dish-image">
                                    <?php else: ?>
                                        <div class="similar-dish-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; height: 200px;">
                                            <span style="font-size: 3rem;">📷</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="similar-dish-info">
                                    <h3 class="similar-dish-name"><?= htmlspecialchars($similarDish['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="similar-dish-description"><?= htmlspecialchars(substr($similarDish['description'], 0, 100), ENT_QUOTES, 'UTF-8') ?>...</p>
                                    <div class="similar-dish-meta">
                                        <span class="similar-dish-price">$<?= number_format($similarDish['price'], 2) ?></span>
                                        <div class="similar-dish-rating">★★★★☆</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-similar-dishes">
                            <p>No similar dishes found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Recently Viewed Section -->
        <section class="recently-viewed-section">
            <div class="container">
                <h2 class="section-title">Recently Viewed</h2>
                <div class="recently-viewed-grid" id="recentlyViewedGrid">
                    <p>Recently viewed dishes will appear here.</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        // View dish function
        function viewDish(dishId) {
            window.location.href = `dish-details.php?id=${dishId}`;
        }

        // Quantity controls
        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            let currentValue = parseInt(quantityInput.value);
            let newValue = currentValue + change;
            
            if (newValue >= 1 && newValue <= 10) {
                quantityInput.value = newValue;
            }
        }

        // Add to cart
        function addToCart() {
            showNotification('Please login as a customer to add items to cart', 'info');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        }

        // Toggle favorite
        function toggleFavorite() {
            showNotification('Please login as a customer to save favorites', 'info');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        }

        // Show tab
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Book table function - pass dish ID in URL
        function bookTable() {
            <?php if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer'): ?>
                alert('Redirecting to login...');
                window.location.href = 'login.php?redirect=' + encodeURIComponent('customer-panel.php?dish_id=<?= $dishId ?>');
                return;
            <?php endif; ?>
            
            // If logged in, go directly to customer panel with dish ID
            window.location.href = 'customer-panel.php?dish_id=<?= $dishId ?>';
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Show notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'} ${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Mobile menu toggle function
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            mobileMenu.classList.toggle('active');
            menuBtn.classList.toggle('active');
            
            // Prevent body scroll when menu is open
            if (mobileMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        // Close mobile menu when clicking on links
        document.addEventListener('DOMContentLoaded', function() {
            const mobileLinks = document.querySelectorAll('.mobile-nav-links a, .mobile-nav-buttons a, .mobile-nav-buttons button');
            
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    const mobileMenu = document.getElementById('mobileMenu');
                    const menuBtn = document.querySelector('.mobile-menu-btn');
                    
                    mobileMenu.classList.remove('active');
                    menuBtn.classList.remove('active');
                    document.body.style.overflow = '';
                });
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                const mobileMenu = document.getElementById('mobileMenu');
                const menuBtn = document.querySelector('.mobile-menu-btn');
                
                if (!mobileMenu.contains(e.target) && !menuBtn.contains(e.target)) {
                    mobileMenu.classList.remove('active');
                    menuBtn.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    </script>
</body>
</html>

<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
<!-- Booking Modal - Only show for logged-in customers -->
<div id="bookingModal" class="booking-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Book a Table</h2>
            <span class="close-modal" onclick="closeBookingModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="bookingForm" method="POST" action="process-booking.php">
                <input type="hidden" name="dish_id" value="<?= $dishId ?>">
                <input type="hidden" name="dealer_id" value="<?= $dish['dealer_id'] ?>">
                <input type="hidden" name="customer_id" value="<?= $_SESSION['user_id'] ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customerName">Full Name *</label>
                        <input type="text" id="customerName" name="customer_name" 
                               value="<?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="customerEmail">Email *</label>
                        <input type="email" id="customerEmail" name="customer_email" 
                               value="<?= htmlspecialchars($_SESSION['email']) ?>" required>
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
                
                <div class="booking-summary">
                    <h3>Booking Summary</h3>
                    <div class="summary-item">
                        <span>Restaurant:</span>
                        <span><?= htmlspecialchars($dish['business_name']) ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Dish Interest:</span>
                        <span><?= htmlspecialchars($dish['title']) ?></span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeBookingModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Book Table</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>










