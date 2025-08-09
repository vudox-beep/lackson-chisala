<?php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Sanitize inputs
        $business_name = trim($_POST['business_name']);
        $business_phone = trim($_POST['business_phone']);
        $business_email = trim($_POST['business_email']);
        $business_address = trim($_POST['business_address']);
        $mission_statement = trim($_POST['mission_statement']);
        $operating_hours = $_POST['operating_hours'];
        
        // Validate inputs
        if (empty($business_name)) $errors['business_name'] = "Business name is required";
        if (empty($business_phone)) $errors['business_phone'] = "Phone number is required";
        if (empty($business_email)) $errors['business_email'] = "Email is required";
        
        // Handle logo upload
        $logoUploaded = false;
        if (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] === UPLOAD_ERR_OK) {
            $logoUploaded = true;
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $fileType = mime_content_type($_FILES['business_logo']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors['business_logo'] = "Only JPG and PNG images are allowed";
            }
            
            if ($_FILES['business_logo']['size'] > 2 * 1024 * 1024) {
                $errors['business_logo'] = "Logo size must be less than 2MB";
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Handle logo upload
                $logo_path = null;
                if ($logoUploaded) {
                    $uploadDir = 'uploads/logos/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileExt = strtolower(pathinfo($_FILES['business_logo']['name'], PATHINFO_EXTENSION));
                    $fileName = 'logo_' . $dealer_id . '_' . uniqid() . '.' . $fileExt;
                    $logo_path = $uploadDir . $fileName;
                    
                    if (!move_uploaded_file($_FILES['business_logo']['tmp_name'], $logo_path)) {
                        throw new Exception("Failed to upload logo");
                    }
                }
                
                // Update dealer profile
                $sql = "UPDATE dealers SET 
                        business_name = ?, 
                        business_phone = ?, 
                        business_email = ?, 
                        business_address = ?, 
                        mission_statement = ?, 
                        operating_hours = ?";
                
                $params = [$business_name, $business_phone, $business_email, $business_address, $mission_statement, json_encode($operating_hours)];
                
                if ($logo_path) {
                    $sql .= ", business_logo = ?";
                    $params[] = $logo_path;
                }
                
                $sql .= " WHERE dealer_id = ?";
                $params[] = $dealer_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $pdo->commit();
                $success = "Profile updated successfully!";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors['database'] = "Error updating profile: " . $e->getMessage();
            }
        }
    }
}

// Fetch current profile data - simplified query without reviews for now
try {
    $stmt = $pdo->prepare("SELECT d.*, u.first_name, u.last_name
                          FROM dealers d 
                          JOIN users u ON d.user_id = u.user_id 
                          WHERE d.dealer_id = ?");
    $stmt->execute([$dealer_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        $errors['database'] = "Profile not found";
        $profile = []; // Initialize empty array to prevent undefined variable
    }
    
    $operating_hours = $profile['operating_hours'] ? json_decode($profile['operating_hours'], true) : [];
    
    // Get reviews separately if reviews table exists
    $avg_rating = 0;
    $total_reviews = 0;
    try {
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                              FROM reviews WHERE dealer_id = ?");
        $stmt->execute([$dealer_id]);
        $review_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($review_data) {
            $avg_rating = $review_data['avg_rating'] ?? 0;
            $total_reviews = $review_data['total_reviews'] ?? 0;
        }
    } catch (Exception $e) {
        // Reviews table doesn't exist yet, use defaults
        $avg_rating = 0;
        $total_reviews = 0;
    }
    
} catch (Exception $e) {
    $errors['database'] = "Error fetching profile: " . $e->getMessage();
    $profile = []; // Initialize empty array
    $operating_hours = [];
    $avg_rating = 0;
    $total_reviews = 0;
} catch (Exception $e) {
    $errors['database'] = "Error fetching profile: " . $e->getMessage();
    $profile = []; // Initialize empty array
    $operating_hours = [];
    $avg_rating = 0;
    $total_reviews = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Profile - Dealer Panel</title>
    <link rel="stylesheet" href="styles/dealer-panel.css">
    <style>
        .profile-container { max-width: 1200px; margin: 0 auto; }
        .profile-header { background: white; border-radius: 16px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }
        .profile-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .stat-item { background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center; }
        .logo-upload { border: 2px dashed #cbd5e0; border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; }
        .current-logo { max-width: 150px; max-height: 150px; border-radius: 8px; }
        .hours-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; }
        .day-hours { display: flex; align-items: center; gap: 1rem; padding: 0.5rem 0; }
        .day-hours label { min-width: 100px; font-weight: 500; }
        .upload-form { background: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }
        .form-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-input, .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-actions { text-align: center; padding-top: 1rem; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3); }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .upload-icon { font-size: 3rem; margin-bottom: 1rem; }
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .profile-stats { grid-template-columns: 1fr; }
            .hours-grid { grid-template-columns: 1fr; }
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
                <?= strtoupper(substr($profile['first_name'] ?? 'D', 0, 1)) ?>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars(($profile['first_name'] ?? 'Dealer') . ' ' . ($profile['last_name'] ?? '')) ?></h4>
                <p><?= htmlspecialchars($profile['business_name'] ?? 'Business Owner') ?></p>
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
            <a href="business-profile.php" class="nav-item active">
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
                <h1>Business Profile</h1>
            </div>
            <div class="header-right">
                <div class="header-stats">
                    <span class="quick-stat">
                        <span class="stat-label">Status</span>
                        <span class="stat-value"><?= ucfirst($profile['status'] ?? 'pending') ?></span>
                    </span>
                </div>
            </div>
        </header>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <h1>Business Profile Management</h1>
                    <p>Manage your business information, logo, and operating hours</p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <h3>Rating</h3>
                            <span class="stat-number"><?= number_format($avg_rating, 1) ?> ⭐</span>
                            <p><?= $total_reviews ?> reviews</p>
                        </div>
                        <div class="stat-item">
                            <h3>Status</h3>
                            <span class="stat-number"><?= ucfirst($profile['status'] ?? 'pending') ?></span>
                        </div>
                        <div class="stat-item">
                            <h3>Total Sales</h3>
                            <span class="stat-number">$<?= number_format($profile['total_sales'] ?? 0, 2) ?></span>
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

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <p><?= htmlspecialchars($success) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Profile Form -->
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-grid">
                        <!-- Logo Upload -->
                        <div class="form-group">
                            <label class="form-label">Business Logo</label>
                            <?php if ($profile['business_logo']): ?>
                                <div style="margin-bottom: 1rem;">
                                    <img src="<?= htmlspecialchars($profile['business_logo']) ?>" alt="Current Logo" class="current-logo">
                                    <p>Current Logo</p>
                                </div>
                            <?php endif; ?>
                            <div class="logo-upload" onclick="document.getElementById('business_logo').click()">
                                <div class="upload-icon">🏢</div>
                                <p>Click to upload logo</p>
                                <span>PNG, JPG up to 2MB</span>
                            </div>
                            <input type="file" id="business_logo" name="business_logo" accept="image/*" style="display: none;">
                        </div>

                        <!-- Business Details -->
                        <div class="form-details">
                            <div class="form-group">
                                <label class="form-label">Business Name</label>
                                <input type="text" name="business_name" class="form-input" 
                                       value="<?= htmlspecialchars($profile['business_name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="business_phone" class="form-input" 
                                       value="<?= htmlspecialchars($profile['business_phone'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="business_email" class="form-input" 
                                       value="<?= htmlspecialchars($profile['business_email'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Business Address</label>
                                <textarea name="business_address" class="form-textarea" rows="3"><?= htmlspecialchars($profile['business_address'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Mission Statement</label>
                                <textarea name="mission_statement" class="form-textarea" rows="4" 
                                          placeholder="Describe your business mission and values..."><?= htmlspecialchars($profile['mission_statement'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Operating Hours -->
                    <div class="form-group">
                        <label class="form-label">Operating Hours</label>
                        <div class="hours-grid">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day): 
                                $dayHours = $operating_hours[strtolower($day)] ?? ['open' => '09:00', 'close' => '17:00', 'closed' => false];
                            ?>
                                <div class="day-hours">
                                    <label><?= $day ?></label>
                                    <input type="checkbox" name="operating_hours[<?= strtolower($day) ?>][closed]" 
                                           <?= $dayHours['closed'] ? 'checked' : '' ?> onchange="toggleDayHours('<?= strtolower($day) ?>')">
                                    <span>Closed</span>
                                    <input type="time" name="operating_hours[<?= strtolower($day) ?>][open]" 
                                           value="<?= $dayHours['open'] ?>" id="<?= strtolower($day) ?>_open">
                                    <span>to</span>
                                    <input type="time" name="operating_hours[<?= strtolower($day) ?>][close]" 
                                           value="<?= $dayHours['close'] ?>" id="<?= strtolower($day) ?>_close">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        function toggleDayHours(day) {
            const checkbox = document.querySelector(`input[name="operating_hours[${day}][closed]"]`);
            const openTime = document.getElementById(`${day}_open`);
            const closeTime = document.getElementById(`${day}_close`);
            
            if (checkbox.checked) {
                openTime.disabled = true;
                closeTime.disabled = true;
            } else {
                openTime.disabled = false;
                closeTime.disabled = false;
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => toggleDayHours(day));
        });

        // Handle logout
        document.querySelector('.logout-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                // Clear session and redirect
                window.location.href = 'logout.php';
            }
        });

        // Show notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'} ${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Show notifications for PHP messages
        <?php if ($success): ?>
            showNotification('<?= addslashes($success) ?>', 'success');
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            showNotification('Please fix the form errors', 'error');
        <?php endif; ?>
    </script>
</body>
</html>


