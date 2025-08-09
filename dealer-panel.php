<?php
// Start session first
session_start();

// Debug session info
echo "<!-- Debug Info: ";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . " | ";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET');
echo " -->";

// Check if user is logged in as business owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    header("Location: login.php");
    exit;
}

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

// Add this after the dealer_id is found, around line 30
try {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.username 
                          FROM dealers d 
                          JOIN users u ON d.user_id = u.user_id 
                          WHERE d.dealer_id = ?");
    $stmt->execute([$dealer_id]);
    $dealer_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dealer_info) {
        $first_name = $dealer_info['first_name'];
        $last_name = $dealer_info['last_name'];
        $username = $dealer_info['username'];
    } else {
        $first_name = 'Dealer';
        $last_name = '';
        $username = 'Dealer';
    }
} catch (Exception $e) {
    $first_name = 'Dealer';
    $last_name = '';
    $username = 'Dealer';
}

// Fetch dealer's bookings count for dashboard stats
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_bookings, 
                          COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
                          COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings
                          FROM table_bookings WHERE dealer_id = ?");
    $stmt->execute([$dealer_id]);
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $booking_stats = ['total_bookings' => 0, 'pending_bookings' => 0, 'confirmed_bookings' => 0];
}

// Fetch dealer's dishes - only their own listings
try {
    $stmt = $pdo->prepare("SELECT l.listing_id, l.title, l.description, l.price, l.created_at, l.is_approved,
                           i.image_url, c.name as category_name, s.name as subcategory_name
                           FROM food_listings l
                           LEFT JOIN food_images i ON l.listing_id = i.listing_id AND i.is_primary = 1
                           LEFT JOIN food_categories c ON l.category_id = c.category_id
                           LEFT JOIN food_subcategories s ON l.subcategory_id = s.subcategory_id
                           WHERE l.dealer_id = ?
                           ORDER BY l.created_at DESC");
    $stmt->execute([$dealer_id]);
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dishes = [];
    $errors['database'] = "Error fetching dishes: " . $e->getMessage();
}

// Fetch categories for dropdown
try {
    $stmt = $pdo->prepare("SELECT category_id, name FROM food_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

// Fetch subcategories for dropdown
try {
    $stmt = $pdo->prepare("SELECT subcategory_id, category_id, name FROM food_subcategories ORDER BY name");
    $stmt->execute();
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $subcategories = [];
    error_log("Error fetching subcategories: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dishName'])) {
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
    } else {
        // Validate that the selected category exists in the database
        try {
            $stmt = $pdo->prepare("SELECT category_id FROM food_categories WHERE category_id = ?");
            $stmt->execute([$dishCategory]);
            if (!$stmt->fetch()) {
                $errors['dishCategory'] = "Invalid category selected";
            }
        } catch (Exception $e) {
            $errors['dishCategory'] = "Error validating category";
        }
    }
    
    // Validate subcategory if provided
    $dishSubcategory = trim(filter_input(INPUT_POST, 'dishSubcategory', FILTER_SANITIZE_STRING));
    if (!empty($dishSubcategory)) {
        try {
            $stmt = $pdo->prepare("SELECT subcategory_id FROM food_subcategories WHERE subcategory_id = ? AND category_id = ?");
            $stmt->execute([$dishSubcategory, $dishCategory]);
            if (!$stmt->fetch()) {
                $errors['dishSubcategory'] = "Invalid subcategory selected for this category";
            }
        } catch (Exception $e) {
            $errors['dishSubcategory'] = "Error validating subcategory";
        }
    }
    
    // Auto-create subcategory from dish name if none selected
    if (empty($dishSubcategory) && !empty($dishCategory) && empty($errors['dishCategory'])) {
        try {
            // Check if subcategory with dish name already exists for this category
            $stmt = $pdo->prepare("SELECT subcategory_id FROM food_subcategories WHERE name = ? AND category_id = ?");
            $stmt->execute([$dishName, $dishCategory]);
            $existing_subcategory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_subcategory) {
                $dishSubcategory = $existing_subcategory['subcategory_id'];
            } else {
                // Create new subcategory with dish name
                $stmt = $pdo->prepare("INSERT INTO food_subcategories (category_id, name, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$dishCategory, $dishName]);
                $dishSubcategory = $pdo->lastInsertId();
            }
        } catch (Exception $e) {
            error_log("Error creating subcategory: " . $e->getMessage());
            // Continue without subcategory if creation fails
            $dishSubcategory = null;
        }
    }
    
    $dailySpecial = isset($_POST['dailySpecial']) ? 1 : 0;
    $specialPrice = null;
    $specialEndDate = null;

    if ($dailySpecial) {
        $specialPrice = filter_input(INPUT_POST, 'specialPrice', FILTER_VALIDATE_FLOAT);
        $specialEndDate = trim(filter_input(INPUT_POST, 'specialEndDate', FILTER_SANITIZE_STRING));
        
        if ($specialPrice === false || $specialPrice <= 0) {
            $errors['specialPrice'] = "Special price must be a valid positive number";
        }
        
        if (empty($specialEndDate)) {
            $errors['specialEndDate'] = "Special end date is required";
        }
    }
    
    // Validate image
    $imageUploaded = false;
    if (isset($_FILES['dishImage']) && $_FILES['dishImage']['error'] === UPLOAD_ERR_OK) {
        $imageUploaded = true;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = mime_content_type($_FILES['dishImage']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors['dishImage'] = "Only JPG and PNG images are allowed";
        }
        
        if ($_FILES['dishImage']['size'] > 5 * 1024 * 1024) {
            $errors['dishImage'] = "Image size must be less than 5MB";
        }
        
        // Additional security check for image
        $imageInfo = getimagesize($_FILES['dishImage']['tmp_name']);
        if ($imageInfo === false) {
            $errors['dishImage'] = "Invalid image file";
        }
    } elseif (isset($_FILES['dishImage']) && $_FILES['dishImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['dishImage'] = "Error uploading image";
    }
    
    // Process form if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get category_id (it's already passed from the form)
            $category_id = $dishCategory;
            $subcategory_id = !empty($dishSubcategory) ? $dishSubcategory : null;
            
            // Insert food listing using prepared statement
            $stmt = $pdo->prepare("INSERT INTO food_listings 
                (dealer_id, category_id, subcategory_id, title, description, price, is_daily_special, special_price, special_end_date, is_approved, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
            
            $result = $stmt->execute([
                $dealer_id,
                $dishCategory,  // This is category_id from the form
                $dishSubcategory, // This is either selected subcategory_id or auto-created one
                $dishName,
                $dishDescription,
                $dishPrice,
                $dailySpecial,
                $specialPrice,
                $specialEndDate
            ]);
            
            if (!$result) {
                throw new Exception("Failed to insert food listing");
            }
            
            $listing_id = $pdo->lastInsertId();
            
            // Handle image upload with security measures
            if ($imageUploaded) {
                $uploadDir = 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate secure filename
                $fileExt = strtolower(pathinfo($_FILES['dishImage']['name'], PATHINFO_EXTENSION));
                $fileName = 'dish_' . $listing_id . '_' . uniqid() . '.' . $fileExt;
                $filePath = $uploadDir . $fileName;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['dishImage']['tmp_name'], $filePath)) {
                    $stmt = $pdo->prepare("INSERT INTO food_images 
                        (listing_id, image_url, is_primary) 
                        VALUES (?, ?, 1)");
                    $stmt->execute([$listing_id, $filePath]);
                } else {
                    throw new Exception("Failed to upload image");
                }
            }
            
            $pdo->commit();
            $success = "Dish uploaded successfully! It will be visible after approval.";
            
            // Clear form data after successful submission
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['database'] = "Error saving data: " . htmlspecialchars($e->getMessage());
        }
    }
}

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
    <title>Dealer Panel - The Lucksons Spoon</title>
    <link rel="stylesheet" href="styles/dealer-panel.css">
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
                <?= strtoupper(substr($first_name, 0, 1)) ?>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars($first_name . ' ' . $last_name) ?></h4>
                <p><?= htmlspecialchars($username) ?></p>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dealer-panel.php" class="nav-item active">
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
                <h1>Dashboard</h1>
            </div>
            <div class="header-right">
                <div class="header-stats">
                    <span class="quick-stat">
                        <span class="stat-label">Today's Orders</span>
                        <span class="stat-value"><?= rand(5, 25) ?></span>
                    </span>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="content-wrapper">
            <!-- Dashboard Stats -->
            <section class="dashboard-stats">
                <div class="page-welcome">
                    <h2>Welcome back, <?= htmlspecialchars($first_name) ?>! 👋</h2>
                    <p>Here's what's happening with your business today</p>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <h3>Total Dishes</h3>
                            <span class="stat-number"><?= count($dishes) ?></span>
                        </div>
                    </div>
                    <div class="stat-card clickable" onclick="window.location.href='manage-bookings.php'">
                        <div class="stat-icon">📅</div>
                        <div class="stat-info">
                            <h3>Table Bookings</h3>
                            <span class="stat-number"><?= $booking_stats['total_bookings'] ?></span>
                            <?php if ($booking_stats['pending_bookings'] > 0): ?>
                                <span class="stat-badge pending"><?= $booking_stats['pending_bookings'] ?> pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3>Monthly Revenue</h3>
                            <span class="stat-number">$<?= number_format(array_sum(array_column($dishes, 'price')) * 0.7, 2) ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-info">
                            <h3>Confirmed Bookings</h3>
                            <span class="stat-number"><?= $booking_stats['confirmed_bookings'] ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Upload Dish Form -->
            <section class="upload-section">
                <div class="section-header">
                    <h2>Upload New Dish</h2>
                    <p>Add a new dish to your menu</p>
                </div>

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

                <form class="upload-form" id="dishUploadForm" enctype="multipart/form-data" method="POST" action="">
                    <div class="form-grid">
                        <!-- Dish Image Upload -->
                        <div class="form-group image-upload-group">
                            <label class="form-label">Dish Image</label>
                            <div class="image-upload-area" id="imageUploadArea">
                                <div class="upload-placeholder">
                                    <div class="upload-icon">📷</div>
                                    <p>Click to upload or drag and drop</p>
                                    <span>PNG, JPG up to 5MB</span>
                                </div>
                                <input type="file" id="dishImage" name="dishImage" accept="image/*" required>
                                <div class="image-preview" id="imagePreview"></div>
                            </div>
                            <?php if (isset($errors['dishImage'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['dishImage']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Dish Details -->
                        <div class="form-details">
                            <div class="form-group">
                                <label class="form-label" for="dishName">Dish Name</label>
                                <input type="text" id="dishName" name="dishName" class="form-input" 
                                       value="<?= htmlspecialchars($_POST['dishName'] ?? '') ?>" 
                                       placeholder="Enter dish name" required>
                                <?php if (isset($errors['dishName'])): ?>
                                    <span class="error"><?= htmlspecialchars($errors['dishName']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="dishDescription">Description</label>
                                <textarea id="dishDescription" name="dishDescription" class="form-textarea" 
                                          placeholder="Describe your dish..." rows="4" required><?= 
                                          htmlspecialchars($_POST['dishDescription'] ?? '') ?></textarea>
                                <?php if (isset($errors['dishDescription'])): ?>
                                    <span class="error"><?= htmlspecialchars($errors['dishDescription']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="dishPrice">Price ($) *</label>
                                    <input type="number" id="dishPrice" name="dishPrice" class="form-input" 
                                           value="<?= htmlspecialchars($_POST['dishPrice'] ?? '') ?>" 
                                           placeholder="0.00" step="0.01" min="0" required>
                                    <?php if (isset($errors['dishPrice'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['dishPrice']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="dishCategory">Category *</label>
                                    <select id="dishCategory" name="dishCategory" class="form-select" required onchange="loadSubcategories()">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>" 
                                                    <?= (isset($_POST['dishCategory']) && $_POST['dishCategory'] == $category['category_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['dishCategory'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['dishCategory']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="dishSubcategory">Subcategory</label>
                                    <select id="dishSubcategory" name="dishSubcategory" class="form-select" disabled>
                                        <option value="">Select category first</option>
                                    </select>
                                    <?php if (isset($errors['dishSubcategory'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['dishSubcategory']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="dailySpecial" id="dailySpecial" onchange="toggleSpecialFields()">
                                    Mark as Daily Special
                                </label>
                            </div>

                            <div id="specialFields" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label" for="specialPrice">Special Price</label>
                                    <input type="number" id="specialPrice" name="specialPrice" class="form-input" 
                                           step="0.01" min="0" placeholder="Enter special price">
                                    <?php if (isset($errors['specialPrice'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['specialPrice']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="specialEndDate">Special Ends On</label>
                                    <input type="date" id="specialEndDate" name="specialEndDate" class="form-input">
                                    <?php if (isset($errors['specialEndDate'])): ?>
                                        <span class="error"><?= htmlspecialchars($errors['specialEndDate']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn-secondary" onclick="resetForm()">Reset</button>
                                <button type="submit" class="btn-primary">Upload Dish</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- My Dishes Section -->
        <section class="my-dishes-section">
            <div class="container">
                <div class="section-header">
                    <h2>My Dishes</h2>
                    <p>Manage your uploaded dishes</p>
                </div>

                <div class="dishes-grid" id="dishesGrid">
                    <?php foreach ($dishes as $dish): ?>
                        <div class="dish-card">
                            <div class="dish-status approved">
                                Live
                            </div>
                            <?php if ($dish['image_url']): ?>
                                <img src="<?= htmlspecialchars($dish['image_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                     alt="<?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                     class="dish-image">
                            <?php else: ?>
                                <div class="dish-image-placeholder">No Image</div>
                            <?php endif; ?>
                            <div class="dish-info">
                                <h3><?= htmlspecialchars($dish['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="dish-description"><?= htmlspecialchars($dish['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="dish-meta">
                                    <span class="dish-price">$<?= number_format($dish['price'], 2) ?></span>
                                    <span class="dish-date"><?= date('M d, Y', strtotime($dish['created_at'])) ?></span>
                                </div>
                                <div class="dish-actions">
                                    <a href="edit-listing.php?id=<?= $dish['listing_id'] ?>" class="btn-edit">
                                        Edit
                                    </a>
                                    <form method="POST" style="flex: 1; display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this dish?')">
                                        <input type="hidden" name="delete_dish" value="1">
                                        <input type="hidden" name="dish_id" value="<?= $dish['listing_id'] ?>">
                                        <button type="submit" class="btn-delete" style="width: 100%;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Image preview functionality for main upload form
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

        // Toggle special fields function
        function toggleSpecialFields() {
            const checkbox = document.getElementById('dailySpecial');
            const specialFields = document.getElementById('specialFields');
            const specialPrice = document.getElementById('specialPrice');
            const specialEndDate = document.getElementById('specialEndDate');
            
            if (checkbox.checked) {
                specialFields.style.display = 'block';
                specialPrice.required = true;
                specialEndDate.required = true;
            } else {
                specialFields.style.display = 'none';
                specialPrice.required = false;
                specialEndDate.required = false;
            }
        }

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        // Set active navigation item
        function setActiveNav() {
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                }
            });
        }

        // Subcategories data for JavaScript
        const subcategoriesData = <?= json_encode($subcategories) ?>;
        
        // Load subcategories based on selected category
        function loadSubcategories() {
            const categorySelect = document.getElementById('dishCategory');
            const subcategorySelect = document.getElementById('dishSubcategory');
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
                subcategorySelect.innerHTML = '<option value="">Select category first</option>';
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setActiveNav();
        });
    </script>
</body>
</html>



















