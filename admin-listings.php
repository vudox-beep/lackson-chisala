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

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_listing'])) {
        $listing_id = $_POST['listing_id'];
        $stmt = $pdo->prepare("UPDATE food_listings SET is_approved = 1 WHERE listing_id = ?");
        $stmt->execute([$listing_id]);
        $success = "Listing approved successfully!";
    }
    
    if (isset($_POST['reject_listing'])) {
        $listing_id = $_POST['listing_id'];
        $stmt = $pdo->prepare("UPDATE food_listings SET is_approved = 0 WHERE listing_id = ?");
        $stmt->execute([$listing_id]);
        $success = "Listing rejected successfully!";
    }
    
    if (isset($_POST['delete_listing'])) {
        $listing_id = $_POST['listing_id'];
        try {
            $pdo->beginTransaction();
            
            // Delete images first
            $stmt = $pdo->prepare("SELECT image_url FROM food_images WHERE listing_id = ?");
            $stmt->execute([$listing_id]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($images as $image) {
                if (file_exists($image['image_url'])) {
                    unlink($image['image_url']);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM food_images WHERE listing_id = ?");
            $stmt->execute([$listing_id]);
            
            $stmt = $pdo->prepare("DELETE FROM food_listings WHERE listing_id = ?");
            $stmt->execute([$listing_id]);
            
            $pdo->commit();
            $success = "Listing deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error deleting listing: " . $e->getMessage();
        }
    }
    
    // Bulk actions
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_listings'])) {
        $action = $_POST['bulk_action'];
        $selected_listings = $_POST['selected_listings'];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_listings as $listing_id) {
                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE food_listings SET is_approved = 1 WHERE listing_id = ?");
                    $stmt->execute([$listing_id]);
                } elseif ($action === 'reject') {
                    $stmt = $pdo->prepare("UPDATE food_listings SET is_approved = 0 WHERE listing_id = ?");
                    $stmt->execute([$listing_id]);
                } elseif ($action === 'delete') {
                    // Delete images first
                    $stmt = $pdo->prepare("SELECT image_url FROM food_images WHERE listing_id = ?");
                    $stmt->execute([$listing_id]);
                    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($images as $image) {
                        if (file_exists($image['image_url'])) {
                            unlink($image['image_url']);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM food_images WHERE listing_id = ?");
                    $stmt->execute([$listing_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM food_listings WHERE listing_id = ?");
                    $stmt->execute([$listing_id]);
                }
            }
            
            $pdo->commit();
            $success = ucfirst($action) . " completed for " . count($selected_listings) . " listings!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['filter'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(l.title LIKE ? OR l.description LIKE ? OR d.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.name = ?";
    $params[] = $category_filter;
}

if ($status_filter === 'pending') {
    $where_conditions[] = "l.is_approved = 0";
} elseif ($status_filter === 'approved') {
    $where_conditions[] = "l.is_approved = 1";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all listings with dealer info
try {
    $sql = "SELECT 
               l.listing_id, 
               l.dealer_id,
               l.title, 
               l.description, 
               l.price, 
               l.is_approved,
               l.created_at, 
               i.image_url, 
               c.name as category_name,
               CONCAT(u.first_name, ' ', u.last_name) as dealer_name,
               u.email as dealer_email,
               d.status as dealer_status,
               d.business_name,
               d.business_phone,
               d.business_type,
               d.business_address
               FROM food_listings l
               LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
               LEFT JOIN food_categories c ON l.category_id = c.category_id
               LEFT JOIN dealers d ON l.dealer_id = d.dealer_id
               LEFT JOIN users u ON d.user_id = u.user_id
               $where_clause
               ORDER BY $sort_by $sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allListings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allListings = [];
    $error = "Error fetching listings: " . $e->getMessage();
}

// Get categories for filter
try {
    $stmt = $pdo->prepare("SELECT DISTINCT name FROM food_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
}

// Get statistics
$totalListings = count($allListings);
$approvedCount = count(array_filter($allListings, fn($l) => $l['is_approved'] == 1));
$pendingCount = count(array_filter($allListings, fn($l) => $l['is_approved'] == 0));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Listings - Admin Panel</title>
    <link rel="stylesheet" href="styles/admin-panel.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">📊 Manage Listings</div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="admin-panel.php">Dashboard</a>
                <a href="admin-listings.php" class="active">Listings</a>
                <a href="admin-dealers.php">Dealers</a>
                <a href="admin-categories.php">Categories</a>
                <a href="admin-approvals.php">Approvals</a>
            </div>
            <div class="user-menu">
                <span class="admin-name">Administrator</span>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <h1>Manage Listings</h1>
                    <p>Review and manage all food listings</p>
                </div>
                <div class="header-stats">
                    <div class="header-stat">
                        <span class="stat-number"><?= $totalListings ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="header-stat approved">
                        <span class="stat-number"><?= $approvedCount ?></span>
                        <span class="stat-label">Approved</span>
                    </div>
                    <div class="header-stat pending">
                        <span class="stat-number"><?= $pendingCount ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-section">
                <form class="filters-form" method="GET">
                    <div class="search-group">
                        <input type="text" name="search" placeholder="Search listings, dealers..." 
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
                        
                        <select name="filter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                        
                        <select name="sort" class="filter-select">
                            <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                            <option value="title" <?= $sort_by === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Price</option>
                        </select>
                        
                        <button type="submit" class="btn-filter">Apply</button>
                        <a href="admin-listings.php" class="btn-reset">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <?php if (!empty($allListings)): ?>
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
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                                <option value="delete">Delete</option>
                            </select>
                            
                            <button type="submit" class="btn-bulk" onclick="return confirmBulkAction()">
                                Apply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Listings Grid -->
            <div class="listings-container">
                <?php if (empty($allListings)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3>No listings found</h3>
                        <p>No listings match your current filters</p>
                    </div>
                <?php else: ?>
                    <div class="listings-grid">
                        <?php foreach ($allListings as $listing): ?>
                            <div class="listing-card">
                                <div class="listing-header">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="selected_listings[]" 
                                               value="<?= $listing['listing_id'] ?>" class="listing-checkbox">
                                        <span class="checkmark"></span>
                                    </label>
                                    <div class="listing-status <?= $listing['is_approved'] ? 'approved' : 'pending' ?>">
                                        <?= $listing['is_approved'] ? 'Approved' : 'Pending' ?>
                                    </div>
                                </div>
                                
                                <?php if ($listing['image_url'] && file_exists($listing['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($listing['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($listing['title']) ?>" 
                                         class="listing-image">
                                <?php else: ?>
                                    <div class="listing-image-placeholder">
                                        <span>📷</span>
                                        <p>No Image</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="listing-info">
                                    <h3 class="listing-title"><?= htmlspecialchars($listing['title']) ?></h3>
                                    <p class="listing-description"><?= htmlspecialchars(substr($listing['description'], 0, 100)) ?>...</p>
                                    
                                    <div class="listing-meta">
                                        <span class="listing-price">$<?= number_format($listing['price'], 2) ?></span>
                                        <span class="listing-category"><?= htmlspecialchars($listing['category_name'] ?: 'Uncategorized') ?></span>
                                    </div>
                                    
                                    <div class="listing-dealer">
                                        <span class="dealer-info">
                                            <strong>Business:</strong> 
                                            <?php if (!empty($listing['business_name'])): ?>
                                                <?= htmlspecialchars($listing['business_name']) ?>
                                                <?php if (!empty($listing['contact_person'])): ?>
                                                    <small>(Contact: <?= htmlspecialchars($listing['contact_person']) ?>)</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($listing['dealer_name'] ?: 'Unknown Business') ?>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($listing['dealer_status'])): ?>
                                                <span class="dealer-status <?= $listing['dealer_status'] ?>">
                                                    (<?= ucfirst($listing['dealer_status']) ?>)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        
                                        <?php if (!empty($listing['business_phone'])): ?>
                                            <div class="dealer-contact">
                                                <small>📞 <?= htmlspecialchars($listing['business_phone']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($listing['dealer_email'])): ?>
                                            <div class="dealer-contact">
                                                <small>✉️ <?= htmlspecialchars($listing['dealer_email']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($listing['business_type'])): ?>
                                            <div class="dealer-type">
                                                <small class="role-badge <?= strtolower(str_replace(' ', '_', $listing['business_type'])) ?>">
                                                    <?= htmlspecialchars($listing['business_type']) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="dealer-id">
                                            <small>ID: <?= $listing['dealer_id'] ?: 'N/A' ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="listing-date">
                                        Added: <?= date('M d, Y H:i', strtotime($listing['created_at'])) ?>
                                    </div>
                                    
                                    <div class="listing-actions">
                                        <?php if (!$listing['is_approved']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="listing_id" value="<?= $listing['listing_id'] ?>">
                                                <button type="submit" name="approve_listing" class="btn-approve">✅ Approve</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="listing_id" value="<?= $listing['listing_id'] ?>">
                                                <button type="submit" name="reject_listing" class="btn-reject">❌ Reject</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this listing?')">
                                            <input type="hidden" name="listing_id" value="<?= $listing['listing_id'] ?>">
                                            <button type="submit" name="delete_listing" class="btn-delete">🗑️ Delete</button>
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

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.listing-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk action confirmation
        function confirmBulkAction() {
            const selected = document.querySelectorAll('.listing-checkbox:checked');
            const action = document.querySelector('[name="bulk_action"]').value;
            
            if (selected.length === 0) {
                alert('Please select at least one listing.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected.length} listing(s)? This action cannot be undone.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${selected.length} listing(s)?`);
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'index.php';
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







