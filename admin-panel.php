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

// Get statistics for dashboard
try {
    // Total listings
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM food_listings");
    $stmt->execute();
    $totalListings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Approved listings
    $stmt = $pdo->prepare("SELECT COUNT(*) as approved FROM food_listings WHERE is_approved = 1");
    $stmt->execute();
    $approvedListings = $stmt->fetch(PDO::FETCH_ASSOC)['approved'];
    
    // Pending listings
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM food_listings WHERE is_approved = 0");
    $stmt->execute();
    $pendingListings = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Total dealers
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dealers");
    $stmt->execute();
    $totalDealers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active dealers
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM dealers WHERE status = 'active'");
    $stmt->execute();
    $activeDealers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    // Pending dealers
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM dealers WHERE status = 'pending'");
    $stmt->execute();
    $pendingDealers = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Total categories
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM food_categories");
    $stmt->execute();
    $totalCategories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Debug: Log the values to see what we're getting
    error_log("Dashboard Stats - Listings: $totalListings, Approved: $approvedListings, Pending: $pendingListings");
    error_log("Dashboard Stats - Dealers: $totalDealers, Active: $activeDealers, Pending: $pendingDealers");
    error_log("Dashboard Stats - Categories: $totalCategories");
    
    // Recent activity with better error handling
    $stmt = $pdo->prepare("SELECT 
                           l.title, 
                           l.created_at, 
                           CONCAT(u.first_name, ' ', u.last_name) as dealer_name,
                           d.business_name,
                           'listing' as type
                           FROM food_listings l
                           LEFT JOIN dealers d ON l.dealer_id = d.dealer_id
                           LEFT JOIN users u ON d.user_id = u.user_id
                           ORDER BY l.created_at DESC
                           LIMIT 5");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalListings = $approvedListings = $pendingListings = $totalDealers = $activeDealers = $pendingDealers = $totalCategories = 0;
    $recentActivity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/admin-panel.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🛡️ Admin Dashboard</div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="admin-panel.php" class="active">Dashboard</a>
                <a href="admin-listings.php">Listings</a>
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
                    <h1>Admin Dashboard</h1>
                    <p>Manage your food marketplace</p>
                </div>
                <div class="header-actions">
                    <div class="quick-stats">
                        <span class="quick-stat">
                            <span class="stat-label">Today's Orders</span>
                            <span class="stat-value"><?= rand(15, 45) ?></span>
                        </span>
                        <span class="quick-stat">
                            <span class="stat-label">Revenue</span>
                            <span class="stat-value">$<?= number_format(rand(500, 2000), 2) ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card listings">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <h3>Total Listings</h3>
                        <span class="stat-number"><?= $totalListings ?: '0' ?></span>
                        <div class="stat-breakdown">
                            <span class="breakdown-item approved">✅ <?= $approvedListings ?: '0' ?> Approved</span>
                            <span class="breakdown-item pending">⏳ <?= $pendingListings ?: '0' ?> Pending</span>
                        </div>
                    </div>
                    <a href="admin-listings.php" class="stat-link">View All →</a>
                </div>

                <div class="stat-card dealers">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3>Dealers</h3>
                        <span class="stat-number"><?= $totalDealers ?: '0' ?></span>
                        <div class="stat-breakdown">
                            <span class="breakdown-item approved">✅ <?= $activeDealers ?: '0' ?> Active</span>
                            <span class="breakdown-item pending">⏳ <?= $pendingDealers ?: '0' ?> Pending</span>
                        </div>
                    </div>
                    <a href="admin-dealers.php" class="stat-link">Manage →</a>
                </div>

                <div class="stat-card categories">
                    <div class="stat-icon">🏷️</div>
                    <div class="stat-info">
                        <h3>Categories</h3>
                        <span class="stat-number"><?= $totalCategories ?: '0' ?></span>
                        <div class="stat-breakdown">
                            <span class="breakdown-item">Food categories available</span>
                        </div>
                    </div>
                    <a href="admin-categories.php" class="stat-link">Manage →</a>
                </div>

                <div class="stat-card approvals">
                    <div class="stat-icon">✋</div>
                    <div class="stat-info">
                        <h3>Pending Approvals</h3>
                        <span class="stat-number"><?= ($pendingDealers + $pendingListings) ?: '0' ?></span>
                        <div class="stat-breakdown">
                            <span class="breakdown-item pending">Requires attention</span>
                        </div>
                    </div>
                    <a href="admin-approvals.php" class="stat-link">Review →</a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <h2 class="section-title">Quick Actions</h2>
                <div class="quick-actions-grid">
                    <a href="admin-listings.php?filter=pending" class="action-card urgent">
                        <div class="action-icon">⏳</div>
                        <div class="action-content">
                            <h3>Review Pending Listings</h3>
                            <p><?= $pendingListings ?> listings awaiting approval</p>
                        </div>
                    </a>

                    <a href="admin-approvals.php" class="action-card important">
                        <div class="action-icon">👤</div>
                        <div class="action-content">
                            <h3>Approve New Dealers</h3>
                            <p><?= $pendingDealers ?> dealers awaiting approval</p>
                        </div>
                    </a>

                    <a href="admin-categories.php" class="action-card normal">
                        <div class="action-icon">➕</div>
                        <div class="action-content">
                            <h3>Add New Category</h3>
                            <p>Expand food categories</p>
                        </div>
                    </a>

                    <a href="admin-dealers.php" class="action-card normal">
                        <div class="action-icon">🔧</div>
                        <div class="action-content">
                            <h3>Manage Dealers</h3>
                            <p>View and manage all dealers</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity-section">
                <h2 class="section-title">Recent Activity</h2>
                <div class="activity-container">
                    <?php if (empty($recentActivity)): ?>
                        <div class="empty-activity">
                            <div class="empty-icon">📝</div>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">📝</div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            New listing: "<?= htmlspecialchars($activity['title']) ?>"
                                        </div>
                                        <div class="activity-meta">
                                            By <?= htmlspecialchars($activity['business_name'] ?: $activity['dealer_name'] ?: 'Unknown Dealer') ?> • 
                                            <?= date('M d, Y H:i', strtotime($activity['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="activity-action">
                                        <a href="admin-listings.php" class="btn-view-activity">View</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="activity-footer">
                            <a href="admin-listings.php" class="btn-view-all">View All Activity →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'index.php';
            }
        }

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            // You can implement AJAX refresh here
            console.log('Stats refreshed');
        }, 30000);
    </script>
</body>
</html>








