<?php
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

// Fetch all listings from database
try {
    $stmt = $pdo->prepare("SELECT 
                           l.listing_id, 
                           l.dealer_id,
                           l.category_id,
                           l.subcategory_id,
                           l.title, 
                           l.description, 
                           l.price, 
                           l.is_approved,
                           l.created_at, 
                           i.image_url, 
                           c.name as category_name,
                           c.description as category_description
                           FROM food_listings l
                           LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                           LEFT JOIN food_categories c ON l.category_id = c.category_id
                           ORDER BY l.created_at DESC");
    $stmt->execute();
    $chefRecommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $chefRecommendations = [];
    error_log("Database error: " . $e->getMessage());
}

// Fetch categories for filter dropdown
try {
    $stmt = $pdo->prepare("SELECT category_id, name, description FROM food_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check what we're getting
    error_log("Categories found: " . count($categories));
    if (!empty($categories)) {
        error_log("First category: " . print_r($categories[0], true));
    }
} catch (Exception $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

// Fetch subcategories for filter dropdown
try {
    $stmt = $pdo->prepare("SELECT subcategory_id, category_id, name FROM food_subcategories ORDER BY name");
    $stmt->execute();
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check what we're getting
    error_log("Subcategories found: " . count($subcategories));
    if (!empty($subcategories)) {
        error_log("First subcategory: " . print_r($subcategories[0], true));
    }
} catch (Exception $e) {
    $subcategories = [];
    error_log("Error fetching subcategories: " . $e->getMessage());
}

// Fetch today's specials (only dishes marked as daily special - NO APPROVAL CHECK)
try {
    $stmt = $pdo->prepare("SELECT 
                           l.listing_id, 
                           l.dealer_id,
                           l.category_id,
                           l.subcategory_id,
                           l.title, 
                           l.description, 
                           l.price, 
                           l.original_price,
                           l.created_at,
                           l.is_daily_special,
                           i.image_url, 
                           c.name as category_name,
                           c.description as category_description,
                           d.business_name,
                           d.business_logo
                           FROM food_listings l
                           LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                           LEFT JOIN food_categories c ON l.category_id = c.category_id
                           LEFT JOIN dealers d ON l.dealer_id = d.dealer_id
                           WHERE l.is_daily_special = 1
                           ORDER BY l.created_at DESC
                           LIMIT 6");
    $stmt->execute();
    $todaysSpecials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Daily specials query executed - showing ALL marked specials");
    error_log("Daily specials found: " . count($todaysSpecials));
    
} catch (Exception $e) {
    $todaysSpecials = [];
    error_log("Error fetching daily specials: " . $e->getMessage());
}

// Debug: Show what we have
error_log("Total categories for dropdown: " . count($categories));
error_log("Total subcategories for dropdown: " . count($subcategories));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Gilded Spoon</title>
    <link rel="stylesheet" href="styles/index.css">
</head>

<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🥄 The lucksons Spoon</div>
            <ul class="nav-links">
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

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1 class="hero-title">Welcome to the best food restaurant in south africa</h1>
            <p class="hero-subtitle">Experience Culinary Excellence in Every Bite</p>

            <button class="filter-toggle-btn" onclick="toggleFilter()">🔍 Search Menu</button>

            <!-- Professional Filter Section -->
            <div class="filter-section" id="filterSection">
                <form class="filter-form" id="menuFilter">
                    <div class="filter-header">
                        <h3>Find Your Perfect Dish</h3>
                        <p>Use our advanced filters to discover exactly what you're craving</p>
                    </div>
                    
                    <div class="filter-grid">
                        <!-- Category (from database) -->
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select class="filter-select" id="category" onchange="loadDishTypes()">
                                <option value="">All Categories</option>
                                <?php if (empty($categories)): ?>
                                    <option value="" disabled>No categories available</option>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>" 
                                                data-name="<?= htmlspecialchars($category['name']) ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small style="color: #666; font-size: 0.8rem;">
                                <?= count($categories) ?> categories available
                            </small>
                        </div>

                        <!-- Subcategory -->
                        <div class="filter-group">
                            <label class="filter-label">Subcategory</label>
                            <select class="filter-select" id="subcategory" disabled>
                                <option value="">Select category first</option>
                            </select>
                        </div>

                        <!-- Cuisine Type -->
                        <div class="filter-group">
                            <label class="filter-label">Cuisine Type</label>
                            <select class="filter-select" id="cuisineType">
                                <option value="">All Cuisines</option>
                                <option value="south-african">South African</option>
                                <option value="italian">Italian</option>
                                <option value="american">American</option>
                                <option value="asian">Asian</option>
                            </select>
                        </div>

                        <!-- Search Input -->
                        <div class="filter-group">
                            <label class="filter-label">Search Dishes</label>
                            <input type="text" class="filter-input" placeholder="Search for dishes..." id="searchInput" list="dishSuggestions">
                            <datalist id="dishSuggestions">
                                <?php foreach ($chefRecommendations as $dish): ?>
                                    <option value="<?= htmlspecialchars($dish['title']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <!-- Location -->
                        <div class="filter-group">
                            <label class="filter-label">Location</label>
                            <select class="filter-select" id="location">
                                <option value="">All Locations</option>
                                <option value="johannesburg">Johannesburg</option>
                                <option value="cape-town">Cape Town</option>
                                <option value="durban">Durban</option>
                                <option value="pretoria">Pretoria</option>
                                <option value="port-elizabeth">Port Elizabeth</option>
                                <option value="bloemfontein">Bloemfontein</option>
                            </select>
                        </div>

                        <!-- Min Price -->
                        <div class="filter-group">
                            <label class="filter-label">Min Price</label>
                            <input type="number" class="filter-input" id="minPrice" placeholder="$0" min="0">
                        </div>

                        <!-- Max Price -->
                        <div class="filter-group">
                            <label class="filter-label">Max Price</label>
                            <input type="number" class="filter-input" id="maxPrice" placeholder="$100" min="0">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="button" class="btn-reset" onclick="resetFilters()">Reset</button>
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>
            </div>
            
            <div class="hero-buttons">
                <button class="btn btn-primary">View Menu</button>
                <button class="btn btn-secondary">Book a Table</button>
            </div>
        </div>
        <div class="hero-overlay"></div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Today's Specials -->
        <section class="specials-section">
            <div class="container">
                <h2 class="section-title">Today's Daily Specials</h2>
                <p class="section-subtitle">Special dishes marked by our restaurants - limited time offers!</p>
                <!-- Results Summary -->
                <div class="results-summary" style="text-align: center; margin-top: 1rem; color: #666;">
                    <p><?= count($todaysSpecials) ?> daily special<?= count($todaysSpecials) !== 1 ? 's' : '' ?> available</p>
                    <?php if (count($todaysSpecials) > 0): ?>
                        <small style="color: #e74c3c; font-weight: bold;">⚡ Limited time offers - Don't miss out!</small>
                    <?php endif; ?>
                </div>
                <div class="specials-grid">
                    <?php if (empty($todaysSpecials)): ?>
                        <div class="no-specials-message">
                            <div class="empty-icon">🍽️</div>
                            <h3>No Daily Specials Available</h3>
                            <p>Check back soon for our daily special dishes!</p>
                            <a href="mainpage.php" class="btn-view-menu">View Full Menu</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($todaysSpecials as $special): ?>
                            <div class="special-card animate-in" onclick="viewDish(<?= $special['listing_id'] ?>)">
                                <div class="card-image-container">
                                    <?php if (!empty($special['image_url']) && file_exists($special['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($special['image_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                             alt="<?= htmlspecialchars($special['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                             class="card-image">
                                    <?php else: ?>
                                        <div class="card-image-placeholder">
                                            <span>🍽️</span>
                                            <p>Daily Special</p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="special-overlay">
                                        <button class="quick-add-btn pulse" 
                                                data-dish-id="<?= $special['listing_id'] ?>" 
                                                data-price="<?= $special['price'] ?>"
                                                data-name="<?= htmlspecialchars($special['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                onmouseenter="this.closest('.special-overlay').style.opacity = '1'"
                                                onmouseleave="this.closest('.special-card').matches(':hover') || (this.closest('.special-overlay').style.opacity = '0')"
                                                onclick="event.stopPropagation(); addToCart(<?= $special['listing_id'] ?>, '<?= htmlspecialchars($special['title'], ENT_QUOTES, 'UTF-8') ?>', <?= $special['price'] ?>)">
                                            <span class="btn-icon">🛒</span> Quick Add
                                        </button>
                                    </div>
                                    <!-- Daily Special Badge -->
                                    <div class="special-badge">
                                        <span class="badge-text">DAILY SPECIAL</span>
                                        <div class="badge-glow"></div>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?= htmlspecialchars($special['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <div class="live-indicator">
                                            <span class="live-dot"></span>
                                            <span class="live-text">Special</span>
                                        </div>
                                    </div>
                                    <!-- Business Info -->
                                    <?php if (!empty($special['business_logo']) && file_exists($special['business_logo'])): ?>
                                        <div class="business-logo-small">
                                            <img src="<?= htmlspecialchars($special['business_logo']) ?>" alt="<?= htmlspecialchars($special['business_name']) ?>">
                                            <span><?= htmlspecialchars($special['business_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="business-info">
                                            <span class="business-name"><?= htmlspecialchars($special['business_name']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <p><?= htmlspecialchars(substr($special['description'], 0, 80), ENT_QUOTES, 'UTF-8') ?>...</p>
                                    <div class="card-footer">
                                        <div class="price-section">
                                            <?php if ($special['original_price'] && $special['price'] < $special['original_price']): ?>
                                                <span class="special-price animate-price">$<?= number_format($special['price'], 2) ?></span>
                                                <span class="original-price">$<?= number_format($special['original_price'], 2) ?></span>
                                                <span class="savings">Save $<?= number_format($special['original_price'] - $special['price'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="price animate-price">$<?= number_format($special['price'], 2) ?></span>
                                                <span class="special-label">Daily Special Price</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="category-tag"><?= htmlspecialchars($special['category_name'] ?: 'Special', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="added-date">
                                        <span class="date-icon">⭐</span>
                                        Daily Special - Limited Time
                                        <span class="time-ago" data-created="<?= $special['created_at'] ?>"></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Chef's Recommendations -->
        <section class="recommendations-section">
            <div class="container">
                <h2 class="section-title">Our food store</h2>
                <div class="recommendations-grid" id="recommendationsGrid">
                    <?php if (empty($chefRecommendations)): ?>
                        <div class="no-dishes-message">
                            <p>No dishes available at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chefRecommendations as $dish): ?>
                            <div class="recommendation-card" 
                                 data-category="<?= htmlspecialchars($dish['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?>" 
                                 data-category-id="<?= $dish['category_id'] ?>"
                                 data-subcategory-id="<?= $dish['subcategory_id'] ?>"
                                 data-price="<?= $dish['price'] ?>"
                                 data-listing-id="<?= $dish['listing_id'] ?>">
                                
                                <div class="card-image-container">
                                    <?php if (!empty($dish['image_url']) && file_exists($dish['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($dish['image_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                             alt="<?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                             class="card-image">
                                    <?php else: ?>
                                        <div class="card-image-placeholder">
                                            <span>📷</span>
                                            <p>No Image</p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-overlay">
                                        <button class="add-to-cart-btn" 
                                                data-dish-id="<?= $dish['listing_id'] ?>" 
                                                data-price="<?= $dish['price'] ?>"
                                                data-name="<?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?>">
                                            Add to Cart
                                        </button>
                                        <button class="view-dish-btn" onclick="viewDish(<?= $dish['listing_id'] ?>)">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3 class="card-title"><?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <span class="card-category"><?= htmlspecialchars($dish['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="card-description"><?= htmlspecialchars($dish['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="card-meta">
                                        <div class="listing-info">
                                            <span class="listing-date">Added: <?= date('M j, Y', strtotime($dish['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="rating">
                                            <span class="stars">★★★★★</span>
                                        </div>
                                        <span class="price">$<?= number_format($dish['price'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Chef's Recommendations Data
        const chefRecommendations = [{
                id: 1,
                name: "Chef's Signature Steak",
                description: "A perfectly cooked ribeye steak with red wine sauce and roasted vegetables",
                price: "$45.99",
                image: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Cdefs%3E%3ClinearGradient id='grad1' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%238B4513;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23654321;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23grad1)'/%3E%3Cellipse cx='200' cy='150' rx='120' ry='80' fill='%23A0522D'/%3E%3Cellipse cx='180' cy='130' rx='80' ry='50' fill='%23D2691E'/%3E%3Ctext x='200' y='280' text-anchor='middle' fill='white' font-size='16' font-family='serif'%3ESignature Steak%3C/text%3E%3C/svg%3E",
                category: "Main Course",
                rating: 5
            },
            {
                id: 2,
                name: "Lobster Thermidor",
                description: "Fresh Maine lobster with creamy sauce and herbs, served with garlic bread",
                price: "$52.99",
                image: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Cdefs%3E%3ClinearGradient id='grad2' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23FF6347;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23DC143C;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23grad2)'/%3E%3Cellipse cx='200' cy='150' rx='140' ry='60' fill='%23FF7F50'/%3E%3Cellipse cx='170' cy='130' rx='40' ry='20' fill='%23FFA500'/%3E%3Cellipse cx='230' cy='130' rx='40' ry='20' fill='%23FFA500'/%3E%3Ctext x='200' y='280' text-anchor='middle' fill='white' font-size='16' font-family='serif'%3ELobster Thermidor%3C/text%3E%3C/svg%3E",
                category: "Seafood",
                rating: 5
            },
            {
                id: 3,
                name: "Truffle Risotto",
                description: "Creamy Arborio rice with black truffles and Parmesan cheese",
                price: "$38.99",
                image: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Cdefs%3E%3ClinearGradient id='grad3' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23F5DEB3;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23DEB887;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23grad3)'/%3E%3Ccircle cx='200' cy='150' r='100' fill='%23FFFACD'/%3E%3Ccircle cx='180' cy='130' r='15' fill='%238B4513'/%3E%3Ccircle cx='220' cy='140' r='12' fill='%238B4513'/%3E%3Ccircle cx='200' cy='170' r='18' fill='%238B4513'/%3E%3Ctext x='200' y='280' text-anchor='middle' fill='%23654321' font-size='16' font-family='serif'%3ETruffle Risotto%3E%3C/svg%3E",
                category: "Vegetarian",
                rating: 4
            },
            {
                id: 4,
                name: "Duck Confit",
                description: "Slow-cooked duck leg with orange glaze and roasted root vegetables",
                price: "$42.99",
                image: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Cdefs%3E%3ClinearGradient id='grad4' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23CD853F;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23A0522D;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23grad4)'/%3E%3Cellipse cx='200' cy='150' rx='110' ry='70' fill='%23D2691E'/%3E%3Cellipse cx='180' cy='130' rx='60' ry='40' fill='%23F4A460'/%3E%3Ccircle cx='160' cy='120' r='8' fill='%23FF8C00'/%3E%3Ccircle cx='190' cy='125' r='6' fill='%23FF8C00'/%3E%3Ctext x='200' y='280' text-anchor='middle' fill='white' font-size='16' font-family='serif'%3EDuck Confit%3E%3C/svg%3E",
                category: "Main Course",
                rating: 5
            },
            {
                id: 5,
                name: "Chocolate Soufflé",
                description: "Rich dark chocolate soufflé with vanilla ice cream and berry coulis",
                price: "$18.99",
                image: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Cdefs%3E%3ClinearGradient id='grad5' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23654321;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%233E2723;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23grad5)'/%3E%3Ccircle cx='200' cy='180' r='80' fill='%236F4E37'/%3E%3Cellipse cx='200' cy='120' rx='70' ry='30' fill='%238B4513'/%3E%3Ccircle cx='180' cy='110' r='4' fill='%23DC143C'/%3E%3Ccircle cx='220' cy='115' r='4' fill='%23DC143C'/%3E%3Ccircle cx='200' cy='105' r='4' fill='%23DC143C'/%3E%3Ctext x='200' y='280' text-anchor='middle' fill='white' font-size='16' font-family='serif'%3EChocolate Soufflé%3E%3C/svg%3E",
                category: "Dessert",
                rating: 5
            },
            {
                id: 6,
                name: "Seared Scallops",
                description: "Pan-seared scallops with cauliflower purée and pancetta crisps",
                price: "$36.99",
                image: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Cdefs%3E%3ClinearGradient id='grad6' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23F0F8FF;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23E6E6FA;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23grad6)'/%3E%3Ccircle cx='160' cy='140' r='35' fill='%23FFF8DC'/%3E%3Ccircle cx='240' cy='140' r='35' fill='%23FFF8DC'/%3E%3Ccircle cx='200' cy='180' r='35' fill='%23FFF8DC'/%3E%3Ccircle cx='160' cy='140' r='25' fill='%23F5DEB3'/%3E%3Ccircle cx='240' cy='140' r='25' fill='%23F5DEB3'/%3E%3Ccircle cx='200' cy='180' r='25' fill='%23F5DEB3'/%3E%3Ctext x='200' y='280' text-anchor='middle' fill='%23333' font-size='16' font-family='serif'%3ESeared Scallops%3E%3C/svg%3E",
                category: "Seafood",
                rating: 4
            }
        ];

        // Function to create star rating
        function createStarRating(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += i <= rating ? '★' : '☆';
            }
            return stars;
        }

        // Function to create recommendation cards
        function createRecommendationCard(item) {
            return `
                <div class="recommendation-card" data-category="${item.category}">
                    <div class="card-image-container">
                        <img src="${item.image}" alt="${item.name}" class="card-image">
                        <div class="card-overlay">
                            <button class="add-to-cart-btn" data-dish-id="${item.id}" data-price="${item.price}">Add to Cart</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="card-header">
                            <h3 class="card-title">${item.name}</h3>
                            <span class="card-category">${item.category}</span>
                        </div>
                        <p class="card-description">${item.description}</p>
                        <div class="card-footer">
                            <div class="rating">
                                <span class="stars">${createStarRating(item.rating)}</span>
                            </div>
                            <span class="price">${item.price}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Function to render recommendations
        function renderRecommendations() {
            const grid = document.getElementById('recommendationsGrid');
            grid.innerHTML = chefRecommendations.map(createRecommendationCard).join('');
        }

        // Smooth scrolling for navigation links
        function initSmoothScrolling() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }

        // Header scroll effect
        function initHeaderScrollEffect() {
            window.addEventListener('scroll', () => {
                const header = document.querySelector('.header');
                if (window.scrollY > 100) {
                    header.style.background = 'rgba(255, 255, 255, 0.98)';
                    header.style.boxShadow = '0 2px 30px rgba(0, 0, 0, 0.15)';
                } else {
                    header.style.background = 'rgba(255, 255, 255, 0.95)';
                    header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
                }
            });
        }

        // Card hover animations
        function initCardAnimations() {
            document.addEventListener('mouseover', (e) => {
                if (e.target.closest('.recommendation-card')) {
                    const card = e.target.closest('.recommendation-card');
                    card.style.transform = 'translateY(-10px)';
                    card.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
                }

                if (e.target.closest('.special-card')) {
                    const card = e.target.closest('.special-card');
                    card.style.transform = 'translateY(-5px)';
                    card.style.boxShadow = '0 15px 30px rgba(0, 0, 0, 0.15)';
                }
            });

            document.addEventListener('mouseout', (e) => {
                if (e.target.closest('.recommendation-card')) {
                    const card = e.target.closest('.recommendation-card');
                    card.style.transform = 'translateY(0)';
                    card.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.1)';
                }

                if (e.target.closest('.special-card')) {
                    const card = e.target.closest('.special-card');
                    card.style.transform = 'translateY(0)';
                    card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
                }
            });
        }

        // Add to cart functionality
        function addToCart(dishId, dishName, price) {
            // You can implement actual cart logic here
            console.log(`Adding to cart: ${dishName} - $${price}`);
            
            // Show notification
            showNotification(`${dishName} added to cart!`, 'success');
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

            // Animate notification
            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Initialize add to cart buttons
        function initAddToCart() {
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('add-to-cart-btn')) {
                    e.preventDefault();
                    
                    const dishId = e.target.getAttribute('data-dish-id');
                    const price = e.target.getAttribute('data-price');
                    const card = e.target.closest('.recommendation-card');
                    const dishName = card.querySelector('.card-title').textContent;

                    addToCart(dishId, dishName, price);
                }
            });
        }

        // Filter toggle function
        function toggleFilter() {
            const filterSection = document.getElementById('filterSection');
            filterSection.classList.toggle('show');
        }

        // Reset filters function
        function resetFilters() {
            document.getElementById('menuFilter').reset();
        }

        // Load subcategories based on selected category
        function loadSubcategories() {
            const categorySelect = document.getElementById('category');
            const subcategorySelect = document.getElementById('subcategory');
            const selectedCategoryId = categorySelect.value;
            
            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
            
            if (selectedCategoryId) {
                // Filter subcategories for selected category
                const relevantSubcategories = subcategoriesData.filter(sub => 
                    sub.category_id == selectedCategoryId
                );
                
                // Add subcategories as options
                relevantSubcategories.forEach(subcategory => {
                    const option = document.createElement('option');
                    option.value = subcategory.subcategory_id;
                    option.textContent = subcategory.name;
                    subcategorySelect.appendChild(option);
                });
                
                subcategorySelect.disabled = false;
            } else {
                subcategorySelect.disabled = true;
            }
        }

        // Filter form submission - redirect to mainpage.php with search parameters
        document.getElementById('menuFilter').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const params = new URLSearchParams();
            
            const search = document.getElementById('searchInput').value.trim();
            const category = document.getElementById('category').value;
            const subcategory = document.getElementById('subcategory').value;
            const location = document.getElementById('location').value;
            const minPrice = document.getElementById('minPrice').value;
            const maxPrice = document.getElementById('maxPrice').value;
            
            if (search) params.append('search', search);
            if (category) params.append('category', category);
            if (subcategory) params.append('subcategory', subcategory);
            if (location) params.append('location', location);
            if (minPrice) params.append('min_price', minPrice);
            if (maxPrice) params.append('max_price', maxPrice);
            
            console.log('Search parameters:', params.toString());
            
            window.location.href = 'mainpage.php?' + params.toString();
        });

        // Add entrance animations
        function initEntranceAnimations() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.special-card, .recommendation-card').forEach(card => {
                observer.observe(card);
            });
        }

        // Enhanced live UI effects
        function initLiveEffects() {
            // Add staggered animation delays
            document.querySelectorAll('.special-card').forEach((card, index) => {
                card.style.setProperty('--index', index);
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Update time ago for specials
            updateTimeAgo();
            setInterval(updateTimeAgo, 60000); // Update every minute

            // Add live typing effect to section title
            typeWriter();

            // Add floating particles effect
            createFloatingParticles();
        }

        // Time ago functionality
        function updateTimeAgo() {
            document.querySelectorAll('.time-ago').forEach(element => {
                const createdDate = new Date(element.getAttribute('data-created'));
                const now = new Date();
                const diffInHours = Math.floor((now - createdDate) / (1000 * 60 * 60));
                
                let timeText = '';
                if (diffInHours < 1) {
                    timeText = '(Just added!)';
                } else if (diffInHours < 24) {
                    timeText = `(${diffInHours}h ago)`;
                } else {
                    const diffInDays = Math.floor(diffInHours / 24);
                    timeText = `(${diffInDays}d ago)`;
                }
                
                element.textContent = timeText;
            });
        }

        // Typing effect for section title
        function typeWriter() {
            const titleElement = document.querySelector('.section-title');
            if (!titleElement) return;
            
            const originalText = titleElement.textContent;
            titleElement.textContent = '';
            
            let i = 0;
            function type() {
                if (i < originalText.length) {
                    titleElement.textContent += originalText.charAt(i);
                    i++;
                    setTimeout(type, 100);
                }
            }
            
            // Start typing when element comes into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        type();
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            observer.observe(titleElement);
        }

        // Floating particles effect
        function createFloatingParticles() {
            const particleContainer = document.createElement('div');
            particleContainer.className = 'particles-container';
            particleContainer.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 1;
                overflow: hidden;
            `;
            
            document.body.appendChild(particleContainer);
            
            // Create particles
            for (let i = 0; i < 20; i++) {
                createParticle(particleContainer);
            }
        }

        function createParticle(container) {
            const particle = document.createElement('div');
            const size = Math.random() * 4 + 2;
            const duration = Math.random() * 20 + 10;
            const delay = Math.random() * 5;
            
            particle.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                background: rgba(231, 76, 60, 0.1);
                border-radius: 50%;
                left: ${Math.random() * 100}%;
                animation: float ${duration}s ${delay}s infinite linear;
            `;
            
            container.appendChild(particle);
            
            // Remove and recreate particle after animation
            setTimeout(() => {
                particle.remove();
                createParticle(container);
            }, (duration + delay) * 1000);
        }

        // Enhanced card interactions
        function initEnhancedInteractions() {
            document.querySelectorAll('.special-card').forEach(card => {
                const overlay = card.querySelector('.special-overlay');
                const button = card.querySelector('.quick-add-btn');
                
                card.addEventListener('mouseenter', () => {
                    card.style.zIndex = '10';
                    if (overlay) overlay.style.opacity = '1';
                });
                
                card.addEventListener('mouseleave', (e) => {
                    // Check if we're moving to a child element
                    if (!card.contains(e.relatedTarget)) {
                        card.style.zIndex = '1';
                        if (overlay) overlay.style.opacity = '0';
                    }
                });
                
                if (button) {
                    button.addEventListener('mouseenter', () => {
                        if (overlay) overlay.style.opacity = '1';
                    });
                    
                    button.addEventListener('mouseleave', (e) => {
                        // Only hide if we're not hovering over the card
                        if (!card.matches(':hover')) {
                            if (overlay) overlay.style.opacity = '0';
                        }
                    });
                }
            });
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            initSmoothScrolling();
            initHeaderScrollEffect();
            initCardAnimations();
            initAddToCart();
            initFilterForm();
            initLiveEffects();
            initEnhancedInteractions();
        });

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
                0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
                10% { opacity: 1; }
                90% { opacity: 1; }
                100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
            }
            
            @keyframes ripple {
                0% { width: 0; height: 0; opacity: 1; }
                100% { width: 300px; height: 300px; opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Call entrance animations after DOM is loaded
        window.addEventListener('load', initEntranceAnimations);

        // View dish function
        function viewDish(dishId) {
            window.location.href = `dish-details.php?id=${dishId}`;
        }

        // Debug: Log subcategories data
        console.log('Subcategories data:', <?= json_encode($subcategories) ?>);
        console.log('Categories data:', <?= json_encode($categories) ?>);
        
        // Subcategories data for JavaScript
        const subcategoriesData = <?= json_encode($subcategories) ?>;
        const categoriesData = <?= json_encode($categories) ?>;
        
        // Load dish types based on selected category
        function loadDishTypes() {
            console.log('loadDishTypes called');
            
            const categorySelect = document.getElementById('category');
            const subcategorySelect = document.getElementById('subcategory');
            const selectedCategoryId = categorySelect.value;
            
            console.log('Selected category ID:', selectedCategoryId);
            console.log('Available subcategories:', subcategoriesData);
            
            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
            
            if (selectedCategoryId) {
                // Enable the subcategory select
                subcategorySelect.disabled = false;
                
                // Filter subcategories for selected category
                const relevantSubcategories = subcategoriesData.filter(sub => {
                    console.log('Checking subcategory:', sub, 'against category:', selectedCategoryId);
                    return sub.category_id == selectedCategoryId;
                });
                
                console.log('Relevant subcategories found:', relevantSubcategories);
                
                // Add subcategories as options
                relevantSubcategories.forEach(subcategory => {
                    const option = document.createElement('option');
                    option.value = subcategory.subcategory_id;
                    option.textContent = subcategory.name;
                    subcategorySelect.appendChild(option);
                });
                
                // If no subcategories found, show message
                if (relevantSubcategories.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No subcategories available for this category';
                    option.disabled = true;
                    subcategorySelect.appendChild(option);
                }
            } else {
                // Disable subcategory select if no category selected
                subcategorySelect.disabled = true;
                subcategorySelect.innerHTML = '<option value="">Select category first</option>';
            }
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            mobileMenu.classList.toggle('active');
            menuBtn.classList.toggle('active');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const mobileMenu = document.getElementById('mobileMenu');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (!mobileMenu.contains(e.target) && !menuBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
                menuBtn.classList.remove('active');
            }
        });

        // Close mobile menu when clicking on links
        document.querySelectorAll('.mobile-menu-link').forEach(link => {
            link.addEventListener('click', function() {
                const mobileMenu = document.getElementById('mobileMenu');
                const menuBtn = document.querySelector('.mobile-menu-btn');
                
                mobileMenu.classList.remove('active');
                menuBtn.classList.remove('active');
            });
        });
    </script>
</body>

</html>

<script>
function toggleFilter() {
    const filterSection = document.getElementById('filterSection');
    filterSection.classList.toggle('show');
}

function resetFilters() {
    document.getElementById('menuFilter').reset();
}

document.getElementById('menuFilter').addEventListener('submit', function(e) {
    e.preventDefault();

    const filters = {
        category: document.getElementById('category').value,
        dishType: document.getElementById('dishType').value,
        cuisineType: document.getElementById('cuisineType').value,
        minPrice: document.getElementById('minPrice').value,
        maxPrice: document.getElementById('maxPrice').value
    };

    console.log('Applying filters:', filters);
    const queryString = new URLSearchParams(filters).toString();
    window.location.href = `mainpage.php?${queryString}`;
});
</script>

<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
<!-- General Booking Modal - Only show for logged-in customers -->
<div id="generalBookingModal" class="booking-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Book a Table</h2>
            <span class="close-modal" onclick="closeGeneralBookingModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="generalBookingForm" method="POST" action="process-booking.php">
                <input type="hidden" name="dealer_id" value="1">
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
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeGeneralBookingModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Book Table</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Update the book table button in index.php
document.addEventListener('DOMContentLoaded', function() {
    const bookBtn = document.querySelector('.book-btn');
    if (bookBtn) {
        bookBtn.addEventListener('click', function() {
            <?php if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer'): ?>
                showNotification('Please login as a customer to book a table', 'info');
                setTimeout(() => {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                }, 2000);
                return;
            <?php endif; ?>
            
            // If logged in as customer, show booking modal
            document.getElementById('generalBookingModal').style.display = 'flex';
        });
    }
});

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
</script>
