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

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
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

// Get listing ID from URL
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($listing_id <= 0) {
    header('Location: dealer-panel.php');
    exit;
}

// Fetch current listing data
try {
    $stmt = $pdo->prepare("SELECT l.*, c.name as category_name, i.image_url
                           FROM food_listings l
                           LEFT JOIN food_categories c ON l.category_id = c.category_id
                           LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                           WHERE l.listing_id = ? AND l.dealer_id = ?");
    $stmt->execute([$listing_id, $dealer_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        header('Location: dealer-panel.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: dealer-panel.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_listing'])) {
    $errors = [];
    
    // Sanitize and validate input data
    $dishName = trim(filter_input(INPUT_POST, 'dishName', FILTER_SANITIZE_STRING));
    $dishDescription = trim(filter_input(INPUT_POST, 'dishDescription', FILTER_SANITIZE_STRING));
    $dishPrice = filter_input(INPUT_POST, 'dishPrice', FILTER_VALIDATE_FLOAT);
    $dishCategory = trim(filter_input(INPUT_POST, 'dishCategory', FILTER_SANITIZE_STRING));
    
    // Validate required fields
    if (empty($dishName)) {
        $errors['dishName'] = "Dish name is required";
    } elseif (strlen($dishName) > 255) {
        $errors['dishName'] = "Dish name must be less than 255 characters";
    }
    
    if (empty($dishDescription)) {
        $errors['dishDescription'] = "Description is required";
    } elseif (strlen($dishDescription) > 1000) {
        $errors['dishDescription'] = "Description must be less than 1000 characters";
    }
    
    if ($dishPrice === false || $dishPrice <= 0) {
        $errors['dishPrice'] = "Price must be a valid positive number";
    } elseif ($dishPrice > 9999.99) {
        $errors['dishPrice'] = "Price cannot exceed $9999.99";
    }
    
    if (empty($dishCategory)) {
        $errors['dishCategory'] = "Category is required";
    }
    
    // Validate new image if uploaded
    $newImageUploaded = false;
    if (isset($_FILES['dishImage']) && $_FILES['dishImage']['error'] === UPLOAD_ERR_OK) {
        $newImageUploaded = true;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = mime_content_type($_FILES['dishImage']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors['dishImage'] = "Only JPG and PNG images are allowed";
        }
        
        if ($_FILES['dishImage']['size'] > 5 * 1024 * 1024) {
            $errors['dishImage'] = "Image size must be less than 5MB";
        }
        
        $imageInfo = getimagesize($_FILES['dishImage']['tmp_name']);
        if ($imageInfo === false) {
            $errors['dishImage'] = "Invalid image file";
        }
    }
    
    // Process form if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get or create category
            $stmt = $pdo->prepare("SELECT category_id FROM food_categories WHERE name = ? LIMIT 1");
            $stmt->execute([$dishCategory]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                $stmt = $pdo->prepare("INSERT INTO food_categories (name) VALUES (?)");
                $stmt->execute([$dishCategory]);
                $category_id = $pdo->lastInsertId();
            } else {
                $category_id = $category['category_id'];
            }
            
            // Update food listing
            $stmt = $pdo->prepare("UPDATE food_listings 
                SET category_id = ?, title = ?, description = ?, price = ?, is_approved = 0 
                WHERE listing_id = ? AND dealer_id = ?");
            
            $result = $stmt->execute([
                $category_id,
                $dishName,
                $dishDescription,
                $dishPrice,
                $listing_id,
                $dealer_id
            ]);
            
            if (!$result) {
                throw new Exception("Failed to update food listing");
            }
            
            // Handle new image upload
            if ($newImageUploaded) {
                // Delete old image first
                $stmt = $pdo->prepare("SELECT image_url FROM food_images WHERE listing_id = ?");
                $stmt->execute([$listing_id]);
                $oldImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($oldImages as $oldImage) {
                    if (file_exists($oldImage['image_url'])) {
                        unlink($oldImage['image_url']);
                    }
                }
                
                // Delete old image records
                $stmt = $pdo->prepare("DELETE FROM food_images WHERE listing_id = ?");
                $stmt->execute([$listing_id]);
                
                // Upload new image
                $uploadDir = 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExt = strtolower(pathinfo($_FILES['dishImage']['name'], PATHINFO_EXTENSION));
                $fileName = 'dish_' . $listing_id . '_' . uniqid() . '.' . $fileExt;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['dishImage']['tmp_name'], $filePath)) {
                    $stmt = $pdo->prepare("INSERT INTO food_images 
                        (listing_id, image_url, is_primary) 
                        VALUES (?, ?, 1)");
                    $stmt->execute([$listing_id, $filePath]);
                    
                    // Update listing data for display
                    $listing['image_url'] = $filePath;
                } else {
                    throw new Exception("Failed to upload new image");
                }
            }
            
            $pdo->commit();
            $success = "Dish updated successfully! Changes will be visible after approval.";
            
            // Refresh listing data
            $stmt = $pdo->prepare("SELECT l.*, c.name as category_name, i.image_url
                                   FROM food_listings l
                                   LEFT JOIN food_categories c ON l.category_id = c.category_id
                                   LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                                   WHERE l.listing_id = ? AND l.dealer_id = ?");
            $stmt->execute([$listing_id, $dealer_id]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['database'] = "Error updating data: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Listing - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/dealer-panel.css">
    <style>
        .edit-page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .breadcrumb {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .current-listing-preview {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        
        .preview-image {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .preview-placeholder {
            width: 200px;
            height: 150px;
            background: #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        
        .edit-form-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
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
                <h4><?= htmlspecialchars(($_SESSION['first_name'] ?? 'Dealer') . ' ' . ($_SESSION['last_name'] ?? '')) ?></h4>
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
                <h1>Edit Dish Listing</h1>
            </div>
            <div class="header-right">
                <a href="dealer-panel.php" class="btn-cancel">Back to Dashboard</a>
            </div>
        </header>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <div class="edit-page-container">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="breadcrumb">
                        <a href="dealer-panel.php">Dashboard</a> > 
                        <a href="dealer-panel.php#my-dishes">My Dishes</a> > 
                        <span>Edit Listing</span>
                    </div>
                    <h1>Edit Dish Listing</h1>
                    <p>Update your dish details and image</p>
                </div>

                <!-- Current Listing Preview -->
                <div class="current-listing-preview">
                    <h3>Current Listing</h3>
                    <div class="preview-grid">
                        <?php if ($listing['image_url'] && file_exists($listing['image_url'])): ?>
                            <img src="<?= htmlspecialchars($listing['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($listing['title']) ?>" 
                                 class="preview-image">
                        <?php else: ?>
                            <div class="preview-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="preview-details">
                            <h4><?= htmlspecialchars($listing['title']) ?></h4>
                            <p><?= htmlspecialchars($listing['description']) ?></p>
                            <div class="preview-meta">
                                <span class="preview-price">$<?= number_format($listing['price'], 2) ?></span>
                                <span class="preview-category"><?= htmlspecialchars($listing['category_name']) ?></span>
                            </div>
                        </div>
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

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <p><?= htmlspecialchars($success) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Edit Form -->
                <div class="upload-section">
                    <div class="section-header">
                        <h2>Update Dish Details</h2>
                        <p>Make changes to your dish listing</p>
                    </div>

                    <form class="upload-form" enctype="multipart/form-data" method="POST" action="">
                        <div class="form-grid">
                            <!-- Image Upload -->
                            <div class="form-group image-upload-group">
                                <label class="form-label">Update Dish Image (Optional)</label>
                                <div class="image-upload-area" id="imageUploadArea">
                                    <div class="upload-placeholder">
                                        <div class="upload-icon">📷</div>
                                        <p>Click to upload new image or drag and drop</p>
                                        <span>PNG, JPG up to 5MB</span>
                                    </div>
                                    <input type="file" id="dishImage" name="dishImage" accept="image/*">
                                    <div class="image-preview" id="imagePreview"></div>
                                </div>
                                <?php if (isset($errors['dishImage'])): ?>
                                    <span class="error"><?= htmlspecialchars($errors['dishImage']) ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Form Details -->
                            <div class="form-details">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Dish Name</label>
                                        <input type="text" id="dishName" name="dishName" class="form-input" 
                                               value="<?= htmlspecialchars($listing['title']) ?>" required>
                                        <?php if (isset($errors['dishName'])): ?>
                                            <span class="error"><?= htmlspecialchars($errors['dishName']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Price ($)</label>
                                        <input type="number" id="dishPrice" name="dishPrice" class="form-input" 
                                               step="0.01" min="0" value="<?= $listing['price'] ?>" required>
                                        <?php if (isset($errors['dishPrice'])): ?>
                                            <span class="error"><?= htmlspecialchars($errors['dishPrice']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea id="dishDescription" name="dishDescription" class="form-textarea" 
                                              rows="4" required><?= htmlspecialchars($listing['description']) ?></textarea>
                                    <?php if (isset($errors['dishDescription'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['dishDescription']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <select id="dishCategory" name="dishCategory" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <option value="Appetizers" <?= $listing['category_name'] === 'Appetizers' ? 'selected' : '' ?>>Appetizers</option>
                                        <option value="Main Course" <?= $listing['category_name'] === 'Main Course' ? 'selected' : '' ?>>Main Course</option>
                                        <option value="Desserts" <?= $listing['category_name'] === 'Desserts' ? 'selected' : '' ?>>Desserts</option>
                                        <option value="Beverages" <?= $listing['category_name'] === 'Beverages' ? 'selected' : '' ?>>Beverages</option>
                                        <option value="Breakfast" <?= $listing['category_name'] === 'Breakfast' ? 'selected' : '' ?>>Breakfast</option>
                                        <option value="Lunch" <?= $listing['category_name'] === 'Lunch' ? 'selected' : '' ?>>Lunch</option>
                                        <option value="Dinner" <?= $listing['category_name'] === 'Dinner' ? 'selected' : '' ?>>Dinner</option>
                                    </select>
                                    <?php if (isset($errors['dishCategory'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['dishCategory']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="dealer-panel.php" class="btn-cancel">Cancel</a>
                            <button type="submit" name="update_listing" class="btn-primary">Update Listing</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Image preview functionality
        document.getElementById('dishImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const uploadArea = document.getElementById('imageUploadArea');
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    uploadArea.querySelector('.upload-placeholder').style.display = 'none';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
                uploadArea.querySelector('.upload-placeholder').style.display = 'flex';
            }
        });

        // Drag and drop functionality
        const uploadArea = document.getElementById('imageUploadArea');
        const fileInput = document.getElementById('dishImage');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        });

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        // Handle logout
        document.querySelector('.logout-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });
    </script>
</body>
</html>


