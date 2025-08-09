<?php
// Start session first
session_start();

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

// Check if user is logged in as business owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    header("Location: login.php");
    exit;
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

$errors = [];
$success = '';

// Handle dish deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_dish'])) {
    $dish_id = $_POST['dish_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete images first
        $stmt = $pdo->prepare("SELECT image_url FROM food_images WHERE listing_id = ?");
        $stmt->execute([$dish_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $image) {
            if (file_exists($image['image_url'])) {
                unlink($image['image_url']);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM food_images WHERE listing_id = ?");
        $stmt->execute([$dish_id]);
        
        // Delete listing
        $stmt = $pdo->prepare("DELETE FROM food_listings WHERE listing_id = ? AND dealer_id = ?");
        $stmt->execute([$dish_id, $dealer_id]);
        
        $pdo->commit();
        $success = "Dish deleted successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors['database'] = "Error deleting dish: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_dishes = $_POST['selected_dishes'] ?? [];
    
    if (!empty($selected_dishes)) {
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_dishes as $dish_id) {
                if ($action === 'delete') {
                    // Delete images first
                    $stmt = $pdo->prepare("SELECT image_url FROM food_images WHERE listing_id = ?");
                    $stmt->execute([$dish_id]);
                    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($images as $image) {
                        if (file_exists($image['image_url'])) {
                            unlink($image['image_url']);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM food_images WHERE listing_id = ?");
                    $stmt->execute([$dish_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM food_listings WHERE listing_id = ? AND dealer_id = ?");
                    $stmt->execute([$dish_id, $dealer_id]);
                } elseif ($action === 'activate') {
                    $stmt = $pdo->prepare("UPDATE food_listings SET is_approved = 1 WHERE listing_id = ? AND dealer_id = ?");
                    $stmt->execute([$dish_id, $dealer_id]);
                } elseif ($action === 'deactivate') {
                    $stmt = $pdo->prepare("UPDATE food_listings SET is_approved = 0 WHERE listing_id = ? AND dealer_id = ?");
                    $stmt->execute([$dish_id, $dealer_id]);
                }
            }
            
            $pdo->commit();
            $success = ucfirst($action) . " completed for " . count($selected_dishes) . " dishes!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['database'] = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query with filters
$where_conditions = ["l.dealer_id = ?"];
$params = [$dealer_id];

if (!empty($search)) {
    $where_conditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.name = ?";
    $params[] = $category_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "l.is_approved = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch dealer's dishes with filters
try {
    $sql = "SELECT l.listing_id, l.title, l.description, l.price, l.created_at, l.is_approved,
                   i.image_url, c.name as category_name, s.name as subcategory_name,
                   COUNT(o.order_id) as order_count
            FROM food_listings l
            LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
            LEFT JOIN food_categories c ON l.category_id = c.category_id
            LEFT JOIN food_subcategories s ON l.subcategory_id = s.subcategory_id
            LEFT JOIN orders o ON l.listing_id = o.listing_id
            WHERE $where_clause
            GROUP BY l.listing_id
            ORDER BY $sort_by $sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dishes = [];
    $errors['database'] = "Error fetching dishes: " . $e->getMessage();
}

// Get categories for filter dropdown
try {
    $stmt = $pdo->prepare("SELECT DISTINCT c.name FROM food_categories c 
                          INNER JOIN food_listings l ON c.category_id = l.category_id 
                          WHERE l.dealer_id = ? ORDER BY c.name");
    $stmt->execute([$dealer_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
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
    <title>My Dishes - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/dealer-panel.css">
    <link rel="stylesheet" href="styles/my-dishes.css">
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
            <a href="my-dishes.php" class="nav-item active">
                <span class="nav-icon">🍽️</span>
                <span class="nav-text">My Dishes</span>
            </a>
            <a href="manage-bookings.php" class="nav-item">
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
                <h1>My Dishes</h1>
            </div>
        </header>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <h1>My Dishes</h1>
                    <p>Manage all your uploaded dishes</p>
                </div>
                <div class="header-actions">
                    <a href="dealer-panel.php#upload" class="btn-primary">
                        <span>➕</span> Add New Dish
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?= htmlspecialchars($success) ?></p>
                </div>
            <?php endif; ?>

            <!-- Filters and Search -->
            <div class="filters-section">
                <form class="filters-form" method="GET">
                    <div class="search-group">
                        <input type="text" name="search" placeholder="Search dishes..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">🔍</button>
                    </div>
                    
                    <div class="filter-group">
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" 
                                        <?= $category_filter === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        
                        <select name="sort" class="filter-select">
                            <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                            <option value="title" <?= $sort_by === 'title' ? 'selected' : '' ?>>Name</option>
                            <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Price</option>
                            <option value="order_count" <?= $sort_by === 'order_count' ? 'selected' : '' ?>>Orders</option>
                        </select>
                        
                        <select name="order" class="filter-select">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                        
                        <button type="submit" class="btn-filter">Apply</button>
                        <a href="my-dishes.php" class="btn-reset">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <?php if (!empty($dishes)): ?>
                <div class="bulk-actions">
                    <form method="POST" id="bulkForm">
                        <div class="bulk-controls">
                            <label class="checkbox-container">
                                <input type="checkbox" id="selectAll">
                                <span class="checkmark"></span>
                                Select All
                            </label>
                            
                            <select name="bulk_action" class="bulk-select" required>
                                <option value="">Bulk Actions</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="delete">Delete</option>
                            </select>
                            
                            <button type="submit" class="btn-bulk" onclick="return confirmBulkAction()">
                                Apply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Dishes Grid -->
            <div class="dishes-container">
                <?php if (empty($dishes)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🍽️</div>
                        <h3>No dishes found</h3>
                        <p>Start by uploading your first dish or adjust your filters</p>
                        <a href="dealer-panel.php#upload" class="btn-primary">Upload First Dish</a>
                    </div>
                <?php else: ?>
                    <div class="dishes-grid">
                        <?php foreach ($dishes as $dish): ?>
                            <div class="dish-card">
                                <div class="dish-header">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="selected_dishes[]" 
                                               value="<?= $dish['listing_id'] ?>" class="dish-checkbox">
                                        <span class="checkmark"></span>
                                    </label>
                                    <div class="dish-status <?= $dish['is_approved'] ? 'approved' : 'pending' ?>">
                                        <?= $dish['is_approved'] ? 'Active' : 'Inactive' ?>
                                    </div>
                                </div>
                                
                                <?php if ($dish['image_url']): ?>
                                    <img src="<?= htmlspecialchars($dish['image_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                         alt="<?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                         class="dish-image">
                                <?php else: ?>
                                    <div class="dish-image-placeholder">
                                        <span>📷</span>
                                        <p>No Image</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="dish-info">
                                    <h3 class="dish-title"><?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="dish-description"><?= htmlspecialchars(substr($dish['description'], 0, 100), ENT_QUOTES, 'UTF-8') ?>...</p>
                                    
                                    <div class="dish-meta">
                                        <span class="dish-price">$<?= number_format($dish['price'], 2) ?></span>
                                        <span class="dish-category"><?= htmlspecialchars($dish['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    
                                    <div class="dish-stats">
                                        <span class="stat-item">
                                            <span class="stat-icon">📅</span>
                                            <?= date('M d, Y', strtotime($dish['created_at'])) ?>
                                        </span>
                                        <span class="stat-item">
                                            <span class="stat-icon">📦</span>
                                            <?= $dish['order_count'] ?> orders
                                        </span>
                                    </div>
                                    
                                    <div class="dish-actions">
                                        <a href="dish-details.php?id=<?= $dish['listing_id'] ?>" 
                                           class="btn-action btn-view" target="_blank" title="View Dish">
                                            👁️
                                        </a>
                                        <a href="edit-listing.php?id=<?= $dish['listing_id'] ?>" 
                                           class="btn-action btn-edit" title="Edit Dish">
                                            ✏️
                                        </a>
                                        <button onclick="viewOrders(<?= $dish['listing_id'] ?>)" 
                                                class="btn-action btn-orders" title="View Orders">
                                            📋
                                        </button>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this dish?')">
                                            <input type="hidden" name="delete_dish" value="1">
                                            <input type="hidden" name="dish_id" value="<?= $dish['listing_id'] ?>">
                                            <button type="submit" class="btn-action btn-delete" title="Delete Dish">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Orders Modal -->
    <div id="ordersModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Dish Orders</h2>
                <span class="close" onclick="closeOrdersModal()">&times;</span>
            </div>
            <div class="modal-body" id="ordersContent">
                <!-- Orders content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.dish-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk action confirmation
        function confirmBulkAction() {
            const selected = document.querySelectorAll('.dish-checkbox:checked');
            const action = document.querySelector('[name="bulk_action"]').value;
            
            if (selected.length === 0) {
                alert('Please select at least one dish.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected.length} dish(es)? This action cannot be undone.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${selected.length} dish(es)?`);
        }

        // View orders modal
        function viewOrders(dishId) {
            document.getElementById('ordersModal').style.display = 'block';
            document.getElementById('ordersContent').innerHTML = '<div class="loading">Loading orders...</div>';
            
            // Simulate loading orders (replace with actual AJAX call)
            setTimeout(() => {
                document.getElementById('ordersContent').innerHTML = `
                    <div class="orders-summary">
                        <div class="summary-card">
                            <h4>Total Orders</h4>
                            <span class="summary-number">${Math.floor(Math.random() * 50)}</span>
                        </div>
                        <div class="summary-card">
                            <h4>This Month</h4>
                            <span class="summary-number">${Math.floor(Math.random() * 20)}</span>
                        </div>
                        <div class="summary-card">
                            <h4>Revenue</h4>
                            <span class="summary-number">$${(Math.random() * 1000).toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="recent-orders">
                        <h4>Recent Orders</h4>
                        <div class="order-item">
                            <span>Order #1234 - $25.99 - 2 hours ago</span>
                        </div>
                        <div class="order-item">
                            <span>Order #1233 - $25.99 - 5 hours ago</span>
                        </div>
                        <div class="order-item">
                            <span>Order #1232 - $25.99 - 1 day ago</span>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        function closeOrdersModal() {
            document.getElementById('ordersModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('ordersModal');
            if (event.target == modal) {
                closeOrdersModal();
            }
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>





