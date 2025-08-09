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

// Get dealer_id (in real app, get from session)
try {
    $stmt = $pdo->prepare("SELECT dealer_id FROM dealers LIMIT 1");
    $stmt->execute();
    $dealer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dealer) {
        $dealer_id = $dealer['dealer_id'];
    } else {
        die("No dealers found in database. Please add a dealer first.");
    }
} catch (Exception $e) {
    die("Error finding dealer: " . $e->getMessage());
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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_dishes = $_POST['selected_dishes'] ?? [];
    
    if (!empty($selected_dishes)) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'delete') {
                foreach ($selected_dishes as $dish_id) {
                    // Delete images
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
                }
                $success = count($selected_dishes) . " dishes deleted successfully!";
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['database'] = "Error processing bulk action: " . $e->getMessage();
        }
    }
}

// Get filter