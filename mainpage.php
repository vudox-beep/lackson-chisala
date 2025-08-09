<?php
session_start();

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

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$subcategory_filter = $_GET['subcategory'] ?? '';
$location = $_GET['location'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Build query with filters - show ALL listings (no approval check)
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(l.title LIKE ? OR l.description LIKE ? OR d.business_name LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "l.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($subcategory_filter)) {
    $where_conditions[] = "l.subcategory_id = ?";
    $params[] = $subcategory_filter;
}

if (!empty($location)) {
    $where_conditions[] = "d.business_address LIKE ?";
    $params[] = "%$location%";
}

if (!empty($min_price) && is_numeric($min_price)) {
    $where_conditions[] = "l.price >= ?";
    $params[] = $min_price;
}

if (!empty($max_price) && is_numeric($max_price)) {
    $where_conditions[] = "l.price <= ?";
    $params[] = $max_price;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch ALL listings (no approval filter)
try {
    $sql = "SELECT l.listing_id, l.title, l.description, l.price, l.original_price, l.created_at,
                   l.is_daily_special, l.preparation_time, l.serves, l.spice_level, l.cuisine_type,
                   l.dietary_info, l.ingredients, l.stock_quantity, l.views_count,
                   i.image_url, c.name as category_name, s.name as subcategory_name,
                   d.business_name, d.business_address, d.business_phone, d.status as dealer_status
            FROM food_listings l
            LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
            LEFT JOIN food_categories c ON l.category_id = c.category_id
            LEFT JOIN food_subcategories s ON l.subcategory_id = s.subcategory_id
            LEFT JOIN dealers d ON l.dealer_id = d.dealer_id
            $where_clause
            ORDER BY 
                CASE 
                    WHEN l.title LIKE ? THEN 1
                    WHEN l.description LIKE ? THEN 2
                    WHEN d.business_name LIKE ? THEN 3
                    WHEN c.name LIKE ? THEN 4
                    WHEN s.name LIKE ? THEN 5
                    ELSE 6
                END,
                l.is_daily_special DESC, l.created_at DESC";
    
    // Add search parameters for ORDER BY clause
    $order_params = [];
    if (!empty($search)) {
        $order_params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
    } else {
        $order_params = ['', '', '', '', ''];
    }
    
    $all_params = array_merge($params, $order_params);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group dishes by category for display
    $dishesByCategory = [];
    foreach ($dishes as $dish) {
        $categoryName = $dish['category_name'] ?: 'Uncategorized';
        if (!isset($dishesByCategory[$categoryName])) {
            $dishesByCategory[$categoryName] = [];
        }
        $dishesByCategory[$categoryName][] = $dish;
    }
    
    // Debug output
    error_log("Total dishes found: " . count($dishes));
    error_log("Categories with dishes: " . count($dishesByCategory));
    
} catch (Exception $e) {
    $dishes = [];
    $dishesByCategory = [];
    $error = "Error fetching dishes: " . $e->getMessage();
    error_log($error);
}

// Get all categories for filter dropdown
try {
    $stmt = $pdo->prepare("SELECT category_id, name FROM food_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Get all subcategories for JavaScript
try {
    $stmt = $pdo->prepare("SELECT subcategory_id, category_id, name FROM food_subcategories ORDER BY name");
    $stmt->execute();
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $subcategories = [];
}

// Sort categories alphabetically
ksort($dishesByCategory);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/main_page.css">
    <link rel="stylesheet" href="styles/index.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🥄 The Lucksons Spoon</div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="mainpage.php" class="active">Menu</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="nav-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'customer'): ?>
                        <a href="customer-panel.php" class="login-btn">My Account</a>
                        <button class="book-btn" onclick="bookTable()">Book a Table</button>
                    <?php else: ?>
                        <a href="dealer-panel.php" class="login-btn">Dashboard</a>
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
                    <a href="mainpage.php" class="active">Menu</a>
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

    <!-- Filter Section -->
    <section class="filter-section-menu">
        <div class="container">
            <h2>Find Your Perfect Dish</h2>
            <form class="menu-filter-form" method="GET">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Search dishes..." 
                           value="<?= htmlspecialchars($search) ?>" class="search-input">
                    
                    <select name="category" class="filter-select" onchange="loadSubcategories()" id="categorySelect">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" 
                                    <?= $category_filter == $category['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="subcategory" class="filter-select" id="subcategorySelect">
                        <option value="">All Dish Types</option>
                    </select>
                    
                    <input type="number" name="min_price" placeholder="Min Price" 
                           value="<?= htmlspecialchars($min_price) ?>" class="price-input">
                    
                    <input type="number" name="max_price" placeholder="Max Price" 
                           value="<?= htmlspecialchars($max_price) ?>" class="price-input">
                    
                    <button type="submit" class="search-btn">Search</button>
                    <a href="mainpage.php" class="reset-btn">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <!-- Results Summary -->
    <section class="results-summary">
        <div class="container">
            <h3><?= count($dishes) ?> dishes available</h3>
            <?php if ($search || $category_filter || $subcategory_filter || $location || $min_price || $max_price): ?>
                <div class="active-filters">
                    <span>Active filters:</span>
                    <?php if ($search): ?>
                        <span class="filter-tag">Search: "<?= htmlspecialchars($search) ?>"</span>
                    <?php endif; ?>
                    <?php if ($category_filter): ?>
                        <?php
                        $category_name = 'Unknown';
                        foreach ($categories as $cat) {
                            if ($cat['category_id'] == $category_filter) {
                                $category_name = $cat['name'];
                                break;
                            }
                        }
                        ?>
                        <span class="filter-tag">Category: <?= htmlspecialchars($category_name) ?></span>
                    <?php endif; ?>
                    <?php if ($min_price): ?>
                        <span class="filter-tag">Min: $<?= htmlspecialchars($min_price) ?></span>
                    <?php endif; ?>
                    <?php if ($max_price): ?>
                        <span class="filter-tag">Max: $<?= htmlspecialchars($max_price) ?></span>
                    <?php endif; ?>
                    <a href="mainpage.php" class="clear-filters">Clear all filters</a>
                </div>
            <?php else: ?>
                <p class="showing-all">Showing all dishes from our restaurant partners</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Menu Content -->
    <main class="menu-container">
        <div class="container">
            <?php if (empty($dishes)): ?>
                <div class="no-results">
                    <div class="empty-icon">🍽️</div>
                    <h3>No dishes found</h3>
                    <p>Try adjusting your search criteria or browse all dishes</p>
                    <a href="mainpage.php" class="btn-primary">View All Dishes</a>
                </div>
            <?php else: ?>
                <?php foreach ($dishesByCategory as $categoryName => $categoryDishes): ?>
                    <div class="category-section">
                        <h2 class="category-title"><?= htmlspecialchars($categoryName) ?></h2>
                        <div class="dishes-grid">
                            <?php foreach ($categoryDishes as $dish): ?>
                                <div class="dish-card" onclick="viewDish(<?= $dish['listing_id'] ?>)">
                                    <div class="dish-image-container">
                                        <?php if (!empty($dish['image_url']) && file_exists($dish['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($dish['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($dish['title']) ?>" 
                                                 class="dish-image">
                                        <?php else: ?>
                                            <div class="dish-image-placeholder">
                                                <span>🍽️</span>
                                                <p>No Image</p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Special Badge -->
                                        <?php if ($dish['is_daily_special']): ?>
                                            <div class="special-badge">
                                                <span class="badge-text">SPECIAL</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Additional Info Badges -->
                                        <?php if ($dish['preparation_time']): ?>
                                            <div class="info-badge prep-time">
                                                <span>⏱️ <?= $dish['preparation_time'] ?> min</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($dish['spice_level']): ?>
                                            <div class="info-badge spice-level">
                                                <span>🌶️ <?= ucfirst($dish['spice_level']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Dealer Status Indicator -->
                                        <?php if ($dish['dealer_status'] === 'suspended'): ?>
                                            <div class="info-badge dealer-suspended">
                                                <span>⏸️ Temporarily Unavailable</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="dish-overlay">
                                            <button class="add-to-cart-btn" 
                                                    onclick="event.stopPropagation(); addToCart(<?= $dish['listing_id'] ?>, '<?= htmlspecialchars($dish['title'], ENT_QUOTES) ?>', <?= $dish['price'] ?>)">
                                                Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                    <div class="dish-content">
                                        <h3 class="dish-title"><?= htmlspecialchars($dish['title']) ?></h3>
                                        <p class="dish-description"><?= htmlspecialchars(substr($dish['description'], 0, 100)) ?>...</p>
                                        
                                        <!-- Dietary Info -->
                                        <?php if ($dish['dietary_info']): ?>
                                            <div class="dietary-info">
                                                <span class="dietary-label">🥗 <?= htmlspecialchars($dish['dietary_info']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Additional dish info -->
                                        <div class="dish-details">
                                            <?php if ($dish['serves']): ?>
                                                <span class="detail-item">👥 Serves <?= $dish['serves'] ?></span>
                                            <?php endif; ?>
                                            <?php if ($dish['cuisine_type']): ?>
                                                <span class="detail-item">🍴 <?= htmlspecialchars($dish['cuisine_type']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($dish['views_count']): ?>
                                                <span class="detail-item">👁️ <?= $dish['views_count'] ?> views</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="dish-meta">
                                            <span class="dish-restaurant"><?= htmlspecialchars($dish['business_name']) ?></span>
                                            <?php if ($dish['subcategory_name']): ?>
                                                <span class="dish-subcategory"><?= htmlspecialchars($dish['subcategory_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dish-footer">
                                            <div class="price-section">
                                                <?php if ($dish['is_daily_special'] && $dish['original_price'] && $dish['price'] < $dish['original_price']): ?>
                                                    <span class="special-price">$<?= number_format($dish['price'], 2) ?></span>
                                                    <span class="original-price">$<?= number_format($dish['original_price'], 2) ?></span>
                                                <?php else: ?>
                                                    <span class="dish-price">$<?= number_format($dish['price'], 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="dish-rating">
                                                <span class="stars">★★★★★</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Subcategories data for JavaScript
        const subcategoriesData = <?= json_encode($subcategories) ?>;
        
        // Load subcategories based on selected category
        function loadSubcategories() {
            const categorySelect = document.getElementById('categorySelect');
            const subcategorySelect = document.getElementById('subcategorySelect');
            const selectedCategoryId = categorySelect.value;
            
            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">All Dish Types</option>';
            
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
            }
        }

        // Book table function
        function bookTable() {
            <?php if (!isset($_SESSION['user_id'])): ?>
                alert('Please login to book a table');
                window.location.href = 'login.php';
                return;
            <?php elseif ($_SESSION['role'] !== 'customer'): ?>
                alert('Only customers can book tables');
                return;
            <?php else: ?>
                window.location.href = 'customer-panel.php';
            <?php endif; ?>
        }

        // View dish function
        function viewDish(dishId) {
            window.location.href = `dish-details.php?id=${dishId}`;
        }

        // Add to cart function
        function addToCart(dishId, dishName, price) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                alert('Please login to add items to cart');
                window.location.href = 'login.php';
                return;
            <?php endif; ?>
            
            console.log(`Adding to cart: ${dishName} - $${price}`);
            alert(`${dishName} added to cart!`);
        }

        // Initialize subcategories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSubcategories();
            
            // Set selected subcategory if exists
            const urlParams = new URLSearchParams(window.location.search);
            const selectedSubcategory = urlParams.get('subcategory');
            if (selectedSubcategory) {
                setTimeout(() => {
                    document.getElementById('subcategorySelect').value = selectedSubcategory;
                }, 100);
            }
        });
    </script>
</body>
</html>
