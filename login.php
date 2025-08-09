<?php
session_start();

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
$loginSuccess = false;

// Sanitize input function
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Check if form is being submitted
    error_log("Form submitted with data: " . print_r($_POST, true));
    
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $captcha = sanitizeInput($_POST['captcha']);
    $accountType = sanitizeInput($_POST['accountType']);
    $rememberMe = isset($_POST['rememberMe']) ? 1 : 0;

    // Debug: Check values
    error_log("Email: $email, Captcha: $captcha, Account Type: $accountType");

    // Validate inputs
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }

    // Simple captcha validation
    if (empty($captcha)) {
        $errors['captcha'] = 'Security verification is required';
    } elseif ($captcha !== '5') {
        $errors['captcha'] = 'Incorrect security verification answer';
    }

    if (empty($accountType)) {
        $errors['accountType'] = 'Please select account type';
    }

    // If no validation errors, check database
    if (empty($errors)) {
        try {
            if ($accountType === 'business') {
                // Check in users table for business accounts and get dealer info
                $stmt = $pdo->prepare("
                    SELECT u.user_id, u.username, u.email, u.password_hash, u.first_name, u.last_name, u.role,
                           d.dealer_id, d.status as dealer_status
                    FROM users u
                    LEFT JOIN dealers d ON u.user_id = d.user_id
                    WHERE u.email = ? AND u.role = 'business_owner'
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Check dealer status from dealers table
                    $stmt = $pdo->prepare("SELECT status FROM dealers WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);
                    $dealer_status = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($dealer_status && $dealer_status['status'] === 'active') {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_name'] = $user['last_name'];

                        // Set remember me cookie if checked
                        if ($rememberMe) {
                            $token = bin2hex(random_bytes(32));
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                            
                            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
                            $stmt->execute([$token, $user['user_id']]);
                        }

                        // Redirect business owners to dealer panel
                        header("Location: dealer-panel.php");
                        exit();
                    } elseif ($dealer_status && $dealer_status['status'] === 'pending') {
                        $errors['login'] = 'Your dealer account is pending approval. Please wait for admin approval.';
                    } elseif ($dealer_status && $dealer_status['status'] === 'suspended') {
                        $errors['login'] = 'Your dealer account has been suspended. Please contact admin.';
                    } else {
                        $errors['login'] = 'Your dealer account is not active. Please contact admin.';
                    }
                } else {
                    $errors['login'] = 'Invalid business credentials';
                }
            } elseif ($accountType === 'customer') {
                $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, first_name, last_name, role FROM users WHERE email = ? AND role = 'customer'");
                $stmt->execute([$email]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check for redirect parameter
                $redirect = $_GET['redirect'] ?? '';

                if ($customer && password_verify($password, $customer['password_hash'])) {
                    $_SESSION['user_id'] = $customer['user_id'];
                    $_SESSION['username'] = $customer['username'];
                    $_SESSION['email'] = $customer['email'];
                    $_SESSION['first_name'] = $customer['first_name'];
                    $_SESSION['last_name'] = $customer['last_name'];
                    $_SESSION['role'] = $customer['role'];
                    $_SESSION['account_type'] = 'customer';

                    // Set remember me cookie if checked
                    if ($rememberMe) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                        
                        $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
                        $stmt->execute([$token, $customer['user_id']]);
                    }

                    // Redirect to specified page or default to customer panel
                    if (!empty($redirect)) {
                        header("Location: " . urldecode($redirect));
                    } else {
                        header("Location: customer-panel.php");
                    }
                    exit();
                } else {
                    $errors['login'] = 'Invalid customer credentials';
                }
            } elseif ($accountType === 'admin') {
                $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, first_name, last_name, role FROM users WHERE email = ? AND role = 'admin'");
                $stmt->execute([$email]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    $_SESSION['user_id'] = $admin['user_id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['email'] = $admin['email'];
                    $_SESSION['first_name'] = $admin['first_name'];
                    $_SESSION['last_name'] = $admin['last_name'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['account_type'] = 'admin';

                    header("Location: admin-panel.php");
                    exit();
                } else {
                    $errors['login'] = 'Invalid admin credentials';
                }
            }
        } catch (PDOException $e) {
            $errors['database'] = 'Login failed: ' . $e->getMessage();
        }
    }
}

// Process forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $resetEmail = sanitizeInput($_POST['reset_email']);
    $resetCaptcha = sanitizeInput($_POST['reset_captcha']);
    
    // Validate inputs
    if (empty($resetEmail)) {
        $errors['reset_email'] = 'Email is required';
    } elseif (!filter_var($resetEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['reset_email'] = 'Invalid email format';
    }
    
    // Simple captcha validation
    if (empty($resetCaptcha)) {
        $errors['reset_captcha'] = 'Security verification is required';
    } elseif ($resetCaptcha !== '8') {
        $errors['reset_captcha'] = 'Incorrect security verification answer';
    }
    
    // If no validation errors, check if email exists and send reset link
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, email, first_name FROM users WHERE email = ?");
            $stmt->execute([$resetEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
                $stmt->execute([$resetToken, $resetExpiry, $user['user_id']]);
                
                // Send email
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $resetToken;
                $subject = "Password Reset - FoodHub";
                $message = "
                <html>
                <head>
                    <title>Password Reset</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>You requested a password reset for your FoodHub account.</p>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='" . $resetLink . "' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this reset, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>FoodHub Team</p>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: noreply@foodhub.com" . "\r\n";
                
                if (mail($resetEmail, $subject, $message, $headers)) {
                    $success = "Password reset link has been sent to your email address.";
                } else {
                    $errors['email_send'] = "Failed to send email. Please try again later.";
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = "If an account with that email exists, a password reset link has been sent.";
            }
        } catch (PDOException $e) {
            $errors['database'] = 'Error processing request: ' . $e->getMessage();
        }
    }
}

// Check for remember me cookie on page load
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role,
                   d.dealer_id, d.status as dealer_status
            FROM users u
            LEFT JOIN dealers d ON u.user_id = d.user_id
            WHERE u.remember_token = ?
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['dealer_id']) {
                $_SESSION['dealer_id'] = $user['dealer_id'];
            }

            // Redirect based on role
            if ($user['role'] === 'admin') {
                $_SESSION['account_type'] = 'admin';
                header("Location: admin-panel.php");
            } elseif ($user['role'] === 'business_owner' && $user['dealer_status'] === 'active') {
                $_SESSION['account_type'] = 'business';
                header("Location: dealer-panel.php");
            } else {
                // Customers or inactive dealers go to customer panel
                $_SESSION['account_type'] = 'customer';
                header("Location: customer-panel.php");
            }
            exit();
        }
    } catch (PDOException $e) {
        // Clear invalid cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Login - FoodHub</title>
    <link rel="stylesheet" href="styles/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-wrapper login-wrapper">
            <!-- Left Side - Branding -->
            <div class="auth-branding">
                <div class="branding-content">
                    <div class="logo">
                        <h1>🍽️ FoodHub</h1>
                        <p>Business Partner Portal</p>
                    </div>
                    <div class="welcome-message">
                        <h2>Welcome Back!</h2>
                        <p>Sign in to access your business dashboard and manage your food listings.</p>
                    </div>
                    <div class="stats">
                        <div class="stat-item">
                            <span class="stat-number">10K+</span>
                            <span class="stat-label">Active Customers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">500+</span>
                            <span class="stat-label">Partner Restaurants</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">50K+</span>
                            <span class="stat-label">Orders Delivered</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="auth-form-container">
                <div class="auth-form">
                    <div class="form-header">
                        <h2>Sign In to Your Account</h2>
                        <p>Access your business dashboard</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="error-messages">
                            <?php foreach ($errors as $error): ?>
                                <p class="error"><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form id="loginForm" class="login-form" method="POST" action="">
                        <div class="form-group">
                            <label for="loginEmail">Email Address</label>
                            <input type="email" id="loginEmail" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                            <span class="error-message" id="emailError"></span>
                        </div>

                        <div class="form-group">
                            <label for="loginPassword">Password</label>
                            <div class="password-input">
                                <input type="password" id="loginPassword" name="password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('loginPassword')">
                                    <span class="eye-icon">👁️</span>
                                </button>
                            </div>
                            <span class="error-message" id="passwordError"></span>
                        </div>

                        <!-- Captcha for Login -->
                        <div class="form-group">
                            <div class="captcha-container">
                                <label for="loginCaptcha">Security Verification</label>
                                <div class="captcha-box">
                                    <div class="captcha-challenge" id="loginCaptchaChallenge">
                                        <span id="loginCaptchaText">7 - 2 = ?</span>
                                        <button type="button" class="captcha-refresh" onclick="refreshLoginCaptcha()">🔄</button>
                                    </div>
                                    <input type="text" id="loginCaptcha" name="captcha" placeholder="Enter answer" required>
                                </div>
                                <span class="error-message" id="captchaError"></span>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="checkbox-label">
                                <input type="checkbox" id="rememberMe" name="rememberMe" <?php echo (isset($rememberMe) && $rememberMe) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Remember me
                            </label>
                            <a href="#" onclick="showForgotPassword()" class="forgot-password-link">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn-primary" id="loginBtn">
                            <span class="btn-text">Sign In</span>
                            <span class="btn-loading" style="display: none;">Signing In...</span>
                        </button>

                        <!-- Account Type Selection -->
                        <div class="account-type-section">
                            <p class="account-type-label">Account Type:</p>
                            <div class="account-type-options">
                                <label class="radio-label">
                                    <input type="radio" name="accountType" value="customer" <?php echo (!isset($accountType) || $accountType === 'customer') ? 'checked' : ''; ?>>
                                    <span class="radio-mark"></span>
                                    Customer
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="accountType" value="business" <?php echo (isset($accountType) && $accountType === 'business') ? 'checked' : ''; ?>>
                                    <span class="radio-mark"></span>
                                    Business Owner
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="accountType" value="admin" <?php echo (isset($accountType) && $accountType === 'admin') ? 'checked' : ''; ?>>
                                    <span class="radio-mark"></span>
                                    Administrator
                                </label>
                            </div>
                        </div>
                    </form>

                    <div class="form-footer">
                        <p>Don't have an account? <a href="register.php">Create Business Account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eyeIcon = field.nextElementSibling.querySelector('.eye-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                field.type = 'password';
                eyeIcon.textContent = '👁️';
            }
        }

        // Refresh login captcha
        function refreshLoginCaptcha() {
            const captchaText = document.getElementById('loginCaptchaText');
            const captchaInput = document.getElementById('loginCaptcha');
            
            // Generate new simple math problem
            const num1 = Math.floor(Math.random() * 10) + 1;
            const num2 = Math.floor(Math.random() * num1);
            captchaText.textContent = `${num1} - ${num2} = ?`;
            captchaInput.value = '';
            captchaInput.setAttribute('data-answer', num1 - num2);
        }

        // Show forgot password modal
        function showForgotPassword() {
            document.getElementById('forgotPasswordModal').classList.add('show');
        }

        // Close forgot password modal
        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').classList.remove('show');
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    closeForgotPassword();
                }
            });
        });
    </script>
</body>
</html>





