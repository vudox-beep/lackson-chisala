<?php
// Sanitize input function
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Database configuration
$host = 'localhost';
$dbname = 'foodsale';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $firstName = sanitizeInput($_POST['firstName']);
    $lastName = sanitizeInput($_POST['lastName']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $dateOfBirth = sanitizeInput($_POST['dateOfBirth']);
    $businessName = sanitizeInput($_POST['businessName']);
    $businessType = sanitizeInput($_POST['businessType']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $captcha = sanitizeInput($_POST['captcha']);
    $role = sanitizeInput($_POST['role']); // Add role field
    $agreeTerms = isset($_POST['agreeTerms']) ? 1 : 0;
    $agreeMarketing = isset($_POST['agreeMarketing']) ? 1 : 0;

    // Validate inputs
    if (empty($firstName)) {
        $errors['firstName'] = 'First name is required';
    }
    
    if (empty($lastName)) {
        $errors['lastName'] = 'Last name is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors['email'] = 'Email already registered';
        }
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    }
    
    if (empty($dateOfBirth)) {
        $errors['dateOfBirth'] = 'Date of birth is required';
    }
    
    if (empty($businessName)) {
        $errors['businessName'] = 'Business name is required';
    }
    
    if (empty($businessType)) {
        $errors['businessType'] = 'Business type is required';
    }
    
    if (empty($role)) {
        $errors['role'] = 'Please select your role';
    } elseif (!in_array($role, ['business_owner', 'customer'])) {
        $errors['role'] = 'Invalid role selected';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
    }
    
    // Simple captcha validation (5 + 3 = 8)
    if ($captcha !== '8') {
        $errors['captcha'] = 'Incorrect security verification answer';
    }
    
    if (!$agreeTerms) {
        $errors['agreeTerms'] = 'You must agree to the terms and conditions';
    }

    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Generate username from email (before @)
            $username = strtolower(explode('@', $email)[0]);
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Current timestamp
            $currentTime = date('Y-m-d H:i:s');
            
            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, 
                    email, 
                    password_hash, 
                    first_name, 
                    last_name, 
                    phone, 
                    date_of_birth, 
                    business_name, 
                    business_type, 
                    role, 
                    is_approved, 
                    agreed_marketing, 
                    created_at, 
                    updated_at
                ) VALUES (
                    :username, 
                    :email, 
                    :password_hash, 
                    :first_name, 
                    :last_name, 
                    :phone, 
                    :date_of_birth, 
                    :business_name, 
                    :business_type, 
                    :role, 
                    0, 
                    :agreed_marketing, 
                    :created_at, 
                    :updated_at
                )
            ");
            
            // Bind parameters
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':first_name', $firstName);
            $stmt->bindParam(':last_name', $lastName);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':date_of_birth', $dateOfBirth);
            $stmt->bindParam(':business_name', $businessName);
            $stmt->bindParam(':business_type', $businessType);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':agreed_marketing', $agreeMarketing, PDO::PARAM_INT);
            $stmt->bindParam(':created_at', $currentTime);
            $stmt->bindParam(':updated_at', $currentTime);
            
            // Execute the statement
            $stmt->execute();
            
            $user_id = $pdo->lastInsertId();
            
            // If registering as business owner, create dealer record
            if ($role === 'business_owner') {
                $stmt = $pdo->prepare("
                    INSERT INTO dealers (
                        user_id, 
                        business_name, 
                        business_type, 
                        business_phone, 
                        business_email,
                        status,
                        created_at,
                        updated_at
                    ) VALUES (
                        :user_id,
                        :business_name,
                        :business_type,
                        :phone,
                        :email,
                        'pending',
                        :created_at,
                        :updated_at
                    )
                ");
                
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':business_name', $businessName);
                $stmt->bindParam(':business_type', $businessType);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':created_at', $currentTime);
                $stmt->bindParam(':updated_at', $currentTime);
                
                $stmt->execute();
            }
            
            $pdo->commit();
            $success = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registration - FoodHub</title>
    <link rel="stylesheet" href="styles/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-wrapper">
            <!-- Left Side - Branding -->
            <div class="auth-branding">
                <div class="branding-content">
                    <div class="logo">
                        <h1>🍽️ FoodHub</h1>
                        <p>Business Partner Portal</p>
                    </div>
                    <div class="features">
                        <div class="feature-item">
                            <span class="feature-icon">📈</span>
                            <div>
                                <h3>Grow Your Business</h3>
                                <p>Reach thousands of hungry customers</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">💰</span>
                            <div>
                                <h3>Increase Revenue</h3>
                                <p>Boost your sales with online ordering</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">📊</span>
                            <div>
                                <h3>Analytics Dashboard</h3>
                                <p>Track performance and customer insights</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Registration Form -->
            <div class="auth-form-container">
                <div class="auth-form">
                    <h2>Join FoodHub</h2>
                    <p>Register as a Business Owner or Customer</p>
                    <?php if ($success): ?>
                        <div class="success-message">
                            <div class="success-icon">✅</div>
                            <h2>Registration Successful!</h2>
                            <p>Your business account has been created. Please wait for approval from our team.</p>
                            <p>You will receive an email once your account is approved.</p>
                            <a href="login.php" class="btn-login">Go to Login</a>
                        </div>
                    <?php else: ?>
                        <h2>Business Registration</h2>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="error-messages">
                                <?php foreach ($errors as $error): ?>
                                    <p class="error"><?php echo $error; ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" onsubmit="addLoadingState()">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="firstName" placeholder="First Name" value="<?php echo isset($firstName) ? htmlspecialchars($firstName) : ''; ?>" required>
                                    <?php if (isset($errors['firstName'])): ?>
                                        <span class="error-message"><?php echo $errors['firstName']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <input type="text" name="lastName" placeholder="Last Name" value="<?php echo isset($lastName) ? htmlspecialchars($lastName) : ''; ?>" required>
                                    <?php if (isset($errors['lastName'])): ?>
                                        <span class="error-message"><?php echo $errors['lastName']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <span class="error-message"><?php echo $errors['email']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="tel" name="phone" placeholder="Phone Number" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <span class="error-message"><?php echo $errors['phone']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dateOfBirth">Date of Birth</label>
                                    <input type="date" name="dateOfBirth" id="dateOfBirth" value="<?php echo isset($dateOfBirth) ? $dateOfBirth : ''; ?>" required>
                                    <?php if (isset($errors['dateOfBirth'])): ?>
                                        <span class="error-message"><?php echo $errors['dateOfBirth']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <input type="text" name="businessName" placeholder="Business Name" value="<?php echo isset($businessName) ? htmlspecialchars($businessName) : ''; ?>" required>
                                <?php if (isset($errors['businessName'])): ?>
                                    <span class="error-message"><?php echo $errors['businessName']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <select name="businessType" required>
                                    <option value="">Select Business Type</option>
                                    <option value="restaurant" <?php echo (isset($businessType) && $businessType === 'restaurant') ? 'selected' : ''; ?>>Restaurant</option>
                                    <option value="cafe" <?php echo (isset($businessType) && $businessType === 'cafe') ? 'selected' : ''; ?>>Cafe</option>
                                    <option value="bakery" <?php echo (isset($businessType) && $businessType === 'bakery') ? 'selected' : ''; ?>>Bakery</option>
                                    <option value="food_truck" <?php echo (isset($businessType) && $businessType === 'food_truck') ? 'selected' : ''; ?>>Food Truck</option>
                                    <option value="catering" <?php echo (isset($businessType) && $businessType === 'catering') ? 'selected' : ''; ?>>Catering Service</option>
                                </select>
                                <?php if (isset($errors['businessType'])): ?>
                                    <span class="error-message"><?php echo $errors['businessType']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="role">I am registering as:</label>
                                <select name="role" id="role" required>
                                    <option value="">Select Role</option>
                                    <option value="business_owner" <?php echo (isset($role) && $role === 'business_owner') ? 'selected' : ''; ?>>Business Owner</option>
                                    <option value="customer" <?php echo (isset($role) && $role === 'customer') ? 'selected' : ''; ?>>Customer</option>
                                </select>
                                <small class="form-note">Note: Administrators do not register through this form</small>
                                <?php if (isset($errors['role'])): ?>
                                    <span class="error-message"><?php echo $errors['role']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="password" name="password" placeholder="Password (min 8 characters)" required>
                                    <?php if (isset($errors['password'])): ?>
                                        <span class="error-message"><?php echo $errors['password']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <input type="password" name="confirmPassword" placeholder="Confirm Password" required>
                                    <?php if (isset($errors['confirmPassword'])): ?>
                                        <span class="error-message"><?php echo $errors['confirmPassword']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="captcha">Security Question: What is 5 + 3?</label>
                                <input type="text" name="captcha" id="captcha" placeholder="Enter your answer" required>
                                <?php if (isset($errors['captcha'])): ?>
                                    <span class="error-message"><?php echo $errors['captcha']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="agreeTerms" required>
                                    <span class="checkmark"></span>
                                    I agree to the <a href="#" target="_blank">Terms and Conditions</a>
                                </label>
                                <?php if (isset($errors['agreeTerms'])): ?>
                                    <span class="error-message"><?php echo $errors['agreeTerms']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="agreeMarketing" <?php echo (isset($agreeMarketing) && $agreeMarketing) ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    I agree to receive marketing communications and updates
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-primary" id="registerBtn">
                                <span class="btn-text">Register Business</span>
                                <span class="btn-loading">Processing...</span>
                            </button>
                        </form>
                        
                        <div class="form-footer">
                            <p>Already have an account? <a href="login.php">Sign in here</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="scripts/auth.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        function refreshCaptcha() {
            // Simple captcha refresh - in a real app you might want something more sophisticated
            document.getElementById('captchaText').textContent = '5 + 3 = ?';
            document.getElementById('captcha').value = '';
        }

        // Add loading state to submit button
        function addLoadingState() {
            const submitBtn = document.getElementById('registerBtn');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        }

        // Remove loading state
        function removeLoadingState() {
            const submitBtn = document.getElementById('registerBtn');
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        }

        // Form validation and submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const captcha = document.querySelector('input[name="captcha"]').value;
                    const agreeTerms = document.querySelector('input[name="agreeTerms"]').checked;

                    if (captcha !== '8') {
                        e.preventDefault();
                        alert('Security question answer is incorrect. 5 + 3 = 8');
                        return;
                    }

                    if (!agreeTerms) {
                        e.preventDefault();
                        alert('You must agree to the terms and conditions');
                        return;
                    }

                    // Add loading state
                    addLoadingState();
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const businessFields = document.querySelectorAll('[name="businessName"], [name="businessType"]');
            const businessGroups = [];
            
            // Find the form groups containing business fields
            businessFields.forEach(field => {
                const formGroup = field.closest('.form-group');
                if (formGroup) {
                    businessGroups.push(formGroup);
                }
            });
            
            function toggleBusinessFields() {
                const selectedRole = roleSelect.value;
                const shouldShow = selectedRole === 'business_owner';
                
                businessGroups.forEach(group => {
                    group.style.display = shouldShow ? 'block' : 'none';
                });
                
                // Update required attributes
                businessFields.forEach(field => {
                    field.required = shouldShow;
                });
            }
            
            // Initial check
            toggleBusinessFields();
            
            // Listen for role changes
            roleSelect.addEventListener('change', toggleBusinessFields);
        });
    </script>
</body>
</html>

















