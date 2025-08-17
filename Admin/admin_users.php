<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

// Initialize variables
$users = [];
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Handle user deletion (POST for safety)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    
    // Prevent admin from deleting themselves
    if ($delete_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account!";
    } else {
        try {
            // First check if user exists and get name for confirmation message
            $check_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $check_stmt->execute([$delete_id]);
            $user_to_delete = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_to_delete) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "User '{$user_to_delete['name']}' deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to delete user!";
                }
            } else {
                $_SESSION['error_message'] = "User not found!";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
        }
    }
    
    // Redirect to preserve filters
    header("Location: admin_users.php?search=" . urlencode($search) . "&role=" . urlencode($role_filter));
    exit();
}

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = trim($_POST['address'] ?? '');
    $role = $_POST['role'];
    $password = password_hash('Password123', PASSWORD_DEFAULT);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($phone) || empty($dob) || empty($gender) || empty($role)) {
        $_SESSION['error_message'] = "All required fields must be filled!";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, phone, dob, gender, address, role, username, password, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
            $stmt->execute([$name, $email, $phone, $dob, $gender, $address, $role, $username, $password]);
            $_SESSION['success_message'] = "User '$name' created successfully! Username: $username, Password: Password123";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error_message'] = "Email or username already exists!";
            } else {
                $_SESSION['error_message'] = "Error creating user: " . $e->getMessage();
            }
        }
    }
    
    header("Location: admin_users.php");
    exit();
}

// Fetch users with filters
try {
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR username LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    
    if (!empty($role_filter)) {
        $query .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching users: " . $e->getMessage();
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management - Personal Care</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #2d3748;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        /* Animated background elements */
        .bg-decoration {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        
        .floating-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 20%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .floating-shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .floating-shape:nth-child(4) {
            width: 100px;
            height: 100px;
            bottom: 10%;
            right: 10%;
            animation-delay: 1s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(20px) rotate(240deg); }
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: gradient-shift 3s ease-in-out infinite;
        }
        
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            color: #2d3748;
            font-size: 32px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header h1 i {
            color: #667eea;
            font-size: 36px;
        }
        
        .welcome {
            font-size: 18px;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .message {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            animation: slideInDown 0.5s ease-out;
            backdrop-filter: blur(10px);
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-success {
            background: rgba(72, 187, 120, 0.1);
            color: #2f855a;
            border: 2px solid rgba(72, 187, 120, 0.3);
            box-shadow: 0 8px 25px rgba(72, 187, 120, 0.15);
        }
        
        .message-error {
            background: rgba(245, 101, 101, 0.1);
            color: #c53030;
            border: 2px solid rgba(245, 101, 101, 0.3);
            box-shadow: 0 8px 25px rgba(245, 101, 101, 0.15);
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .filters {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            flex-grow: 1;
            align-items: center;
        }
        
        .search-box {
            position: relative;
            min-width: 300px;
            flex-grow: 1;
        }
        
        .search-box input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            background: white;
        }
        
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 18px;
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filter-group label {
            font-weight: 600;
            color: white;
            font-size: 16px;
        }
        
        select {
            padding: 15px 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            cursor: pointer;
            font-weight: 500;
            min-width: 150px;
            transition: all 0.3s ease;
        }
        
        select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            background: white;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78, #38a169);
            box-shadow: 0 8px 20px rgba(72, 187, 120, 0.3);
        }
        
        .btn-success:hover {
            box-shadow: 0 12px 30px rgba(72, 187, 120, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            box-shadow: 0 8px 20px rgba(245, 101, 101, 0.3);
        }
        
        .btn-danger:hover {
            box-shadow: 0 12px 30px rgba(245, 101, 101, 0.4);
        }
        
        .user-table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInUp 0.6s ease-out;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 20px 25px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        th {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            color: #2d3748;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tbody tr {
            transition: all 0.3s ease;
        }
        
        tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            transform: scale(1.01);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .user-details h4 {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 2px;
        }
        
        .user-details span {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }
        
        .role {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .role-admin {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            color: white;
        }
        
        .role-doctor {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }
        
        .role-patient {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }
        
        .role-nutritionist {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }
        
        .role-trainer {
            background: linear-gradient(135deg, #9f7aea, #805ad5);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.4s ease;
        }
        
        .action-btn:hover::before {
            left: 100%;
        }
        
        .action-edit {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }
        
        .action-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 153, 225, 0.4);
        }
        
        .action-delete {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
        }
        
        .action-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 101, 101, 0.4);
        }
        
        .delete-form {
            display: inline;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .no-results i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #cbd5e0;
            opacity: 0.7;
        }
        
        .no-results h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #4a5568;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.active .modal {
            transform: scale(1) translateY(0);
        }
        
        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 20px 20px 0 0;
        }
        
        .modal-title {
            font-size: 24px;
            color: #2d3748;
            font-weight: 700;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #a0aec0;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-btn:hover {
            color: #e53e3e;
            background: rgba(245, 101, 101, 0.1);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f7fafc;
            font-weight: 500;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            background: white;
        }
        
        .form-row {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        .modal-footer {
            padding: 25px 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background: #f7fafc;
            border-radius: 0 0 20px 20px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }
        
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .filters {
                width: 100%;
                justify-content: center;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .filter-group {
                flex: 1;
                min-width: 100%;
                justify-content: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .user-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 600px;
            }
            
            .modal {
                width: 95%;
                margin: 20px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-row .form-group {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Background decoration -->
    <div class="bg-decoration">
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <div class="welcome">
                    <i class="fas fa-user-shield"></i> 
                    Welcome, Administrator
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($success_message) ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>

        <div class="actions">
            <div class="filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="filter-group">
                    <label>Role:</label>
                    <select id="role-filter">
                        <option value="">All Roles</option>
                        <option value="patient" <?= $role_filter === 'patient' ? 'selected' : '' ?>>Patient</option>
                        <option value="doctor" <?= $role_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                        <option value="nutritionist" <?= $role_filter === 'nutritionist' ? 'selected' : '' ?>>Nutritionist</option>
                        <option value="trainer" <?= $role_filter === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>
            
            <button class="btn btn-success" id="create-user-btn">
                <i class="fas fa-user-plus"></i> Create User
            </button>
        </div>

        <div class="user-table">
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?= htmlspecialchars($user['name']) ?></h4>
                                            <span>@<?= htmlspecialchars($user['username']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($user['email']) ?></div>
                                    <div style="color: #718096;"><?= htmlspecialchars($user['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="role role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2d3748;">
                                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #a0aec0;">
                                        <?= date('H:i', strtotime($user['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin_edit.php?id=<?= $user['id'] ?>" class="action-btn action-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="delete-form" onsubmit="return confirmDelete('<?= htmlspecialchars($user['name']) ?>')">
                                                <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                                <input type="hidden" name="role" value="<?= htmlspecialchars($role_filter) ?>">
                                                <button type="submit" class="action-btn action-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="action-btn" style="opacity: 0.5; cursor: not-allowed; background: #e2e8f0; color: #a0aec0;" title="Cannot delete own account">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-user-slash"></i>
                    <h3>No users found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div class="modal-overlay" id="create-modal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Create New User</div>
                <button class="close-btn" id="close-create-modal">&times;</button>
            </div>
            
            <form method="POST" id="create-user-form">
                <input type="hidden" name="create_user" value="1">
                
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create-name">Full Name</label>
                            <input type="text" id="create-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="create-email">Email</label>
                            <input type="email" id="create-email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create-phone">Phone</label>
                            <input type="tel" id="create-phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="create-dob">Date of Birth</label>
                            <input type="date" id="create-dob" name="dob" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create-gender">Gender</label>
                            <select id="create-gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create-role">Role</label>
                            <select id="create-role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                                <option value="nutritionist">Nutritionist</option>
                                <option value="trainer">Trainer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="create-address">Address</label>
                        <textarea id="create-address" name="address" placeholder="Enter address (optional)"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" id="cancel-create-btn">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show loading overlay
        function showLoading() {
            document.getElementById('loading-overlay').classList.add('active');
        }
        
        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loading-overlay').classList.remove('active');
        }
        
        // Confirmation function for delete with enhanced styling
        function confirmDelete(userName) {
            const confirmed = confirm(`🗑️ Are you sure you want to delete user "${userName}"?\n\nThis action cannot be undone and will permanently remove all user data.`);
            if (confirmed) {
                showLoading();
                // Add a small delay to show the loading animation
                setTimeout(() => {
                    return true;
                }, 100);
            }
            return confirmed;
        }
        
        // Enhanced filter functionality
        const searchInput = document.getElementById('search');
        const roleFilter = document.getElementById('role-filter');
        
        function applyFilters() {
            const params = new URLSearchParams();
            
            if (searchInput.value.trim()) {
                params.append('search', searchInput.value.trim());
            }
            if (roleFilter.value) {
                params.append('role', roleFilter.value);
            }
            
            showLoading();
            window.location.href = `admin_users.php?${params.toString()}`;
        }
        
        // Debounced search
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (e.target.value.length >= 2 || e.target.value.length === 0) {
                    applyFilters();
                }
            }, 500);
        });
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                applyFilters();
            }
        });
        
        roleFilter.addEventListener('change', applyFilters);
        
        // Enhanced modal functionality
        const createModal = document.getElementById('create-modal');
        const createUserBtn = document.getElementById('create-user-btn');
        const createUserForm = document.getElementById('create-user-form');
        
        // Close create modal
        document.getElementById('close-create-modal').addEventListener('click', () => {
            createModal.classList.remove('active');
            resetForm();
        });
        
        document.getElementById('cancel-create-btn').addEventListener('click', () => {
            createModal.classList.remove('active');
            resetForm();
        });
        
        // Open create modal
        createUserBtn.addEventListener('click', () => {
            createModal.classList.add('active');
            document.getElementById('create-name').focus();
        });
        
        // Close modal when clicking outside
        createModal.addEventListener('click', (e) => {
            if (e.target === createModal) {
                createModal.classList.remove('active');
                resetForm();
            }
        });
        
        // Form submission with loading
        createUserForm.addEventListener('submit', (e) => {
            showLoading();
        });
        
        // Reset form
        function resetForm() {
            createUserForm.reset();
        }
        
        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && createModal.classList.contains('active')) {
                createModal.classList.remove('active');
                resetForm();
            }
        });
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'all 0.5s ease';
                message.style.opacity = '0';
                message.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    message.remove();
                }, 500);
            });
        }, 5000);
        
        // Hide loading on page load
        window.addEventListener('load', () => {
            hideLoading();
        });
        
        // Add loading to all form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                showLoading();
            });
        });
        
        // Smooth animations for table rows
        document.querySelectorAll('tbody tr').forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            row.style.animation = 'slideInUp 0.5s ease-out forwards';
        });
        
        // Enhanced role badge colors
        document.addEventListener('DOMContentLoaded', () => {
            const roles = document.querySelectorAll('.role');
            roles.forEach(role => {
                const roleText = role.textContent.toLowerCase();
                role.classList.add(`role-${roleText}`);
            });
        });
    </script>
</body>
</html>