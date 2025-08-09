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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO food_categories (name, description, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$name, $description]);
                $success = "Category added successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Category name already exists!";
                } else {
                    $error = "Error adding category: " . $e->getMessage();
                }
            }
        } else {
            $error = "Category name is required!";
        }
    }
    
    if (isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE food_categories SET name = ?, description = ? WHERE category_id = ?");
                $stmt->execute([$name, $description, $category_id]);
                $success = "Category updated successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Category name already exists!";
                } else {
                    $error = "Error updating category: " . $e->getMessage();
                }
            }
        } else {
            $error = "Category name is required!";
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'];
        
        try {
            // Check if category has listings
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM food_listings WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $listingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($listingCount > 0) {
                $error = "Cannot delete category with existing listings. Please reassign or delete listings first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM food_categories WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $success = "Category deleted successfully!";
            }
        } catch (Exception $e) {
            $error = "Error deleting category: " . $e->getMessage();
        }
    }
}

// Get all categories with listing counts
try {
    $stmt = $pdo->prepare("SELECT 
                           c.category_id,
                           c.name,
                           c.description,
                           c.created_at,
                           COUNT(l.listing_id) as listing_count,
                           COUNT(CASE WHEN l.is_approved = 1 THEN 1 END) as approved_count
                           FROM food_categories c
                           LEFT JOIN food_listings l ON c.category_id = l.category_id
                           GROUP BY c.category_id
                           ORDER BY c.name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    $error = "Error fetching categories: " . $e->getMessage();
}

// Get statistics
$totalCategories = count($categories);
$totalListings = array_sum(array_column($categories, 'listing_count'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <link rel="stylesheet" href="styles/admin-panel.css">
    <style>
        /* Override and add category-specific styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #2d3748;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Header Styles */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            color: #666;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            background: #f8f9fa;
            color: #e74c3c;
        }

        .nav-links a.active {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-name {
            color: #666;
            font-weight: 500;
        }

        .logout-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Remove sidebar styles and adjust main content */
        .main-content {
            margin-left: 0;
            padding: 2rem;
            min-height: calc(100vh - 80px);
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-content h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .header-content p {
            color: #666;
            font-size: 1.1rem;
            margin: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card.orange {
            border-left: 4px solid #f39c12;
        }

        .stat-card.blue {
            border-left: 4px solid #3498db;
        }

        .stat-card.purple {
            border-left: 4px solid #9b59b6;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 1.5rem;
        }

        .stat-trend {
            background: #e8f5e8;
            color: #27ae60;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stat-body h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-details {
            color: #999;
            font-size: 0.8rem;
        }

        .content-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            margin-bottom: 2rem;
        }

        .section-title h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .section-title p {
            color: #666;
            margin: 0;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .category-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: #e74c3c;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .category-icon {
            font-size: 2rem;
            color: #e74c3c;
        }

        .category-stats {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .listing-count, .approved-count {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
        }

        .listing-count {
            background: #e74c3c;
            color: white;
        }

        .approved-count {
            background: #27ae60;
            color: white;
        }

        .category-name {
            color: #2c3e50;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
        }

        .category-description {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .category-meta {
            margin-bottom: 1.5rem;
        }

        .created-date {
            font-size: 0.85rem;
            color: #999;
        }

        .category-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-edit, .btn-delete {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .category-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🛡️ Admin Panel</div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="admin-panel.php">Dashboard</a>
                <a href="admin-listings.php">Listings</a>
                <a href="admin-dealers.php">Dealers</a>
                <a href="admin-categories.php" class="active">Categories</a>
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
                    <h1>Manage Categories</h1>
                    <p>Add, edit, and organize food categories</p>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" onclick="openAddModal()">
                        ➕ Add New Category
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card orange">
                    <div class="stat-header">
                        <div class="stat-icon">🏷️</div>
                        <div class="stat-trend">+<?= rand(1, 5) ?></div>
                    </div>
                    <div class="stat-body">
                        <h3>Total Categories</h3>
                        <div class="stat-number"><?= $totalCategories ?></div>
                        <div class="stat-details">
                            <span class="detail-item">Food categories available</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-icon">📊</div>
                        <div class="stat-trend">+<?= rand(5, 15) ?>%</div>
                    </div>
                    <div class="stat-body">
                        <h3>Total Listings</h3>
                        <div class="stat-number"><?= $totalListings ?></div>
                        <div class="stat-details">
                            <span class="detail-item">Across all categories</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-icon">📈</div>
                        <div class="stat-trend">+<?= rand(8, 20) ?>%</div>
                    </div>
                    <div class="stat-body">
                        <h3>Average per Category</h3>
                        <div class="stat-number"><?= $totalCategories > 0 ? round($totalListings / $totalCategories, 1) : 0 ?></div>
                        <div class="stat-details">
                            <span class="detail-item">Listings per category</span>
                        </div>
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

            <!-- Categories Section -->
            <div class="content-section">
                <div class="section-header">
                    <div class="section-title">
                        <h2>All Categories</h2>
                        <p>Manage your food categories</p>
                    </div>
                </div>

                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🏷️</div>
                        <h3>No categories found</h3>
                        <p>Start by adding your first food category</p>
                        <button class="btn-primary" onclick="openAddModal()" style="margin-top: 1rem;">
                            ➕ Add First Category
                        </button>
                    </div>
                <?php else: ?>
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card">
                                <div class="category-header">
                                    <div class="category-icon">🏷️</div>
                                    <div class="category-stats">
                                        <span class="listing-count"><?= $category['listing_count'] ?> listings</span>
                                        <span class="approved-count"><?= $category['approved_count'] ?> approved</span>
                                    </div>
                                </div>
                                
                                <div class="category-body">
                                    <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                                    <p class="category-description">
                                        <?= htmlspecialchars($category['description'] ?: 'No description provided') ?>
                                    </p>
                                    <div class="category-meta">
                                        <span class="created-date">
                                            Created: <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="category-actions">
                                    <button class="btn-edit" onclick="openEditModal(<?= $category['category_id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($category['description'], ENT_QUOTES) ?>')">
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?= htmlspecialchars($category['name']) ?>', <?= $category['listing_count'] ?>)">
                                        <input type="hidden" name="category_id" value="<?= $category['category_id'] ?>">
                                        <button type="submit" name="delete_category" class="btn-delete">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Category</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" class="modal-form">
                <div class="form-group">
                    <label for="add_name">Category Name *</label>
                    <input type="text" id="add_name" name="name" required maxlength="100" 
                           placeholder="e.g., Italian Cuisine, Desserts, Beverages">
                </div>
                
                <div class="form-group">
                    <label for="add_description">Description</label>
                    <textarea id="add_description" name="description" rows="3" maxlength="500"
                              placeholder="Brief description of this category..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_category" class="btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" id="edit_category_id" name="category_id">
                
                <div class="form-group">
                    <label for="edit_name">Category Name *</label>
                    <input type="text" id="edit_name" name="name" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3" maxlength="500"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_category" class="btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('add_name').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.querySelector('#addModal form').reset();
        }

        function openEditModal(id, name, description) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').style.display = 'block';
            document.getElementById('edit_name').focus();
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.querySelector('#editModal form').reset();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Confirm delete
        function confirmDelete(categoryName, listingCount) {
            if (listingCount > 0) {
                return confirm(`Are you sure you want to delete "${categoryName}"? This category has ${listingCount} listing(s). You should reassign or delete those listings first.`);
            }
            return confirm(`Are you sure you want to delete "${categoryName}"? This action cannot be undone.`);
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

    <style>
        /* Categories Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .category-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .category-icon {
            font-size: 1.5rem;
            opacity: 0.8;
            color: #e74c3c;
        }

        .category-stats {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }

        .listing-count, .approved-count {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .approved-count {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .category-name {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
        }

        .category-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .category-meta {
            margin-bottom: 1rem;
        }

        .created-date {
            font-size: 0.8rem;
            color: #999;
        }

        .category-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-edit, .btn-delete {
            flex: 1;
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .category-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>






