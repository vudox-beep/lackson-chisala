<?php
session_start();
require_once 'config.php';

// Handle dealer approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_dealer'])) {
        $dealer_id = $_POST['dealer_id'];
        try {
            $stmt = $pdo->prepare("UPDATE dealers SET status = 'active' WHERE dealer_id = ?");
            $stmt->execute([$dealer_id]);
            $success = "Dealer approved successfully!";
        } catch (Exception $e) {
            $error = "Error approving dealer: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject_dealer'])) {
        $dealer_id = $_POST['dealer_id'];
        try {
            $stmt = $pdo->prepare("UPDATE dealers SET status = 'suspended' WHERE dealer_id = ?");
            $stmt->execute([$dealer_id]);
            $success = "Dealer rejected successfully!";
        } catch (Exception $e) {
            $error = "Error rejecting dealer: " . $e->getMessage();
        }
    }
}

// Get pending dealers for approval
try {
    $stmt = $pdo->prepare("SELECT d.dealer_id, d.business_name, d.business_type, d.business_phone, 
                          d.business_email, d.business_address, d.created_at,
                          CONCAT(u.first_name, ' ', u.last_name) as owner_name, u.email as owner_email
                          FROM dealers d
                          JOIN users u ON d.user_id = u.user_id
                          WHERE d.status = 'pending'
                          ORDER BY d.created_at DESC");
    $stmt->execute();
    $pending_dealers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pending_dealers = [];
    $error = "Error fetching pending dealers: " . $e->getMessage();
}
?>

/* Approval Stats */
.approval-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-item {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Approval Grid */
.approval-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

.approval-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.approval-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
}

.approval-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.approval-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.dish-title {
    color: #2c3e50;
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.dish-info {
    margin-bottom: 1.5rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.info-label {
    color: #666;
    font-weight: 500;
}

.info-value {
    color: #2c3e50;
    font-weight: 600;
}

.dish-description {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

.approval-actions {
    display: flex;
    gap: 1rem;
}

.btn-approve, .btn-reject {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-approve {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
}

.btn-approve:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
}

.btn-reject {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
}

.btn-reject:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.category-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.category-name {
    color: #2c3e50;
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
}

.category-count {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.category-description {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

.category-actions {
    display: flex;
    gap: 1rem;
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
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
}

.btn-delete {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
}

@media (max-width: 768px) {
    .approval-grid {
        grid-template-columns: 1fr;
    }
    
    .approval-stats {
        gap: 1rem;
    }
    
    .stat-item {
        padding: 0.5rem;
    }
    
    .approval-actions {
        flex-direction: column;
    }
}



