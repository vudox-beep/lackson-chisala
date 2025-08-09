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
    if (isset($_POST['activate_dealer'])) {
        $dealer_id = $_POST['dealer_id'];
        $stmt = $pdo->prepare("UPDATE dealers SET status = 'active' WHERE dealer_id = ?");
        $stmt->execute([$dealer_id]);
        $success = "Dealer activated successfully!";
    }
    
    if (isset($_POST['suspend_dealer'])) {
        $dealer_id = $_POST['dealer_id'];
        $stmt = $pdo->prepare("UPDATE dealers SET status = 'suspended' WHERE dealer_id = ?");
        $stmt->execute([$dealer_id]);
        $success = "Dealer suspended successfully!";
    }
    
    if (isset($_POST['delete_dealer'])) {
        $dealer_id = $_POST['dealer_id'];
        try {
            $pdo->beginTransaction();
            
            // Delete dealer's listing images
            $stmt = $pdo->prepare("SELECT i.image_url FROM food_images i 
                                  JOIN food_listings l ON i.listing_id = l.listing_id 
                                  WHERE l.dealer_id = ?");
            $stmt->execute([$dealer_id]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($images as $image) {
                if (file_exists($image['image_url'])) {
                    unlink($image['image_url']);
                }
            }
            
            // Delete images records
            $stmt = $pdo->prepare("DELETE i FROM food_images i 
                                  JOIN food_listings l ON i.listing_id = l.listing_id 
                                  WHERE l.dealer_id = ?");
            $stmt->execute([$dealer_id]);
            
            // Delete listings
            $stmt = $pdo->prepare("DELETE FROM food_listings WHERE dealer_id = ?");
            $stmt->execute([$dealer_id]);
            
            // Delete dealer record
            $stmt = $pdo->prepare("DELETE FROM dealers WHERE dealer_id = ?");
            $stmt->execute([$dealer_id]);
            
            $pdo->commit();
            $success = "Dealer and all associated data deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error deleting dealer: " . $e->getMessage();
        }
    }
    
    // Bulk actions
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_dealers'])) {
        $action = $_POST['bulk_action'];
        $selected_dealers = $_POST['selected_dealers'];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($selected_dealers as $dealer_id) {
                if ($action === 'activate') {
                    $stmt = $pdo->prepare("UPDATE dealers SET status = 'active' WHERE dealer_id = ?");
                    $stmt->execute([$dealer_id]);
                } elseif ($action === 'suspend') {
                    $stmt = $pdo->prepare("UPDATE dealers SET status = 'suspended' WHERE dealer_id = ?");
                    $stmt->execute([$dealer_id]);
                } elseif ($action === 'delete') {
                    // Delete dealer's listing images
                    $stmt = $pdo->prepare("SELECT i.image_url FROM food_images i 
                                          JOIN food_listings l ON i.listing_id = l.listing_id 
                                          WHERE l.dealer_id = ?");
                    $stmt->execute([$dealer_id]);
                    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($images as $image) {
                        if (file_exists($image['image_url'])) {
                            unlink($image['image_url']);
                        }
                    }
                    
                    // Delete images records
                    $stmt = $pdo->prepare("DELETE i FROM food_images i 
                                          JOIN food_listings l ON i.listing_id = l.listing_id 
                                          WHERE l.dealer_id = ?");
                    $stmt->execute([$dealer_id]);
                    
                    // Delete listings
                    $stmt = $pdo->prepare("DELETE FROM food_listings WHERE dealer_id = ?");
                    $stmt->execute([$dealer_id]);
                    
                    // Delete dealer
                    $stmt = $pdo->prepare("DELETE FROM dealers WHERE dealer_id = ?");
                    $stmt->execute([$dealer_id]);
                }
            }
            
            $pdo->commit();
            $success = ucfirst($action) . " completed for " . count($selected_dealers) . " dealers!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR d.business_phone LIKE ? OR d.business_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "d.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';

// Get all dealers with user info
try {
    $sql = "SELECT 
               d.dealer_id,
               d.user_id,
               CONCAT(u.first_name, ' ', u.last_name) as name,
               u.email,
               d.business_phone as phone,
               d.business_name,
               d.business_type,
               d.business_address as address,
               d.status,
               d.commission_rate,
               d.total_sales,
               d.rating,
               d.created_at,
               COUNT(l.listing_id) as total_listings,
               COUNT(CASE WHEN l.is_approved = 1 THEN 1 END) as approved_listings,
               COUNT(CASE WHEN l.is_approved = 0 THEN 1 END) as pending_listings
               FROM dealers d
               JOIN users u ON d.user_id = u.user_id
               LEFT JOIN food_listings l ON d.dealer_id = l.dealer_id
               WHERE 1=1 $where_clause
               GROUP BY d.dealer_id
               ORDER BY $sort_by $sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allDealers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allDealers = [];
    $error = "Error fetching dealers: " . $e->getMessage();
}

// Get statistics
$totalDealers = count($allDealers);
$activeCount = count(array_filter($allDealers, fn($d) => $d['status'] === 'active'));
$suspendedCount = count(array_filter($allDealers, fn($d) => $d['status'] === 'suspended'));
$pendingCount = count(array_filter($allDealers, fn($d) => $d['status'] === 'pending'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Dealers - Admin Panel</title>
    <link rel="stylesheet" href="styles/admin-panel.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">👥 Manage Dealers</div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="admin-panel.php">Dashboard</a>
                <a href="admin-listings.php">Listings</a>
                <a href="admin-dealers.php" class="active">Dealers</a>
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
                    <h1>Manage Dealers</h1>
                    <p>View and manage all registered dealers</p>
                </div>
                <div class="header-stats">
                    <div class="header-stat">
                        <span class="stat-number"><?= $totalDealers ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="header-stat approved">
                        <span class="stat-number"><?= $activeCount ?></span>
                        <span class="stat-label">Active</span>
                    </div>
                    <div class="header-stat suspended">
                        <span class="stat-number"><?= $suspendedCount ?></span>
                        <span class="stat-label">Suspended</span>
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
                        <input type="text" name="search" placeholder="Search dealers..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">🔍</button>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                        
                        <select name="sort" class="filter-select">
                            <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Date Joined</option>
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name</option>
                            <option value="total_listings" <?= $sort_by === 'total_listings' ? 'selected' : '' ?>>Total Listings</option>
                        </select>
                        
                        <select name="order" class="filter-select">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                        
                        <button type="submit" class="btn-filter">Apply</button>
                        <a href="admin-dealers.php" class="btn-reset">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <?php if (!empty($allDealers)): ?>
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
                                <option value="suspend">Suspend</option>
                                <option value="delete">Delete</option>
                            </select>
                            
                            <button type="submit" class="btn-bulk" onclick="return confirmBulkAction()">
                                Apply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Dealers Table -->
            <div class="dealers-container">
                <?php if (empty($allDealers)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <h3>No dealers found</h3>
                        <p>No dealers match your current filters</p>
                    </div>
                <?php else: ?>
                    <div class="dealers-table-container">
                        <table class="dealers-table">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="checkbox-container">
                                            <input type="checkbox" id="selectAllTable">
                                            <span class="checkmark"></span>
                                        </label>
                                    </th>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Listings</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allDealers as $dealer): ?>
                                    <tr class="dealer-row">
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="selected_dealers[]" 
                                                       value="<?= $dealer['dealer_id'] ?>" class="dealer-checkbox">
                                                <span class="checkmark"></span>
                                            </label>
                                        </td>
                                        <td class="dealer-id"><?= $dealer['dealer_id'] ?></td>
                                        <td class="dealer-name">
                                            <strong><?= htmlspecialchars($dealer['name']) ?></strong>
                                        </td>
                                        <td class="dealer-email"><?= htmlspecialchars($dealer['email']) ?></td>
                                        <td class="dealer-phone"><?= htmlspecialchars($dealer['phone'] ?: 'N/A') ?></td>
                                        <td class="dealer-status">
                                            <span class="status-badge <?= $dealer['status'] ?>">
                                                <?= ucfirst($dealer['status'] ?: 'pending') ?>
                                            </span>
                                        </td>
                                        <td class="dealer-listings">
                                            <div class="listings-info">
                                                <span class="total-listings"><?= $dealer['total_listings'] ?> total</span>
                                                <div class="listings-breakdown">
                                                    <span class="approved-listings">✅ <?= $dealer['approved_listings'] ?></span>
                                                    <span class="pending-listings">⏳ <?= $dealer['pending_listings'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="dealer-date"><?= date('M d, Y', strtotime($dealer['created_at'])) ?></td>
                                        <td class="dealer-actions">
                                            <div class="action-buttons">
                                                <?php if ($dealer['status'] !== 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="dealer_id" value="<?= $dealer['dealer_id'] ?>">
                                                        <button type="submit" name="activate_dealer" class="btn-activate" title="Activate">
                                                            ✅
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($dealer['status'] !== 'suspended'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="dealer_id" value="<?= $dealer['dealer_id'] ?>">
                                                        <button type="submit" name="suspend_dealer" class="btn-suspend" title="Suspend">
                                                            ⏸️
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button onclick="viewDealerDetails(<?= $dealer['dealer_id'] ?>)" 
                                                        class="btn-view" title="View Details">
                                                    👁️
                                                </button>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this dealer and all their listings?')">
                                                    <input type="hidden" name="dealer_id" value="<?= $dealer['dealer_id'] ?>">
                                                    <button type="submit" name="delete_dealer" class="btn-delete" title="Delete">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Dealer Details Modal -->
    <div id="dealerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Dealer Details</h2>
                <span class="close" onclick="closeDealerModal()">&times;</span>
            </div>
            <div class="modal-body" id="dealerContent">
                <!-- Dealer details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.dealer-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        document.getElementById('selectAllTable').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.dealer-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk action confirmation
        function confirmBulkAction() {
            const selected = document.querySelectorAll('.dealer-checkbox:checked');
            const action = document.querySelector('[name="bulk_action"]').value;
            
            if (selected.length === 0) {
                alert('Please select at least one dealer.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected.length} dealer(s) and all their listings? This action cannot be undone.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${selected.length} dealer(s)?`);
        }

        // View dealer details
        function viewDealerDetails(dealerId) {
            document.getElementById('dealerModal').style.display = 'block';
            document.getElementById('dealerContent').innerHTML = '<div class="loading">Loading dealer details...</div>';
            
            // Simulate loading dealer details (replace with actual AJAX call)
            setTimeout(() => {
                document.getElementById('dealerContent').innerHTML = `
                    <div class="dealer-details">
                        <div class="detail-section">
                            <h4>Contact Information</h4>
                            <p><strong>Email:</strong> dealer@example.com</p>
                            <p><strong>Phone:</strong> +1 234 567 8900</p>
                            <p><strong>Address:</strong> 123 Main St, City, State</p>
                        </div>
                        <div class="detail-section">
                            <h4>Business Statistics</h4>
                            <p><strong>Total Listings:</strong> ${Math.floor(Math.random() * 50)}</p>
                            <p><strong>Approved Listings:</strong> ${Math.floor(Math.random() * 40)}</p>
                            <p><strong>Total Orders:</strong> ${Math.floor(Math.random() * 200)}</p>
                            <p><strong>Revenue:</strong> $${(Math.random() * 5000).toFixed(2)}</p>
                        </div>
                        <div class="detail-section">
                            <h4>Recent Activity</h4>
                            <p>Last login: 2 hours ago</p>
                            <p>Last listing: 1 day ago</p>
                            <p>Account created: 3 months ago</p>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        function closeDealerModal() {
            document.getElementById('dealerModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('dealerModal');
            if (event.target == modal) {
                closeDealerModal();
            }
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







